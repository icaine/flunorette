<?php

/**
 * Test: Connection exceptions.
 * @dataProvider? databases.ini  mysql
 */
require __DIR__ . '/connect.inc.php'; // create $options

$e = Assert::exception(function () use ($options) {
	$connection = new Flunorette\Connection($options['dsn'], 'unknown', 'unknown');
}, 'Flunorette\ConnectionException', '%a% Access denied for user %a%');
Assert::same(1045, $e->getDriverCode());
Assert::contains($e->getSqlState(), array('HY000', '28000'));
Assert::same($e->getCode(), $e->getSqlState());


$e = Assert::exception(function () use ($connection) {
	$connection->rollback();
}, 'Flunorette\DriverException', 'There is no active transaction', 0);
Assert::same(null, $e->getDriverCode());


$e = Assert::exception(function () use ($connection) {
	$connection->commit();
}, 'Flunorette\DriverException', 'No transaction is started.', 0);
Assert::same(null, $e->getDriverCode());