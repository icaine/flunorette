<?php

namespace Flunorette\Hydrators;

use Flunorette\Selections\Selection;
use Flunorette\Statement;

/**
 * @internal
 */
class HydratorSelection extends Hydrator {

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
