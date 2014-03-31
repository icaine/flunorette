<?php

namespace Flunorette;

use Nette\ArrayHash;
use PDO;

abstract class Hydrator {

	final public function __invoke() {
		return call_user_func_array(array($this, 'hydrate'), func_get_args());
	}

	abstract public function hydrate(Statement $statement);

}

class HydratorSelectionDefault extends Hydrator {

	/** @var Selection */
	protected $selection;

	function __construct(Selection $selection = null) {
		$this->selection = $selection;
	}

	public function getSelection() {
		return $this->selection;
	}

	public function setSelection(Selection $selection) {
		$this->selection = $selection;
		return $this;
	}

	public function hydrate(Statement $statement) {
		$selectionFactory = $this->selection->getSelectionFactory();
		$rows = array();
		foreach ($statement as $key => $row) {
			$row = $selectionFactory->createRow($row, $this->selection);
			$primary = $row->getSignature(false);
			$rows[$primary ? : $key] = $row;
		}
		return $rows;
	}

}

abstract class HydratorCommon extends Hydrator {

	/** @var bool */
	public $fetchAll = true;

	function __construct($fetchAll = true) {
		$this->fetchAll = $fetchAll;
	}

}

class HydratorArrayHash extends HydratorCommon {

	public function hydrate(Statement $statement) {
		$res = null;
		if ($this->fetchAll) {
			foreach ($statement as $key => $value) {
				$res[$key] = ArrayHash::from($value);
			}
		} else {
			$res = ArrayHash::from($statement->fetch());
		}
		return $res;
	}

}

class HydratorResult extends Hydrator {

	/** @var int */
	public $columnIndex = 0;

	function __construct($columnIndex = 0) {
		$this->columnIndex = $columnIndex;
	}

	public function hydrate(Statement $statement) {
		d($statement->getColumnMeta($this->columnIndex));
		$res = $statement->fetchField($this->columnIndex);
		$statement->closeCursor();
		return $res;
	}

}
