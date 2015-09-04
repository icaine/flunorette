<?php

/**
 * Test: Connection exceptions.
 */
use Flunorette\Connection;

require __DIR__ . '/../bootstrap.php';

$e = Assert::exception(function () {
	$connection = new Connection('unknown');
}, 'Flunorette\ConnectionException', 'invalid data source name', 0);
Assert::same(null, $e->getDriverCode());
Assert::same(null, $e->getSqlState());