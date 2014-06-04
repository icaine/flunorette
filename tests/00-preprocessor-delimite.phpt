<?php

/**
 * Test: Flunorette\SqlPreprocessor::tryDelimite
 * @dataProvider? databases.ini
 */
use Flunorette\SqlPreprocessor;

include_once __DIR__ . '/connect.inc.php';
/* @var $connection Flunorette\Connection */

#replace test
$preprocessor = new SqlPreprocessor($connection, SqlPreprocessor::DELIMITE_MODE_REPLACE);
test(function() use ($preprocessor) {
	$sql = $preprocessor->tryDelimite("SELECT owner.[id] FROM author AS `owner` WHERE id = ? OR \"id\" = 2 OR name IN ('Dan', 'marek', 1, 2) OR [name] LIKE '%a%'");
	Assert::same("SELECT owner.`id` FROM author AS `owner` WHERE id = ? OR `id` = 2 OR name IN ('Dan', 'marek', 1, 2) OR `name` LIKE '%a%'", $sql);
});

#adding delimiters test
$preprocessor = new SqlPreprocessor($connection, SqlPreprocessor::DELIMITE_MODE_ADD);
test(function() use ($preprocessor) {
	$sql = $preprocessor->tryDelimite("SELECT `owner`.id FROM author AS owner WHERE id = ? OR id=2 OR id = :id name IN ('Dan', 'marek', 1, 2) OR name LIKE '%a%'");
	Assert::same("SELECT `owner`.`id` FROM `author` AS `owner` WHERE `id` = ? OR `id`=2 OR `id` = :id `name` IN ('Dan', 'marek', 1, 2) OR `name` LIKE '%a%'", $sql);
});

$preprocessor = new SqlPreprocessor($connection);
test(function() use ($preprocessor) {
	$sql = $preprocessor->tryDelimite("SELECT owner.[id] FROM author AS `owner` WHERE id = ? OR \"id\" = 2 OR name IN ('Dan', 'marek', 1, 2) OR [name] LIKE '%a%'");
	Assert::same("SELECT `owner`.`id` FROM `author` AS `owner` WHERE `id` = ? OR `id` = 2 OR `name` IN ('Dan', 'marek', 1, 2) OR `name` LIKE '%a%'", $sql);
});

$preprocessor = new SqlPreprocessor($connection);
test(function() use ($preprocessor) { //not delimiting inside sql string
	$sql = $preprocessor->tryDelimite($src = "INSERT INTO user (name, web, born) VALUES ('Catelyn Stark \\\\', 'http://example.com is \n \\\\\\'best\\'', '2011-11-11 00:00:00', '')");
	Assert::same("INSERT INTO `user` (`name`, `web`, `born`) VALUES ('Catelyn Stark \\\\', 'http://example.com is \n \\\\\\'best\\'', '2011-11-11 00:00:00', '')", $sql);
});
