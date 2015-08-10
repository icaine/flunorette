<?php

namespace Flunorette\Selections;

use Flunorette\InvalidStateException;
use Flunorette\Reflections\ReflectionException;
use Flunorette\UndeclaredColumnException;
use Nette\Object;

class ActiveRow extends Object implements \IteratorAggregate, \ArrayAccess, \Countable {

	/** @var Selection */
	protected $selection;

	/** @var array of row data */
	protected $data;

	/** @var array of new values {@see ActiveRow::update()} */
	protected $modified = array();

	/** @var bool */
	protected $deleted = false;

	/**
	 *
	 * @param array $data
	 * @param Selection $selection
	 */
	public function __construct(array $data, Selection $selection) { //TODO possibility to create empty active row
		$this->data = $data;
		$this->selection = $selection;
	}

	/**
	 * @internal
	 * @ignore
	 */
	public function setTable(Selection $table) {
		$this->selection = $table;
	}

	/**
	 * @internal
	 * @ignore
	 */
	public function getTable() {
		return $this->selection;
	}

	public function __toString() {
		try {
			return (string) $this->getPrimary();
		} catch (\Exception $e) {
			trigger_error("Exception in " . __METHOD__ . "(): {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}", E_USER_ERROR);
		}
	}

	/**
	 * @return array
	 */
	public function toArray() {
		return $this->data;
	}

	/** @return bool */
	public function isDeleted() {
		return $this->deleted;
	}

	/**
	 * Returns primary key value.
	 * @param  bool
	 * @return mixed
	 */
	public function getPrimary($need = true) {
		$primary = $this->selection->getPrimary($need);
		if ($primary === null) {
			return null;
		} elseif (!is_array($primary)) {
			if (isset($this->data[$primary])) {
				return $this->data[$primary];
			} elseif ($need) {
				throw new InvalidStateException("Row does not contain primary $primary column data.");
			} else {
				return null;
			}
		} else {
			$primaryVal = array();
			foreach ($primary as $key) {
				if (!isset($this->data[$key])) {
					if ($need) {
						throw new InvalidStateException("Row does not contain primary $key column data.");
					} else {
						return NULL;
					}
				}
				$primaryVal[$key] = $this->data[$key];
			}
			return $primaryVal;
		}
	}

	/**
	 * Returns row signature (composition of primary keys)
	 * @param  bool
	 * @return string
	 */
	public function getSignature($need = TRUE) {
		return implode('|', (array) $this->getPrimary($need));
	}

	/**
	 * Returns referenced row.
	 * @param  string
	 * @param  string
	 * @return ActiveRow or NULL if the row does not exist
	 */
	public function ref($key, $throughColumn = NULL) {
		if (!$throughColumn) {
			list($key, $throughColumn) = $this->selection->getDatabaseReflection()->getBelongsToReference($this->selection->getName(), $key);
			if (is_array($throughColumn)) {
				$key = $throughColumn[0];
				$throughColumn = $throughColumn[1];
			}
		}

		return $this->getReference($key, $throughColumn);
	}

	/**
	 * Returns referencing rows.
	 * @param  string
	 * @param  string
	 * @return GroupedSelection
	 */
	public function related($key, $throughColumn = NULL) {
		if (strpos($key, '.') !== FALSE) {
			list($key, $throughColumn) = explode('.', $key);
		} elseif (!$throughColumn) {
			list($key, $throughColumn) = $this->selection->getDatabaseReflection()->getHasManyReference($this->selection->getName(), $key);
		}

		return $this->selection->getReferencingTable($key, $throughColumn, $this[$this->selection->getPrimary()]);
	}

	/**
	 * Saves (updates or inserts) the row.
	 * @param array $data
	 * @return bool success?
	 */
	public function save($data = null) {
		$hasPrimary = $this->getPrimary(false);
		if ($hasPrimary) {
			if (0 == $this->selection->createSelectionInstance()->wherePrimary($hasPrimary)->limit(1)->count('*')) {
				goto INSERT_ROW;
			}
			$res = $this->update($data);
		} else {
			INSERT_ROW:
			if ($data === null) {
				$data = $this->modified + $this->data;
			}
			$res = $this->selection->insert($data);
			if ($res instanceof ActiveRow) {
				$this->data = $res->toArray();
				$this->selection->addRow($this);
			}
			$this->modified = array();
		}

		if ($res === false) {
			trigger_error('Data could not be saved: unknown reason', E_USER_WARNING);
		} else {
			$res = true;
		}
		return $res;
	}

	/**
	 * Updates row.
	 * @param  array or NULL for all modified values
	 * @return int number of affected rows or FALSE in case of an error
	 */
	public function update($data = null) {
		if ($data instanceof \Traversable) {
			$data = iterator_to_array($data);
		}
		if ($data === NULL) {
			$data = $this->modified;
		} else {
			foreach ($data as $key => $value) {
				$this->offsetSet($key, $value);
			}
		}
		$res = $this->selection->createSelectionInstance()
			->wherePrimary($this->getPrimary())
			->update($data);
		if ($res !== false) {
			$this->modified = array();
		}
		return $res;
	}

	/**
	 * Deletes row.
	 * @return int number of affected rows or FALSE in case of an error
	 */
	public function delete() {
		$res = $this->selection->createSelectionInstance()
			->wherePrimary($this->getPrimary())
			->delete();

		if ($res > 0) {
			$this->deleted = true;
			if (($signature = $this->getSignature(false))) {
				unset($this->selection[$signature]);
			}
		}

		return $res;
	}

	/**
	 * Number of columns
	 * @return int
	 */
	public function count() {
		return count($this->data);
	}

	//======================= interface IteratorAggregate =======================//

	public function getIterator() {
		return new \ArrayIterator($this->data);
	}

	//======================= interface ArrayAccess & magic accessors =======================//

	/**
	 * Stores value in column.
	 * @param  string column name
	 * @param  string value
	 */
	public function offsetSet($key, $value) {
		$this->__set($key, $value);
	}

	/**
	 * Returns value of column.
	 * @param  string column name
	 * @return string
	 */
	public function offsetGet($key) {
		return $this->__get($key);
	}

	/**
	 * Tests if column exists.
	 * @param  string column name
	 * @return bool
	 */
	public function offsetExists($key) {
		return $this->__isset($key);
	}

	/**
	 * Removes column from data.
	 * @param  string column name
	 */
	public function offsetUnset($key) {
		$this->__unset($key);
	}

	public function __set($key, $value) {
		$this->data[$key] = $value;
		$this->modified[$key] = $value;
	}

	public function &__get($key) {
		if (array_key_exists($key, $this->data)) {
			return $this->data[$key];
		}

		try {
			list($key, $column) = $this->selection->getConnection()->getDatabaseReflection()->getBelongsToReference($this->selection->getName(), $key);
			$referenced = $this->getReference($key, $column);
			if ($referenced !== FALSE) {
				return $referenced;
			}
		} catch (ReflectionException $e) {

		}

		throw new UndeclaredColumnException("Cannot read an undeclared column '$key'.");
	}

	public function __isset($key) {
		return isset($this->data[$key]);
	}

	public function __unset($key) {
		unset($this->data[$key]);
		unset($this->modified[$key]);
	}

	protected function getReference($table, $column) {
		if (array_key_exists($column, $this->data)) {
			$value = (string) $this->data[$column];
			$referenced = $this->selection->getReferencedTable($table, $column, $value);
			return isset($referenced[$value]) ? $referenced[$value] : NULL; // referenced row may not exist
		}

		return FALSE;
	}

}
