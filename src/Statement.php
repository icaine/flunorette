<?php

namespace Flunorette;

use DateInterval;
use Flunorette\Drivers\IDriver;
use Flunorette\Hydrators\HydratorColumn;
use Flunorette\Hydrators\HydratorField;
use Flunorette\Hydrators\HydratorPairs;
use Flunorette\Reflections\IReflection;
use Nette\DateTime;
use Nette\ObjectMixin;
use Nette\Reflection\ClassType;
use PDO;
use PDOException;
use PDOStatement;

class Statement extends PDOStatement {

	/** @var Connection */
	protected $connection;

	/** @var IDriver */
	protected $driver;

	/** @var float */
	protected $time;

	/** @var array */
	protected $columnTypes;

	protected function __construct(Connection $connection) {
		$this->connection = $connection;
		$this->driver = $connection->getDriver();
		$this->setFetchMode(PDO::FETCH_CLASS, 'Flunorette\Row', array($this));
	}

	/**
	 * Executes statement.
	 * @param  array
	 * @return self
	 */
	public function execute($params = array()) {
		static $types = array(
			'boolean' => PDO::PARAM_BOOL,
			'integer' => PDO::PARAM_INT,
			'resource' => PDO::PARAM_LOB,
			'NULL' => PDO::PARAM_NULL
		);

		foreach ($params as $param => $value) {
			$type = gettype($value);
			$this->bindValue(is_int($param) ? $param + 1 : $param, $value, isset($types[$type]) ? $types[$type] : PDO::PARAM_STR);
		}

		$time = microtime(TRUE);
		try {
			parent::execute();
		} catch (PDOException $e) {
			$e->queryString = $this->queryString;
			$this->connection->onError($this, $params, $e);
			throw $e;
		}
		$this->time = microtime(TRUE) - $time;
		$this->connection->onQuery($this, $params); // $this->connection->onQuery() in PHP 5.3

		return $this;
	}

	/**
	 * Custom fetch mode when more control over how data should be fetched is needed
	 * @param mixed $hydrator Hydrator or callback(Statement $statement)
	 * @return mixed
	 */
	public function hydrate($hydrator) {
		if (is_callable($hydrator)) {
			return $hydrator($this);
		} else {
			throw Exception('Invalid hydrator passed.');
		}
	}

	/**
	 * Fetches into an array where the 1st column is a key and all subsequent columns are values.
	 * @return array
	 */
	public function fetchPairs() {
		return $this->hydrate(new HydratorPairs());
	}

	/**
	 * Fetches single field.
	 * @param mixed $column index or key
	 * @return mixed|FALSE
	 */
	public function fetchField($column = 0) {
		return $this->hydrate(new HydratorField($column));
	}

	/**
	 * Fetches all values from given $column
	 * @param mixed $column index or key
	 * @return array
	 */
	public function fetchColumn($column = 0) {
		return $this->hydrate(new HydratorColumn($column, true));
	}

	/**
	 * Normalizes result row.
	 * @param  array
	 * @return array
	 */
	public function normalizeRow($row) {
		if ($this->columnTypes === NULL) {
			$this->columnTypes = (array) $this->driver->getColumnTypes($this);
			$this->columnTypes += array_values($this->columnTypes);
		}

		$row = (array) $row;
		foreach ($row as $key => $value) {
			$type = $this->columnTypes[$key];
			if ($value === NULL || $value === FALSE || $type === IReflection::FIELD_TEXT) {

			} elseif ($type === IReflection::FIELD_INTEGER) {
				$row[$key] = is_float($tmp = $value * 1) ? $value : $tmp;
			} elseif ($type === IReflection::FIELD_FLOAT) {
				if (($pos = strpos($value, '.')) !== FALSE) {
					$value = rtrim(rtrim($pos === 0 ? "0$value" : $value, '0'), '.');
				}
				$float = (float) $value;
				$row[$key] = (string) $float === $value ? $float : $value;
			} elseif ($type === IReflection::FIELD_BOOL) {
				$row[$key] = ((bool) $value) && $value !== 'f' && $value !== 'F';
			} elseif ($type === IReflection::FIELD_DATETIME || $type === IReflection::FIELD_DATE || $type === IReflection::FIELD_TIME) {
				$row[$key] = new DateTime($value);
			} elseif ($type === IReflection::FIELD_TIME_INTERVAL) {
				preg_match('#^(-?)(\d+)\D(\d+)\D(\d+)\z#', $value, $m);
				$row[$key] = new DateInterval("PT$m[2]H$m[3]M$m[4]S");
				$row[$key]->invert = (int) (bool) $m[1];
			} elseif ($type === IReflection::FIELD_UNIX_TIMESTAMP) {
				$row[$key] = DateTime::from($value);
			}
		}

		return $this->driver->normalizeRow($row);
	}

	/**
	 * @return Connection
	 */
	public function getConnection() {
		return $this->connection;
	}

	/**
	 * @return string
	 */
	public function getQueryString() {
		return $this->queryString;
	}

	/**
	 * @return int
	 */
	public function getColumnCount() {
		return $this->columnCount();
	}

	/**
	 * @return int
	 */
	public function getRowCount() {
		return $this->rowCount();
	}

	/**
	 * @return float
	 */
	public function getTime() {
		return $this->time;
	}

	/**
	 * @return ClassType
	 */
	public static function getReflection() {
		return new ClassType(get_called_class());
	}

	public function __call($name, $args) {
		return ObjectMixin::call($this, $name, $args);
	}

	public function &__get($name) {
		return ObjectMixin::get($this, $name);
	}

	public function __set($name, $value) {
		return ObjectMixin::set($this, $name, $value);
	}

	public function __isset($name) {
		return ObjectMixin::has($this, $name);
	}

	public function __unset($name) {
		ObjectMixin::remove($this, $name);
	}

}
