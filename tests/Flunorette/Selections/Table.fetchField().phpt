<?php

/**
 * Test: Flunorette\Selection: Fetch column
 * @dataProvider? ../databases.ini
 */



require __DIR__ . '/../connect.inc.php'; // create $connection

Flunorette\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");

// fetch field on Selection
test(function () use ($connection) {
	$books = $connection->table('book')->order('title')->select('COUNT(*) AS cnt')->fetchField('cnt');  // SELECT * FROM `book` ORDER BY `title`
	Assert::same(4, $books);
});

// fetch field on Selection
test(function () use ($connection) {
	$books = $connection->table('book')->order('title')->select('COUNT(*) AS cnt')->fetchField();  // SELECT * FROM `book` ORDER BY `title`
	Assert::same(4, $books);
});

// fetch field on GroupedSelection
test(function () use ($connection) {
	$tags = array();
	foreach ($connection->table('book')->order('title') as $book) {
		$tags[$book->id] = $book->related('book_tag')->fetchField('tag_id');
	}
	Assert::same(array(
		1 => 21,
		4 => 21,
		2 => 23,
		3 => 21
	), $tags);
});

// fetch field on GroupedSelection
test(function () use ($connection) {
	$tags = array();
	foreach ($connection->table('book')->order('title') as $book) {
		$tags[$book->id] = $book->related('book_tag')->fetchField(1);
	}
	Assert::same(array(
		1 => 21,
		4 => 21,
		2 => 23,
		3 => 21
	), $tags);
});
