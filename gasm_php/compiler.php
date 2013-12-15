#!/usr/bin/php
<?php
require('Gasm.php');

if (empty($argv[1])) {
    die ("PLEASE SPECIFY A FILE\n");
}

$file = $argv[1];
$outfile = !empty($argv[2]) ? $argv[2] : 'a.out';
$handle = @fopen($file, 'r');

if (empty($handle)) {
    die ("COULD NOT OPEN FILE {$file}\n");
}

$gasm = new Gasm();
$gasm->load($handle);
fclose($handle);

$out = "#!/usr/bin/php\n";
$out .= file_get_contents(__DIR__.'/Gasm.php');
$out .= ';$gasm = unserialize(\''.str_replace("'", "\'", serialize($gasm)).'\');';
$out .= '$gasm->execute();';

file_put_contents($outfile, $out);
file_put_contents($outfile, php_strip_whitespace($outfile));

