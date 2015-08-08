<?php

namespace Flunorette\Selections;

use Flunorette\Connection;
use Flunorette\Drivers\IDriver;
use Flunorette\Hydrators\HydratorColumn;
use Flunorette\Hydrators\HydratorField;
use Flunorette\Hydrators\HydratorSelection;
use Flunorette\InvalidArgumentException;
use Flunorette\InvalidStateException;
use Flunorette\IQueryObject;
use Flunorette\Reflections\IReflection;
use Flunorette\Queries\DeleteQuery;
use Flunorette\Queries\InsertQuery;
use Flunorette\Queries\Query;
use Flunorette\Queries\QueryContext;
use Flunorette\Queries\SelectQuery;
use Flunorette\Queries\UpdateQuery;
use Flunorette\SqlLiteral;
use Flunorette\Statement;
use Nette\Object;
use Nette\Utils\Arrays;

class Selection extends Object implements IQueryObject, \Iterator, \ArrayAccess, \Countable {

	/** @var HydratorSelection */
	static protected $hydrator;

	/** @var QueryContext contains sql builders' data */
	protected $context;

	/** @var Query[] */
	protected $sqlBuilders = array();

	/** @var SelectionFactory */
	protected $selectionFactory;

	/** @var bool */
	protected $frozen = false;

	/** @var Connection */
	protected $connection;

	/** @var string primary key field name */
	protected $primary;

	/** @var string|bool primary column sequence name, FALSE for autodetection */
	protected $primarySequence = false;

	/** @var ActiveRow[] data read from database in [primary key => ActiveRow] format */
	protected $rows;

	/** @var ActiveRow[] modifiable data in [primary key => ActiveRow] format */
	protected $data;

	/** @var mixed cache array of Selection and GroupedSelection prototypes */
	protected $globalRefCache;

	/** @var mixed */
	protected $refCache;

	/** @var string */
	protected $generalCacheKey;

	/** @var string */
	protected $specificCacheKey;

	/** @var array of [conditions => [key => ActiveRow]]; used by GroupedSelection */
	protected $aggregation = array();

	/** @var array of primary key values */
	protected $keys = array();

	/**
	 * Creates filtered table representation.
	 * @param  string  database table name
	 * @param  Connection
	 */
	public function __construct($table, Connection $connection) {
		$this->connection = $connection;
		$this->context = new QueryContext($table, $connection);
		$this->selectionFactory = $connection->getSelectionFactory();
		$this->primary = $this->context->getDatabaseReflection()->getPrimary($this->context->getTable());
		$this->refCache = & $this->getRefTable($refPath)->globalRefCache[$refPath];
	}

	public function getQuery() {
		return $this->getSqlBuilder()->getQuery();
	}

	public function getParameters() {
		return $this->getSqlBuilder()->getParameters();
	}

	public function __toString() {
		return $this->getSqlBuilder()->getQueryExpanded();
	}

	public function getHash() {
		return md5(serialize(array($this->getQuery(), $this->getParameters())));
	}

	public function unfreeze() {
		$this->frozen = false;
	}

	public function __clone() {
		$this->context = clone $this->context;
		$this->sqlBuilders = array();
	}

	/**
	 * @return Connection
	 */
	public function getConnection() {
		return $this->connection;
	}

	/**
	 * @return IReflection
	 */
	public function getDatabaseReflection() {
		return $this->context->getDatabaseReflection();
	}

	/**
	 * @return ISelectionFactory
	 */
	public function getSelectionFactory() {
		return $this->selectionFactory;
	}

	/**
	 * @return string
	 */
	public function getName() {
		return $this->context->getFrom();
	}

	/**
	 * @param  bool
	 * @return string|array
	 */
	public function getPrimary($need = true) {
		if ($this->primary === null && $need) {
			throw new \LogicException("Table \"{$this->name}\" does not have a primary key.");
		}
		return $this->primary;
	}

	/**
	 * @return string
	 */
	public function getPrimarySequence() {
		if ($this->primarySequence === FALSE) {
			$this->primarySequence = NULL;
			$driver = $this->connection->getDriver();
			if ($driver->isSupported(IDriver::SUPPORT_SEQUENCE) && $this->primary !== NULL) {
				foreach ($driver->getColumns($this->name) as $column) {
					if ($column['name'] === $this->primary) {
						$this->primarySequence = $column['vendor']['sequence'];
						break;
					}
				}
			}
		}

		return $this->primarySequence;
	}

	/**
	 * @param  string
	 * @return self
	 */
	public function setPrimarySequence($sequence) {
		$this->primarySequence = $sequence;
		return $this;
	}

