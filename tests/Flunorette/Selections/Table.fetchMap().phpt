<?php

/**
 * Test: Flunorette\Selection: Fetch map
 * @dataProvider? ../databases.ini
 */



require __DIR__ . '/../connect.inc.php'; // create $connection

Flunorette\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");

test(function () use ($connection) {
	$books = $connection->table('book')->order('title')->fetchMap();  // SELECT * FROM `book` ORDER BY `title`
	foreach ($books as $k => $book) {
		$books[$k] = $book->title;
	}
	Assert::same(array(
		1 => '1001 tipu a triku pro PHP',
		4 => 'Dibi',
		2 => 'JUSH',
		3 => 'Nette',
	), $books);
});

test(function () use ($connection) {
	$books = $connection->table('book')->order('title')->fetchMap(function ($row) {
		return $row->title;
	});  // SELECT * FROM `book` ORDER BY `title`
	Assert::same(array(
		1 => '1001 tipu a triku pro PHP',
		4 => 'Dibi',
		2 => 'JUSH',
		3 => 'Nette',
	), $books);
});

// fetch map on GroupedSelection
test(function () use ($connection) {
	$tags = array();
	foreach ($connection->table('book')->order('title') as $book) {
		$tags[$book->id] = $book->related('book_tag')->fetchMap(function ($row) {
			return $row->tag_id;
		});
	}
	Assert::same(array(
			1 => array('1|21' => 21, '1|22' => 22),
			4 => array('4|21' => 21, '4|22' => 22),
			2 => array('2|23' => 23),
			3 => array('3|21' => 21))
		, $tags);
});