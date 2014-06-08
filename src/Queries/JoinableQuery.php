<?php

namespace Flunorette\Queries;

use Flunorette\Helpers;
use Flunorette\IQueryObject;
use Flunorette\Reflections\ReflectionException;
use Nette\Utils\Strings;

/**
 * CommonQuery adds JOIN clauses for (SELECT, UPDATE, DELETE)
 */
abstract class JoinableQuery extends Query {

	public function enableSmartJoin() {
		$this->getContext()->isSmartJoinEnabled = true;
		return $this;
	}

	public function disableSmartJoin() {
		$this->getContext()->isSmartJoinEnabled = false;
		return $this;
	}

	public function isSmartJoinEnabled() {
		return $this->getContext()->isSmartJoinEnabled;
	}

	protected function addStatement($clause, $statement, array $parameters = array()) {
		if ('WHERE' === $clause) {
			$statement = "AND ($statement)";
		} elseif ('WHERE AND' === $clause) {
			$clause = 'WHERE';
			$statement = "AND $statement";
		} elseif ('WHERE OR' === $clause) {
			$clause = 'WHERE';
			$statement = "OR $statement";
		}
		return parent::addStatement($clause, $statement, $parameters);
	}

	/**
	 * @param $clause
	 * @param array $parameters - first is $statement followed by $parameters
	 * @return $this|SelectQuery
	 */
	public function __call($clause, $parameters = array()) {
		$this->getContext(); //context must be available
		$clause = Helpers::toUpperWords($clause);
		if ($clause == 'GROUP') {
			$clause = 'GROUP BY';
		} elseif ($clause == 'ORDER') {
			$clause = 'ORDER BY';
		} elseif ($clause == 'FOOT NOTE') {
			$clause = "\n--";
		} elseif ($clause == 'WHERE PRIMARY') {
			$clause = 'WHERE';
			$primaryKey = $this->context->getDatabaseReflection()->getPrimary($this->getTable());
			array_unshift($parameters, "{$this->getTableAlias()}.$primaryKey");
		}

		$args = array_merge(array($clause), $parameters);
		if (Strings::contains($clause, 'JOIN')) {
			return call_user_func_array(array($this, 'addJoinStatements'), $args);
		}
		return call_user_func_array(array($this, 'processStatement'), $args);
	}

	protected function getClauseJoin() {
		$str = implode(' ', $this->context->statements['JOIN']);
		return ' ' == $str[0] ? $str : " $str";
	}

	protected function getClauseWhere() {
		$imp = implode(' ', $this->context->statements['WHERE']);
		return ' WHERE ' . substr($imp, substr($imp, 0, 2) === 'OR' ? 3 : 4); //remove leading OR|AND
	}

	/**
	 * @return string
	 */
	protected function buildQuery() {
		# first create extra join from statements with columns with referenced tables
		$statementsWithReferences = array('WHERE', 'SELECT', 'GROUP BY', 'ORDER BY');
		foreach ($statementsWithReferences as $clause) {
			if (array_key_exists($clause, $this->context->statements)) {
				$this->context->statements[$clause] = array_map(array($this, 'createUndefinedJoins'), $this->context->statements[$clause]);
			}
		}

		return parent::buildQuery();
	}

	/**
	 * Create undefined joins from statement with column with referenced tables
	 * @param string $statement
	 * @return string  rewrited $statement (e.g. tab1.tab2:col => tab2.col)
	 */
	private function createUndefinedJoins($statement) {
		if (!$this->isSmartJoinEnabled() || $statement instanceof IQueryObject) {
			return $statement;
		}
		preg_match_all('~\\b([a-z_][#a-z0-9_.:]*[.:])[a-z_]*~i', $statement, $matches);
		foreach ($matches[1] as $join) {
			#replace keys with table names
			$direction = substr($join, -1);
			if (in_array($direction, array('.', ':'))) {
				$reflection = $this->getContext()->getDatabaseReflection();
				$key = substr($join, 0, -1);
				if (false == $reflection->hasTable($key)) {
					try {
						if ('.' == $direction) {
							list($table, ) = $reflection->getBelongsToReference($this->getTable(), $key);
						} else {
							list($table, ) = $reflection->getHasManyReference($this->getTable(), $key);
						}

						if ($key != $table) {
							if ($table == $this->getTable()) {
								$joinAlias = ($direction == '.' ? $table : "$table:") . " AS $key";
								$table = $key;
							}

							$statement = preg_replace('~\\b' . preg_quote($join, '~') . '~', $table . $direction, $statement, 1);
							$join = (isset($joinAlias) ? $joinAlias : $table . $direction);
						}
					} catch (ReflectionException $exc) {

					}
				}
			}

			if (!in_array(substr($join, 0, -1), $this->context->joins)) {
				$this->addJoinStatements('LEFT JOIN', $join);
			}
		}

		# don't rewrite table from other databases
		foreach ($this->context->joins as $join) {
			if (strpos($join, '.') !== FALSE && strpos($statement, $join) === 0) {
				return $statement;
			}
		}

		# remove extra referenced tables (rewrite tab1.tab2#hint_attribute:col => tab2.col)
		$statement = preg_replace('~(?:\\b[a-z_][#a-z0-9_.:]*[.:])?([a-z_][a-z0-9_]*)(?:#[a-z_][a-z0-9_]*)?[.:]([a-z_*])~i', '\\1.\\2', $statement);
		return $statement;
	}

