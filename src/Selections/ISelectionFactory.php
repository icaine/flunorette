<?php

namespace Flunorette;

interface ISelectionFactory {

	/** @return Selection */
	public function createSelection($table, Connection $connection);

	/** @return GroupedSelection */
	public function createGrouped(Selection $refSelection, $table, $column);

	/** @return ActiveRow */
	public function createRow($data, Selection $selection);

}
