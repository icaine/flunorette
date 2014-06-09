<?php

/**
 * Test: Flunorette\Queries - Queries with joins
 * @dataProvider? ../databases.ini
 */
use Flunorette\SqlLiteral;

require __DIR__ . '/../connect.inc.php';
Flunorette\Helpers::loadFromFile($connection, __DIR__ . '/../files/flunorette_blog.sql');

//explicit join same table
$query = $connection->createSelect('user');
$query->innerJoin('user AS creator ON user.created_by = creator.id');
$query->where('user.id = 1');
Assert::same('SELECT user.* FROM user INNER JOIN user AS creator ON user.created_by = creator.id WHERE (user.id = 1)', $query->getQuery());

//explicit autojoin
$query = $connection->createSelect('article');
$query->leftJoin('user');
Assert::same("SELECT article.* FROM article LEFT JOIN user ON user.id = article.user_id", $query->getQuery());

//explicit autojoin with column hint
$query = $connection->createSelect('article');
$query->leftJoin('user#approver');
Assert::same("SELECT article.* FROM article LEFT JOIN user ON user.id = article.approver", $query->getQuery());

//explicit autojoin with alias
$query = $connection->createSelect('article');
$query->leftJoin('user author');
Assert::same("SELECT article.* FROM article LEFT JOIN user AS author ON author.id = article.user_id", $query->getQuery());

//explicit autojoin with alias
$query = $connection->createSelect('article');
$query->leftJoin('user AS author');
Assert::same("SELECT article.* FROM article LEFT JOIN user AS author ON author.id = article.user_id", $query->getQuery());

//explicit autojoin with alias and column hint
$query = $connection->createSelect('article');
$query->leftJoin('user#approver AS approver');
Assert::same("SELECT article.* FROM article LEFT JOIN user AS approver ON approver.id = article.approver", $query->getQuery());

//auto join missing relation (in this case wrong direction is used)
$query = $connection->createSelect('user');
$query->where('comment.id = 1');
Assert::exception(array($query, 'getQuery'), 'Flunorette\\Reflections\\ReflectionException', 'No reference found for $user->comment but reverse $comment->user was found (wrong direction?)');

//auto join missing relation (in this case wrong direction is used)
$query = $connection->createSelect('comment');
$query->where('user:id = 1');
Assert::exception(array($query, 'getQuery'), 'Flunorette\\Reflections\\ReflectionException', 'No reference found for $comment->related(user) but reverse one $user->related(comment) was found (wrong direction?).');

//back ref auto joins
$query = $connection->createSelect('user');
$query->where('comment:id = 1');
Assert::same('SELECT user.* FROM user LEFT JOIN comment ON comment.user_id = user.id WHERE (comment.id = 1)', $query->getQuery());

//back ref auto joins
$query = $connection->createSelect('user');
$query->select('user.*, COUNT(comment:id) AS comment_count');
Assert::same('SELECT user.*, COUNT(comment.id) AS comment_count FROM user LEFT JOIN comment ON comment.user_id = user.id', $query->getQuery());

//back ref auto joins with alias
$query = $connection->createSelect('user');
$query->select('user.*, COUNT(comment_aliased.id) AS comment_count');
$query->leftJoin('comment: AS comment_aliased');
Assert::same('SELECT user.*, COUNT(comment_aliased.id) AS comment_count FROM user LEFT JOIN comment AS comment_aliased ON comment_aliased.user_id = user.id', $query->getQuery());

//back ref auto joins with hint
$query = $connection->createSelect('user');
$query->select('user.*, COUNT(article#approver:id) AS approved_articles');
Assert::same('SELECT user.*, COUNT(article.id) AS approved_articles FROM user LEFT JOIN article ON article.approver = user.id', $query->getQuery());

//back ref auto joins with alias and hint
$query = $connection->createSelect('user');
$query->select('user.*, COUNT(approved_articles.id) AS approved_count');
$query->leftJoin('article#approver AS approved_articles');
Assert::same('SELECT user.*, COUNT(approved_articles.id) AS approved_count FROM user LEFT JOIN article AS approved_articles ON approved_articles.id = user.approver', $query->getQuery());

//multiple auto joins
$query = $connection->createSelect('article');
$query->leftJoin('user.city.region');
$query->where('region.id', 1);
Assert::same("SELECT article.* FROM article LEFT JOIN user ON user.id = article.user_id  LEFT JOIN city ON city.id = user.city_id  LEFT JOIN region ON region.id = city.region_id WHERE (region.id = ?)", $query->getQuery());
Assert::same(array(1), $query->getParameters());

//sql literal in join clause
$query = $connection->createSelect('article');
$query->join('user ON ?', new SqlLiteral('article.user_id = user.id AND user.id = ?', 1));
Assert::same("SELECT article.* FROM article JOIN user ON article.user_id = user.id AND user.id = ?", $query->getQuery());
Assert::same(array(1), $query->getParameters());

//auto join column hints
$query = $connection->createSelect('article');
$query->where('user#approver.city.region.country.id', 1);
$query->where('country.id', 1);
Assert::same("SELECT article.* FROM article LEFT JOIN user ON user.id = article.approver  LEFT JOIN city ON city.id = user.city_id  LEFT JOIN region ON region.id = city.region_id  LEFT JOIN country ON country.id = region.country_id WHERE (country.id = ?) AND (country.id = ?)", $query->getQuery());
Assert::same(array(1, 1), $query->getParameters());

//auto join column hints
$query = $connection->createSelect('article');
$query->where('user#approver.city#city_id.region#region_id.country#country_id.id', 1);
$query->where('country.id', 1);
Assert::same("SELECT article.* FROM article LEFT JOIN user ON user.id = article.approver  LEFT JOIN city ON city.id = user.city_id  LEFT JOIN region ON region.id = city.region_id  LEFT JOIN country ON country.id = region.country_id WHERE (country.id = ?) AND (country.id = ?)", $query->getQuery());
Assert::same(array(1, 1), $query->getParameters());

//full join - articles approved by admin
$query = $connection->createSelect('article');
$query->leftJoin('user ON user.id = article.approver');
$query->where('user.type', 'admin');
Assert::same("SELECT article.* FROM article LEFT JOIN user ON user.id = article.approver WHERE (user.type = ?)", $query->getQuery());
Assert::same(array('admin'), $query->getParameters());

//autojoin with hint in order by
$query = $connection->createSelect('article');
$query->order('user#approver.type', 'admin');
Assert::same("SELECT article.* FROM article LEFT JOIN user ON user.id = article.approver ORDER BY user.type = ?", $query->getQuery());
Assert::same(array('admin'), $query->getParameters());

//back ref autojoin with hint
$query = $connection->createSelect('user');
$query->select('user.*, article#approver:content');
Assert::same("SELECT user.*, article.content FROM user LEFT JOIN article ON article.approver = user.id", $query->getQuery());

//back ref autojoin via key
$query = $connection->createSelect('article');
$query->select('category.name');
Assert::same("SELECT categories.name FROM article LEFT JOIN categories ON categories.id = article.category_id", $query->getQuery());

//back ref autojoin with hint
$query = $connection->createSelect('user');
$query->select('created.name');
Assert::same("SELECT created.name FROM user LEFT JOIN user AS created ON created.id = user.created_by", $query->getQuery());
