<?php

namespace Flunorette\Queries;

use DateTime;
use Flunorette\Drivers\IDriver;
use Flunorette\Exception;
use Flunorette\Helpers;
use Flunorette\InvalidArgumentException;
use Flunorette\InvalidStateException;
use Flunorette\IQueryObject;
use Flunorette\Selections\Selection;
use Flunorette\SqlLiteral;

/**
 * Base query builder
 */
abstract class Query implements IQueryObject {

	/** @var array of definition clauses */
	protected $clauses = array();

	/** @var QueryContext */
	protected $context;

	protected function __construct($clauses, QueryContext $context = null) {
		$this->context = $context;
		$this->clauses = $clauses;
		$this->init();
	}

	public function setContext(QueryContext $context) {
		$this->context = $context;
		$this->contextAttached();
	}

	/** @return QueryContext */
	public function getContext($need = true) {
		if ($need && null == $this->context) {
			throw new InvalidStateException('Context must be set first.');
		}
		return $this->context;
	}

	/** @return string table name */
	public function getTable() {
		return $this->getContext()->getTable();
	}

	/** @return string table alias */
	public function getTableAlias() {
		return $this->getContext()->getTableAlias();
	}

	protected function init() {
		if ($this->context && $this->context->getActiveQuery() !== $this) {
			$this->context->setActiveQuery($this);
			$this->initClauses();
			$this->contextAttached();
		}
	}

	protected function contextAttached() {

	}

	private function initClauses() {
		if ($this->context) {
			foreach ($this->clauses as $clause => $value) {
				if ($value) {
					if (!isset($this->context->statements[$clause])) {
						$this->context->statements[$clause] = array();
					}
					if (!isset($this->context->parameters[$clause])) {
						$this->context->parameters[$clause] = array();
					}
				} else {
					if (!isset($this->context->statements[$clause])) {
						$this->context->statements[$clause] = null;
					}
					if (!isset($this->context->parameters[$clause])) {
						$this->context->parameters[$clause] = null;
					}
				}
			}
		}
	}

	private function clauseNotEmpty($clause) {
		if ($this->clauses[$clause]) {
			return (boolean) count($this->context->statements[$clause]);
		} else {
			return (boolean) $this->context->statements[$clause];
		}
	}

	/**
	 * Remove all prev defined statements
	 * @param $clause
	 * @return $this
	 */
	protected function resetClause($clause) {
		$this->context->statements[$clause] = null;
		$this->context->parameters[$clause] = array();
		if ($this->clauses[$clause]) {
			$this->context->statements[$clause] = array();
		}
		return $this;
	}

	/**
	 * Add statement for all kind of clauses
	 * @param $clause
	 * @param $statement
	 * @param array $parameters
	 * @return $this|SelectQuery
	 */
	protected function addStatement($clause, $statement, array $parameters = array()) {
		if ($statement === null) {
			return $this->resetClause($clause);
		}
		# $statement !== null
		if ($this->clauses[$clause]) {
			if (is_array($statement)) {
				$this->context->statements[$clause] = array_merge($this->context->statements[$clause], $statement);
			} else {
				$this->context->statements[$clause][] = $statement;
			}
			$this->context->parameters[$clause] = array_merge($this->context->parameters[$clause], $parameters);
		} else {
			$this->context->statements[$clause] = $statement;
			$this->context->parameters[$clause] = $parameters;
		}
		return $this;
	}

	/**
	 * Generate query
	 * @return string
	 * @throws Exception
	 */
	protected function buildQuery() {
		$query = '';
		foreach ($this->clauses as $clause => $separator) {
			if ($this->clauseNotEmpty($clause)) {
				if (is_string($separator)) {
					$query .= " $clause " . implode($separator, $this->context->statements[$clause]);
				} elseif ($separator === null) {
					$query .= " $clause " . $this->context->statements[$clause];
				} elseif (is_callable($separator)) {
					$query .= call_user_func($separator);
				} else {
					throw new Exception("Clause '$clause' is incorrectly set to '$separator'.");
				}
			}
		}
		return $this->context->getPreprocessor()->tryDelimite(trim($query));
	}

