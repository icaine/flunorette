<?php

namespace Flunorette;

use Nette\ArrayHash;

class Row extends ArrayHash {

	function __construct(Statement $statement) {
		foreach ($statement->normalizeRow($this) as $key => $value) {
			$this->$key = $value;
		}
	}

	/**
	 * Returns a item.
	 * @param  mixed  key or index
	 * @return mixed
	 */
	public function offsetGet($key) {
		if (is_int($key)) {
			$arr = array_slice((array) $this, $key, 1);
			if (!$arr) {
				trigger_error('Undefined offset: ' . __CLASS__ . "[$key]", E_USER_NOTICE);
			}
			return current($arr);
		}
		return $this->$key;
	}

	/**
	 * Checks if $key exists.
	 * @param  mixed  key or index
	 * @return bool
	 */
	public function offsetExists($key) {
		if (is_int($key)) {
			return (bool) current(array_slice((array) $this, $key, 1));
		}
		return parent::offsetExists($key);
	}

}
