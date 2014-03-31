<?php

namespace Flunorette;

use Flunorette\Drivers\IDriver;
use Nette\Object;

class QueryContext extends Object {

	/** @var Query */
	protected $activeQuery;

	/** @var Connection */
	protected $connection;

	/** @var string */
	protected $from;

	/** @var string */
	protected $table;

	/** @var string */
	protected $tableAlias;

	/** @var array */
	public $statements = array();

	/** @var array */
	public $parameters = array();

	/** @var bool */
	public $ndbtCompatibility = true; //TODO when composition is used [todo:1] use some overlay class instead (e.g. NdbtCommonQuery)

	/** @var array of used tables (also include table from clause FROM) */
	public $joins = array();

	/** @var bool disable adding undefined joins to query? */
	public $isSmartJoinEnabled = true;

	/** @var bool */
	public $ignore = false;

	function __construct($from, Connection $connection) {
		$this->from = $from;
		$this->setTableParts($from);
		$this->connection = $connection;
	}

	public function __clone() {
		$this->activeQuery = null;
	}

	protected function setTableParts($str) {
		$tableParts = explode(' ', trim($str));
		$this->table = reset($tableParts);
		$this->tableAlias = end($tableParts);
	}

	/** @return Query */
	public function getActiveQuery() {
		return $this->activeQuery;
	}

	public function setActiveQuery(Query $activeQuery) {
		$this->activeQuery = $activeQuery;
	}

	/** @return Connection */
	public function getConnection() {
		return $this->connection;
	}

	/** @return string */
	public function getFrom() {
		return $this->from;
	}

	/** @return string */
	public function getTable() {
		return $this->table;
	}

	/** @return string */
	public function getTableAlias() {
		return $this->tableAlias;
	}

	/** @return IDriver */
	public function getDriver() {
		return $this->connection->getDriver();
	}

	/** @return IReflection */
	public function getDatabaseReflection() {
		return $this->connection->getDatabaseReflection();
	}

	/** @return SqlPreprocessor */
	public function getPreprocessor() {
		return $this->connection->getPreprocessor();
	}

	public function __sleep() {
		return array('table', 'tableAlias', 'statements', 'parameters', 'joins', 'ndbtCompatibility', 'isSmartJoinEnabled');
	}

}
