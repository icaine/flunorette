<?php

/**
 * Test: Transactions
 * @dataProvider? databases.ini
 */
require __DIR__ . '/connect.inc.php';


//transaction counter
test(function () use ($connection) {
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
});



Flunorette\Helpers::loadFromFile($connection, __DIR__ . "/files/{$driverName}-nette_test1.sql");

test(function() use ($connection) {
	$connection->beginTransaction();
	$connection->query('DELETE FROM book');
	$connection->rollBack();

	Assert::same(3, $connection->fetchField('SELECT id FROM book WHERE id = ?', 3));
});


test(function() use ($connection) {
	$connection->beginTransaction();
	$connection->query('DELETE FROM book');
	$connection->commit();

	Assert::false($connection->fetchField('SELECT id FROM book WHERE id = ?', 3));
});
