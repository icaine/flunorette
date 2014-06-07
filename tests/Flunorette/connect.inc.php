<?php

use Flunorette\Connection;

require_once __DIR__ . '/../bootstrap.php';

try {
	$options = Tester\Environment::loadData() + array('user' => NULL, 'password' => NULL);
} catch (Exception $e) {
	Tester\Environment::skip($e->getMessage());
}

if (strpos($options['dsn'], 'sqlite::memory:') === FALSE) {
	Tester\Environment::lock($options['dsn'], dirname(TEMP_DIR));
}

try {
	$connection = new Connection($options['dsn'], $options['user'], $options['password'], array('delimiteMode' => 0));
	$connection->setCacheStorage($cacheStorage = new Nette\Caching\Storages\FileStorage(TEMP_DIR));
} catch (PDOException $e) {
	Tester\Environment::skip("Connection to '$options[dsn]' failed. Reason: " . $e->getMessage());
}


global $panel;
if (isset($panel)) {
	$connection->onQuery[] = function ($statement, $params = null) use ($panel) {
		$panel->logQuery($statement, $params);
	};
}

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
