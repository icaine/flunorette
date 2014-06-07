<?php

/**
 * Test: Flunorette\Queries - Literals in queries
 * @dataProvider? ../databases.ini
 */
use Flunorette\SqlLiteral;

require __DIR__ . '/../connect.inc.php';

// test resolving sql literal in order clause
$query = $connection->createSelect('user');
$query->where('id > ?', 0)->order('name IN (?) OR id ?', new SqlLiteral('SELECT name FROM user WHERE name IN (?)', array('Marek')), array(1, 2));
Assert::same('SELECT * FROM user WHERE (id > ?) ORDER BY name IN (SELECT name FROM user WHERE name IN (?)) OR id IN (?)', $query->getQuery());
Assert::same(array(0, 'Marek', array(1, 2)), $query->getParameters());

$query = $connection->createSelect('user');
$query->where('id > ?', 0)->order('?, id ?', new SqlLiteral('EXISTS (SELECT * FROM user WHERE name IN (?)) DESC', array('Marek')), array(1, 2));
Assert::same('SELECT * FROM user WHERE (id > ?) ORDER BY EXISTS (SELECT * FROM user WHERE name IN (?)) DESC, id IN (?)', $query->getQuery());
Assert::same(array(0, 'Marek', array(1, 2)), $query->getParameters());

$query = $connection->createSelect('user');
$query->where('id > ?', 0)->order('id ?, ?', array(1, 2), new SqlLiteral('EXISTS (SELECT * FROM user WHERE name IN (?)) DESC', array('Marek')));
Assert::same('SELECT * FROM user WHERE (id > ?) ORDER BY id IN (?), EXISTS (SELECT * FROM user WHERE name IN (?)) DESC', $query->getQuery());
Assert::same(array(0, array(1, 2), 'Marek'), $query->getParameters());

$query = $connection->createSelect('user');
$query->where('id > ?', 0)->order('?', new SqlLiteral('EXISTS (SELECT * FROM user WHERE name IN (?)) DESC', array('Marek')));
Assert::same('SELECT * FROM user WHERE (id > ?) ORDER BY EXISTS (SELECT * FROM user WHERE name IN (?)) DESC', $query->getQuery());
Assert::same(array(0, 'Marek'), $query->getParameters());

$query = $connection->createSelect('user');
$query->order(new SqlLiteral('EXISTS (SELECT * FROM user WHERE name IN (?)) DESC', array('Marek')));
Assert::same('SELECT * FROM user ORDER BY EXISTS (SELECT * FROM user WHERE name IN (?)) DESC', $query->getQuery());
Assert::same(array('Marek'), $query->getParameters());

$query = $connection->createSelect('user');
$query->order('name IN (?)', new SqlLiteral('SELECT name FROM user WHERE name = :name', array(':name' => 'Marek')));
Assert::same('SELECT * FROM user ORDER BY name IN (SELECT name FROM user WHERE name = :name)', $query->getQuery());
Assert::same(array(':name' => 'Marek'), $query->getParameters());
