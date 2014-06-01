<?php

namespace Flunorette;

use DateTime;
use DateTimeInterface;
use Flunorette\Drivers\IDriver;
use Flunorette\Selections\ActiveRow;
use Nette\Utils\Strings;
use Traversable;

/**
 * @see usage: https://github.com/nette/nette/blob/v2.1/tests/Nette/Database/SqlPreprocessor.phpt
 */
class SqlPreprocessor {

	/** delimiting is not used */
	const DELIMITE_MODE_NONE = 0;

	/** replaces known delimiters (`"[]) with current driver's */
	const DELIMITE_MODE_REPLACE = 1;

	/** tries delimite undelimited statements (works only when sql keywords are uppercased and table/column names are NOT uppercased) */
	const DELIMITE_MODE_ADD = 2;

	/** default mode combines add and replace modes */
	const DELIMITE_MODE_DEFAULT = 3;

	/** @var Connection */
	protected $connection;

	/** @var int */
	protected $delimiteMode;

	/** @var IDriver */
	protected $driver;

	/** @var array of input parameters */
	protected $params;

	/** @var array of parameters to be processed by PDO */
	protected $remaining;

	/** @var int */
	protected $counter;

	/** @var string values|assoc|multi|select|union */
	protected $arrayMode;

	/** @var array */
	protected $arrayModes;

	public function __construct(Connection $connection, $delimiteMode = null) {
		$this->connection = $connection;
		$this->delimiteMode = null !== $delimiteMode ? $delimiteMode : self::DELIMITE_MODE_DEFAULT;
		$this->driver = $connection->getDriver();
		$this->arrayModes = array(
			'INSERT' => $this->driver->isSupported(IDriver::SUPPORT_MULTI_INSERT_AS_SELECT) ? 'select' : 'values',
			'REPLACE' => 'values',
			'UPDATE' => 'assoc',
			'WHERE' => 'and',
			'HAVING' => 'and',
			'ORDER BY' => 'order',
			'GROUP BY' => 'order',
		);
	}

	/**
	 * @param  array
	 * @return array of [sql, params]
	 */
	public function process($params) {
		$this->params = $params;
		$this->counter = 0;
		$this->remaining = array();
		$this->arrayMode = 'assoc';
		$res = array();

		while ($this->counter < count($params)) {
			$param = $params[$this->counter++];

			if (($this->counter === 2 && count($params) === 2) || !is_scalar($param)) {
				$res[] = $this->formatValue($param);
			} else {
				$res[] = Strings::replace(
						$param, '~\'.*?\'|".*?"|\?|\b(?:INSERT|REPLACE|UPDATE|WHERE|HAVING|ORDER BY|GROUP BY)\b|/\*.*?\*/|--[^\n]*~si', array($this, 'callback')
				);
			}
		}

		return array(trim(implode(' ', $res)), $this->remaining);
	}

	/** @internal */
	public function callback($m) {
		$m = $m[0];
		if ($m[0] === "'" || $m[0] === '"' || $m[0] === '/' || $m[0] === '-') { // string or comment
			return $m;
		} elseif ($m === '?') { // placeholder
			if ($this->counter >= count($this->params)) {
				throw new InvalidArgumentException('There are more placeholders than passed parameters.');
			}
			return $this->formatValue($this->params[$this->counter++]);
		} else { // command
			$this->arrayMode = $this->arrayModes[strtoupper($m)];
			return $m;
		}
	}

