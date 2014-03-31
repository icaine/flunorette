<?php

//Interchangable context

use Flunorette\SelectQuery;
use Flunorette\DeleteQuery;
use Flunorette\UpdateQuery;

require __DIR__ . '/../connect.inc.php';

/* @var $connection Flunorette\Connection */

$query = $connection->createSelect('user');
$query->where('id', '1');
$cloned = SelectQuery::fromQuery($query, true);
$cloned->order('name');
$notcloned = SelectQuery::fromQuery($query);
$notcloned->order('id');
Assert::same('SELECT * FROM user WHERE (id = ?) ORDER BY id', $query->getQuery());
Assert::same('SELECT * FROM user WHERE (id = ?) ORDER BY name', $cloned->getQuery());
Assert::same($query->getQuery(), $notcloned->getQuery());



$query = $connection->createSelect('user');
$query->select('user.*');
Assert::same('SELECT user.* FROM user', $query->getQuery());
$new = DeleteQuery::fromQuery($query);
Assert::same('DELETE FROM user', $new->getQuery());
Assert::same('SELECT user.* FROM user', $query->getQuery());



$select = $connection->createSelect('user');
$select->select('user.*');
$select->wherePrimary(1);
$delete = DeleteQuery::fromQuery($select);
Assert::same('DELETE FROM user WHERE (user.id = ?)', $delete->getQuery());
Assert::same('SELECT user.* FROM user WHERE (user.id = ?)', $select->getQuery());
Assert::same(array(1), $select->getParameters());
Assert::same(array(1), $delete->getParameters());

$update = UpdateQuery::fromQuery($delete);
$update->set('name', 'Daniel');
Assert::same('UPDATE user SET name = ? WHERE (user.id = ?)', $update->getQuery());
Assert::same(array('Daniel', 1), $update->getParameters());
Assert::same('DELETE FROM user WHERE (user.id = ?)', $delete->getQuery());
Assert::same('SELECT user.* FROM user WHERE (user.id = ?)', $select->getQuery());
Assert::same(array(1), $select->getParameters());
Assert::same(array(1), $delete->getParameters());

$newSelect = new SelectQuery();
$newSelect->setContext($delete->getContext());
Assert::same('SELECT user.* FROM user WHERE (user.id = ?)', $newSelect->getQuery());
