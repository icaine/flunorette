<?php

/**
 * Test: Transactions
 * @dataProvider? databases.ini
 */
require __DIR__ . '/connect.inc.php';
/* @var $connection Flunorette\Connection */

Assert::same(0, $connection->getTransactionCounter()->getCount());
Assert::true($connection->beginTransaction());
Assert::same(1, $connection->getTransactionCounter()->getCount());
Assert::true($connection->beginTransaction());
Assert::same(2, $connection->getTransactionCounter()->getCount());
Assert::true($connection->commit());
Assert::same(1, $connection->getTransactionCounter()->getCount());
Assert::true($connection->commit());

Assert::true($connection->beginTransaction());
Assert::true($connection->rollBack());

Assert::exception(function () use ($connection) {
	$connection->rollBack();
}, 'PDOException', 'There is no active transaction');


Assert::true($connection->beginTransaction());
Assert::true($connection->beginTransaction());
Assert::true($connection->rollBack());


Assert::exception(function () use ($connection) {
	$connection->commit();
}, 'PDOException', 'No transaction is started.');

Assert::same(0, $connection->getTransactionCounter()->getCount());

