<?php

/**
 * Test: Flunorette\Queries\SelectQuery
 * @dataProvider? ../databases.ini
 */
use Flunorette\Queries\SelectQuery;
use Flunorette\SqlLiteral;

require __DIR__ . '/../connect.inc.php';
Flunorette\Helpers::loadFromFile($connection, __DIR__ . '/../files/flunorette_blog.sql');

/* @var $connection Flunorette\Connection */

Assert::exception(function () { //creating empty selection query and calling where
	$query = new SelectQuery();
	$query->where('name', 1);
}, 'Flunorette\InvalidStateException', 'Context must be set first.');

$query = $connection->createSelect('user');
$query->select('user.*');
Assert::same('SELECT user.* FROM user', $query->getQuery());

$query = $connection->createSelect('user');
$query->select('user.*');
$query->wherePrimary(1);
Assert::same('SELECT user.* FROM user WHERE (user.id = ?)', $query->getQuery());
Assert::same(array(1), $query->getParameters());

$query = $connection->createSelect('user');
$query->select('user.*');
$query->wherePrimary(array(1, 2));
Assert::same('SELECT user.* FROM user WHERE (user.id IN (?))', $query->getQuery());
Assert::same(array(array(1, 2)), $query->getParameters());

$query = $connection->createSelect('user AS u');
$query->select('u.*');
$query->wherePrimary(1);
Assert::same('SELECT u.* FROM user AS u WHERE (u.id = ?)', $query->getQuery());
Assert::same(array(1), $query->getParameters());

$query = $connection->createSelect('user');
$query->where('id > ?', 0)->orderBy('name')->where('name', 'Marek');
Assert::same('SELECT * FROM user WHERE (id > ?) AND (name = ?) ORDER BY name', $query->getQuery());
Assert::same(array(0, 'Marek'), $query->getParameters());

//using whereAnd and whereOr
$query = $connection->createSelect('user');
$query->whereAnd('id > ?', 0)->whereOr('name', 'Marek');
Assert::same('SELECT * FROM user WHERE id > ? OR name = ?', $query->getQuery());
Assert::same(array(0, 'Marek'), $query->getParameters());

// test cond resolving in order
$query = $connection->createSelect('user');
$query->where('id > ?', 0)->order('name', 'Marek');
Assert::same('SELECT * FROM user WHERE (id > ?) ORDER BY name = ?', $query->getQuery());
Assert::same(array(0, 'Marek'), $query->getParameters());

$query = $connection->createSelect('user');
$query->where('id > ?', 0)->order('name', array('Marek', 'Jan'));
Assert::same('SELECT * FROM user WHERE (id > ?) ORDER BY name IN (?)', $query->getQuery());
Assert::same(array(0, array('Marek', 'Jan')), $query->getParameters());

$query = $connection->createSelect('user');
$query->where('id > ?', 0)->order('name', array());
Assert::same('SELECT * FROM user WHERE (id > ?) ORDER BY name IS NULL AND FALSE', $query->getQuery());
Assert::same(array(0), $query->getParameters());

$query = $connection->createSelect('user');
$query->where('id > ?', 0)->order('name', null);
Assert::same('SELECT * FROM user WHERE (id > ?) ORDER BY name IS NULL', $query->getQuery());
Assert::same(array(0), $query->getParameters());

$query = $connection->createSelect('user');
$query->where('id > ?', 0)->orderBy('name')->where('name = ?', 'Marek')->where('name', 'Lame');
Assert::same('SELECT * FROM user WHERE (id > ?) AND (name = ?) AND (name = ?) ORDER BY name', $query->getQuery());
Assert::same(array(0, 'Marek', 'Lame'), $query->getParameters());

// test multiple placeholder parameter
$query = $connection->createSelect('user');
$query->where('id ? OR id ?', 1, NULL);
Assert::same('SELECT * FROM user WHERE (id = ? OR id IS NULL)', $query->getQuery());
Assert::same(array(1), $query->getParameters());

// test reset where
$query = $connection->createSelect('user');
$query->where('id ? OR id ?', array(1, NULL));
$query->where(null);
Assert::same('SELECT * FROM user', $query->getQuery());
Assert::same(array(), $query->getParameters());

