<?php

/**
 * Test: Flunorette\Hydrators
 * @dataProvider? ../databases.ini
 */
use Flunorette\Connection;
use Flunorette\Hydrators\HydratorArrayHash;
use Flunorette\Hydrators\HydratorColumn;
use Flunorette\Hydrators\HydratorField;
use Flunorette\Hydrators\HydratorPairs;
use Flunorette\Statement;
use Nette\ArrayHash;
use Nette\DateTime;

require __DIR__ . '/../connect.inc.php';
Flunorette\Helpers::loadFromFile($connection, __DIR__ . '/../files/flunorette_blog.sql');

/* @var $connection Connection */
//test hydrator fetch field no result
test(function () use ($connection) {
	$actual = $connection->hydrate(new HydratorField('title'), 'SELECT title FROM article WHERE id = 0');
	Assert::equal(false, $actual);
});

//test hydrator callback
test(function () use ($connection) {
	$actual = $connection->queryArgs('SELECT * FROM user')->hydrate(function (Statement $statement) {
		return $statement->fetchAll(PDO::FETCH_COLUMN, 0);
	});
	Assert::same(array('1', '2', '3', '4'), $actual);
});



//test hydrator fetch column normalized via index
test(function () use ($connection) {
	$actual = $connection->queryArgs('SELECT * FROM user')->hydrate(new HydratorColumn);
	Assert::same(array(1, 2, 3, 4), $actual);
});

//test hydrator fetch column via index
test(function () use ($connection) {
	$actual = $connection->queryArgs('SELECT * FROM user')->hydrate(new HydratorColumn(0, false));
	Assert::same(array('1', '2', '3', '4'), $actual);
});

//test hydrator fetch column via index
test(function () use ($connection) {
	$actual = $connection->queryArgs('SELECT id, published_at FROM article')->hydrate(new HydratorColumn(1));
	$expected = array_map(function ($d) {
		return new DateTime($d);
	}, array('2011-12-10 12:10:00', '2011-12-20 16:20:00', '2012-01-04 22:00:00', '2014-02-01 18:30:00', '2014-01-30 11:45:00'));
	Assert::equal($expected, $actual);
});

//test hydrator fetch column via key
test(function () use ($connection) {
	$actual = $connection->queryArgs('SELECT id, published_at FROM article')->hydrate(new HydratorColumn('published_at'));
	$expected = array_map(function ($d) {
		return new DateTime($d);
	}, array('2011-12-10 12:10:00', '2011-12-20 16:20:00', '2012-01-04 22:00:00', '2014-02-01 18:30:00', '2014-01-30 11:45:00'));
	Assert::equal($expected, $actual);
});



//test hydrator fetch array hash normalized
test(function () use ($connection) {
	$actual = $connection->hydrate(new HydratorArrayHash(), 'SELECT id, published_at FROM article LIMIT 1');
	Assert::equal(array(ArrayHash::from(array('id' => 1, 'published_at' => \Nette\Utils\DateTime::from('2011-12-10 12:10:00')))), $actual);
});

//test hydrator fetch array hash
test(function () use ($connection) {
	$actual = $connection->hydrate(new HydratorArrayHash(false), 'SELECT id, published_at FROM article LIMIT 1');
	Assert::equal(array(ArrayHash::from(array('id' => "1", 'published_at' => '2011-12-10 12:10:00'))), $actual);
});

//test hydrator fetch array hash - zero results
test(function () use ($connection) {
	$actual = $connection->hydrate(new HydratorArrayHash(false), 'SELECT id, published_at FROM article LIMIT 0');
	Assert::equal(array(), $actual);
});



//test hydrator fetch field normalized
test(function () use ($connection) {
	$actual = $connection->hydrate(new HydratorField, 'SELECT MAX(published_at) FROM article');
	Assert::equal(new DateTime('2014-02-01 18:30:00'), $actual);
});

//test hydrator fetch field
test(function () use ($connection) {
	$actual = $connection->hydrate(new HydratorField(0, false), 'SELECT MAX(published_at) FROM article');
	Assert::equal('2014-02-01 18:30:00', $actual);
});

//test hydrator fetch field via key
test(function () use ($connection) {
	$actual = $connection->hydrate(new HydratorField('title', false), 'SELECT title FROM article');
	Assert::equal('article 1', $actual);
});



//test hydrator fetch pairs
test(function () use ($connection) {
	$actual = $connection->hydrate(new HydratorPairs(), 'SELECT id, user_id FROM article ORDER BY id');
	Assert::same(array(1 => 2, 3, 4, 2, 1), $actual);
});

//test hydrator fetch pairs
test(function () use ($connection) {
	$actual = $connection->hydrate(new HydratorPairs('id', 'title'), 'SELECT id, user_id, title FROM article ORDER BY id');
	Assert::same(array(1 => 'article 1', 'article 2', 'article 3', 'article 4', 'article 5'), $actual);
});

//test hydrator fetch pairs
test(function () use ($connection) {
	$actual = $connection->hydrate(new HydratorPairs(null, 'title'), 'SELECT id, user_id, title FROM article ORDER BY id');
	Assert::same(array('article 1', 'article 2', 'article 3', 'article 4', 'article 5'), $actual);
});

//test hydrator fetch pairs
test(function () use ($connection) {
	$actual = $connection->hydrate(new HydratorPairs('id', null), 'SELECT id, user_id FROM article ORDER BY id');
	Assert::same(array(
		1 => array(
			'id' => 1,
			'user_id' => 2,
		),
		2 => array(
			'id' => 2,
			'user_id' => 3,
		),
		3 => array(
			'id' => 3,
			'user_id' => 4,
		),
		4 => array(
			'id' => 4,
			'user_id' => 2,
		),
		5 => array(
			'id' => 5,
			'user_id' => 1,
		)
	,), $actual);
});
