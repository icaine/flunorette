<?php

require './bootstrap.php';

$useCache = TRUE;
$flunorette = 1;
$panel = false;

date_default_timezone_set('Europe/Prague');

//Flunorette\NetteDatabaseReplacer::replace();

$cacheStorage = new Nette\Caching\Storages\FileStorage(__DIR__ . '/temp');

if ($flunorette) {
	$connection = new \Flunorette\Connection('mysql:dbname=employees', 'root', '');
	$connection->setCacheStorage($useCache ? $cacheStorage : NULL);
	$connection->setDatabaseReflection(new Flunorette\DiscoveredReflection($connection));
	Nette\Diagnostics\Debugger::$bar->addPanel($panel = new Flunorette\Bridges\Nette\Diagnostics\ConnectionPanel);
} else {
	$connection = new Nette\Database\Connection('mysql:dbname=employees', 'root', '');
	$connection->setCacheStorage($useCache ? $cacheStorage : NULL);
	//$connection->setDatabaseReflection(new \Nette\Database\Reflection\DiscoveredReflection($cacheStorage));
	Nette\Diagnostics\Debugger::$bar->addPanel($panel = new Nette\Database\Diagnostics\ConnectionPanel());
}

if ($panel) {
	$connection->onQuery[] = function ($result, $params) use ($panel) {
		$panel->logQuery($result, $params);
	};
}


$dao = $connection;


$time = -microtime(TRUE);
ob_start();

foreach ($dao->table('employees')->limit(500) as $employe) {
	echo "$employe->first_name $employe->last_name ($employe->emp_no)\n";
	echo "Salaries:\n";
	foreach ($employe->related('salaries') as $salary) {
		echo $salary->salary, "\n";
	}
	echo "Departments:\n";
	foreach ($employe->related('dept_emp') as $department) {
		echo $department->dept->dept_name, "\n";
	}
}


//ob_end_flush();
ob_end_clean();

echo 'Time: ', sprintf('%0.3f', $time + microtime(TRUE)), ' s | ',
 'Memory: ', (memory_get_peak_usage() >> 20), ' MB | ',
 'PHP: ', PHP_VERSION, ' | ',
 'Nette: ', Nette\Framework::VERSION;
