<?php

/**
 * Test: Flunorette\Selections: grouping.
 * @dataProvider? ../databases.ini
*/



require __DIR__ . '/../connect.inc.php'; // create $connection

Flunorette\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");


$authors = $connection->table('book')->group('author_id')->order('author_id')->fetchPairs('author_id', 'author_id');
Assert::same(array(11, 12), array_values($authors));
