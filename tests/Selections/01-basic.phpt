<?php

/**
 * Test: Compatibility with Nette Database
 * @dataProvider? ../databases.ini
 */
require __DIR__ . '/../connect.inc.php';
Flunorette\Helpers::loadFromFile($connection, __DIR__ . '/../flunorette_blog.sql');

function e($key, $value, $indent = 0) {
	echo str_repeat("\t", $indent) . "$key: $value\n";
}

/* @var $connection Flunorette\Connection */

//$ndb = new \Flunorette\Connection('mysql:dbname=flunorette_blog;host=127.0.0.1', 'root', null);
//$ndb->setDatabaseReflection(new \Flunorette\Reflection\DiscoveredReflection($cacheStorage));
//\Nette\Diagnostics\Debugger::$bar->addPanel($panel = new \Flunorette\Diagnostics\ConnectionPanel());
//$ndb->onQuery[] = array($panel, 'logQuery');
//
//$nsel = $ndb->table('user')->select('*')->order('id DESC');


//$select = $ndb->table('user')
$select = $connection->table('user')
	->select('*')
	->order('id DESC');

ob_start();
foreach ($select as $user) { /* @var $user \Flunorette\ActiveRow */
	e('author', $user->name);
	if ($user->type == 'admin') { //if admin show only news
		foreach ($user->related('article')->where('category_id', 1) as $article) {
			e('news title', $article->title, 1);
			foreach ($article->related('article_tag') as $articleTag) {
				e('tag', $articleTag->tag->name, 2);
			}

			e('category via ->category', $article->category->name, 2);
			e('category via ->categories', $article->categories->name, 2);
		}
	} else {
		$cc = $user->city;
		e('city ', $cc->name, 1);
		$cr = $cc->region;
		e('region ', $cr->name, 1);
		$cn = $cr->country;
		e('country ', $cn->name, 1);

		foreach ($user->related('article') as $article) {
			e('article title', $article->title, 1);
			foreach ($article->related('article_tag') as $articleTag) {
				e('tag', $articleTag->tag->name, 2);
			}

			e('category via ->categories', $article->categories->name, 2);
			e('category via ->category', $article->category->name, 2);
			e('category via ->ref(categories)', $article->ref('categories')->name, 2);
			e('category via ->ref(category)', $article->ref('category')->name, 2);
		}
	}

	$createdBy = $user->ref('user', 'created_by');
	if ($createdBy) {
		e('author created by', $createdBy->name, 1);

		$cc = $createdBy->city;
		e('city ', $cc->name, 2);
		$cr = $cc->region;
		e('region ', $cr->name, 2);
		$cn = $cr->country;
		e('country ', $cn->name, 2);
	}

}
$actual = ob_get_clean();

$expected = <<<EXPECTED
author: Marek
	city : Bratislava
	region : Bratislavský
	country : Slovakia
	article title: article 3
		tag: php
		tag: fluent pdo
		tag: mysql
		category via ->categories: news
		category via ->category: news
		category via ->ref(categories): news
		category via ->ref(category): news
	author created by: Dan
		city : Praha
		region : Středočeský
		country : Czech Republic
author: Jakub
	city : Praha
	region : Středočeský
	country : Czech Republic
	article title: article 2
		tag: php
		tag: notorm
		tag: mysql
		category via ->categories: tutorials
		category via ->category: tutorials
		category via ->ref(categories): tutorials
		category via ->ref(category): tutorials
	author created by: Dan
		city : Praha
		region : Středočeský
		country : Czech Republic
author: David
	city : Brno
	region : Jihomoravský
	country : Czech Republic
	article title: article 1
		tag: php
		tag: nette
		category via ->categories: news
		category via ->category: news
		category via ->ref(categories): news
		category via ->ref(category): news
	article title: article 4
		category via ->categories: tutorials
		category via ->category: tutorials
		category via ->ref(categories): tutorials
		category via ->ref(category): tutorials
	author created by: Dan
		city : Praha
		region : Středočeský
		country : Czech Republic
author: Dan
	news title: article 5
		tag: flunorette
		category via ->category: news
		category via ->categories: news

EXPECTED;

Assert::same($expected, $actual);