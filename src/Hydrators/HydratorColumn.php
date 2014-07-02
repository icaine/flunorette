<?php

namespace Flunorette\Hydrators;

use Flunorette\Statement;
use PDO;

class HydratorColumn extends Hydrator {

	/** @var mixed */
	public $columnIndex = 0;

	/**
	 * Whether to normalize values (e.g. dates to DateTime objects)
	 * @var bool
	 */
	public $normalize = true;

	function __construct($columnIndex = 0, $normalize = true) {
		$this->columnIndex = $columnIndex;
		$this->normalize = $normalize;
	}

	public function hydrate(Statement $statement) {
		$columnIndex = $this->columnIndex;
		if (is_string($columnIndex)) {
			$columnCount = $statement->getColumnCount();
			for ($i = 0; $i < $columnCount; $i++) {
				$meta = $statement->getColumnMeta($i);
				if ($meta['name'] == $columnIndex) {
					$columnIndex = $i;
					break;
				}
			}
		}

		$res = $statement->fetchAll(PDO::FETCH_COLUMN, $columnIndex);
		if ($res !== false && $this->normalize) {
			foreach ($res as $key => $value) {
				$value = $statement->normalizeRow(array($columnIndex => $value));
				$res[$key] = $value[$columnIndex];
			}
		}
		return $res;
	}

}
