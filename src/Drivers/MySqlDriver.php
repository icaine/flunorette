<?php

namespace Flunorette\Drivers;

use Flunorette\Connection;
use Flunorette\ConnectionException;
use Flunorette\DriverException;
use Flunorette\ForeignKeyConstraintViolationException;
use Flunorette\Helpers;
use Flunorette\NotNullConstraintViolationException;
use Flunorette\Reflections\IReflection;
use Flunorette\UniqueConstraintViolationException;
use PDOException;
use PDOStatement;

class MySqlDriver implements IDriver {

	const ERROR_ACCESS_DENIED = 1045;

	const ERROR_DUPLICATE_ENTRY = 1062;

	const ERROR_DATA_TRUNCATED = 1265;

	/** @var Connection */
	private $connection;

	/**
	 * @param Connection $connection
	 * @param array $options
	 * Driver options:
	 *   - charset => character encoding to set (default is utf8)
	 *   - sqlmode => see http://dev.mysql.com/doc/refman/5.0/en/server-sql-mode.html
	 */
	public function __construct(Connection $connection, array $options) {
		$this->connection = $connection;
		$charset = isset($options['charset']) ? $options['charset'] : 'utf8';
		if ($charset) {
			$connection->query("SET NAMES '$charset'");
		}
		if (isset($options['sqlmode'])) {
			$connection->query("SET sql_mode='$options[sqlmode]'");
		}
	}

	/**
	 * @param PDOException $e
	 * @return DriverException
	 */
	public function convertException(PDOException $e) {
		$code = isset($e->errorInfo[1]) ? $e->errorInfo[1] : null;
		if (in_array($code, array(1216, 1217, 1451, 1452, 1701), true)) {
			return ForeignKeyConstraintViolationException::from($e);
		} elseif (in_array($code, array(1062, 1557, 1569, 1586), true)) {
			return UniqueConstraintViolationException::from($e);
		} elseif ($code >= 2001 && $code <= 2028) {
			return ConnectionException::from($e);
		} elseif (in_array($code, array(1048, 1121, 1138, 1171, 1252, 1263, 1566), true)) {
			return NotNullConstraintViolationException::from($e);
		} else {
			return DriverException::from($e);
		}
	}

	/**
	 * Delimites identifier for use in a SQL statement.
	 */
	public function delimite($name) {
		// @see http://dev.mysql.com/doc/refman/5.0/en/identifiers.html
		return '`' . str_replace('`', '``', $name) . '`';
	}

	/**
	 * Formats boolean for use in a SQL statement.
	 */
	public function formatBool($value) {
		return $value ? '1' : '0';
	}

	/**
	 * Formats date-time for use in a SQL statement.
	 */
	public function formatDateTime(/* \DateTimeInterface */ $value) {
		return $value->format("'Y-m-d H:i:s'");
	}

	/**
	 * Encodes string for use in a LIKE statement.
	 */
	public function formatLike($value, $pos) {
		$value = addcslashes(str_replace('\\', '\\\\', $value), "\x00\n\r\\'%_");
		return ($pos <= 0 ? "'%" : "'") . $value . ($pos >= 0 ? "%'" : "'");
	}

	/**
	 * Injects LIMIT/OFFSET to the SQL query.
	 */
	public function applyLimit(& $sql, $limit, $offset) {
		if ($limit >= 0 || $offset > 0) {
			// see http://dev.mysql.com/doc/refman/5.0/en/select.html
			$sql .= ' LIMIT ' . ($limit < 0 ? '18446744073709551615' : (int) $limit)
				. ($offset > 0 ? ' OFFSET ' . (int) $offset : '');
		}
	}

	/**
	 * Normalizes result row.
	 */
	public function normalizeRow($row) {
		return $row;
	}

