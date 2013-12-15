#!/usr/bin/php
<?php
require('Gasm.php');

$gasm = new Gasm();

while ($f = fgets(STDIN)) {
    try {
        $gasm->execute_line($f);
    } catch (Exception $e) {
        echo $e->getMessage();
    }
}