	/**
	 * Statement can contain more tables (e.g. "table1.table2:table3:")
	 * @param $clause
	 * @param $statement
	 * @param array $parameters
	 * @return $this|SelectQuery
	 */
	private function addJoinStatements($clause, $statement, $parameters = array()) {
		if ($statement === null) {
			$this->context->joins = array();
			return $this->resetClause('JOIN');
		}
		if (array_search(substr($statement, 0, -1), $this->context->joins) !== FALSE) {
			return $this;
		}

		# match "tables AS alias"
		preg_match('~[`"\[]?([a-z_][#a-z0-9_\.:]*)[`"\]]?(\s+AS)?(\s+[`"\[]?([a-z_][a-z0-9_]*)[`"\]]?)?~i', $statement, $matches);
		$joinAlias = '';
		$joinTable = '';

		if ($matches) {
			$joinTable = $matches[1];
			if (isset($matches[4]) && !in_array(strtoupper($matches[4]), array('ON', 'USING'))) {
				$joinAlias = $matches[4];
			}
		}

		if (stripos($statement, ' ON ') || stripos($statement, ' USING')) {
			if (!$joinAlias) {
				$joinAlias = $joinTable;
			}
			if (in_array($joinAlias, $this->context->joins)) {
				return $this;
			} else {
				$this->context->joins[] = $joinAlias;
				$statement = " $clause $statement";

				$args = array('JOIN', $statement);
				if (3 == func_num_args()) {
					$args[] = $parameters;
				}
				return call_user_func_array(array($this, 'processStatement'), $args);
			}
		}

		# $joinTable is list of tables for join e.g.: table1.table2:table3....
		if (!in_array(substr($joinTable, -1), array('.', ':'))) {
			$joinTable .= '.';
		}

		preg_match_all('~([a-z_][#a-z0-9_]*[\.:]?)~i', $joinTable, $matches);
		if (isset($this->context->statements['FROM'])) {
			$mainTable = $this->context->statements['FROM'];
		} elseif (isset($this->context->statements['UPDATE'])) {
			$mainTable = $this->context->statements['UPDATE'];
		} else {
			return $this;
		}

		$lastItem = end($matches[1]);
		foreach ($matches[1] as $joinItem) {
			if (!$joinAlias && $mainTable == substr($joinItem, 0, -1)) {
				continue;
			}

			# use $joinAlias only for $lastItem
			$alias = '';
			if ($joinItem == $lastItem) {
				$alias = $joinAlias;
			}

			$newJoin = $this->createJoinStatement($clause, $mainTable, $joinItem, $alias);
			if ($newJoin) {
				$this->addStatement('JOIN', $newJoin, $parameters);
				//call_user_func_array(array($this, 'processStatement'), array('JOIN', $newJoin, $parameters));
			}
			$mainTable = $joinItem;
		}
		return $this;
	}

	/**
	 * Create join string
	 * @param $clause
	 * @param $mainTable
	 * @param $joinTable
	 * @param string $joinAlias
	 * @return string
	 */
	private function createJoinStatement($clause, $mainTable, $joinTable, $joinAlias = '') {
		if (in_array(substr($mainTable, -1), array(':', '.'))) {
			$mainTable = substr($mainTable, 0, -1);
		}

		#strip hint attributes from $mainTable
		if (preg_match('~#([a-z][a-z0-9_]*)~i', $mainTable, $matches2, PREG_OFFSET_CAPTURE)) {
			$mainTable = substr_replace($mainTable, '', $matches2[0][1], strlen($matches2[0][0]));
		}

		$referenceDirection = substr($joinTable, -1);
		$joinTable = substr($joinTable, 0, -1);
		#strip hint attributes from $joinTable
		if (preg_match('~#([a-z][a-z0-9_]*)~i', $joinTable, $matches2, PREG_OFFSET_CAPTURE)) {
			$joinTable = substr_replace($joinTable, '', $matches2[0][1], strlen($matches2[0][0]));
			$foreignKey = $matches2[1][0]; #use hint attribute
		}

		$asJoinAlias = '';
		if ($joinAlias) {
			$asJoinAlias = " AS $joinAlias";
		} else {
			$joinAlias = $joinTable;
		}
		if (in_array($joinAlias, $this->context->joins)) {
			# if join exists don't create same again
			return '';
		} else {
			$this->context->joins[] = $joinAlias;
		}
		if ($referenceDirection == ':') {
			# back reference
			$primaryKey = $this->context->getDatabaseReflection()->getPrimary($mainTable);
			if (false == isset($foreignKey)) {
				list ($joinTable, $foreignKey) = $this->context->getDatabaseReflection()->getHasManyReference($mainTable, $joinTable);
			}
			return " $clause $joinTable$asJoinAlias ON $joinAlias.$foreignKey = $mainTable.$primaryKey";
		} else {
			$primaryKey = $this->context->getDatabaseReflection()->getPrimary($joinTable);
			if (false == isset($foreignKey)) {
				list ($joinTable, $foreignKey) = $this->context->getDatabaseReflection()->getBelongsToReference($mainTable, $joinTable);
			}
			return " $clause $joinTable$asJoinAlias ON $joinAlias.$primaryKey = $mainTable.$foreignKey";
		}
	}

	/** @return array table aliases used in joins */
	public function getJoins() {
		return $this->getContext()->joins;
	}

}