// test named params
$query = $connection->createSelect('user');
$query->where('id = :id OR name = :name', array(':id' => 1, ':name' => 'Marek'));
Assert::same('SELECT * FROM user WHERE (id = :id OR name = :name)', $query->getQuery());
Assert::same(array(':id' => 1, ':name' => 'Marek'), $query->getParameters());

// test named params
$query = $connection->createSelect('user');
$query->where('id = :id OR name = :name', array(':id' => 1, ':name' => 'Marek'));
$query->order('id = :id');
Assert::same('SELECT * FROM user WHERE (id = :id OR name = :name) ORDER BY id = :id', $query->getQuery());
Assert::same(array(':id' => 1, ':name' => 'Marek'), $query->getParameters());

// test SqlLiteral
$query = $connection->createSelect('user');
$query->where('id IN (?)', new SqlLiteral('1, 2, 3'));
Assert::same('SELECT * FROM user WHERE (id IN (1, 2, 3))', $query->getQuery());
Assert::same(array(), $query->getParameters());

// test auto type detection
$query = $connection->createSelect('user');
$query->where('id ? OR id ? OR id ?', 1, 'test', array(1, 2));
Assert::same('SELECT * FROM user WHERE (id = ? OR id = ? OR id IN (?))', $query->getQuery());
Assert::same(array(1, 'test', array(1, 2)), $query->getParameters());

// test empty array
$query = $connection->createSelect('user');
$query->where('id', array());
Assert::same('SELECT * FROM user WHERE (id IS NULL AND FALSE)', $query->getQuery());
Assert::same(array(), $query->getParameters());

// test backward compatibility
$query = $connection->createSelect('user');
$query->where('id = ? OR id ? OR id IN ? OR id LIKE ? OR id > ?', 1, 2, array(1, 2), '%test', 3);
$query->where('name', 'var');
$query->where('MAIN', 0); // "IN" is not considered as the operator
$query->where('id IN (?)', array(1, 2));
Assert::same('SELECT * FROM user WHERE (id = ? OR id = ? OR id IN (?) OR id LIKE ? OR id > ?) AND (name = ?) AND (MAIN = ?) AND (id IN (?))', $query->getQuery());
Assert::same(array(1, 2, array(1, 2), '%test', 3, 'var', 0, array(1, 2)), $query->getParameters());

// test auto operator
$query = $connection->createSelect('user');
$query->where('FOO(?)', 1);
$query->where('FOO(id, ?)', 1);
$query->where('id & ? = ?', 1, 1);
$query->where('?', 1);
$query->where('NOT ? OR ?', 1, 1);
$query->where('? + ? - ? / ? * ? % ?', 1, 1, 1, 1, 1, 1);
Assert::same('SELECT * FROM user WHERE (FOO(?)) AND (FOO(id, ?)) AND (id & ? = ?) AND (?) AND (NOT ? OR ?) AND (? + ? - ? / ? * ? % ?)', $query->getQuery());
Assert::same(array(1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1), $query->getParameters());

// test multiline cond
$query = $connection->createSelect('user');
$query->where("\ncol1 ?\nOR col2 ?\n", 1, 1);
Assert::same("SELECT * FROM user WHERE (col1 = ?\nOR col2 = ?)", $query->getQuery());
Assert::same(array(1, 1), $query->getParameters());

// backward compatibility
$query = $connection->createSelect('user');
$query->where("\ncol1 ?\nOR col2 ?\n", 1, 1);
Assert::same("SELECT * FROM user WHERE (col1 = ?\nOR col2 = ?)", $query->getQuery());
Assert::same(array(1, 1), $query->getParameters());


$query = $connection->createSelect('user');
$query->where('id <> ? OR id >= ?', 1, 2);
Assert::same('SELECT * FROM user WHERE (id <> ? OR id >= ?)', $query->getQuery());
Assert::same(array(1, 2), $query->getParameters());

//array with shifted offset as arg
$query = $connection->createSelect('user');
$query->where('id', array(5 => 1, 6 => 2));
Assert::same('SELECT * FROM user WHERE (id IN (?))', $query->getQuery());
Assert::same('SELECT * FROM user WHERE (id IN (1, 2))', $query->getQueryExpanded());
Assert::same(array(array(1, 2)), $query->getParameters());
