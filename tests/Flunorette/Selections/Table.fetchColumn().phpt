<?php

/**
 * Test: Flunorette\Selection: Fetch column
 * @dataProvider? ../databases.ini
 */



require __DIR__ . '/../connect.inc.php'; // create $connection

Flunorette\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");

// fetch column on Selection
test(function () use ($connection) {
	$apps = $connection->table('book')->order('title')->fetchColumn('id');  // SELECT * FROM `book` ORDER BY `title`
	Assert::same(array(
		1,
		4,
		2,
		3,
	), $apps);
});

test(function () use ($connection) {
	$apps = $connection->table('book')->order('title')->select('title')->fetchColumn();  // SELECT * FROM `book` ORDER BY `title`
	Assert::same(array(
		'1001 tipu a triku pro PHP',
		'Dibi',
		'JUSH',
		'Nette'
	), $apps);
});

// fetch column on GroupedSelection
test(function () use ($connection) {
	$tags = array();
	foreach ($connection->table('book')->order('title') as $book) {
		$tags[$book->id] = $book->related('book_tag')->fetchColumn('tag_id');
	}
	Assert::same(array(
		1 => array(21, 22),
		4 => array(21, 22),
		2 => array(23),
		3 => array(21)
	), $tags);
});

// fetch column on GroupedSelection
test(function () use ($connection) {
	$tags = array();
	foreach ($connection->table('book')->order('title') as $book) {
		$tags[$book->id] = $book->related('book_tag')->fetchColumn(1);
	}
	Assert::same(array(
		1 => array(21, 22),
		4 => array(21, 22),
		2 => array(23),
		3 => array(21)
	), $tags);
});