	/** @return QueryContext */
	protected function getContext() {
		return $this->context;
	}

	/** @return string */
	public function getSql($type = 'select') {
		return $this->getSqlBuilder($type)->getQuery();
	}

	/**
	 *
	 * @param string $type
	 * @return SelectQuery|UpdateQuery|DeleteQuery|InsertQuery
	 */
	public function getSqlBuilder($type = 'select') {
		$type = ucfirst(strtolower($type));
		if (false == isset($this->sqlBuilders[$type])) {
			$class = "Flunorette\\Queries\\{$type}Query";
			$this->sqlBuilders[$type] = new $class($this->context);
		}
		return $this->sqlBuilders[$type];
	}

	//======================= quick access =======================//

	/**
	 * Returns row specified by primary key.
	 * @param  mixed primary key
	 * @return ActiveRow or FALSE if there is no such row
	 */
	public function get($key) {
		$clone = clone $this;
		return $clone->wherePrimary($key)->fetch();
	}

	/**
	 * Returns next row of result.
	 * @return ActiveRow or FALSE if there is no row
	 */
	public function fetch() {
		$this->execute();
		$return = current($this->data);
		next($this->data);
		return $return;
	}

	/**
	 * Returns all rows as associative array.
	 * @param  string
	 * @param  string column name used for an array value or NULL for the whole row
	 * @return array
	 */
	public function fetchPairs($key = null, $value = NULL) {
		$return = array();
		if (null === $key) {
			$key = $this->getPrimary();
		}

		foreach ($this as $row) {
			$return[is_object($row[$key]) ? (string) $row[$key] : $row[$key]] = ($value ? $row[$value] : $row);
		}
		return $return;
	}

	/**
	 * Returns all values from specified $column
	 * @param mixed $column index or key
	 * @return array
	 */
	public function fetchColumn($column = 0) {
		return $this->hydrate(new HydratorColumn($column));
	}

	/**
	 * Returns single value from specified $column
	 * @param mixed $column index or key
	 * @return mixed
	 */
	public function fetchField($column = 0) {
		return $this->hydrate(new HydratorField($column));
	}

	/**
	 * Custom fetch mode
	 * @param mixed $hydrator Any hydrator or callback(Statement)
	 * @return mixed
	 * @throws \PDOException
	 * @see Statement::hydrate()
	 */
	public function hydrate($hydrator) {
		try {
			$result = $this->query($this->getSql(), 'SELECT');
		} catch (\PDOException $exception) {
			throw $exception;
		}
		return $result->hydrate($hydrator);
	}

	//======================= sql selectors =======================//

	/**
	 * Call sql builder's method
	 * @param string $method name of the method (e.g. select|leftJoin)
	 * @param array|string $args any arguments to be passed to the method
	 * @param string $type query type (select|update|delete|insert)
	 * @return self
	 */
	public function callSqlBuilder($method, $args = array(), $type = 'select') {
		if ($this->frozen) {
			throw new InvalidStateException('Selection cannot be altered while it is frozen.');
		}

		$this->emptyResultSet();
		call_user_func_array(array($this->getSqlBuilder($type), $method), is_array($args) ? $args : array($args));
		return $this;
	}

	/**
	 * Adds select clause, more calls appends to the end.
	 * @param  string for example "column, MD5(column) AS column_md5"
	 * @return self
	 */
	public function select($columns) {
		$this->callSqlBuilder('select', func_get_args());
		return $this;
	}

	/**
	 * Adds join clause.
	 * @note Primarily inteded for better control over the query when some "filtering" is needed.
	 * @param string e.g. "t2 ON t1.id = t2.parent_id"
	 * @return self
	 */
	public function join($clause) {
		$this->callSqlBuilder('join', func_get_args());
		return $this;
	}

	/**
	 * Adds left join clause.
	 * @note Primarily inteded for better control over the query when some "filtering" is needed.
	 * @param string e.g. "t2 ON t1.id = t2.parent_id"
	 * @return self
	 */
	public function leftJoin($clause) {
		$this->callSqlBuilder('leftJoin', func_get_args());
		return $this;
	}

	/**
	 * Method is deprecated, use wherePrimary() instead.
	 * @return self
	 */
	public function find($key) {
		return $this->wherePrimary($key);
	}

