#!/usr/bin/php
<?php
require('Gasm.php');

function error($message) {
    echo "ERROR:\n    {$message}\n";
}

$gasm = new Gasm();

while ($f = fgets(STDIN)) {
    try {
        $gasm->execute_line($f);
    } catch (Exception $e) {
        error($e->getMessage());
    }
}
