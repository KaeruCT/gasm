#!/usr/bin/php
<?php
function line_number() {
    global $pcounter, $code_start;
    return 1 + $code_start + $pcounter;
}

function strip_comments($line) {
    return trim(preg_replace('%;(.*)$%m', '', $line));
}

function is_label ($line) {
    return substr($line, -1) === ':';
}

function parse_line($line) {
    return array_map('trim', preg_split('%\s%', $line, 2));
}

function parse_args($args) {
    return array_map('trim', preg_split('%,%', $args));
}

function eval_expression($exp) {
    global $vars;
    $matches = [];
    $ops = [
        '+' => function ($a, $b) {return $a + $b;},
        '-' => function ($a, $b) {return $a - $b;},
        '*' => function ($a, $b) {return $a * $b;},
        '/' => function ($a, $b) {return $a / $b;},
        '%' => function ($a, $b) {return $a % $b;},
    ];

    // match a simple infix notation (ie: 4+2, n*8, 2/a)
    $ifx = '/(\w+)\s*(['.preg_quote(implode('', array_keys($ops)), '/').'])\s*(\w+)/';

    if (preg_match('%^".*"$%', $exp)) {
        $val = substr($exp, 1, -1); // remove "" and store as string
        $val = str_replace(['\n', '\t'], ["\n", "\t"], $val); // allow printing newlines and tabs
    } else if (preg_match($ifx, $exp, $matches)) { // basic math expression
        list($dummy, $a, $op, $b) = $matches;
        if (array_key_exists($a, $vars)) $a = $vars[$a]; // replace of $a exists
        if (array_key_exists($b, $vars)) $b = $vars[$b]; // replace if $b exists
        $val = $ops[$op]((double)$a, (double)$b);
    } else if (array_key_exists($exp, $vars)) {
        $val = $vars[$exp]; // replace value with variable
    } else if (is_numeric($exp)) {
        $val = (double)$exp; // force value to be a number
    } else {
        $val = $exp; // this shouldn't happen, ever...
    }
    return $val;
}

$vars = [];
$labels = [];
$stack = [];
$comparison = false;

$file = empty($argv[1]) ? 'php://stdin' : $argv[1];
$f = @fopen($file, 'r');
$code = [];
$parse_data = true;

for ($i = 0; false !== ($line = fgets($f)); $i += 1) {
    $line = strip_comments($line);
    if ($line === 'CODE') {
        $parse_data = false;
        $code_start = $i + 1;
        continue;
    }
    if ($parse_data) { // set up vars
        if ($line === 'DATA') continue;
        @list($name, $value) = parse_line($line);
        if (!empty($name)) $vars[$name] = eval_expression($value);
    } else { // set up labels
        if (is_label($line)) {
            $lname = substr($line, 0, -1);
            $labels[$lname] = $i - $code_start;
        }
        $code[] = $line;
    }
}
fclose($f);
$code_len = sizeof($code);

function execute_line($line) {
    global $pcounter, $vars, $labels, $stack, $comparison;

    if (is_label($line)) return;
    if ($line = parse_line($line)) @list($op, $args) = $line;
    if (empty($op)) return;

    $op = strtolower($op);
    if (!empty($args)) $args = parse_args($args);

    switch($op) {
    case 'println':
    case 'print':
        // print expression
        $val = eval_expression($args[0]);
        if ($op === 'println') $val .= "\n"; // println shortcut
        echo $val;
        break;

    case 'inc':
        // increment variable
        $vars[$args[0]] += 1;
        break;

    case 'dec':
        // decrement variable
        $vars[$args[0]] -= 1;
        break;

    case 'push':
        // push value into stack
        $stack[] = eval_expression($args[0]);
        break;

    case 'pop':
        $val = array_pop($stack);
        // store value in var if an argument was passed to pop
        if (!empty($args[0])) $vars[$args[0]] = $val;
        break;

    case 'mov':
        // store value in var
        $val = eval_expression($args[0]);
        $vars[$args[1]] = $val;
        break;

    case 'cmp':
        $a = eval_expression($args[0]);
        $b = eval_expression($args[1]);
        // store comparison in comparison var
        $comparison = ($a == $b);
        break;

    case 'jne':
    case 'je':
        $eq = $comparison; // use last comparison made
        if ($op === 'jne') $eq = !$eq; // negate condition if op was jne
        if ($eq) {
            $pcounter = $labels[$args[0]]; // jump to first label
        } else if (!empty($args[1])) {
            $pcounter = $labels[$args[1]]; // jump to second label
        }
        break;

    case 'jmp':
        $pcounter = $labels[$args[0]]; // jump to label
        break;

    case 'nop':
        break;

    default:
        $line = line_number($pcounter);
        die("INSTRUCTION NOT RECOGNIZED: {$op}, at line {$line}\n");
        break;
    }
}

for ($pcounter = 0; $pcounter < $code_len; $pcounter += 1) {
    execute_line($code[$pcounter]);
}
