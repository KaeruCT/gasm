#!/usr/bin/php
<?php
require_once('Gasm.php');

if (empty($argv[1])) {
    die ("please specify a file\n");
}

$file = $argv[1];
$outfile = !empty($argv[2]) ? $argv[2] : 'a.out';
$handle = @fopen($file, 'r');

if (empty($handle)) {
    error("FATAL ERROR:\n    could not open file: {$file}\n");
}

$gasm = new Gasm();
$gasm->load($handle);
fclose($handle);

$out = "#!/usr/bin/php\n";
$out .= file_get_contents(__DIR__.'/Gasm.php');
$out .= ';$gasm = unserialize(\''.str_replace("'", "\'", serialize($gasm)).'\');';
$out .= 'try{$gasm->execute();}catch(Exception $e){die("FATAL ERROR:\n    {$e->getMessage()}\n");}';

file_put_contents($outfile, $out);
file_put_contents($outfile, php_strip_whitespace($outfile));

