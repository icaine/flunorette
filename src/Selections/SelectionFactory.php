<?php

namespace Flunorette;

class SelectionFactory implements ISelectionFactory {

	public function createSelection($table, Connection $connection) {
		return new Selection($table, $connection);
	}

	public function createGrouped(Selection $refSelection, $table, $column) {
		return new GroupedSelection($refSelection, $table, $column);
	}

	public function createRow($data, Selection $selection) {
		return new ActiveRow((array) $data, $selection);
	}

}
