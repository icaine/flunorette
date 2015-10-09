<?php

namespace Flunorette;

use Flunorette\Drivers\IDriver;
use Flunorette\Queries\DeleteQuery;
use Flunorette\Queries\InsertQuery;
use Flunorette\Queries\Query;
use Flunorette\Queries\QueryContext;
use Flunorette\Queries\SelectQuery;
use Flunorette\Queries\UpdateQuery;
use Flunorette\Reflections\DiscoveredReflection;
use Flunorette\Reflections\IReflection;
use Flunorette\Selections\ActiveRow;
use Flunorette\Selections\ISelectionFactory;
use Flunorette\Selections\Selection;
use Flunorette\Selections\SelectionFactory;
use Nette\Caching\Cache;
use Nette\Caching\IStorage;
use Nette\Object;
use PDO;

/**
 * @method SelectQuery createSelect($table)
 * @method UpdateQuery createUpdate($table)
 * @method DeleteQuery createDelete($table)
 * @method InsertQuery createInsert($table)
 */
class Connection extends Object {

	/** @var array */
	static public $defaultOptions = array(
		'lazy' => true,
		'transactionCounter' => true,
		'delimiteMode' => SqlPreprocessor::DELIMITE_MODE_DEFAULT
	);

	/** @var array */
	protected $params;

	/** @var array */
	protected $options;

	/** @var IDriver */
	protected $driver;

	/** @var PDO */
	protected $pdo;

	/** @var SqlPreprocessor */
	protected $preprocessor;

	/** @var IReflection */
	protected $reflection;

	/** @var SelectionFactory */
	protected $selectionFactory;

	/** @var TransactionCounter */
	protected $transactionCounter;

	/** @var Cache */
	protected $cache;

	/** @var array of functions(Statement $result, $params); Occurs after a query is executed */
	public $onQuery = array();

	/** @var array of functions(Connection $connection); when connected */
	public $onConnect = array();

	/** @var array of functions(Statement $result, $params, PDOException $e); when an error during execution appears */
	public $onError = array();

	/**
	 * @param string $dsn
	 * @param string $username
	 * @param string $password
	 * @param array $options [driverClass => string, lazy => bool, transactionCounter => bool, [driverSpecificOptions]]
	 */
	public function __construct($dsn, $username = null, $password = null, $options = null) {
		$options = (array) $options;
		if (func_num_args() > 4) { // back compatibility
			$options['driverClass'] = func_get_arg(4);
		}
		$this->params = array($dsn, $username, $password);
		$this->options = $options + static::$defaultOptions;

		if (empty($options['lazy'])) {
			$this->connect();
		}
	}

