<?php

//Discovered reflection


require __DIR__ . '/../connect.inc.php';

/* @var $connection Flunorette\Connection */
$df = new Flunorette\DiscoveredReflection($connection);
