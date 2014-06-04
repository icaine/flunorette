<?php

/**
 * Test: Flunorette\Selections: Join.
 * @dataProvider? ../databases.ini
*/



require __DIR__ . '/../connect.inc.php'; // create $connection

Flunorette\Helpers::loadFromFile($connection, __DIR__ . "/{$driverName}-nette_test1.sql");

/* @var $connection Flunorette\Connection */
$oldDelim = $connection->getPreprocessor()->getDelimiteMode();
$connection->getPreprocessor()->setDelimiteMode(Flunorette\SqlPreprocessor::DELIMITE_MODE_DEFAULT);

$apps = array();
foreach ($connection->table('book')->select('book.*')->order('author.name, title') as $book) {  // SELECT `book`.* FROM `book` LEFT JOIN `author` ON `book`.`author_id` = `author`.`id` ORDER BY `author`.`name`, `title`
	$apps[$book->title] = $book->author->name;  // SELECT * FROM `author` WHERE (`author`.`id` IN (12, 11))
}

Assert::same(array(
	'Dibi' => 'David Grudl',
	'Nette' => 'David Grudl',
	'1001 tipu a triku pro PHP' => 'Jakub Vrana',
	'JUSH' => 'Jakub Vrana',
), $apps);


$joinSql = $connection->table('book_tag')->where('book_id', 1)->select('tag.*')->getSql();
Assert::same(reformat('SELECT [tag].* FROM [book_tag] LEFT JOIN [tag] ON [tag].[id] = [book_tag].[tag_id] WHERE ([book_id] = ?)'), $joinSql);


$joinSql = $connection->table('book_tag')->where('book_id', 1)->select('tag.id')->getSql();
Assert::same(reformat('SELECT [tag].[id] FROM [book_tag] LEFT JOIN [tag] ON [tag].[id] = [book_tag].[tag_id] WHERE ([book_id] = ?)'), $joinSql);


$tags = array();
foreach ($connection->table('book_tag')->select('book_tag.*')->where('book.author.name', 'Jakub Vrana')->group('book_tag.tag_id')->order('book_tag.tag_id') as $book_tag) {  // SELECT `book_tag`.* FROM `book_tag` INNER JOIN `book` ON `book_tag`.`book_id` = `book`.`id` INNER JOIN `author` ON `book`.`author_id` = `author`.`id` WHERE (`author`.`name` = ?) GROUP BY `book_tag`.`tag_id`
	$tags[] = $book_tag->tag->name;  // SELECT * FROM `tag` WHERE (`tag`.`id` IN (21, 22, 23))
}

Assert::same(array(
	'PHP',
	'MySQL',
	'JavaScript',
), $tags);


Assert::same(2, $connection->table('author')->where('author_id', 11)->count('book:id')); // SELECT COUNT(book.id) FROM `author` LEFT JOIN `book` ON `author`.`id` = `book`.`author_id` WHERE (`author_id` = 11)

$connection->getPreprocessor()->setDelimiteMode($oldDelim);
