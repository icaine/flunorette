<?php

//Expanding queries

use Flunorette\SqlLiteral;

require __DIR__ . '/../connect.inc.php';

$query = $connection->createSelect('user');
$query->select('user.*')->where('id', array(1, 2));
Assert::same('SELECT user.* FROM user WHERE (id IN (1, 2))', (string) $query);

$query = $connection->createSelect('user');
$query->where('id > ?', 0)->orderBy('name')->where('name', 'Marek');
Assert::same('SELECT * FROM user WHERE (id > 0) AND (name = \'Marek\') ORDER BY name', $query->getQueryExpanded());

$query = $connection->createSelect('user');
$query->where('id > ?', 0)->order('name', array('Marek', 'Jan'));
Assert::same('SELECT * FROM user WHERE (id > 0) ORDER BY name IN (\'Marek\', \'Jan\')', $query->getQueryExpanded());

// test multiple placeholder parameter
$query = $connection->createSelect('user');
$query->where('id ? OR id ?', 1, NULL);
Assert::same('SELECT * FROM user WHERE (id = 1 OR id IS NULL)', $query->getQueryExpanded());

// test named params
$query = $connection->createSelect('user');
$query->where('id = :id OR name = :name', array(':id' => 1, ':name' => 'Marek'));
$query->order('id = :id, name = :name');
Assert::same("SELECT * FROM user WHERE (id = 1 OR name = 'Marek') ORDER BY id = 1, name = 'Marek'", $query->getQueryExpanded());

// test SqlLiteral
$query = $connection->createSelect('user');
$query->where('id IN (?)', new SqlLiteral('1, 2, 3'));
Assert::same('SELECT * FROM user WHERE (id IN (1, 2, 3))', $query->getQueryExpanded());

// test auto type detection
$query = $connection->createSelect('user');
$query->where('id ? OR id ? OR id ?', 1, 'test', array(1, 2));
Assert::same('SELECT * FROM user WHERE (id = 1 OR id = \'test\' OR id IN (1, 2))', $query->getQueryExpanded());

// test backward compatibility
$query = $connection->createSelect('user');
$query->where('id = ? OR id ? OR id IN ? OR id LIKE ? OR id > ?', 1, 2, array(8, 9), '%test', 3);
$query->where('name', 'var');
$query->where('MAIN', 11); // "IN" is not considered as the operator
$query->where('id IN (?)', array(5, 6));
Assert::same('SELECT * FROM user WHERE (id = 1 OR id = 2 OR id IN (8, 9) OR id LIKE \'%test\' OR id > 3) AND (name = \'var\') AND (MAIN = 11) AND (id IN (5, 6))', $query->getQueryExpanded());

// test auto operator
$query = $connection->createSelect('user');
$query->where('FOO(?)', 1);
$query->where('FOO(id, ?)', 1);
$query->where('id & ? = ?', 1, 1);
$query->where('?', 1);
$query->where('NOT ? OR ?', 1, 1);
$query->where('? + ? - ? / ? * ? % ?', 1, 1, 1, 1, 1, 1);
Assert::same('SELECT * FROM user WHERE (FOO(1)) AND (FOO(id, 1)) AND (id & 1 = 1) AND (1) AND (NOT 1 OR 1) AND (1 + 1 - 1 / 1 * 1 % 1)', $query->getQueryExpanded());
