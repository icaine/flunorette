<?php

// parse command line aruments
$opts = getopt('v');
$verbose = array_key_exists('v', (array) $opts);
$verbose = true;
$error = false;

if (php_sapi_name() != 'cli') {
	header('Content-Type: text/plain');
	$testNumber = @$_GET['n'];
}

$start = microtime(true);

require_once './bootstrap.php';

$dir_iterator = new RecursiveDirectoryIterator(__DIR__);
$iterator = new RecursiveIteratorIterator($dir_iterator, RecursiveIteratorIterator::SELF_FIRST);
$tests = array_values(array_filter(array_map(function (SplFileInfo $file) {
	return $file->getExtension() == 'phpt' ? $file->getPathname() : false;
}, iterator_to_array($iterator))));

natsort($tests);
foreach ($tests as $filename) {
	if ($testNumber && $testNumber != (int) @reset(explode('-', basename($filename)))) {
		continue;
	}

	cleanCache();

	if (preg_match('~[/\\\\]Selections[/\\\\]~', $filename)) {
		//continue;
	} else {
		//continue;
	}

	try {
		test(function () use ($filename, &$error, $verbose) {
			//echo "$filename\n";
			include $filename;
		});
	} catch (Exception $exc) {
		printf("EXCEPTION IN FILE: %s:%s (%s)\n", $filename, $exc->getLine(), $exc->getMessage());
		throw $exc;
	}
}

printf("%.3F s, %d KiB\n", microtime(true) - $start, memory_get_peak_usage() / 1024);
if ($error) {
	exit(1);
}
