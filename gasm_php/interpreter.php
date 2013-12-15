#!/usr/bin/php
<?php
require('Gasm.php');

$file = empty($argv[1]) ? 'php://stdin' : $argv[1];
$f = @fopen($file, 'r');

function error($message) {
    die("FATAL ERROR:\n    {$message}\n");
}

if (!$f) {
    error("could not open file: {$file}");
}

$gasm = new Gasm();
$gasm->load($f);
try {
    $gasm->execute();
} catch (Exception $e) {
    error($e->getMessage());
}
fclose($f);
