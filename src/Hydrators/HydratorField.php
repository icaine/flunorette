<?php

namespace Flunorette\Hydrators;

use Flunorette\Statement;
use PDO;

class HydratorField extends Hydrator {

	/** @var int */
	public $columnIndex = 0;

	/** @var bool */
	public $normalize = true;

	function __construct($columnIndex = 0, $normalize = true) {
		$this->columnIndex = $columnIndex;
		$this->normalize = $normalize;
	}

	public function hydrate(Statement $statement) {
		$res = $statement->fetch(PDO::FETCH_BOTH);
		if ($this->normalize) {
			$value = $statement->normalizeRow([$this->columnIndex => $res[$this->columnIndex]]);
			$res = $value;
		}
		return isset($res[$this->columnIndex]) ? $res[$this->columnIndex] : false;
	}

}
