<?php

namespace Flunorette\Hydrators;

use Flunorette\Statement;
use PDO;

class HydratorPairs extends Hydrator {

	/** @var string */
	public $key;

	/** @var string */
	public $value;

	/**
	 * Whether to normalize values (e.g. dates to DateTime objects)
	 * @var bool
	 */
	public $normalize = true;

	function __construct($key = null, $value = null, $normalize = true) {
		$this->key = $key;
		$this->value = $value;
		$this->normalize = $normalize;
	}

	public function hydrate(Statement $statement) {
		$rows = $statement->fetchAll(PDO::FETCH_ASSOC);

		if (!$rows) {
			return array();
		}

		if ($this->normalize) {
			foreach ($rows as $key => $row) {
				$rows[$key] = $statement->normalizeRow($row);
			}
		}

		$keys = array_keys((array) reset($rows));
		if (!count($keys)) {
			throw new \LogicException('Result set does not contain any column.');

		} elseif ($this->key === NULL && $this->value === NULL) {
			if (count($keys) === 1) {
				list($this->value) = $keys;
			} else {
				list($this->key, $this->value) = $keys;
			}
		}

		$return = array();
		if ($this->key === NULL) {
			foreach ($rows as $row) {
				$return[] = ($this->value === NULL ? $row : $row[$this->value]);
			}
		} else {
			foreach ($rows as $row) {
				$return[is_object($row[$this->key]) ? (string) $row[$this->key] : $row[$this->key]] = ($this->value === NULL ? $row : $row[$this->value]);
			}
		}

		return $return;
	}

}
