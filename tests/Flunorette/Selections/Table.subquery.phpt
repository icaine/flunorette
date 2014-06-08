<?php

/**
 * Test: Flunorette\Selections: Subqueries.
 * @dataProvider? ../databases.ini
*/



require __DIR__ . '/../connect.inc.php'; // create $connection

Flunorette\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");

//Sub Selection
test(function () use ($connection) {
	$apps = array();
	$selection = $connection->table('author')->where('name LIKE ?', '%David%'); // authors with name David
	foreach ($connection->table('book')->where('author_id', $selection) as $book) {
		$apps[] = $book->title;
	}

	Assert::same(array(
		'Nette',
		'Dibi'
	), $apps);
});


//Sub SelectionQuery
test(function () use ($connection) {
	$apps = array();
	$subselection = $connection->createSelect('author')->where('id', array(12, 13))->select('id');
	foreach ($connection->table('book')->where('author_id IN (?)', $subselection)->select('title') as $book) {
		$apps[] = $book->title;
	}

	Assert::same(array(
		'Nette',
		'Dibi',
	), $apps);
});