	private function buildParameters() {
		$this->init();
		$parameters = array();
		foreach ($this->clauses as $clause => $separator) {
			if ($clauses = $this->context->parameters[$clause]) {
				if (is_array($clauses)) {
					foreach ($clauses as $value) {
						if (Helpers::containsNamedParams($value)) {
							// this is named params e.g. (':name' => 'Mark')
							$parameters = array_merge($parameters, $value);
						} else {
							$parameters[] = $value;
						}
					}
				} else {
					$parameters[] = $clauses;
				}
			}
		}
		return $parameters;
	}

	/**
	 * @param mixed $statement
	 * @param mixed $parameters
	 * @return Query
	 * @throws InvalidArgumentException
	 */
	protected function processStatement($clause, $statement, $parameters = array()) {
		// <editor-fold defaultstate="collapsed" desc="where composition">
//		if (is_array($condition) && is_array($parameters) && !empty($parameters)) {
//			return $this->addWhereComposition($condition, $parameters);
//		}
		// </editor-fold>

		if ($statement === null) {
			return $this->resetClause($clause);
		}
		if (!$statement) {
			return $this;
		}
		if (is_array($statement)) { // where(array("column1" => 1, "column2 > ?" => 2))
			foreach ($statement as $innerStatement => $innerParams) {
				$this->processStatement($clause, $innerStatement, $innerParams);
			}
			return $this;
		}
		if ($statement instanceof IQueryObject) {
			return $this->addStatement($clause, $statement, $statement->getParameters());
		}

		$args = func_get_args();
		array_shift($args);

		$placeholderCount = substr_count($statement, '?');
		if ($placeholderCount > 1 && count($args) === 2 && is_array($parameters)) {
			$args = $parameters;
		} elseif ($placeholderCount === 0 && count($args) === 2 && Helpers::containsNamedParams($parameters)) { //named params
			return $this->addStatement($clause, $statement, array($parameters));
		} else {
			array_shift($args);
		}

		$statement = trim($statement);
		if ($placeholderCount === 0 && count($args) === 1) {
			$statement .= ' ?';
		} elseif ($placeholderCount !== count($args)) {
			throw new InvalidArgumentException('Argument count does not match placeholder count.');
		}

		$replace = NULL;
		$placeholderNum = 0;
		$params = array();
		foreach ($args as $arg) {
			preg_match('#(?:.*?\?.*?){' . $placeholderNum . '}(((?:&|\||^|~|\+|-|\*|/|%|\(|,|<|>|=|(?<=\W|^)(?:REGEXP|ALL|AND|ANY|BETWEEN|EXISTS|IN|[IR]?LIKE|OR|NOT|SOME|INTERVAL))\s*)?(?:\(\?\)|\?))#s', $statement, $match, PREG_OFFSET_CAPTURE);
			$hasOperator = ($match[1][0] === '?' && $match[1][1] === 0) ? TRUE : !empty($match[2][0]);

			if ($arg === NULL) {
				if ($hasOperator) {
					throw new InvalidArgumentException('Column operator does not accept NULL argument.');
				}
				$replace = 'IS NULL';
			} elseif (is_array($arg) || $arg instanceof Selection) { //TODO select query
				if ($hasOperator) {
					if (trim($match[2][0]) === 'NOT') {
						$match[2][0] = rtrim($match[2][0]) . ' IN ';
					} elseif (trim($match[2][0]) !== 'IN') {
						throw new InvalidArgumentException('Column operator does not accept array argument.');
					}
				} else {
					$match[2][0] = 'IN ';
				}

				if ($arg instanceof Selection) {
					$clone = clone $arg;
					if (!$clone->getSqlBuilder()->getClause('SELECT')) {
						try {
							$clone->select($clone->getPrimary());
						} catch (\LogicException $e) {
							throw new InvalidArgumentException('Selection argument must have defined a select column.', 0, $e);
						}
					}

					if ($this->context->getDriver()->isSupported(IDriver::SUPPORT_SUBSELECT)) {
						$arg = NULL;
						$replace = $match[2][0] . '(' . $clone->getSql() . ')';
						$this->context->parameters['WHERE'] = array_merge($this->context->parameters['WHERE'], $clone->getSqlBuilder()->getContext()->parameters['WHERE']);
					} else {
						$arg = array();
						foreach ($clone as $row) {
							$arg[] = array_values($row->toArray());
						}
					}
				}

				if ($arg !== NULL) {
					if (!$arg) {
						$hasBrackets = strpos($statement, '(') !== FALSE;
						$hasOperators = preg_match('#AND|OR#', $statement);
						$hasNot = strpos($statement, 'NOT') !== FALSE;
						$hasPrefixNot = strpos($match[2][0], 'NOT') !== FALSE;
						if (!$hasBrackets && ($hasOperators || ($hasNot && !$hasPrefixNot))) {
							throw new InvalidArgumentException('Possible SQL query corruption. Add parentheses around operators.');
						}
						if ($hasPrefixNot) {
							$replace = 'IS NULL OR TRUE';
						} else {
							$replace = 'IS NULL AND FALSE';
						}
						$arg = NULL;
					} else {
						$replace = $match[2][0] . '(?)';
						$params[] = array_values($arg);
					}
				}
			} elseif ($arg instanceof IQueryObject) {
				$statement = substr_replace($statement, str_replace('?', $arg->getQuery(), $match[1][0]), $match[1][1], strlen($match[1][0]));
				$argParams = $arg->getParameters();
				if ($argParams) {
					if (Helpers::containsNamedParams($argParams)) {
						$argParams = array($argParams);
					}
					$params = array_merge($params, $argParams);
				}
			} else {
				if (!$hasOperator) {
					$replace = '= ?';
				}
				$params[] = $arg;
			}

			if ($replace) {
				$statement = substr_replace($statement, $replace, $match[1][1], strlen($match[1][0]));
				$replace = NULL;
			}

			if ($arg !== NULL) {
				$placeholderNum++;
			}
		}

		return $this->addStatement($clause, $statement, $params);
	}