	/**
	 * Adds condition for primary key.
	 * @param  mixed
	 * @return self
	 */
	public function wherePrimary($key) {
		if (is_array($this->primary) && Arrays::isList($key)) {
			if (isset($key[0]) && is_array($key[0])) {
				$this->where($this->primary, $key);
			} else {
				foreach ($this->primary as $i => $primary) {
					$this->where($this->name . '.' . $primary, $key[$i]);
				}
			}
		} elseif (is_array($key) && !Arrays::isList($key)) { // key contains column names
			$this->where($key);
		} else {
			$this->where($this->name . '.' . $this->getPrimary(), $key);
		}

		//$this->callSqlBuilder('wherePrimary', func_get_args());
		return $this;
	}

	/**
	 * Adds where condition enclosed in colons "(id = 1)", more calls appends with AND.
	 * @param  string condition possibly containing ?
	 * @param  mixed
	 * @param  mixed ...
	 * @return self
	 */
	public function where($condition, $parameters = array()) {
		$this->callSqlBuilder('where', func_get_args());
		return $this;
	}

	/**
	 * Adds where condition, more calls appends with AND without enclosing inside colons.
	 * @param  string condition possibly containing ?
	 * @param  mixed
	 * @param  mixed ...
	 * @return self
	 */
	public function whereAnd($condition, $parameters = array()) {
		$this->callSqlBuilder('whereAnd', func_get_args());
		return $this;
	}

	/**
	 * Adds where condition, more calls appends with OR without enclosing inside colons.
	 * @param  string condition possibly containing ?
	 * @param  mixed
	 * @param  mixed ...
	 * @return self
	 */
	public function whereOr($condition, $parameters = array()) {
		$this->callSqlBuilder('whereOr', func_get_args());
		return $this;
	}

	/**
	 * Adds order clause, more calls appends to the end.
	 * @param  string for example 'column1, column2 DESC'
	 * @return self
	 */
	public function order($columns) {
		$this->callSqlBuilder('order', func_get_args());
		return $this;
	}

	/**
	 * Sets limit clause, more calls rewrite old values.
	 * @param  int
	 * @param  int
	 * @return self
	 */
	public function limit($limit, $offset = NULL) {
		$this->callSqlBuilder('limit', array($limit));
		$this->callSqlBuilder('offset', array($offset));
		return $this;
	}

	/**
	 * Sets offset using page number, more calls rewrite old values.
	 * @param  int
	 * @param  int
	 * @return self
	 */
	public function page($page, $itemsPerPage, &$numOfPages = null) {
		if (func_num_args() > 2) {
			$numOfPages = (int) ceil($this->count('*') / $itemsPerPage);
		}
		return $this->limit($itemsPerPage, ($page - 1) * $itemsPerPage);
	}

	/**
	 * Sets group clause, more calls rewrite old values.
	 * For BC with NDB2.0 this method contains both group by and having clauses. For separate use there are groupBy() and having() methods
	 * @param  string
	 * @param  string
	 * @return self
	 */
	public function group($columns, $having = NULL) {
		//reset old values (BC)
		$this->callSqlBuilder('group', array(null));
		$this->callSqlBuilder('having', array(null));

		//set new values
		$this->callSqlBuilder('group', array($columns));
		if (null !== $having) {
			$this->callSqlBuilder('having', array($having));
		}
		return $this;
	}

	/**
	 * Sets group clause
	 * @param  string
	 * @return self
	 */
	public function groupBy($columns) {
		$this->callSqlBuilder('group', func_get_args());
		return $this;
	}

	/**
	 * Sets having clause
	 * @param  string
	 * @return self
	 */
	public function having($statement) {
		$this->callSqlBuilder('having', func_get_args());
		return $this;
	}

	//======================= aggregations =======================//

	/**
	 * Executes aggregation function.
	 * @param  string select call in "FUNCTION(column)" format
	 * @return string
	 */
	public function aggregation($function) {
		$selection = $this->createSelectionInstance();
		$selection->getSqlBuilder()->setContext(clone $this->getContext());
		$selection->select(null);
		$selection->order(null);
		$selection->groupBy(null);
		$selection->having(null);
		$selection->select($function);
		foreach ($selection->fetch() as $val) { //TODO fetch single
			return $val;
		}
	}

	/**
	 * Counts number of rows.
	 * @param  string  if it is not provided returns count of result rows, otherwise runs new sql counting query
	 * @return int
	 */
	public function count($column = NULL) {
		if (!$column) {
			$this->execute();
			return count($this->data);
		}
		return $this->aggregation("COUNT($column)");
	}

	/**
	 * Returns minimum value from a column.
	 * @param  string
	 * @return int
	 */
	public function min($column) {
		return $this->aggregation("MIN($column)");
	}

