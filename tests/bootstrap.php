<?php

use Flunorette\Bridges\Nette\Diagnostics\ConnectionPanel;
use Nette\Caching\Storages\FileStorage;
use Nette\Diagnostics\Debugger;
use Nette\Diagnostics\IBarPanel;
use Nette\Loaders\RobotLoader;
use Nette\Utils\Finder;

define('EOL', "\n");
error_reporting(E_ALL | E_STRICT);

require_once __DIR__ . '/../Vendor/autoload.php';

date_default_timezone_set('Europe/Prague');

Debugger::$strictMode = TRUE;
Debugger::enable(Debugger::DEVELOPMENT);

$loader = new RobotLoader();
$loader->setCacheStorage(new FileStorage(__DIR__ . '/../temp/cache'));
$loader->addDirectory(__DIR__ . '/../src');
$loader->addDirectory(__DIR__ . '/../vendor/nette');
$loader->register();

class_alias('Tester\Assert', 'Assert');
Tester\Environment::setup();
Tester\Dumper::$maxLength = 10e3;

function vd($param, $title = null) {
	echo "<pre><b>$title</b>\n";
	Debugger::dump($param);
	echo '</pre>';
	return $param;
}

function d($param, $title = null) {
	static $c = array();

	Debugger::barDump($param, ($title ? : '- ') . '[' . @ ++$c[$title] . '] (' . strtoupper(gettype($param)) . ')');
	return $param;
}

function p($param) {
	print_r($param);
	echo "\n";
}

function pr($actual) {
	return trim(print_r($actual, true));
}

function t($name = null) {
	return Debugger::timer($name);
}

function et($name = null) {
	vd((t($name) * 1000) . ' ms', $name);
}

function cleanCache() {
	$files = array();
	foreach (Finder::findFiles('*')->from(__DIR__ . '/temp') as $filename => $fileInfo) { //clean cache
		$files[] = $filename;
	}
	foreach ($files as $f) {
		unlink($f);
	}

	$dirs = array();
	foreach (Finder::findDirectories('*')->from(__DIR__ . '/temp') as $filename => $fileInfo) { //clean cache
		$dirs[] = $filename;
	}
	foreach ($dirs as $d) {
		rmdir($d);
	}
}

function test($closure) {
	$closure();
}


if (php_sapi_name() != 'cli') {
//	header('Content-Type: text/plain');
	$testNumber = @$_GET['n'];
	/**
	 * Causes output in browsers to appear as text/plain without messing with debugger or output itself
	 */
	class FakeStyle implements IBarPanel {

		public function getPanel() {
			return '';
		}

		public function getTab() {
			$str = "<style> * { white-space: pre; font-family: Monospace } </style>";
			$str .= "<style> #nette-debug * { white-space: normal; } </style>";
			$str .= "<style> #nette-debug pre, #nette-debug code { white-space: pre; } </style>";
			return '<span>FakeStyle</span>' . $str;
		}

	}



	Debugger::$bar->addPanel($panel = new ConnectionPanel());
	Debugger::$bar->addPanel(new FakeStyle);
}



$driverName = '';
