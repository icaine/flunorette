<?php

/**
 * Test: Flunorette\Connection fetch methods.
 * @dataProvider? databases.ini
 */
require __DIR__ . '/connect.inc.php'; // create $connection

Flunorette\Helpers::loadFromFile($connection, __DIR__ . "/files/{$driverName}-nette_test1.sql");


test(function() use ($connection) { // hydrate
	$row = $connection->hydrate(function ($statement) {
		return $statement->fetch();
	}, 'SELECT name, id FROM author WHERE id = ?', 11);
	Assert::equal(array(
		'name' => 'Jakub Vrana',
		'id' => 11,
	), (array) $row);
});

test(function() use ($connection) { // fetch
	$row = $connection->fetch('SELECT name, id FROM author WHERE id = ?', 11);
	Assert::type('Flunorette\Row', $row);
	Assert::equal(array(
		'name' => 'Jakub Vrana',
		'id' => 11,
	), (array) $row);
});


test(function() use ($connection) { // fetchField
	Assert::same('Jakub Vrana', $connection->fetchField('SELECT name FROM author ORDER BY id'));
});


test(function() use ($connection) { // fetchPairs
	$pairs = $connection->fetchPairs('SELECT name, id FROM author WHERE id > ? ORDER BY id', 11);
	Assert::equal(array(
		'David Grudl' => 12,
		'Geek' => 13,
	), $pairs);
});


test(function() use ($connection) { // fetchColumn
	$data = $connection->fetchColumn('SELECT name FROM author WHERE id > ? ORDER BY id', 11);
	Assert::equal(array(
		'David Grudl',
		'Geek',
	), $data);
});


test(function() use ($connection) { // fetchAll
	$arr = $connection->fetchAll('SELECT name, id FROM author WHERE id < ? ORDER BY id', 13);
	Assert::equal(array(
		array('name' => 'Jakub Vrana', 'id' => 11),
		array('name' => 'David Grudl', 'id' => 12),
	), array_map(function ($e) {
		return (array) $e;
	}, $arr));
});
