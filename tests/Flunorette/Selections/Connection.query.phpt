<?php

/**
 * Test: Flunorette\Connection query methods.
 * @dataProvider? ../databases.ini
*/



require __DIR__ . '/../connect.inc.php'; // create $connection

Flunorette\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");


$res = $connection->query('SELECT id FROM author WHERE id = ?', 11);
Assert::type( 'Flunorette\Statement', $res );
Assert::same( 'SELECT id FROM author WHERE id = ?', $res->getQueryString() );


$res = $connection->query('SELECT id FROM author WHERE id = ? OR id = ?', 11, 12);
Assert::same( 'SELECT id FROM author WHERE id = ? OR id = ?', $res->getQueryString() );


$res = $connection->queryArgs('SELECT id FROM author WHERE id = ? OR id = ?', array(11, 12));
Assert::same( 'SELECT id FROM author WHERE id = ? OR id = ?', $res->getQueryString() );
