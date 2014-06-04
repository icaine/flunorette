<?php

/**
 * Test: Flunorette\Queries\InsertQuery
 * @dataProvider? ../databases.ini
 */
use Flunorette\SqlLiteral;

require __DIR__ . '/../connect.inc.php';
Flunorette\Helpers::loadFromFile($connection, __DIR__ . '/../flunorette_blog.sql');

$birthday = new DateTime('1986-01-01');

//simple insert
$query = $connection->createInsert('user');
$query->values(array(
	'name' => 'John',
	'type' => 'admin',
	'birthday' => $birthday
));
Assert::same("INSERT INTO user (name, type, birthday) VALUES ('John', 'admin', '1986-01-01 00:00:00')", $query->getQuery());

//test traversable
$query = $connection->createInsert('user');
$query->values(\Nette\ArrayHash::from(array(
		'name' => 'John',
		'type' => 'admin',
		'birthday' => $birthday
)));
Assert::same("INSERT INTO user (name, type, birthday) VALUES ('John', 'admin', '1986-01-01 00:00:00')", $query->getQuery());

//test datetime in values
$query = $connection->createInsert('user');
$query->ignore()->values(array(
	'name' => 'John',
	'type' => 'admin',
	'birthday' => $birthday
));
Assert::same("INSERT IGNORE INTO user (name, type, birthday) VALUES ('John', 'admin', '1986-01-01 00:00:00')", $query->getQuery());

//multi insert
$query = $connection->createInsert('user');
$query->values(array(
	array(
		'name' => 'Catelyn Stark',
		'web' => 'http://example.com',
		'born' => new DateTime('2011-11-11'),
	),
	array(
		'name' => 'Sansa Stark',
		'web' => 'http://example.com',
		'born' => new DateTime('2021-11-11'),
	),
));
Assert::same("INSERT INTO user (name, web, born) VALUES ('Catelyn Stark', 'http://example.com', '2011-11-11 00:00:00'), ('Sansa Stark', 'http://example.com', '2021-11-11 00:00:00')", $query->getQuery());

//sql literal
$query = $connection->createInsert('user');
$query->values(array(
	'id' => 1,
	'updated_at' => new SqlLiteral('DAY(?)', array($date = new DateTime('2014-01-31'))),
	'title' => 'new title',
	'content' => 'NOW()'
));
Assert::same("INSERT INTO user (id, updated_at, title, content) VALUES (1, DAY(?), 'new title', 'NOW()')", $query->getQuery());
Assert::same(array($date), $query->getParameters());
//expanded params with sql literal
Assert::same("INSERT INTO user (id, updated_at, title, content) VALUES (1, DAY('2014-01-31 00:00:00'), 'new title', 'NOW()')", $query->getQueryExpanded());

//on duplicate key
$query = $connection->createInsert('user');
$query->onDuplicateKeyUpdate(array(
	'title' => 'article 1b',
	'content' => 'content 1b',
));
$query->values(array(
	'id' => 1,
	'updated_at' => new SqlLiteral('NOW()'),
	'title' => 'new title',
	'content' => 'NOW()'
));
Assert::same("INSERT INTO user (id, updated_at, title, content) VALUES (1, NOW(), 'new title', 'NOW()') ON DUPLICATE KEY UPDATE title = 'article 1b', content = 'content 1b'", $query->getQuery());
