<?php

namespace Flunorette\Queries;

use Flunorette\Exception;
use Flunorette\InvalidArgumentException;
use Flunorette\SqlLiteral;
use Traversable;

/**
 * INSERT query builder
 */
class InsertQuery extends Query {

	private $columns = array();

	private $firstValue = array();

	public function __construct(QueryContext $context = null) {
		$clauses = array(
			'INSERT INTO' => array($this, 'getClauseInsertInto'),
			'VALUES' => array($this, 'getClauseValues'),
			'ON DUPLICATE KEY UPDATE' => array($this, 'getClauseOnDuplicateKeyUpdate'),
		);

		parent::__construct($clauses, $context);
	}

	protected function contextAttached() {
		parent::contextAttached();
		$this->context->statements['INSERT INTO'] = $this->context->getFrom();
		$this->columns = array();
		$this->firstValue = array();
	}

	protected function buildQuery() {
		$this->init();
		return parent::buildQuery();
	}

	/** INSERT IGNORE - insert operation fails silently
	 * @return InsertQuery
	 */
	public function ignore() {
		$this->getContext()->ignore = true;
		return $this;
	}

	/** Add ON DUPLICATE KEY UPDATE
	 * @param array $values
	 * @return InsertQuery
	 */
	public function onDuplicateKeyUpdate($values) {
		$this->getContext(); //context must be available
		$this->context->statements['ON DUPLICATE KEY UPDATE'] = array_merge(//TODO check whether addStatement() should not be used instead
			$this->context->statements['ON DUPLICATE KEY UPDATE'], $values
		);
		return $this;
	}

	/**
	 * Add VALUES
	 * @param $values
	 * @return InsertQuery
	 * @throws Exception
	 */
	public function values($values) {
		$this->getContext(); //context must be available
		if (false == is_array($values)) {
			if ($values instanceof Traversable) {
				$values = iterator_to_array($values);
			} else {
				throw new InvalidArgumentException('Param VALUES for INSERT query must be array');
			}
		}

		$first = reset($values);
		if (is_string(key($values))) {
			# is one row array
			$this->addOneValue($values);
		} elseif (is_array($first) && is_string(key($first))) {
			# this is multi values
			foreach ($values as $oneValue) {
				$this->addOneValue($oneValue);
			}
		}
		return $this;
	}

	private function addOneValue($oneValue) {
		# check if all $keys are strings
		foreach ($oneValue as $key => $value) {
			if (!is_string($key)) {
				throw new InvalidArgumentException('INSERT query: All keys of value array have to be strings.');
			}
			if ($value instanceof SqlLiteral && $value->getParameters()) {
				$this->context->parameters['VALUES'] = array_merge($this->context->parameters['VALUES'], $value->getParameters());
			}
		}
		if (!$this->firstValue) {
			$this->firstValue = $oneValue;
		}
		if (!$this->columns) {
			$this->columns = array_keys($oneValue);
		}

		if ($this->columns != array_keys($oneValue)) {
			throw new InvalidArgumentException('INSERT query: All VALUES have to same keys (columns).');
		}
		$this->context->statements['VALUES'][] = $oneValue;
	}

	protected function getClauseInsertInto() {
		return 'INSERT' . ($this->context->ignore ? ' IGNORE' : '') . ' INTO ' . $this->context->statements['INSERT INTO'];
	}

	protected function getClauseValues() {
		$valuesArray = array();
		foreach ($this->context->statements['VALUES'] as $rows) {
			$quoted = array_map(array($this, 'quote'), $rows);
			$valuesArray[] = '(' . implode(', ', $quoted) . ')';
		}
		$columns = implode(', ', $this->columns);
		$values = implode(', ', $valuesArray);
		return " ($columns) VALUES $values";
	}

	protected function getClauseOnDuplicateKeyUpdate() {
		$result = array();
		foreach ($this->context->statements['ON DUPLICATE KEY UPDATE'] as $key => $value) {
			$result[] = "$key = " . $this->quote($value);
		}
		return ' ON DUPLICATE KEY UPDATE ' . implode(', ', $result);
	}

}
