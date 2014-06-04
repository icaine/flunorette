<?php

define('EOL', "\n");

date_default_timezone_set('Europe/Prague');
iconv_set_encoding('internal_encoding', 'UTF-8');
extension_loaded('mbstring') && mb_internal_encoding('UTF-8');
umask(0007);

if (@!include __DIR__ . '/../vendor/autoload.php') {
	echo 'Install dependencies using `composer update --dev`';
	exit(1);
}

Tester\Environment::setup();
Tester\Dumper::$maxLength = 10e3;

// create temporary directory
define('TEMP_DIR', __DIR__ . '/temp/' . getmypid());
@mkdir(dirname(TEMP_DIR)); // @ - directory may already exist
Tester\Helpers::purge(TEMP_DIR);

class_alias('Tester\Assert', 'Assert');

function test($closure) {
	$closure();
}

include_once __DIR__ . '/browser-dev.php';