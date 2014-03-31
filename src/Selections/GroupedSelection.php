<?php

namespace Flunorette;

use Flunorette\Utils\Arrays;

class GroupedSelection extends Selection {

	/** @var Selection referenced table */
	protected $refSelection;

	/** @var  mixed current assigned referencing array */
	protected $refCacheCurrent;

	/** @var string grouping column name */
	protected $column;

	/** @var int primary key */
	protected $active;

	/**
	 * Creates filtered and grouped table representation.
	 * @param  Selection  $refSelection
	 * @param  string  database table name
	 * @param  string  joining column
	 */
	public function __construct(Selection $refSelection, $table, $column) {
		$this->refSelection = $refSelection;
		$this->column = $column;
		parent::__construct($table, $refSelection->getConnection());
	}

	public function callSqlBuilder($method, $args = array(), $type = 'select') {
		//$this->refSelection->notifyAboutChangesInThis
		return parent::callSqlBuilder($method, $args, $type);
	}

	/**
	 * Sets active group.
	 * @internal
	 * @param  int  primary key of grouped rows
	 * @return GroupedSelection
	 */
	public function setActive($active) {
		$this->active = $active;
		return $this;
	}

	public function select($columns) {
		if (!$this->getSqlBuilder()->getClause('SELECT')) {
			$this->getSqlBuilder()->select("$this->name.$this->column");
		}
		return call_user_func_array('parent::select', func_get_args());
	}

	public function order($columns) {
		if (!$this->getSqlBuilder()->getClause('ORDER BY')) {
			// improve index utilization
			$this->getSqlBuilder()->orderBy("$this->name.$this->column" . (preg_match('~\bDESC\z~i', $columns) ? ' DESC' : ''));
		}
		return call_user_func_array('parent::order', func_get_args());
	}

	//======================= aggregations =======================//

	public function aggregation($function) {
		$aggregation = & $this->getRefTable($refPath)->aggregation[$refPath . $function . $this->getSql() . serialize($this->getParameters())];

		if ($aggregation === NULL) {
			$aggregation = array();

			$selection = $this->createSelectionInstance();
			$selection->context = clone $this->context;
			$selection->select(null);
			$selection->groupBy(null);
			$selection->having(null);
			$selection->order(null);
			$selection->limit(null, null);
			$selection->select($function);
			$selection->select("$this->name.$this->column");
			$selection->group("$this->name.$this->column");

			foreach ($selection as $row) {
				$aggregation[$row[$this->column]] = $row;
			}
		}

		if (isset($aggregation[$this->active])) {
			foreach ($aggregation[$this->active] as $val) {
				return $val; //TODO fetch single
			}
		}
	}

	public function count($column = NULL) {
		$return = parent::count($column);
		return isset($return) ? $return : 0;
	}

	//======================= internal =======================//

	protected function execute() {
		if ($this->rows !== NULL) {
			return;
		}

		$this->loadRefCache();

		if (!isset($this->refCacheCurrent['data'])) {
			$limit = $this->getSqlBuilder()->getClause('LIMIT');
			$rows = count($this->refSelection->rows);
			if ($limit && $rows > 1) {
				$this->limit(null, null);
			}

			parent::execute();
			$this->getSqlBuilder()->limit($limit);
			$data = array();
			$offset = array();
			foreach ((array) $this->rows as $key => $row) {
				$ref = & $data[$row[$this->column]];
				$skip = & $offset[$row[$this->column]];
				if ($limit === NULL || $rows <= 1 || (count($ref) < $limit && $skip >= $this->getSqlBuilder()->getClause('OFFSET'))) {
					$ref[$key] = $row;
				} else {
					unset($this->rows[$key]);
				}
				$skip++;
				unset($ref, $skip);
			}

			$this->refCacheCurrent['data'] = $data;
			$this->data = & $this->refCacheCurrent['data'][$this->active];
		}

		if ($this->data === NULL) {
			$this->data = array();
		} else {
			foreach ($this->data as $row) {
				$row->setTable($this); // injects correct parent GroupedSelection
			}
			reset($this->data);
		}
	}

	protected function getRefTable(& $refPath) {
		$refObj = $this->refSelection;
		$refPath = $this->getName() . '.';
		while ($refObj instanceof GroupedSelection) {
			$refPath .= $refObj->getName() . '.';
			$refObj = $refObj->refSelection;
		}

		return $refObj;
	}

	protected function loadRefCache() {
		$hash = $this->getSpecificCacheKey();
		$referencing = & $this->refCache['referencing'][$this->getGeneralCacheKey()];
		$this->refCacheCurrent = & $referencing[$hash];
		$this->specificCacheKey = & $referencing[$hash]['specificCacheKey'];
		$this->rows = & $referencing[$hash]['rows'];

		if (isset($referencing[$hash]['data'][$this->active])) {
			$this->data = & $referencing[$hash]['data'][$this->active];
		}
	}

	//======================= manipulation =======================//

	public function insert($data) {
		if ($data instanceof \Traversable && !$data instanceof Selection) {
			$data = iterator_to_array($data);
		}

		if (Arrays::isList($data)) {
			foreach (array_keys($data) as $key) {
				$data[$key][$this->column] = $this->active;
			}
		} else {
			$data[$this->column] = $this->active;
		}

		return parent::insert($data);
	}

	public function update($data) {
		$context = $this->context;

		$this->context = clone $context;
		$this->where($this->column, $this->active);
		$return = parent::update($data);

		$this->context = $context;
		return $return;
	}

	public function delete() {
		$context = $this->context;

		$this->context = clone $context;
		$this->where($this->column, $this->active);
		$return = parent::delete();

		$this->context = $context;
		return $return;
	}

}
