<?php

namespace Flunorette\Reflections;

use Flunorette\Connection;
use Nette\Caching\Cache;
use Nette\Object;

class DiscoveredReflection extends Object implements IReflection {

	/** @var Connection */
	protected $connection;

	/** @var Cache */
	protected $cache;

	/** @var array */
	protected $structure = array();

	/** @var array */
	protected $loadedStructure;

	/** @var bool */
	private $testReverse = true;

	/**
	 * Creates autodiscovery structure.
	 * @param Connection $connection
	 */
	public function __construct(Connection $connection) {
		$this->connection = $connection;
		$cache = $this->connection->getCache();
		if ($cache) {
			$this->cache = $cache->derive('DiscoveredReflection');
			$this->structure = $this->loadedStructure = $this->cache->load('structure') ? : array();
		}

		if (empty($this->structure)) {
			$this->reloadAllForeignKeys();
		}
	}

	public function __destruct() {
		if ($this->cache && $this->structure !== $this->loadedStructure) {
			$this->cache->save('structure', $this->structure);
		}
	}

	public function getPrimary($table) {
		$primary = & $this->structure['primary'][strtolower($table)];
		if (isset($primary)) {
			return empty($primary) ? NULL : $primary;
		}

		$columns = $this->connection->getDriver()->getColumns($table);
		$primary = array();
		foreach ($columns as $column) {
			if ($column['primary']) {
				$primary[] = $column['name'];
			}
		}

		if (count($primary) === 0) {
			return NULL;
		} elseif (count($primary) === 1) {
			$primary = reset($primary);
		}

		return $primary;
	}

	public function getHasManyReference($table, $key, $refresh = false) {
		if (isset($this->structure['hasMany'][strtolower($table)])) {
			$candidates = $columnCandidates = array();
			foreach ($this->structure['hasMany'][strtolower($table)] as $targetPair) {
				list($targetColumn, $targetTable) = $targetPair;
				if (stripos($targetTable, $key) === FALSE) {
					continue;
				}

				$candidates[] = array($targetTable, $targetColumn);
				if (stripos($targetColumn, $table) !== FALSE) {
					$columnCandidates[] = $candidate = array($targetTable, $targetColumn);
					if (strtolower($targetTable) === strtolower($key)) {
						return $candidate;
					}
				}
			}

			if (count($columnCandidates) === 1) {
				return reset($columnCandidates);
			} elseif (count($candidates) === 1) {
				return reset($candidates);
			}

			foreach ($candidates as $candidate) {
				if (strtolower($candidate[0]) === strtolower($key)) {
					return $candidate;
				}
			}
		}

		if ($refresh) {
			$this->reloadAllForeignKeys();
			return $this->getHasManyReference($table, $key, FALSE);
		}

		if (empty($candidates)) {
			if ($this->testReverse) {
				$this->testReverse = false;
				try {
					$this->getBelongsToReference($table, $key, false);
					$found = true;
				} catch (ReflectionException $exc) {
					$found = false;
				}
				$this->testReverse = true;
				if ($found) {
					throw new ReflectionException("No reference found for \${$table}->related({$key}) but reverse one \${$key}->related({$table}) was found (wrong direction?).");
				}
			}
			throw new ReflectionException("No reference found for \${$table}->related({$key}).");
		} else {
			throw new ReflectionException('Ambiguous joining column in related call.');
		}
	}

	public function getBelongsToReference($table, $key, $refresh = false) {
		if (isset($this->structure['belongsTo'][strtolower($table)])) {
			foreach ($this->structure['belongsTo'][strtolower($table)] as $column => $targetTable) {
				if (stripos($column, $key) !== FALSE) {
					return array($targetTable, $column);
				}
			}
		}

		if (isset($this->structure['foreignKeys'][$table][$key])) {
			return $this->structure['foreignKeys'][$table][$key];
		}

		if ($refresh) {
			$this->reloadForeignKeys($table);
			return $this->getBelongsToReference($table, $key, FALSE);
		}

		if ($this->testReverse) {
			$this->testReverse = false;
			try {
				$this->getHasManyReference($table, $key, false);
				$found = true;
			} catch (ReflectionException $exc) {
				$found = false;
			}
			$this->testReverse = true;
			if ($found) {
				throw new ReflectionException("No reference found for \${$table}->{$key} but reverse \${$key}->{$table} was found (wrong direction?)");
			}
		}
		throw new ReflectionException("No reference found for \${$table}->{$key}.");
	}

	public function hasTable($name) {
		if (!isset($this->structure['tables'])) {
			$this->reloadAllForeignKeys();
		}
		return isset($this->structure['tables'][$name]);
	}

	public function reload() {
		$this->reloadAllForeignKeys();
	}

	protected function reloadAllForeignKeys() {
		$this->structure['hasMany'] = array();
		$this->structure['belongsTo'] = array();
		$this->structure['foreignKeys'] = array();
		$this->structure['tables'] = array();

		foreach ($this->connection->getDriver()->getTables() as $table) {
			$this->structure['tables'][$table['name']] = true;
			if ($table['view'] == FALSE) {
				$this->reloadForeignKeys($table['name']);
			}
		}

		foreach ($this->structure['hasMany'] as & $table) {
			uksort($table, function($a, $b) {
				return strlen($a) - strlen($b);
			});
		}
	}

	protected function reloadForeignKeys($table) {
		foreach ($this->connection->getDriver()->getForeignKeys($table) as $row) {
			$this->structure['belongsTo'][strtolower($table)][$row['column']] = $row['ref_table'];
			$this->structure['hasMany'][strtolower($row['ref_table'])][$row['column'] . $table] = array($row['column'], $table);
			$this->structure['foreignKeys'][$table][$row['ref_table']] = array($row['ref_table'], $row['column']);
		}

		if (isset($this->structure['belongsTo'][$table])) {
			uksort($this->structure['belongsTo'][$table], function($a, $b) {
				return strlen($a) - strlen($b);
			});
		}
	}

}