	/**
	 * @param string $clause
	 * @return array
	 */
	public function getClause($clause) {
		$clause = strtoupper($clause);
		return isset($this->getContext()->statements[$clause]) ? $this->getContext()->statements[$clause] : null;
	}

	/**
	 * @param string $clause
	 * @return array
	 */
	public function getClauseParams($clause) {
		$clause = strtoupper($clause);
		return isset($this->getContext()->parameters[$clause]) ? $this->getContext()->parameters[$clause] : null;
	}

	/**
	 * Get query parameters
	 * @return array
	 */
	public function getParameters() {
		$this->getContext(); //context must be available
		return $this->buildParameters();
	}

	/**
	 * Get query string
	 * @return string
	 */
	public function getQuery() {
		$this->getContext(); //context must be available
		return $this->buildQuery();
	}

	/**
	 * Get query string with expanded params
	 * @param boolean return
	 * @return string
	 */
	public function getQueryExpanded() {
		$query = $this->getQuery(); //must be called before getParameters
		$containsNamedParams = false;
		if (($params = $this->getParameters())) {
			if (($containsNamedParams = Helpers::containsNamedParams($params))) {
				$params = array($params);
			}
			list($query, $params) = $this->context->getPreprocessor()->process(array_merge(array($query), $params));
		} else {
			list($query, $params) = $this->context->getPreprocessor()->process(array($query));
		}

		if ($containsNamedParams) {
			$that = $this;
			$query = preg_replace_callback('#(?<=\s):[a-z]+#i', function ($m) use ($params, $that) {
				return $that->quote($params[$m[0]]);
			}, $query);
		}
		return $query;
	}

	public function quote($value) {
		$this->getContext(); //context must be available
		if (null === $value) {
			return 'NULL';
		}
		if (is_array($value)) { // (a, b) IN ((1, 2), (3, 4))
			return '(' . implode(', ', array_map(array($this, 'quote'), $value)) . ')';
		}
		if ($value instanceof DateTime) {
			return $this->context->getDriver()->formatDateTime($value);
		}
		if (is_float($value)) {
			return sprintf('%F', $value); // otherwise depends on setlocale()
		}
		if (is_bool($value)) {
			return $this->context->getDriver()->formatBool($value);
		}
		if (is_int($value) || $value instanceof SqlLiteral) { // number or SQL code - for example "NOW()"
			return (string) $value;
		}
		return $this->context->getPreprocessor()->quote($value);
	}

	public function __toString() {
		try {
			return $this->getQueryExpanded();
		} catch (\Exception $e) {
			return __METHOD__ . ':' . $e->getMessage();
		}
	}

	public function getHash() {
		return md5(serialize($this));
	}

	/**
	 * @param Query $query
	 * @param bool $clone whether to create independent query (cloned context)
	 * @return static
	 */
	static public function fromQuery(Query $query, $clone = false) {
		$context = $query->getContext(false);
		return new static($clone && $context ? clone $context : $context);
	}

}
