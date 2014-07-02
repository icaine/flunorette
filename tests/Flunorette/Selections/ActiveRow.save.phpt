<?php

/**
 * Test: Flunorette\Selections\ActiveRow: save()
 * @dataProvider? ../databases.ini
 */



require __DIR__ . '/../connect.inc.php'; // create $connection

Flunorette\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");


//update
$author = $connection->table('author')->get(12);  // SELECT * FROM `author` WHERE (`id` = ?)
$author->name = 'Tyrion Lannister';
$author->save();  // UPDATE `author` SET `name`='Tyrion Lannister' WHERE (`id` = 12)

$book = $connection->table('book');

$book1 = $book->get(1);  // SELECT * FROM `book` WHERE (`id` = ?)
Assert::same('Jakub Vrana', $book1->author->name);  // SELECT * FROM `author` WHERE (`author`.`id` IN (11))
//insert
$newAuthor = $connection->table('author')->createRow();
$newAuthor->name = 'Bill';
$newAuthor->web = 'http://web.com';
Assert::true($newAuthor->save());
Assert::equal(1, $connection->table('author')->where('name', 'Bill')->count('*'));


//table with multiple non autoincrement keys
$newBookTag = $connection->table('book_tag')->createRow();
$newBookTag->book_id = 1;
$newBookTag->tag_id = 24;
$newBookTag->save();

Assert::equal(1, $connection->table('book_tag')->where($newBookTag->getPrimary())->count('*'));
