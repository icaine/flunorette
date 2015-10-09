<?php

/**
 * Test: Transactions
 * @dataProvider? databases.ini
 */
use Flunorette\Connection;

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


test(function() use ($connection) {
	$book = array(
		'title' => 'Winterfell',
		'author_id' => 11,
	);

	Assert::exception(function () use ($connection, $book) {
		$result = $connection->doInTransaction(function (Connection $connection, $book) {
			$connection->exec('INSERT INTO book', $book);
			throw new \Exception();
		}, array($connection, $book));
	}, 'Exception');

	Assert::same(0, $connection->fetchField('SELECT COUNT(*) FROM book'));
});


test(function() use ($connection) {
	$book = array(
		'title' => 'Winterfell',
		'author_id' => 11,
	);

	$result = $connection->doInTransaction(function (Connection $connection, $book) {
		return $connection->exec('INSERT INTO book', $book);
	}, array($connection, $book));

	Assert::same(1, $result);
	Assert::same(1, $connection->fetchField('SELECT COUNT(*) FROM book'));
});


test(function() use ($connection) {
	Assert::exception(function () use ($connection) {
		$connection->doInTransaction('unknownFunctionThatIsNotCallable');
	}, 'Flunorette\InvalidArgumentException', 'First parameter is not callable!');
});
