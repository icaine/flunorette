<?php

/**
 * Test: Flunorette\Selections: Shared related data caching.
 * @dataProvider? ../databases.ini
 */



require __DIR__ . '/../connect.inc.php'; // create $connection

Flunorette\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");

test(function () use ($connection) {
	$books = $connection->table('book');
	foreach ($books as $book) {
		foreach ($book->related('book_tag') as $bookTag) {
			$bookTag->tag;
		}
	}

	$tags = array();
	foreach ($books as $book) {
		foreach ($book->related('book_tag_alt') as $bookTag) {
			$tags[] = $bookTag->tag->name;
		}
	}

	Assert::same(array(
		'PHP',
		'MySQL',
		'JavaScript',
		'Neon',
	), $tags);
});

test(function () use ($connection) {
	$connection->query('UPDATE book SET translator_id = 12 WHERE id = 2');
	$author = $connection->table('author')->get(11);

	foreach ($author->related('book')->limit(1) as $book) {
		$book->ref('author', 'translator_id')->name;
	}

	$translators = array();
	foreach ($author->related('book')->limit(2) as $book) {
		$translators[] = $book->ref('author', 'translator_id')->name;
	}
	sort($translators);

	Assert::same(array(
		'David Grudl',
		'Jakub Vrana',
	), $translators);
});

test(function () use ($connection) { // cache can't be affected by inner query!
	$author = $connection->table('author')->get(11);
	$secondBookTagRels = NULL;
	foreach ($author->related('book')->order('id') as $book) {
		if (!isset($secondBookTagRels)) {
			$bookFromAnotherSelection = $author->related('book')->where('id', $book->id)->fetch();
			$bookFromAnotherSelection->related('book_tag')->fetchPairs('id');
			$secondBookTagRels = array();
		} else {
			foreach ($book->related('book_tag') as $bookTagRel) {
				$secondBookTagRels[] = $bookTagRel->tag->name;
			}
		}
	}
	Assert::same(array('JavaScript'), $secondBookTagRels);
});