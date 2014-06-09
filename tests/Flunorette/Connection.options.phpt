<?php

/**
 * Test: Nette\Database\Connection connection options.
 * @dataProvider? databases.ini
 */
require __DIR__ . '/connect.inc.php';


if (!class_exists('PDO')) {
	Tester\Environment::skip('Requires PHP extension PDO.');
}


test(function() { // non lazy
	Assert::exception(function() {
		$connection = new Flunorette\Connection('dsn', 'user', 'password');
	}, 'PDOException', 'invalid data source name');
});


test(function() { // lazy
	$connection = new Flunorette\Connection('dsn', 'user', 'password', array('lazy' => TRUE));
	Assert::exception(function() use ($connection) {
		$connection->query('SELECT ?', 10);
	}, 'PDOException', 'invalid data source name');
});


test(function() {
	$connection = new Flunorette\Connection('dsn', 'user', 'password', array('lazy' => TRUE));
	Assert::exception(function() use ($connection) {
		$connection->quote('x');
	}, 'PDOException', 'invalid data source name');
});




// transaction counter
test(function() use ($options) {
	//$connection = new Connection(, array('delimiteMode' => 0));
	$connection = new Flunorette\Connection($options['dsn'], $options['user'], $options['password'], array('transactionCounter' => false));
	Assert::exception(function() use ($connection) {
		$connection->beginTransaction();
		$connection->beginTransaction();
	}, 'PDOException', 'There is already an active transaction');
	$connection->rollBack();
});

test(function() use ($options) {
	$connection = new Flunorette\Connection($options['dsn'], $options['user'], $options['password'], array('transactionCounter' => true));
	$connection->beginTransaction();
	$connection->beginTransaction();
	Tester\Assert::true(true);
	$connection->rollBack();
});



// delimite mode
test(function() use ($options) {
	$connection = new Flunorette\Connection($options['dsn'], $options['user'], $options['password'], array('delimiteMode' => Flunorette\SqlPreprocessor::DELIMITE_MODE_NONE));
	Flunorette\Helpers::loadFromFile($connection, __DIR__ . "/files/flunorette_blog.sql");
	Assert::same('SELECT user.name FROM user', $connection->getPreprocessor()->tryDelimite('SELECT user.name FROM user'));
});

test(function() use ($options) {
	$connection = new Flunorette\Connection($options['dsn'], $options['user'], $options['password'], array('delimiteMode' => Flunorette\SqlPreprocessor::DELIMITE_MODE_DEFAULT));
	Flunorette\Helpers::loadFromFile($connection, __DIR__ . "/files/flunorette_blog.sql");
	Assert::same(reformat('SELECT [user].[name] FROM [user]'), $connection->getPreprocessor()->tryDelimite('SELECT user.name FROM user'));
});