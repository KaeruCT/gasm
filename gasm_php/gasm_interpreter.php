#!/usr/bin/php
<?php
require('Gasm.php');

$file = empty($argv[1]) ? STDIN : $argv[1];
$f = @fopen($file, 'r');

if (!$f) {
    die ("COULD NOT OPEN FILE: {$file}\n");
}

$gasm = new Gasm();
$gasm->load($f);
$gasm->execute();
fclose($f);
