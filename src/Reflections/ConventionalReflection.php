<?php

namespace Flunorette\Reflections;

use Nette\Object;

class ConventionalReflection extends Object implements IReflection {

	/** @var string */
	protected $primary;

	/** @var string */
	protected $foreign;

	/** @var string */
	protected $table;

	/** @var array */
	protected $tables = array();

	/**
	 * Create conventional structure.
	 * @param  string %s stands for table name
	 * @param  string %1$s stands for key used after ->, %2$s for table name
	 * @param  string %1$s stands for key used after ->, %2$s for table name
	 * @param  array tables in database
	 */
	public function __construct($primary = 'id', $foreign = '%s_id', $table = '%s', $tables = array()) {
		$this->primary = $primary;
		$this->foreign = $foreign;
		$this->table = $table;
		$this->tables = array_flip($tables);
	}

	public function getPrimary($table) {
		return sprintf($this->primary, $this->getColumnFromTable($table));
	}

	public function getHasManyReference($table, $key) {
		$table = $this->getColumnFromTable($table);
		return array(
			sprintf($this->table, $key, $table),
			sprintf($this->foreign, $table, $key),
		);
	}

	public function getBelongsToReference($table, $key) {
		$table = $this->getColumnFromTable($table);
		return array(
			sprintf($this->table, $key, $table),
			sprintf($this->foreign, $key, $table),
		);
	}

	public function hasTable($name) {
		return isset($this->tables[$name]);
	}

	protected function getColumnFromTable($name) {
		if ($this->table !== '%s' && preg_match('(^' . str_replace('%s', '(.*)', preg_quote($this->table)) . '\z)', $name, $match)) {
			return $match[1];
		}

		return $name;
	}

}
