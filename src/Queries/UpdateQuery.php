<?php

namespace Flunorette;

/**
 * UPDATE query builder
 *
 * @method UpdateQuery  leftJoin($statement) add LEFT JOIN to query ($statement can be 'table' name only or 'table:' means back reference)
 * @method UpdateQuery  innerJoin($statement) add INNER JOIN to query ($statement can be 'table' name only or 'table:' means back reference)
 * @method UpdateQuery  where($statement) add WHERE to query using AND
 * @method UpdateQuery  whereAnd($statement) add WHERE to query using AND
 * @method UpdateQuery  whereOr($statement) add WHERE to query using OR
 * @method UpdateQuery  wherePrimary($id) add WHERE with primary key
 * @method UpdateQuery  orderBy($column) add ORDER BY to query
 * @method UpdateQuery  limit(int $limit) add LIMIT to query
 */
class UpdateQuery extends JoinableQuery {

	public function __construct(QueryContext $context = null) {
		$clauses = array(
			'UPDATE' => array($this, 'getClauseUpdate'),
			'JOIN' => array($this, 'getClauseJoin'),
			'SET' => array($this, 'getClauseSet'),
			'WHERE' => array($this, 'getClauseWhere'),
			'ORDER BY' => ', ',
			'LIMIT' => null,
		);

		parent::__construct($clauses, $context);
	}

	protected function contextAttached() {
		parent::contextAttached();
		$this->context->statements['UPDATE'] = $this->context->getFrom();
		if (!in_array($this->getTableAlias(), $this->context->joins)) {
			$this->context->joins[] = $this->getTableAlias();
		}
	}

	protected function buildQuery() {
		$this->init();
		return parent::buildQuery();
	}

	/**
	 * @param string|array $fieldOrArray
	 * @param mixed $value
	 * @return $this
	 * @throws InvalidArgumentException
	 */
	public function set($fieldOrArray, $value = null) {
		$this->getContext(); //context must be available
		if (!$fieldOrArray) {
			return $this;
		}

		if (is_string($fieldOrArray)) {
			$this->context->statements['SET'][$fieldOrArray] = $value;
			if ($value instanceof SqlLiteral && $value->getParameters()) {
				$this->context->parameters['SET'] = array_merge($this->context->parameters['SET'], $value->getParameters());
			}
		} elseif (is_array($fieldOrArray)) {
			foreach ($fieldOrArray as $field => $value) {
				if (false == is_string($field)) {
					throw new InvalidArgumentException("Array keys must be strings");
				}
				$this->set($field, $value);
			}
		} else {
			throw new InvalidArgumentException('You must pass a value or provide the SET list as an associative array. column => value');
		}

		return $this;
	}

	protected function getClauseUpdate() {
		return 'UPDATE ' . $this->context->statements['UPDATE'];
	}

	protected function getClauseSet() {
		$setArray = array();
		foreach ($this->context->statements['SET'] as $field => $value) {
			if ($value instanceof SqlLiteral) {
				$setArray[] = $field . ' = ' . $value;
			} else {
				$setArray[] = $field . ' = ?';
				$this->context->parameters['SET'][$field] = $value;
			}
		}

		return ' SET ' . implode(', ', $setArray);
	}

}
