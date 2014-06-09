<?php

/**
 * Test: Flunorette\Queries\DeleteQuery
 * @dataProvider? ../databases.ini
 */
use Flunorette\SqlLiteral;

require __DIR__ . '/../connect.inc.php';
Flunorette\Helpers::loadFromFile($connection, __DIR__ . '/../files/flunorette_blog.sql');

$birthday = new DateTime('1986-01-01');

//simple update
$query = $connection->createDelete('user');
$query->where('id', 1);
Assert::same("DELETE FROM user WHERE (id = ?)", $query->getQuery());
Assert::same(array(1), $query->getParameters());
Assert::same("DELETE FROM user WHERE (id = 1)", $query->getQueryExpanded());

$query = $connection->createDelete('user');
$query->ignore()->where('id', 1);
Assert::same("DELETE IGNORE FROM user WHERE (id = ?)", $query->getQuery());
Assert::same(array(1), $query->getParameters());
Assert::same("DELETE IGNORE FROM user WHERE (id = 1)", $query->getQueryExpanded());

$query = $connection->createDelete('user');
$query->where('id', 2)->orderBy('name')->limit(1);
Assert::same("DELETE FROM user WHERE (id = ?) ORDER BY name LIMIT 1", $query->getQuery());
Assert::same(array(2), $query->getParameters());
Assert::same("DELETE FROM user WHERE (id = 2) ORDER BY name LIMIT 1", $query->getQueryExpanded());

$query = $connection->createDelete('t1, t2');
$query->from('t1')->innerJoin('t2 USING(id)')->innerJoin('t3 ON t2.id = t3.id')->where('t1.id', 1);
Assert::same("DELETE t1, t2 FROM t1 INNER JOIN t2 USING(id) INNER JOIN t3 ON t2.id = t3.id WHERE (t1.id = ?)", $query->getQuery());
Assert::same(array(1), $query->getParameters());
Assert::same("DELETE t1, t2 FROM t1 INNER JOIN t2 USING(id) INNER JOIN t3 ON t2.id = t3.id WHERE (t1.id = 1)", $query->getQueryExpanded());

$query = $connection->createDelete('t1, t2');
$query->from('t1')->innerJoin('t2 USING(id)')->innerJoin('t3 ON (?)', new SqlLiteral('t2.id = t3.id AND t1.id > ?', 10))->where('t1.id', 1);
Assert::same("DELETE t1, t2 FROM t1 INNER JOIN t2 USING(id) INNER JOIN t3 ON (t2.id = t3.id AND t1.id > ?) WHERE (t1.id = ?)", $query->getQuery());
Assert::same(array(10, 1), $query->getParameters());
Assert::same("DELETE t1, t2 FROM t1 INNER JOIN t2 USING(id) INNER JOIN t3 ON (t2.id = t3.id AND t1.id > 10) WHERE (t1.id = 1)", $query->getQueryExpanded());
