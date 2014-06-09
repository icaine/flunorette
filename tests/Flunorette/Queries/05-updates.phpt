<?php

/**
 * Test: Flunorette\Queries\UpdateQuery
 * @dataProvider? ../databases.ini
 */
use Flunorette\SqlLiteral;

require __DIR__ . '/../connect.inc.php';
Flunorette\Helpers::loadFromFile($connection, __DIR__ . '/../files/flunorette_blog.sql');

$birthday = new DateTime('1986-01-01');

//simple update
$query = $connection->createUpdate('user');
$query->set('name', 'aikavolS')->where('id', 1);
Assert::same("UPDATE user SET name = ? WHERE (id = ?)", $query->getQuery());
Assert::same(array('aikavolS', 1), $query->getParameters());
Assert::same("UPDATE user SET name = 'aikavolS' WHERE (id = 1)", $query->getQueryExpanded());

//update with literal
$query = $connection->createUpdate('article');
$query->set('published_at', $literal = new SqlLiteral('NOW()'))->where('user_id', 1);
Assert::same("UPDATE article SET published_at = NOW() WHERE (user_id = ?)", $query->getQuery());
Assert::same(array(1), $query->getParameters());
Assert::same("UPDATE article SET published_at = NOW() WHERE (user_id = 1)", $query->getQueryExpanded());

//update with literal with parameters
$query = $connection->createUpdate('article');
$query->set('published_at', $literal = new SqlLiteral('DATE(?)', '2014-01-31'))->where('user_id', 1);
Assert::same("UPDATE article SET published_at = DATE(?) WHERE (user_id = ?)", $query->getQuery());
Assert::same(array('2014-01-31', 1), $query->getParameters());
Assert::same("UPDATE article SET published_at = DATE('2014-01-31') WHERE (user_id = 1)", $query->getQueryExpanded());

//first called getQueryExpanded before getQuery
$query = $connection->createUpdate('user');
$query->wherePrimary(1)->orderBy('EXIST(?)', new SqlLiteral('1 = 1'));
$query->set(array('name' => 'Daniel'));
Assert::same("UPDATE user SET name = 'Daniel' WHERE (user.id = 1) ORDER BY EXIST(1 = 1)", $query->getQueryExpanded());
Assert::same("UPDATE user SET name = ? WHERE (user.id = ?) ORDER BY EXIST(1 = 1)", $query->getQuery());

//
$query = $connection->createUpdate('article');
$query->wherePrimary(1)->set(array('name' => 'keraM'));
Assert::same("UPDATE article SET name = ? WHERE (article.id = ?)", $query->getQuery());
Assert::same(array('keraM', 1), $query->getParameters());
Assert::same("UPDATE article SET name = 'keraM' WHERE (article.id = 1)", $query->getQueryExpanded());

$query = $connection->createUpdate('article');
$query->set(array('name' => 'keraM', '`type`' => 'author'))->where('id', 1);
Assert::same("UPDATE article SET name = ?, `type` = ? WHERE (id = ?)", $query->getQuery());
Assert::same(array('keraM', 'author', 1), $query->getParameters());
Assert::same("UPDATE article SET name = 'keraM', `type` = 'author' WHERE (id = 1)", $query->getQueryExpanded());

$query = $connection->createUpdate('user');
$query->set(array('name' => 'Marek', '`type`' => 'admin'))->where('id', 1);
Assert::same("UPDATE user SET name = ?, `type` = ? WHERE (id = ?)", $query->getQuery());
Assert::same(array('Marek', 'admin', 1), $query->getParameters());
Assert::same("UPDATE user SET name = 'Marek', `type` = 'admin' WHERE (id = 1)", $query->getQueryExpanded());
