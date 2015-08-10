<?php

/**
 * Test: emptyResultSet.
 * @dataProvider? ../databases.ini
 */

require __DIR__ . '/../connect.inc.php'; // create $connection

Flunorette\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");

test(function () use ($connection) {
	$selection = $connection->table('book');
	$selection->get(2)->author->name; //reading via reference
	$connection->table('book')->get(2)->author->update(array('name' => 'New name'));
	$connection->table('book')->get(2)->update(array('title' => 'New book title'));
	$selection->limit(NULL); //should invalidate cache of data and references
	$book = $selection->get(2);
	Assert::same('New book title', $book->title); //data cache invalidated
	Assert::same('New name', $book->author->name); //references NOT invalidated
});