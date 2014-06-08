<?php

/**
 * Test: Flunorette\Queries\InsertQuery
 * @dataProvider? ../databases.ini
 */
require __DIR__ . '/../connect.inc.php';
Flunorette\Helpers::loadFromFile($connection, __DIR__ . '/../files/flunorette_blog.sql');

//resolving inner query
$inner = $connection->createSelect('user');
$inner->select('name');
$inner->where('name', array('Marek'));
$query = $connection->createSelect('user');
$query->order('name IN (?)', $inner);
Assert::same('SELECT user.* FROM user ORDER BY name IN (SELECT name FROM user WHERE (name IN (?)))', $query->getQuery());
Assert::same(array(array('Marek')), $query->getParameters());
Assert::same("SELECT user.* FROM user ORDER BY name IN (SELECT name FROM user WHERE (name IN ('Marek')))", $query->getQueryExpanded());

$inner = $connection->createSelect('user');
$inner->select('name');
$inner->where('name = ? OR name = ?', 'Marek', 'Dan');
$query = $connection->createSelect('user');
$query->order('EXISTS(?)', $inner);
Assert::same("SELECT user.* FROM user ORDER BY EXISTS(SELECT name FROM user WHERE (name = ? OR name = ?))", $query->getQuery());
Assert::same(array('Marek', 'Dan'), $query->getParameters());
Assert::same("SELECT user.* FROM user ORDER BY EXISTS(SELECT name FROM user WHERE (name = 'Marek' OR name = 'Dan'))", $query->getQueryExpanded());

//resolving inner query with named params
$inner = $connection->createSelect('article');
$inner->select('id');
$inner->where('user_id = :id OR approver = :id', array(':id' => 1));
$query = $connection->createSelect('article');
$query->where('id IN (?)', $inner);
Assert::same("SELECT article.* FROM article WHERE (id IN (SELECT id FROM article WHERE (user_id = :id OR approver = :id)))", $query->getQuery());
Assert::same(array(':id' => 1), $query->getParameters());
Assert::same("SELECT article.* FROM article WHERE (id IN (SELECT id FROM article WHERE (user_id = 1 OR approver = 1)))", $query->getQueryExpanded());
