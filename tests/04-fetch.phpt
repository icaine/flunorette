<?php

//Fetching data

use Flunorette\Connection;
use Flunorette\HydratorArrayHash;
use Flunorette\HydratorResult;
use Flunorette\Statement;
use Nette\ArrayHash;
use Nette\DateTime;

require __DIR__ . '/connect.inc.php';
/* @var $connection Connection */

//test callback
$actual = $connection->queryArgs('SELECT * FROM user')->hydrate(function (Statement $statement) {
	return $statement->fetchAll(PDO::FETCH_COLUMN, 0);
});
Assert::same(array('1', '2', '3', '4'), $actual);

//test callback
$actual = $connection->hydrate(new HydratorArrayHash(false), 'SELECT id, name FROM user');
Assert::equal(ArrayHash::from(array('id' => 1, 'name' => 'Dan')), $actual);

//test callback
$actual = $connection->hydrate(new HydratorResult(), 'SELECT MAX(published_at) FROM article');
Assert::equal(new DateTime('2014-02-01 18:30:00'), $actual);

////test hydrator
//$actual = $connection->queryArgs('SELECT * FROM user')->hydrate(new \Flunorette\HydratorResult);
//Assert::same('1', $actual);
