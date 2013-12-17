#!/usr/bin/php
<?php
require('Gasm.php');

function error($message) {
    echo "ERROR:\n    {$message}\n";
}

$gasm = new Gasm();
$f = '';
echo "gasm 0.1";

do {
    try {
        $gasm->repl_execute_line($f);
    } catch (Exception $e) {
        error($e->getMessage());
    }
    echo "\n>>> ";
} while ($f = fgets(STDIN));
