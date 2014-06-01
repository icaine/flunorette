<?php

namespace Flunorette\Queries;

/**
 * DELETE query builder
 *
 * @method DeleteQuery  leftJoin($statement) add LEFT JOIN to query ($statement can be 'table' name only or 'table:' means back reference)
 * @method DeleteQuery  innerJoin($statement) add INNER JOIN to query ($statement can be 'table' name only or 'table:' means back reference)
 * @method DeleteQuery  from(string $table) add LIMIT to query
 * @method DeleteQuery  where($statement) add WHERE to query using AND
 * @method DeleteQuery  whereAnd($statement) add WHERE to query using AND
 * @method DeleteQuery  whereOr($statement) add WHERE to query using OR
 * @method DeleteQuery  wherePrimary($id) add WHERE with primary key
 * @method DeleteQuery  orderBy($column) add ORDER BY to query
 * @method DeleteQuery  limit(int $limit) add LIMIT to query
 */
class DeleteQuery extends JoinableQuery {

	public function __construct(QueryContext $context = null) {
		$clauses = array(
			'DELETE FROM' => array($this, 'getClauseDeleteFrom'),
			'DELETE' => array($this, 'getClauseDelete'),
			'FROM' => null,
			'JOIN' => array($this, 'getClauseJoin'),
			'WHERE' => array($this, 'getClauseWhere'),
			'ORDER BY' => ', ',
			'LIMIT' => null,
		);

		parent::__construct($clauses, $context);
	}

	/** DELETE IGNORE - Delete operation fails silently
	 * @return DeleteQuery
	 */
	public function ignore() {
		$this->getContext()->ignore = true;
		return $this;
	}

	protected function contextAttached() {
		parent::contextAttached();
		$this->context->statements['FROM'] = null;
		$this->context->statements['DELETE FROM'] = $this->context->getFrom();
		$this->context->statements['DELETE'] = $this->context->getFrom();
	}

	/**
	 * @return string
	 */
	protected function buildQuery() {
		$this->init();
		if ($this->context->statements['FROM']) {
			unset($this->clauses['DELETE FROM']);
		} else {
			unset($this->clauses['DELETE']);
		}
		return parent::buildQuery();
	}

	protected function getClauseDelete() {
		return 'DELETE' . ($this->context->ignore ? ' IGNORE' : '') . ' ' . $this->context->statements['DELETE'];
	}

	protected function getClauseDeleteFrom() {
		return 'DELETE' . ($this->context->ignore ? ' IGNORE' : '') . ' FROM ' . $this->context->statements['DELETE FROM'];
	}

}