	/**
	 * Returns list of tables.
	 */
	public function getTables() {
		$tables = array();
		foreach ($this->connection->query('SHOW FULL TABLES') as $row) {
			$row = array_values(is_array($row) ? $row : iterator_to_array($row));
			$tables[] = array(
				'name' => $row[0],
				'view' => isset($row[1]) && $row[1] === 'VIEW',
			);
		}
		return $tables;
	}

	/**
	 * Returns metadata for all columns in a table.
	 */
	public function getColumns($table) {
		$columns = array();
		foreach ($this->connection->query('SHOW FULL COLUMNS FROM ' . $this->delimite($table)) as $row) {
			$type = explode('(', $row['Type']);
			$columns[] = array(
				'name' => $row['Field'],
				'table' => $table,
				'nativetype' => strtoupper($type[0]),
				'size' => isset($type[1]) ? (int) $type[1] : NULL,
				'unsigned' => (bool) strstr($row['Type'], 'unsigned'),
				'nullable' => $row['Null'] === 'YES',
				'default' => $row['Default'],
				'autoincrement' => $row['Extra'] === 'auto_increment',
				'primary' => $row['Key'] === 'PRI',
				'vendor' => (array) $row,
			);
		}
		return $columns;
	}

	/**
	 * Returns metadata for all indexes in a table.
	 */
	public function getIndexes($table) {
		$indexes = array();
		foreach ($this->connection->query('SHOW INDEX FROM ' . $this->delimite($table)) as $row) {
			$indexes[$row['Key_name']]['name'] = $row['Key_name'];
			$indexes[$row['Key_name']]['unique'] = !$row['Non_unique'];
			$indexes[$row['Key_name']]['primary'] = $row['Key_name'] === 'PRIMARY';
			$indexes[$row['Key_name']]['columns'][$row['Seq_in_index'] - 1] = $row['Column_name'];
		}
		return array_values($indexes);
	}

	/**
	 * Returns metadata for all foreign keys in a table.
	 */
	public function getForeignKeys($table) {
		$keys = array();
		$query = 'SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE '
			. 'WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL AND TABLE_NAME = ' . $this->connection->quote($table);

		foreach ($this->connection->query($query) as $id => $row) {
			$keys[$id]['name'] = $row['CONSTRAINT_NAME']; // foreign key name
			$keys[$id]['column'] = $row['COLUMN_NAME']; // local columns
			$keys[$id]['ref_table'] = $row['REFERENCED_TABLE_NAME']; // referenced table
			$keys[$id]['ref_column'] = $row['REFERENCED_COLUMN_NAME']; // referenced columns
		}

		return array_values($keys);
	}

	/**
	 * Returns associative array of detected types (IReflection::FIELD_*) in result set.
	 */
	public function getColumnTypes(PDOStatement $statement) {
		$types = array();
		$count = $statement->columnCount();
		for ($col = 0; $col < $count; $col++) {
			$meta = $statement->getColumnMeta($col);

			if (PHP_VERSION_ID < 50417) { // PHP bug #48724
				switch ($meta['name']) {
					case 'tinyint':
						$meta['native_type'] = 'TINY';
						break;
					case 'bit':
						$meta['native_type'] = 'BIT';
						break;
					case 'year':
						$meta['native_type'] = 'YEAR';
						break;
					default:
						break;
				}
			}

			if (isset($meta['native_type'])) {
				$types[$meta['name']] = $type = Helpers::detectType($meta['native_type']);
				if ($type === IReflection::FIELD_TIME) {
					$types[$meta['name']] = IReflection::FIELD_TIME_INTERVAL;
				}
			}
		}
		return $types;
	}

	/**
	 * @return bool
	 */
	public function isSupported($item) {
		// MULTI_COLUMN_AS_OR_COND due to mysql bugs:
		// - http://bugs.mysql.com/bug.php?id=31188
		// - http://bugs.mysql.com/bug.php?id=35819
		// and more.
		return $item === self::SUPPORT_SELECT_UNGROUPED_COLUMNS || $item === self::SUPPORT_MULTI_COLUMN_AS_OR_COND;
	}

}
