<?php

/**
 * Test: Flunorette\Selection: Fetch pairs, column and field
 * @dataProvider? ../databases.ini
*/



require __DIR__ . '/../connect.inc.php'; // create $connection

Flunorette\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");


$apps = $connection->table('book')->order('title')->fetchPairs('id', 'title');  // SELECT * FROM `book` ORDER BY `title`
Assert::same(array(
	1 => '1001 tipu a triku pro PHP',
	4 => 'Dibi',
	2 => 'JUSH',
	3 => 'Nette',
), $apps);


$ids = $connection->table('book')->order('id')->fetchPairs('id', 'id');  // SELECT * FROM `book` ORDER BY `id`
Assert::same(array(
	1 => 1,
	2 => 2,
	3 => 3,
	4 => 4,
), $ids);


$ids = $connection->table('book')->order('id')->fetchPairs(null, 'id');  // SELECT * FROM `book` ORDER BY `id`
Assert::same(array(
	1 => 1,
	2 => 2,
	3 => 3,
	4 => 4,
), $ids);


$connection->table('author')->get(11)->update(array('born' => new DateTime('2002-02-20')));
$connection->table('author')->get(12)->update(array('born' => new DateTime('2002-02-02')));
$list = $connection->table('author')->where('born IS NOT NULL')->order('born')->fetchPairs('born', 'name');
Assert::same(array(
	'2002-02-02 00:00:00' => 'David Grudl',
	'2002-02-20 00:00:00' => 'Jakub Vrana',
), $list);



$apps = $connection->table('book')->order('title')->fetchColumn('id');  // SELECT * FROM `book` ORDER BY `title`
Assert::same(array(
	1,
	4,
	2,
	3,
), $apps);



$books = $connection->table('book')->order('title')->select('COUNT(*) AS cnt')->fetchField('cnt');  // SELECT * FROM `book` ORDER BY `title`
Assert::same(4, $books);