<?php

/**
 * Test: Flunorette\Row.
 * @dataProvider? ../databases.ini
 */



require __DIR__ . '/../connect.inc.php'; // create $connection

Flunorette\Helpers::loadFromFile($connection, __DIR__ . "/{$driverName}-nette_test1.sql");

// numeric field
$row = $connection->fetch("SELECT 123 AS {$connection->supplementalDriver->delimite('123')}");


Assert::same(123, $row->{123});
Assert::same(123, $row->{'123'});
Assert::true(isset($row->{123}));
Assert::false(isset($row->{1}));

Assert::same(123, $row[0]);
Assert::true(isset($row[0]));
Assert::false(isset($row[123]));
//Assert::false(isset($row['0'])); // this is buggy since PHP 5.4 (bug #63217)
Assert::false(isset($row[1]));


Assert::error(function () use ($row) {
	$row->{1};
}, E_NOTICE, 'Undefined property: Flunorette\Row::$1');

Assert::error(function () use ($row) {
	$row[1];
}, E_USER_NOTICE, 'Undefined offset: Flunorette\Row[1]');