	protected function formatValue($value) {
		if (is_string($value)) {
			if (strlen($value) > 20) {
				$this->remaining[] = $value;
				return '?';
			} else {
				return $this->connection->quote($value);
			}
		} elseif (is_int($value)) {
			return (string) $value;
		} elseif (is_float($value)) {
			return rtrim(rtrim(number_format($value, 10, '.', ''), '0'), '.');
		} elseif (is_bool($value)) {
			return $this->driver->formatBool($value);
		} elseif ($value === NULL) {
			return 'NULL';
		} elseif ($value instanceof ActiveRow) {
			return $value->getPrimary();
		} elseif (is_array($value) || $value instanceof Traversable) {
			$vx = $kx = array();

			if ($value instanceof Traversable) {
				$value = iterator_to_array($value);
			}

			reset($value);
			if (isset($value[0])) { // non-associative; value, value, value
				foreach ($value as $v) {
					if (is_array($v) && isset($v[0])) { // no-associative; (value), (value), (value)
						$vx[] = '(' . $this->formatValue($v) . ')';
					} else {
						$vx[] = $this->formatValue($v);
					}
				}
				if ($this->arrayMode === 'union') {
					return implode(' ', $vx);
				}
				return implode(', ', $vx);
			} elseif (Helpers::containsNamedParams($value)) { //named params e.g. ":id"
				foreach ($value as $k => $v) {
					if (is_array($v)) {
						throw new InvalidArgumentException("Named param '$k' contains unsupported array as value.");
					}
				}
				$this->remaining = $this->remaining + $value;
			} elseif ($this->arrayMode === 'values') { // (key, key, ...) VALUES (value, value, ...)
				$this->arrayMode = 'multi';
				foreach ($value as $k => $v) {
					$kx[] = $this->driver->delimite($k);
					$vx[] = $this->formatValue($v);
				}
				return '(' . implode(', ', $kx) . ') VALUES (' . implode(', ', $vx) . ')';
			} elseif ($this->arrayMode === 'select') { // (key, key, ...) SELECT value, value, ...
				$this->arrayMode = 'union';
				foreach ($value as $k => $v) {
					$kx[] = $this->driver->delimite($k);
					$vx[] = $this->formatValue($v);
				}
				return '(' . implode(', ', $kx) . ') SELECT ' . implode(', ', $vx);
			} elseif ($this->arrayMode === 'assoc') { // key=value, key=value, ...
				foreach ($value as $k => $v) {
					if (substr($k, -1) === '=') {
						$k2 = $this->driver->delimite(substr($k, 0, -2));
						$vx[] = $k2 . '=' . $k2 . ' ' . substr($k, -2, 1) . ' ' . $this->formatValue($v);
					} else {
						$vx[] = $this->driver->delimite($k) . '=' . $this->formatValue($v);
					}
				}
				return implode(', ', $vx);
			} elseif ($this->arrayMode === 'multi') { // multiple insert (value, value, ...), ...
				foreach ($value as $v) {
					$vx[] = $this->formatValue($v);
				}
				return '(' . implode(', ', $vx) . ')';
			} elseif ($this->arrayMode === 'union') { // UNION ALL SELECT value, value, ...
				foreach ($value as $v) {
					$vx[] = $this->formatValue($v);
				}
				return 'UNION ALL SELECT ' . implode(', ', $vx);
			} elseif ($this->arrayMode === 'and') { // (key [operator] value) AND ...
				foreach ($value as $k => $v) {
					$k = $this->driver->delimite($k);
					if (is_array($v)) {
						$vx[] = $v ? ($k . ' IN (' . $this->formatValue(array_values($v)) . ')') : '1=0';
					} else {
						$v = $this->formatValue($v);
						$vx[] = $k . ($v === 'NULL' ? ' IS ' : ' = ') . $v;
					}
				}
				return $value ? '(' . implode(') AND (', $vx) . ')' : '1=1';
			} elseif ($this->arrayMode === 'order') { // key, key DESC, ...
				foreach ($value as $k => $v) {
					$vx[] = $this->driver->delimite($k) . ($v > 0 ? '' : ' DESC');
				}
				return implode(', ', $vx);
			}
		} elseif ($value instanceof DateTime || $value instanceof DateTimeInterface) {
			return $this->driver->formatDateTime($value);
		} elseif ($value instanceof SqlLiteral) {
			$this->remaining = array_merge($this->remaining, $value->getParameters());
			return $value->__toString();
		} else {
			$this->remaining[] = $value;
			return '?';
		}
	}

	public function tryDelimite($str, $delimiteMode = null) {
		$delimiteMode = null !== $delimiteMode ? $delimiteMode : $this->delimiteMode;
		if (self::DELIMITE_MODE_NONE == $delimiteMode) {
			return $str;
		}

		$driver = $this->connection->getDriver();
		$delimited = '';
		$splits = preg_split("~('(?:[^\\\\']+|\\\\.)*')~", $str, -1, PREG_SPLIT_DELIM_CAPTURE); //split by single quoted strings
		foreach ($splits as $part) {
			if (!empty($part)) {
				if (strlen($part) <= 1 || "'" != $part[0] || "'" != $part[strlen($part) - 1]) { //ignore parts that are sql string (inside single quotes)
					if ($delimiteMode & self::DELIMITE_MODE_REPLACE) {
						$part = preg_replace_callback('~(["`\[])([a-z_][a-z0-9_]*)(["`\]])~i', function($m) use ($driver) {
							return $driver->delimite($m[2]);
						}, $part);
					}
					if ($delimiteMode & self::DELIMITE_MODE_ADD) {
						$part = preg_replace_callback('~(?<=[^"`:\'\[\w]|^)[a-z_][a-z0-9_]*(?=[^"`\'\]\w]|\z)~i', function($m) use ($driver) {
							return $m[0] == strtoupper($m[0]) ? $m[0] : $driver->delimite($m[0]);
						}, $part);
					}
				}
				$delimited .= $part;
			}
		}
		return $delimited;
	}

	public function quote($str) {
		return $this->connection->getPdo()->quote($str);
	}

	public function getDelimiteMode() {
		return $this->delimiteMode;
	}

	public function setDelimiteMode($delimiteMode) {
		$this->delimiteMode = $delimiteMode;
	}

}
