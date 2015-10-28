<?php

/**
 * Test: Flunorette\Selections: Shared related data caching.
 * @dataProvider? ../../databases.ini
 */



use Flunorette\Statement;

require __DIR__ . '/../../connect.inc.php'; // create $connection

Flunorette\Helpers::loadFromFile($connection, __DIR__ . "/../../files/{$driverName}-nette_test1.sql");

// add additional tags (not relevant to other tests)
$connection->query("INSERT INTO book_tag_alt (book_id, tag_id, state) VALUES (1, 24, 'private');");
$connection->query("INSERT INTO book_tag_alt (book_id, tag_id, state) VALUES (2, 24, 'private');");
$connection->query("INSERT INTO book_tag_alt (book_id, tag_id, state) VALUES (2, 22, 'private');");

test(function () use ($connection) { // query count
	// build cache first
	$connection->table('author')->get(11);
	foreach ($connection->table('book') as $book) {
		foreach ($book->related('book_tag_alt')->where('state', 'private') as $bookTag) {
			$tag = $bookTag->tag;
		}
	}

	$count = 0;
	$connection->onQuery[] = function(Statement $s) use (& $count) {
		echo($s->getQueryString()) . PHP_EOL;
		$count++;
	};
	foreach ($connection->table('book') as $book) {
		foreach ($book->related('book_tag_alt')->where('state', 'private') as $bookTag) {
			$tag = $bookTag->tag;
		}
	}
	Assert::same(3, $count);
});