<?php

//Hydrators

use Flunorette\Connection;
use Flunorette\Hydrators\HydratorArrayHash;
use Flunorette\Hydrators\HydratorColumn;
use Flunorette\Hydrators\HydratorField;
use Flunorette\Statement;
use Nette\ArrayHash;
use Nette\DateTime;

require __DIR__ . '/../connect.inc.php';
/* @var $connection Connection */

//test hydrator callback
$actual = $connection->queryArgs('SELECT * FROM user')->hydrate(function (Statement $statement) {
	return $statement->fetchAll(PDO::FETCH_COLUMN, 0);
});
Assert::same(array('1', '2', '3', '4'), $actual);



//test hydrator fetch column normalized via index
$actual = $connection->queryArgs('SELECT * FROM user')->hydrate(new HydratorColumn);
Assert::same(array(1, 2, 3, 4), $actual);

//test hydrator fetch column via index
$actual = $connection->queryArgs('SELECT * FROM user')->hydrate(new HydratorColumn(0, false));
Assert::same(array('1', '2', '3', '4'), $actual);

//test hydrator fetch column via index
$actual = $connection->queryArgs('SELECT id, published_at FROM article')->hydrate(new HydratorColumn(1));
$expected = array_map(function ($d) { return new DateTime($d); }, array('2011-12-10 12:10:00', '2011-12-20 16:20:00', '2012-01-04 22:00:00', '2014-02-01 18:30:00', '2014-01-30 11:45:00'));
Assert::equal($expected, $actual);

//test hydrator fetch column via key
$actual = $connection->queryArgs('SELECT id, published_at FROM article')->hydrate(new HydratorColumn('published_at'));
$expected = array_map(function ($d) { return new DateTime($d); }, array('2011-12-10 12:10:00', '2011-12-20 16:20:00', '2012-01-04 22:00:00', '2014-02-01 18:30:00', '2014-01-30 11:45:00'));
Assert::equal($expected, $actual);



//test hydrator fetch array hash
$actual = $connection->hydrate(new HydratorArrayHash(false), 'SELECT id, name FROM user');
Assert::equal(ArrayHash::from(array('id' => 1, 'name' => 'Dan')), $actual);



//test hydrator fetch result normalized
$actual = $connection->hydrate(new HydratorField, 'SELECT MAX(published_at) FROM article');
Assert::equal(new DateTime('2014-02-01 18:30:00'), $actual);

//test hydrator fetch result
$actual = $connection->hydrate(new HydratorField(0, false), 'SELECT MAX(published_at) FROM article');
Assert::equal('2014-02-01 18:30:00', $actual);

//test hydrator fetch result via key
$actual = $connection->hydrate(new HydratorField('title', false), 'SELECT title FROM article');
Assert::equal('article 1', $actual);

