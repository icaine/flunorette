<?php

namespace Flunorette\Queries;

/**
 * SELECT query builder
 *
 * @method SelectQuery  select($column) add one or more columns in SELECT to query
 * @method SelectQuery  leftJoin($statement) add LEFT JOIN to query ($statement can be 'table' name only or 'table:' means back reference)
 * @method SelectQuery  innerJoin($statement) add INNER JOIN to query ($statement can be 'table' name only or 'table:' means back reference)
 * @method SelectQuery  where($statement) add WHERE to query using AND
 * @method SelectQuery  whereAnd($statement) add WHERE to query using AND
 * @method SelectQuery  whereOr($statement) add WHERE to query using OR
 * @method SelectQuery  wherePrimary($id) add WHERE with primary key
 * @method SelectQuery  groupBy($statement) add GROUP BY to query
 * @method SelectQuery  having($statement) add HAVING query
 * @method SelectQuery  orderBy($statement) add ORDER BY to query
 * @method SelectQuery  limit(int $limit) add LIMIT to query
 * @method SelectQuery  offset(int $offset) add OFFSET to query
 */
class SelectQuery extends JoinableQuery {

	function __construct(QueryContext $context = null) {
		$clauses = array(
			'SELECT' => ', ',
			'FROM' => null,
			'JOIN' => array($this, 'getClauseJoin'),
			'WHERE' => array($this, 'getClauseWhere'),
			'GROUP BY' => ',',
			'HAVING' => ' AND ',
			'ORDER BY' => ', ',
			'LIMIT' => null,
			'OFFSET' => null,
			"\n--" => "\n--",
		);

		parent::__construct($clauses, $context);
	}

	protected function contextAttached() {
		parent::contextAttached();
		$this->context->statements['FROM'] = $this->context->getFrom();
		if (!in_array($this->getTableAlias(), $this->context->joins)) {
			$this->context->joins[] = $this->getTableAlias();
		}
	}

	protected function buildQuery() {
		$this->init();
		if (empty($this->context->statements['SELECT'])) {
			$this->context->statements['SELECT'][] = $this->getTableAlias() . '.*';
		}
		return parent::buildQuery();
	}

}
