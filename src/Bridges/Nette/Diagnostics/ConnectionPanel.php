<?php

namespace Flunorette\Bridges\Nette\Diagnostics;

use Flunorette\Connection;
use Flunorette\Helpers;
use Flunorette\Statement;
use Nette;
use PDOException;

/**
 * Debug panel for Flunorette.
 */
class ConnectionPanel extends Nette\Object implements Nette\Diagnostics\IBarPanel {

	/** @var int maximum SQL length */
	static public $maxLength = 1000;

	/** @var int */
	public $maxQueries = 100;

	/** @var int logged time */
	private $totalTime = 0;

	/** @var int */
	private $count = 0;

	/** @var array */
	private $queries = array();

	/** @var string */
	public $name;

	/** @var bool|string explain queries? */
	public $explain = TRUE;

	/** @var bool */
	public $disabled = FALSE;

	public function __construct(Connection $connection = null) {
		if ($connection) {
			$connection->onQuery[] = array($this, 'logQuery');
		}
	}

	public function logQuery(Statement $result, array $params = NULL) {
		if ($this->disabled) {
			return;
		}

		$source = NULL;
		foreach (debug_backtrace(FALSE) as $row) {
			if (isset($row['file']) && is_file($row['file']) && strpos($row['file'], NETTE_DIR . DIRECTORY_SEPARATOR) !== 0) {
				if (isset($row['function']) && strpos($row['function'], 'call_user_func') === 0) {
					continue;
				}
				if (isset($row['class']) && (
					is_a($row['class'], 'Flunorette\\Connection', true) ||
					is_a($row['class'], 'Flunorette\\Selection', true) ||
					is_a($row['class'], 'PDOStatement', true) ||
					$row['class'] == 'Nette\\Object'
					)) {
					continue;
				}
				$source = array($row['file'], (int) $row['line']);
				break;
			}
		}

		$this->count++;
		$this->totalTime += $result->getTime();
		if ($this->count < $this->maxQueries) {
			$this->queries[] = array($result->queryString, $params, $result->getTime(), $result->rowCount(), $result->getConnection(), $source);
		}
	}

	public static function renderException($e) {
		if (!$e instanceof PDOException) {
			return;
		}
		if (isset($e->queryString)) {
			$sql = $e->queryString;
		} elseif ($item = Nette\Diagnostics\Helpers::findTrace($e->getTrace(), 'PDO::prepare')) {
			$sql = $item['args'][0];
		}
		return isset($sql) ? array(
			'tab' => 'SQL',
			'panel' => Helpers::dumpSql($sql),
			) : NULL;
	}

	public function getTab() {
		return '<span title="Flunorette ' . htmlSpecialChars($this->name) . '">'
			. '<img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAQAAAC1+jfqAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAEYSURBVBgZBcHPio5hGAfg6/2+R980k6wmJgsJ5U/ZOAqbSc2GnXOwUg7BESgLUeIQ1GSjLFnMwsKGGg1qxJRmPM97/1zXFAAAAEADdlfZzr26miup2svnelq7d2aYgt3rebl585wN6+K3I1/9fJe7O/uIePP2SypJkiRJ0vMhr55FLCA3zgIAOK9uQ4MS361ZOSX+OrTvkgINSjS/HIvhjxNNFGgQsbSmabohKDNoUGLohsls6BaiQIMSs2FYmnXdUsygQYmumy3Nhi6igwalDEOJEjPKP7CA2aFNK8Bkyy3fdNCg7r9/fW3jgpVJbDmy5+PB2IYp4MXFelQ7izPrhkPHB+P5/PjhD5gCgCenx+VR/dODEwD+A3T7nqbxwf1HAAAAAElFTkSuQmCC" />'
			. $this->count . ' ' . ($this->count === 1 ? 'query' : 'queries')
			. ($this->totalTime ? ' / ' . sprintf('%0.1f', $this->totalTime * 1000) . 'ms' : '')
			. '</span>';
	}

