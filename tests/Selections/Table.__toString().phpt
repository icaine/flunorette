<?php

/**
 * Test: Nette\Database\Table: Calling __toString().
 *
 * @author     Jakub Vrana
 * @author     Jan Skrasek
 * @dataProvider? databases.ini
*/



require __DIR__ . '/connect.inc.php'; // create $connection

Flunorette\Helpers::loadFromFile($connection, __DIR__ . "/{$driverName}-nette_test1.sql");


Assert::same('2', (string) $connection->table('book')->get(2));


Assert::same(2, $connection->table('book')->get(2)->getPrimary());