	/**
	 * Returns maximum value from a column.
	 * @param  string
	 * @return int
	 */
	public function max($column) {
		return $this->aggregation("MAX($column)");
	}

	/**
	 * Returns sum of values in a column.
	 * @param  string
	 * @return int
	 */
	public function sum($column) {
		return $this->aggregation("SUM($column)");
	}

	//======================= internal =======================//

	protected function execute() {
		if ($this->rows !== null) {
			return;
		}

		if ($this->primary === null && $this->getSqlBuilder()->getClause('SELECT') === null) {
			throw new InvalidStateException('Table with no primary key requires an explicit select clause.');
		}

		try {
			$result = $this->query($this->getSql(), 'SELECT');
		} catch (\PDOException $exception) {
			throw $exception;
		}

		if (null === self::$hydrator) {
			self::$hydrator = new HydratorSelection();
		}
		$this->data = $this->rows = self::$hydrator->setSelection($this)->hydrate($result);

		$first = reset($this->rows);
		if ($first && $result->columnCount() != count($first)) {
			$sql = $this->getSql();
			trigger_error('Some columns have been overwritten - may return wrong results (especially if those columns were primary) in "' . $sql . '"', E_USER_NOTICE);
		}
	}

	public function createRow(array $row = array()) {
		return $this->selectionFactory->createRow($row, $this);
	}

	public function createSelectionInstance($table = NULL) {
		return $this->selectionFactory->createSelection($table ? : $this->getName(), $this->connection);
	}

	protected function createGroupedSelectionInstance($table, $column) {
		return $this->selectionFactory->createGrouped($this, $table, $column);
	}

	/** @return Statement */
	protected function query($query, $type) {
		return $this->connection->queryArgs($query, $this->getSqlBuilder($type)->getParameters());
	}

	protected function emptyResultSet() {
		$this->rows = null;
		$this->specificCacheKey = null;
		$this->generalCacheKey = null;
		$this->refCache['referencingPrototype'] = array();
		$this->refCache['referenced'] = array();
	}

	/**
	 * Loads refCache references
	 */
	protected function loadRefCache() {

	}

	/**
	 * Returns Selection parent for caching.
	 * @return Selection
	 */
	protected function getRefTable(& $refPath) {
		return $this;
	}

	/**
	 * Returns general cache key indenpendent on query parameters or sql limit
	 * Used e.g. for previously accessed columns caching
	 * @return string
	 */
	protected function getGeneralCacheKey() {
		if ($this->generalCacheKey) {
			return $this->generalCacheKey;
		}

		$builder = $this->getSqlBuilder();
		return $this->generalCacheKey = md5(serialize(array(__CLASS__, $this->getName(), $builder->getClause('WHERE'))));
	}

	/**
	 * Returns object specific cache key dependent on query parameters
	 * Used e.g. for reference memory caching
	 * @return string
	 */
	protected function getSpecificCacheKey() {
		if ($this->specificCacheKey) {
			return $this->specificCacheKey;
		}

		$builder = $this->getSqlBuilder();
		return $this->specificCacheKey = md5($builder->getQuery() . serialize($builder->getParameters()));
	}

	//======================= manipulation =======================//

	/**
	 * Inserts row in a table.
	 * @param  array|\Traversable|Selection array($column => $value)|\Traversable|Selection for INSERT ... SELECT
	 * @return ActiveRow|int|bool Returns IRow or number of affected rows for Selection or table without primary key
	 */
	public function insert($data) {
		if ($data instanceof Selection) {
			$data = new SqlLiteral($data->getQuery(), $data->getParameters());
		} elseif ($data instanceof \Traversable) {
			$data = iterator_to_array($data);
		}

		$return = $this->connection->query($this->getSqlBuilder('insert')->getQuery(), $data);
		$this->loadRefCache();

		if ($data instanceof SqlLiteral || $this->primary === null) {
			unset($this->refCache['referencing'][$this->getGeneralCacheKey()][$this->getSpecificCacheKey()]);
			return $return->getRowCount();
		}

		$primaryKey = $this->connection->getInsertId($this->getPrimarySequence());
		if ($primaryKey === false) {
			unset($this->refCache['referencing'][$this->getGeneralCacheKey()][$this->getSpecificCacheKey()]);
			return $return->getRowCount();
		}

		if (is_array($this->getPrimary())) {
			$primaryKey = array();

			foreach ((array) $this->getPrimary() as $key) {
				if (!isset($data[$key])) {
					return $data;
				}

				$primaryKey[$key] = $data[$key];
			}
			if (count($primaryKey) === 1) {
				$primaryKey = reset($primaryKey);
			}
		}

		$row = $this->createSelectionInstance()
			->wherePrimary($primaryKey)
			->fetch();

		if ($this->rows !== NULL) {
			if ($signature = $row->getSignature(false)) {
				$this->rows[$signature] = $row;
				$this->data[$signature] = $row;
			} else {
				$this->rows[] = $row;
				$this->data[] = $row;
			}
		}

		return $row;
	}

