#!/usr/bin/php
<?php
require('Gasm.php');

$gasm = new Gasm();

while($f = fgets(STDIN)){
    $gasm->execute_line($f);
}
