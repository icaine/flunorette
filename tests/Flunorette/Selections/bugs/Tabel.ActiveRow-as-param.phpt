<?php

/**
 * @dataProvider? ../../databases.ini
 */



use Flunorette\SqlLiteral;
use Flunorette\Statement;

require __DIR__ . '/../../connect.inc.php'; // create $connection

Flunorette\Helpers::loadFromFile($connection, __DIR__ . "/../../files/{$driverName}-nette_test1.sql");

test(function () use ($connection) {
	$author = $connection->table('author')->get(11);
	foreach ($connection->table('book')->where('author_id', $author) as $book) {
		Assert::same(11, $book->ref('author')->id);
	}

	foreach ($connection->table('book')->where(new SqlLiteral('author_id = ?', $author)) as $book) {
		Assert::same(11, $book->ref('author')->id);
	}
});