	/**
	 * Updates all rows in result set.
	 * Joins in UPDATE are supported only in MySQL
	 * @param  array|\Traversable ($column => $value)
	 * @return int number of affected rows
	 */
	public function update($data) {
		if ($data instanceof \Traversable) {
			$data = iterator_to_array($data);
		} elseif (!is_array($data)) {
			throw new InvalidArgumentException;
		}

		if (!$data) {
			return 0;
		}

		$builder = clone $this->getSqlBuilder('UPDATE');
		$builder->set(null);
		$builder->set($data);

		return $this->connection->queryArgs(
			$builder->getQuery(), $builder->getParameters()
		)->getRowCount();
	}

	/**
	 * Deletes all rows in result set.
	 * @return int number of affected rows
	 */
	public function delete() {
		return $this->query($this->getSqlBuilder('delete')->getQuery(), 'DELETE')->getRowCount();
	}

	//======================= references =======================//

	/**
	 * Returns referenced row.
	 * @param  string
	 * @param  string
	 * @param  mixed   primary key to check for $table and $column references
	 * @return Selection or array() if the row does not exist
	 */
	public function getReferencedTable($table, $column, $checkPrimaryKey) {
		$referenced = & $this->refCache['referenced'][$this->getSpecificCacheKey()]["$table.$column"];
		$selection = & $referenced['selection'];
		$cacheKeys = & $referenced['cacheKeys'];
		if ($selection === NULL || ($checkPrimaryKey !== NULL && !isset($cacheKeys[$checkPrimaryKey]))) {
			$this->execute();
			$cacheKeys = array();
			foreach ($this->rows as $row) {
				if ($row[$column] === NULL) {
					continue;
				}

				$key = (string) $row[$column];
				$cacheKeys[$key] = TRUE;
			}

			if ($cacheKeys) {
				$selection = $this->createSelectionInstance($table);
				$selection->where($selection->getPrimary(), array_keys($cacheKeys));
			} else {
				$selection = array();
			}
		}

		return $selection;
	}

	/**
	 * Returns referencing rows.
	 * @param  string
	 * @param  string
	 * @param  int primary key
	 * @return GroupedSelection
	 */
	public function getReferencingTable($table, $column, $active = NULL) {
		$prototype = & $this->refCache['referencingPrototype'][$this->getSpecificCacheKey()]["$table.$column"];
		if (!$prototype) {
			$prototype = $this->createGroupedSelectionInstance($table, $column);
			$prototype->where("$table.$column", array_keys((array) $this->rows));
		}

		$clone = clone $prototype;
		$clone->setActive($active);
		return $clone;
	}

	//======================= interface Iterator =======================//

	public function rewind() {
		$this->execute();
		$this->keys = array_keys($this->data);
		reset($this->keys);
		$this->frozen = true;
	}

	/** @return ActiveRow */
	public function current() {
		if (($key = current($this->keys)) !== FALSE) {
			return $this->data[$key];
		} else {
			return FALSE;
		}
	}

	/** @return string row ID */
	public function key() {
		return current($this->keys);
	}

	public function next() {
		next($this->keys);
	}

	public function valid() {
		if (current($this->keys) === FALSE) {
			$this->unfreeze();
			return false;
		}
		return true;
	}

	//======================= interface ArrayAccess =======================//

	/**
	 * Mimic row.
	 * @param  string row ID
	 * @param  ActiveRow
	 * @return NULL
	 */
	public function offsetSet($key, $value) {
		$this->execute();
		$this->rows[$key] = $value;
	}

	/**
	 * Returns specified row.
	 * @param  string row ID
	 * @return ActiveRow or NULL if there is no such row
	 */
	public function offsetGet($key) {
		$this->execute();
		return $this->rows[$key];
	}

	/**
	 * Tests if row exists.
	 * @param  string row ID
	 * @return bool
	 */
	public function offsetExists($key) {
		$this->execute();
		return isset($this->rows[$key]);
	}

	/**
	 * Removes row from result set.
	 * @param  string row ID
	 * @return NULL
	 */
	public function offsetUnset($key) {
		$this->execute();
		unset($this->rows[$key], $this->data[$key]);
	}

}
