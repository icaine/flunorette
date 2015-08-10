<?php

/**
 * Test: Flunorette\Selections\ActiveRow: save()
 * @dataProvider? ../databases.ini
 */



use Tester\Assert;

require __DIR__ . '/../connect.inc.php'; // create $connection

Flunorette\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");

test(function () use ($connection) {
	$author = $connection->table('author')->get(12);  // SELECT * FROM `author` WHERE (`id` = ?)
	$author->name = 'Tyrion Lannister';
	$author->save();  // UPDATE `author` SET `name`='Tyrion Lannister' WHERE (`id` = 12)

	$book = $connection->table('book');

	$book1 = $book->get(1);  // SELECT * FROM `book` WHERE (`id` = ?)
	Assert::same('Jakub Vrana', $book1->author->name);  // SELECT * FROM `author` WHERE (`author`.`id` IN (11))
});


test(function () use ($connection) { // insert
	$newAuthor = $connection->table('author')->createRow();
	$newAuthor->name = 'Bill';
	$newAuthor->web = 'http://web.com';
	Assert::true($newAuthor->save());
	Assert::equal(1, $connection->table('author')->where('name', 'Bill')->count('*'));
});


test(function () use ($connection) { // insert 2
	$newAuthor = $connection->table('author')->createRow(array(
		'name' => 'Jacob'
	));
	Assert::true($newAuthor->save());
	Assert::equal(1, $connection->table('author')->where('name', 'Jacob')->count('*'));
});


test(function () use ($connection) { // table with multiple non autoincrement keys
	$newBookTag = $connection->table('book_tag')->createRow();
	$newBookTag->book_id = 1;
	$newBookTag->tag_id = 24;
	$newBookTag->save();

	Assert::equal(1, $connection->table('book_tag')->where($newBookTag->getPrimary())->count('*'));
});


test(function () use ($connection) { // ref/related after save
	$author = $connection->table('author')->createRow(array(
		'id'   => 16,
		'name' => 'example author',
		'web'  => 'http://example.com',
	));
	$author->save();
	Assert::equal('SELECT book.* FROM book WHERE (book.author_id IN (16))', $author->related('book')->getSqlBuilder()->getQueryExpanded());
	Assert::false($author->related('book')->fetch());

	$book = $author->related('book')->createRow([
		'author_id' => 16,
		'title'     => 'example title'
	]);
	$book->save();

	Assert::equal('example author', $book->author->name);
	Assert::equal('SELECT book.* FROM book WHERE (book.author_id IN (16))', $author->related('book')->getSqlBuilder()->getQueryExpanded());
	Assert::equal('example title', $author->related('book')->fetch()->title);
});