	public function getPanel() {
		$this->disabled = TRUE;
		$s = '';
		$h = 'htmlSpecialChars';

		$counts = array();

		$transactionTime = null;

		foreach ($this->queries as $i => $query) {
			list($sql, $params, $time, $rows, $connection, $source) = $query;

			$command = '';
			if (preg_match('#^\s*(\w+)#', $sql, $m)) {
				$command = strtoupper($m[1]);
				@$counts[$command] ++;
			}

			if ($sql == 'TRANSACTION BEGIN') {
				$transactionTime = 0;
			} elseif (preg_match('#TRANSACTION (COMMIT|ROLLBACK)#', $sql)) {
				$time = $transactionTime;
				$transactionTime = null;
			} elseif ($transactionTime !== null) {
				$transactionTime += $time;
			}

			foreach ((array) $params as $param) {
				if (!is_numeric($param))
					$param = str_replace("'", "\'", $param);
				$sql = preg_replace('#\?#', "'$param'", $sql, 1);
			}

			$explain = NULL; // EXPLAIN is called here to work SELECT FOUND_ROWS()
			if ($this->explain && preg_match('#\s*\(?\s*SELECT\s#iA', $sql)) {
				try {
					$cmd = is_string($this->explain) ? $this->explain : 'EXPLAIN';
					$explain = $connection->queryArgs("$cmd $sql", $params)->fetchAll();
				} catch (PDOException $e) {

				}
			}

			$s .= "<tr class='nette-DbConnectionPanel-type-$command'><td>" . sprintf('%0.3f', $time * 1000);
			if ($explain) {
				static $counter;
				$counter++;
				$s .= "<br /><a href='#' class='nette-toggle-collapsed' data-ref='#nette-DbConnectionPanel-row-$counter'>explain&nbsp;</a>";
			}

			$s .= '</td><td class="nette-DbConnectionPanel-sql">' . Helpers::dumpSql(self::$maxLength ? Nette\Utils\Strings::truncate($sql, self::$maxLength) : $sql);
			if ($explain) {
				$s .= "<table id='nette-DbConnectionPanel-row-$counter' class='nette-collapsed'><tr>";
				foreach ($explain[0] as $col => $foo) {
					$s .= "<th>{$h($col)}</th>";
				}
				$s .= "</tr>";
				foreach ($explain as $row) {
					$s .= "<tr>";
					foreach ($row as $col) {
						$s .= "<td>{$h($col)}</td>";
					}
					$s .= "</tr>";
				}
				$s .= "</table>";
			}
			if ($source) {
				$s .= Nette\Diagnostics\Helpers::editorLink($source[0], $source[1])->class('nette-DbConnectionPanel-source');
			}

			$s .= '</td>';
			$s .= '<td>' . $rows . '</td></tr>';
		}

		$c = '';
		foreach ($counts as $command => $count) {
			$c .= "<tr><th>$command</th><td>$count</td><td><input type='checkbox' class='nette-DbConnectionPanel-type-switcher' name='$command' id='nette-DbConnectionPanel-type-$command' checked='checked' /></td></tr>";
		}



		return empty($this->queries) ? '' :
			'<style> #nette-debug td.nette-DbConnectionPanel-sql { background: white !important }
			#nette-debug .nette-DbConnectionPanel-source { color: #BBB !important } </style>
			<script type="text/javascript">
				(function() {
					var $ = Nette.Q ? Nette.Q.factory : Nette.Query.factory;
					$(".nette-DbConnectionPanel-type-switcher").bind("click", function(e) {
						var name = this.name;
						if (this.checked) {
							$(".nette-DbConnectionPanel-type-" + name).show();
						} else {
							$(".nette-DbConnectionPanel-type-" + name).hide();
						}
					});
				})();
			</script>
			<h1>Queries: ' . count($this->queries) . ($this->totalTime ? ', time: ' . sprintf('%0.3f', $this->totalTime * 1000) . ' ms' : '') . '</h1>
			<div class="nette-inner nette-DbConnectionPanel">
			<table>
				<tr><th>Type</th><th>Count</th><th>Filter</th></tr>' . $c . '
			</table>
			<br />
			<table>
				<tr><th>Time&nbsp;ms</th><th>SQL Statement</th><th>Rows</th></tr>' . $s . '
			</table>
			</div>';
	}

	static public function createDebugPanel($connection, $explain = TRUE, $name = NULL) {
		$panel = new static($connection);
		$panel->explain = $explain;
		$panel->name = $name;
		Nette\Diagnostics\Debugger::getBar()->addPanel($panel);
		return $panel;
	}

}
