#!/usr/bin/php
<?php
require_once('Gasm.php');
$gasm = new Gasm();

function repl() {
    global $is_repl, $gasm;
    $is_repl = true;
    $f = '';
    echo "gasm 0.1";

    do {
        try {
            $gasm->repl_execute_line($f);
        } catch (Exception $e) {
            echo "ERROR:\n    {$e->getMessage()}\n";
        }
        echo "\n>>> ";
    } while ($f = fgets(STDIN));
}

function interpret ($stream) {
    global $gasm;
    if ($stream === false) {
        error("could not open file");
    }

    $gasm->load($stream);
    try {
        $gasm->execute();
    } catch (Exception $e) {
        die("FATAL ERROR:\n    {$e->getMessage()}\n");
    }
    fclose($stream);
}


if (posix_isatty(STDIN) && !isset($argv[1])) {
    repl();
} else {
    $stream = isset($argv[1]) ? @fopen($argv[1], 'r') : STDIN;
    interpret($stream);
}
