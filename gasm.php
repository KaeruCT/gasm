#!/usr/bin/php
<?php
    if (empty($argv[1])) {
        die("please specify a file\n");
    }

    function line_number() {
        global $pcounter, $datalen;
        return 1 + $pcounter + $datalen;
    }

    function is_comment($line) {
        return @$line[0] === ';';
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
        $operations = [
            '+' => function ($a, $b) {return $a + $b;},
            '-' => function ($a, $b) {return $a - $b;},
            '*' => function ($a, $b) {return $a * $b;},
            '/' => function ($a, $b) {return $a / $b;},
            '%' => function ($a, $b) {return $a % $b;},
        ];

        // match a simple infix notation (ie: 4+2, n*8, 2/a)
        $exprgxp = '/(\w+)\s*(['.preg_quote(implode('', array_keys($operations)), '/').'])\s*(\w+)/';

        if (preg_match('%^".*"$%', $exp)) {
            // remove "" and store as string
            $val = substr($exp, 1, -1);
        } else if (preg_match($exprgxp, $exp, $matches)) {
            // basic math expression
            list($dummy, $a, $op, $b) = $matches;

            if (array_key_exists($a, $vars)) {
                // replace value if variable named $a exists
                $a = $vars[$a];
            }

            if (array_key_exists($b, $vars)) {
                // replace value if variable named $b exists
                $b = $vars[$b];
            }

            // force values to be numbers
            $val = $operations[$op]((double)$a, (double)$b);
        } else if (array_key_exists($exp, $vars)) {
            // replace value with variable
            $val = $vars[$exp];
        } else if (is_numeric($exp)) {
            // force value to be a number
            $val = (double)$exp;
        }

        return $val;
    }

    $contents = file_get_contents($argv[1]);
    @list($datasec, $codesec) = explode("CODE\n", $contents);

    $data = array_map('trim', explode("\n", $datasec));
    $code = array_map('trim', explode("\n", $codesec));
    $datalen = sizeof($data);
    $codelen = sizeof($code);

    $vars = [];
    $labels = [];
    $stack = [];
    $comparison = false;
    $pcounter = 0;

    // set up vars
    foreach ($data as $dline) {
        if ($dline !== 'DATA' && !is_comment($dline)) {
            @list($name, $value) = parse_line($dline);
            if (!empty($name)) {
                $vars[$name] = eval_expression($value);
            }
        }
    }

    // set up labels
    foreach ($code as $i => $cline) {
        if (is_label($cline)) {
            $lname = substr($cline, 0, -1);
            $labels[$lname] = $i;
        }
    }

    function execute_line($line) {
        global $pcounter, $vars, $labels, $stack, $comparison;

        if (is_label($line) || empty($line)) {
            return;
        }

        @list($op, $args) = parse_line($line);
        $op = strtolower($op);

        if (!empty($args)) {
            $args = parse_args($args);
        }

        switch($op) {
        case 'print':
            $val = eval_expression($args[0]);
            if ($val === '\n') { // allow printing newlines
                $val = "\n";
            }
            echo $val;
            break;

        case 'inc':
            $vars[$args[0]] += 1;
            break;

        case 'dec':
            $vars[$args[0]] -= 1;
            break;

        case 'push':
            $stack[] = eval_expression($args[0]);
            break;

        case 'pop':
            if (!empty($args[0])) {
                $vars[$args[0]] = array_pop($stack);
            } else {
                array_pop($stack);
            }
            break;

        case 'mov':
            $val = eval_expression($args[0]);
            $var = $args[1];

            $vars[$var] = $val;
            break;

        case 'cmp':
            $a = eval_expression($args[0]);
            $b = eval_expression($args[1]);
            $comparison = ($a == $b);
            break;

        case 'jne':
        case 'je':
            $eq = $comparison;

            // negate condition if op was jne
            if ($op === 'jne') {
                $eq = !$eq;
            }

            if ($eq) {
                $pcounter = $labels[$args[0]];
            } else if (!empty($args[1])) {
                $pcounter = $labels[$args[1]];
            }
            break;

        case 'jmp':
            $pcounter = $labels[$args[0]];
            break;

        case 'nop':
            break;

        default:
            $line = line_number($pcounter);
            die("INSTRUCTION NOT RECOGNIZED: {$op}, at line {$line}\n");
            break;
        }
    }

    for (; $pcounter < $codelen; $pcounter += 1) {
        execute_line($code[$pcounter]);
    }
