<?php

use Flunorette\Connection;
use Flunorette\Helpers;
use Nette\Caching\Cache;
use Nette\Caching\Storages\FileStorage;

require_once __DIR__ . '/bootstrap.php';

$connection = new Connection('mysql:dbname=flunorette_blog;host=127.0.0.1', 'root', null, array('delimiteMode' => 0));
$connection->setCacheStorage($cacheStorage = new FileStorage(__DIR__ . '/temp'));
$connection->getCache()->clean(array(Cache::ALL => true));

//Helpers::loadFromFile($connection, __DIR__ . '/flunorette_blog.sql');

global $panel;

if (isset($panel)) {
	$connection->onQuery[] = function ($statement, $params = null) use ($panel) {
		$panel->logQuery($statement, $params);
	};
}


global $driverName;
$driverName = $connection->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME);


if (!function_exists('reformat')) {

	function reformat($s) {
		global $driverName;
		if (is_array($s)) {
			if (isset($s[$driverName])) {
				return $s[$driverName];
			}
			$s = $s[0];
		}
		if ($driverName === 'mysql') {
			return strtr($s, '[]', '``');
		} elseif ($driverName === 'pgsql') {
			return strtr($s, '[]', '""');
		} elseif ($driverName === 'sqlsrv' || $driverName === 'sqlite' || $driverName === 'sqlite2') {
			return $s;
		} else {
			trigger_error("Unsupported driver $driverName", E_USER_WARNING);
		}
	}

}

