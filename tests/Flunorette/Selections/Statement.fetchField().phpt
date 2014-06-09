<?php

/**
 * Test: Flunorette\Statement: Fetch field.
 * @dataProvider? ../databases.ini
*/



require __DIR__ . '/../connect.inc.php'; // create $connection

Flunorette\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");


$res = $connection->query('SELECT name, id FROM author ORDER BY id');

Assert::same('Jakub Vrana', $res->fetchField());
Assert::same(12, $res->fetchField(1));
Assert::same('Geek', $res->fetchField('name'));