	protected function connect() {
		if (null === $this->pdo) {
			try {
				$pdo = new PDO($this->params[0], $this->params[1], $this->params[2], $this->options);
				$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
				$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
				$pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, array('Flunorette\Statement', array($this)));
			} catch (\PDOException $e) {
				throw ConnectionException::from($e);
			}
			$this->pdo = $pdo;

			if (!empty($this->options['transactionCounter'])) {
				$this->transactionCounter = new TransactionCounter($pdo);
			}

			if (isset($this->options['driverClass'])) {
				$driverClass = $this->options['driverClass'];
			} else {
				$driverClass = 'Flunorette\\Drivers\\' . ucfirst(str_replace('sql', 'Sql', $pdo->getAttribute(PDO::ATTR_DRIVER_NAME))) . 'Driver';
			}
			$this->driver = new $driverClass($this, $this->options);
			$this->preprocessor = new SqlPreprocessor($this, $this->options['delimiteMode']);

			$this->onConnect($this);
		}
	}

	/** @return Selection */
	public function table($name) {
		return $this->getSelectionFactory()->createSelection($name, $this);
	}

	/** @return PDO */
	public function getPdo() {
		$this->connect();
		return $this->pdo;
	}

	public function getDsn() {
		return $this->params[0];
	}

	/** @return IDriver */
	public function getDriver() {
		if (null === $this->driver) {
			$this->connect();
		}
		return $this->driver;
	}

	/**
	 * Sets database reflection.
	 * @return self
	 */
	public function setDatabaseReflection(IReflection $reflection) {
		$this->reflection = $reflection;
		return $this;
	}

	/** @return IReflection */
	public function getDatabaseReflection() {
		if (null === $this->reflection) {
			$this->reflection = new DiscoveredReflection($this);
		}
		return $this->reflection;
	}

	/** @return ISelectionFactory */
	public function getSelectionFactory() {
		if (null === $this->selectionFactory) {
			$this->selectionFactory = new SelectionFactory();
		}
		return $this->selectionFactory;
	}

	/** @return self */
	public function setSelectionFactory(ISelectionFactory $selectionFactory) {
		$this->selectionFactory = $selectionFactory;
		return $this;
	}

	/**
	 * Sets cache storage engine.
	 * @return self
	 */
	public function setCacheStorage(IStorage $storage = NULL) {
		$this->cache = $storage ? new Cache($storage, 'Flunorette.Database.' . md5($this->params[0])) : NULL;
		return $this;
	}

	/** @return Cache */
	public function getCache() {
		return $this->cache;
	}

	/** @return SqlPreprocessor */
	public function getPreprocessor() {
		$this->connect();
		return $this->preprocessor;
	}

	public function __call($name, $args) {
		if (preg_match('~^create(Select|Update|Delete|Insert)$~', $name, $m)) { #query object factory
			$class = "Flunorette\\Queries\\{$m[1]}Query";
			$queryContext = new QueryContext(reset($args), $this);
			return new $class($queryContext);
		}
		return parent::__call($name, $args);
	}

	//======================= querying part =======================//

	/**
	 * @param  string  sequence object
	 * @return string
	 */
	public function getInsertId($name = NULL) {
		return $this->getPdo()->lastInsertId($name);
	}

	/**
	 * @param  string  string to be quoted
	 * @param  int     data type hint
	 * @return string
	 */
	public function quote($string, $type = PDO::PARAM_STR) {
		return $this->getPdo()->quote($string, $type);
	}

	/**
	 * @param  string|Query  statement
	 * @param  array
	 * @return Statement
	 */
	public function queryArgs($statement, array $params = array()) {
		if ($statement instanceof Query) {
			$params = $statement->getParameters();
			$statement = $statement->getQuery();
		}

		foreach ($params as $param) {
			if (is_array($param) || is_object($param)) {
				$needPreprocessing = true;
				break;
			}
		}
		if (isset($needPreprocessing) && $this->preprocessor !== null) {
			array_unshift($params, $statement);
			list($statement, $params) = $this->preprocessor->process($params);
		}

		return $this->getPdo()->prepare($statement)->execute($params);
	}

	/**
	 * Generates and executes SQL query.
	 * @param  string  statement
	 * @param  mixed   [parameters, ...]
	 * @return Statement
	 */
	public function query($statement) {
		$args = func_get_args();
		return $this->queryArgs(array_shift($args), $args);
	}

	/**
	 * Generates and executes SQL query.
	 * @param  string  statement
	 * @param  mixed   [parameters, ...]
	 * @return int     number of affected rows
	 */
	public function exec($statement) {
		$args = func_get_args();
		return $this->queryArgs(array_shift($args), $args)->rowCount();
	}

	//======================= Fetch shortcuts =======================//

	/**
	 * Shortcut for query()->hydrate()
	 * @param mixed $hydrator Hydrator subclass or callback(Statement $statement)
	 * @param string $statement sql statement
	 * @param mixed $params
	 * @return mixed
	 */
	public function hydrate($hydrator, $args) {
		$args = func_get_args();
		$hydrator = array_shift($args);
		return $this->queryArgs(array_shift($args), $args)->hydrate($hydrator);
	}

	/**
	 * Shortcut for query()->fetch()
	 * @param  string  statement
	 * @param  mixed   [parameters, ...]
	 * @return ActiveRow
	 */
	public function fetch($args) {
		$args = func_get_args();
		return $this->queryArgs(array_shift($args), $args)->fetch();
	}

	/**
	 * Shortcut for query()->fetchField(0)
	 * @param  string  statement
	 * @param  mixed   [parameters, ...]
	 * @return mixed
	 */
	public function fetchField($args) {
		$args = func_get_args();
		return $this->queryArgs(array_shift($args), $args)->fetchField();
	}

	/**
	 * Shortcut for query()->fetchColumn(0)
	 * @param  string  statement
	 * @param  mixed   [parameters, ...]
	 * @return mixed
	 */
	public function fetchColumn() {
		$args = func_get_args();
		return $this->queryArgs(array_shift($args), $args)->fetchColumn();
	}

	/**
	 * Shortcut for query()->fetchPairs()
	 * @param  string  statement
	 * @param  mixed   [parameters, ...]
	 * @return array
	 */
	public function fetchPairs($args) {
		$args = func_get_args();
		return $this->queryArgs(array_shift($args), $args)->fetchPairs();
	}

	/**
	 * Shortcut for query()->fetchAll()
	 * @param  string  statement
	 * @param  mixed   [parameters, ...]
	 * @return array
	 */
	public function fetchAll($args) {
		$args = func_get_args();
		return $this->queryArgs(array_shift($args), $args)->fetchAll();
	}

	//======================= Transactions =======================//

	/** @return TransactionCounter|null */
	public function getTransactionCounter() {
		return $this->transactionCounter;
	}

	/** @return bool */
	public function inTransaction() {
		return $this->getPdo()->inTransaction();
	}

	/** @return bool */
	public function beginTransaction() {
		$this->onQuery($this->getPdo()->prepare('TRANSACTION BEGIN'));
		try {
			return $this->transactionCounter ? $this->transactionCounter->beginTransaction() : $this->getPdo()->beginTransaction();
		} catch (\PDOException $e) {
			throw $this->driver->convertException($e);
		}
	}

	/** @return bool */
	public function commit() {
		$this->onQuery($this->getPdo()->prepare('TRANSACTION COMMIT'));
		try {
			return $this->transactionCounter ? $this->transactionCounter->commit() : $this->getPdo()->commit();
		} catch (\PDOException $e) {
			throw $this->driver->convertException($e);
		}
	}

	/** @return bool */
	public function rollBack() {
		$this->onQuery($this->getPdo()->prepare('TRANSACTION ROLLBACK'));
		try {
			return $this->transactionCounter ? $this->transactionCounter->rollBack() : $this->getPdo()->rollBack();
		} catch (\PDOException $e) {
			throw $this->driver->convertException($e);
		}
	}

	/**
	 * @param callable $callable
	 * @param array $callableArgs
	 * @return mixed
	 * @throws \Exception
	 */
	public function doInTransaction($callable, array $callableArgs = array()) {
		$this->beginTransaction();
		try {
			if (!is_callable($callable)) {
				throw new InvalidArgumentException('First parameter is not callable!');
			}
			$result = call_user_func_array($callable, $callableArgs);
			$this->commit();
			return $result;
		} catch (\Exception $e) {
			$this->rollBack();
			throw $e;
		}
	}

	//======================= BC =======================//

	/**
	 * @deprecated
	 */
	public function getSupplementalDriver() {
		return $this->getDriver();
	}

	/**
	 * @deprecated
	 */
	public function lastInsertId($name) {
		return $this->getInsertId($name);
	}

}
