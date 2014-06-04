<?php

/**
 * Test: Basic operations
 * @dataProvider? databases.ini
 */
require __DIR__ . '/connect.inc.php';
Flunorette\Helpers::loadFromFile($connection, __DIR__ . '/flunorette_blog.sql');
/* @var $connection Flunorette\Connection */


function pr($actual) {
	return trim(print_r($actual, true));
}

$expected = <<<EXPECTED
Flunorette\Row Object
(
    [id] => 1
    [created_by] => 0
    [type] => admin
    [name] => Dan
    [city_id] => 1
)
EXPECTED;
$actual = $connection->fetch('SELECT * FROM user WHERE id = ?', 1);
Assert::same($expected, pr($actual));



$expected = <<<EXPECTED
Array
(
    [0] => Flunorette\Row Object
        (
            [name] => Dan
        )

    [1] => Flunorette\Row Object
        (
            [name] => David
        )

)
EXPECTED;
$actual = $connection->fetchAll('SELECT name FROM user WHERE id IN (?)', array(1, 2));
Assert::same($expected, pr($actual));



$expected = <<<EXPECTED
Array
(
)
EXPECTED;
$actual = $connection->fetchAll('SELECT name FROM user WHERE id IN (?) AND name = ?', array(1), 'Marek');
Assert::same($expected, pr($actual));



$expected = <<<EXPECTED
Array
(
    [0] => Flunorette\Row Object
        (
            [name] => David
        )

)
EXPECTED;
$actual = $connection->fetchAll('SELECT name FROM user WHERE id IN ?', new Flunorette\SqlLiteral('(SELECT ?)', array(2)));
Assert::same($expected, pr($actual));



$expected = <<<EXPECTED
Array
(
    [0] => Flunorette\Row Object
        (
            [name] => Dan
        )

    [1] => Flunorette\Row Object
        (
            [name] => David
        )

)
EXPECTED;
$actual = $connection->fetchAll('SELECT name FROM user WHERE', array('id' => array(1, 2)));
Assert::same($expected, pr($actual));



$expected = <<<EXPECTED
Array
(
    [0] => Flunorette\Row Object
        (
            [name] => Dan
        )

    [1] => Flunorette\Row Object
        (
            [name] => David
        )

)
EXPECTED;
$actual = $connection->fetchAll('SELECT name FROM user WHERE name = :id OR id = :di OR id = :id', array(':id' => 1, ':di' => 2));
Assert::same($expected, pr($actual));



//fetching query object
$expected = <<<EXPECTED
Array
(
    [0] => Flunorette\Row Object
        (
            [name] => David
            [articles] => 2
        )

    [1] => Flunorette\Row Object
        (
            [name] => Dan
            [articles] => 1
        )

)
EXPECTED;
$query = $connection->createSelect('user');
$query->select('user.name, COUNT(article:id) AS articles');
$query->where('name', array('Dan', 'David'));
$query->group('user.id');
$query->order('articles DESC');
$actual = $connection->fetchAll($query);
Assert::same($expected, pr($actual));
