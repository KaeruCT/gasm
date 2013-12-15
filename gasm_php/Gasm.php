<?php
class Gasm {
    private $code = [];
    private $pc = 0;
    private $code_start = 0;
    private $code_len = 0;
    private $vars = [];
    private $labels = [];
    private $stack = [];
    private $comparison = false;

    private function line_number() {
        return 1 + $this->code_start + $this->pc;
    }

    private function strip_comments($line) {
        return trim(preg_replace('%;(.*)$%m', '', $line));
    }

    private function is_label ($line) {
        return substr($line, -1) === ':';
    }

    private function parse_line($line) {
        return array_map('trim', preg_split('%\s%', $line, 2));
    }

    private function parse_args($args) {
        return array_map('trim', preg_split('%,%', $args));
    }

    private function eval_expression($exp) {
        $matches = [];
        $ops = [
            '+' => function ($a, $b) {return $a + $b;},
            '-' => function ($a, $b) {return $a - $b;},
            '*' => function ($a, $b) {return $a * $b;},
            '/' => function ($a, $b) {return $a / $b;},
            '%' => function ($a, $b) {return $a % $b;},
        ];
        $literals = [
            '\n' => "\n",
            '\t' => "\t"
        ];

        // match a simple infix notation (ie: 4+2, n*8, 2/a)
        $ifx = '/(\w+)\s*(['.preg_quote(implode('', array_keys($ops)), '/').'])\s*(\w+)/';

        if (preg_match('%^".*"$%', $exp)) {
            $val = str_replace(array_keys($literals), $literals, substr($exp, 1, -1)); // replace literals in strings
        } else if (preg_match($ifx, $exp, $matches)) { // basic math expression
            list($_, $a, $op, $b) = $matches;
            if (array_key_exists($a, $this->vars)) $a = $this->vars[$a]; // replace if $a exists
            if (array_key_exists($b, $this->vars)) $b = $this->vars[$b]; // replace if $b exists
            $val = $ops[$op]((double)$a, (double)$b);
        } else if (array_key_exists($exp, $this->vars)) {
            $val = $this->vars[$exp]; // replace value with variable
        } else if (is_numeric($exp)) {
            $val = (double)$exp; // force value to be a number
        } else {
            $val = $exp; // treat unsolved expressions as strings
        }
        return $val;
    }

    public function load($handle) {
        $parse_data = true;

        for ($i = 0; false !== ($line = fgets($handle)); $i += 1) {
            $line = $this->strip_comments($line);
            if ($line === 'CODE') {
                $parse_data = false;
                $this->code_start = $i + 1;
                continue;
            }
            if ($parse_data) { // set up vars
                if ($line === 'DATA') continue;
                @list($name, $value) = $this->parse_line($line);
                if (!empty($name)) $this->vars[$name] = $this->eval_expression($value);
            } else { // set up labels
                if ($this->is_label($line)) {
                    $lname = substr($line, 0, -1);
                    $this->labels[$lname] = $i - $this->code_start;
                }
                $this->code[] = $line;
            }
        }
        $this->code_len = sizeof($this->code);
    }

    public function execute_line($line) {
        if ($this->is_label($line)) return;
        if ($line = $this->parse_line($line)) @list($op, $args) = $line;
        if (empty($op)) return;

        $op = strtolower($op);
        if (!empty($args)) $args = $this->parse_args($args);

        switch($op) {
        case 'println':
        case 'print':
            // print expression
            $val = $this->eval_expression($args[0]);
            if ($op === 'println') $val .= "\n"; // println shortcut
            echo $val;
            break;

        case 'inc':
            // increment variable
            $this->vars[$args[0]] += 1;
            break;

        case 'dec':
            // decrement variable
            $this->vars[$args[0]] -= 1;
            break;

        case 'push':
            // push value into stack
            $this->stack[] = $this->eval_expression($args[0]);
            break;

        case 'pop':
            $val = array_pop($this->stack);
            // store value in var if an argument was passed to pop
            if (!empty($args[0])) $this->vars[$args[0]] = $val;
            break;

        case 'mov':
            // store value in var
            $val = $this->eval_expression($args[0]);
            $this->vars[$args[1]] = $val;
            break;

        case 'cmp':
            $a = $this->eval_expression($args[0]);
            $b = $this->eval_expression($args[1]);
            // store comparison in comparison var
            $this->comparison = ($a == $b);
            break;

        case 'jne':
        case 'je':
            $eq = $this->comparison; // use last comparison made
            if ($op === 'jne') $eq = !$eq; // negate condition if op was jne
            if ($eq) {
                $this->pc = $this->labels[$args[0]]; // jump to first label
            } else if (!empty($args[1])) {
                $this->pc = $this->labels[$args[1]]; // jump to second label
            }
            break;

        case 'jmp':
            $this->pc = $this->labels[$args[0]]; // jump to label
            break;

        case 'nop':
            break;

        default:
            $line = $this->line_number();
            throw new Exception("INSTRUCTION NOT RECOGNIZED: {$op}, at line {$line}\n");
            break;
        }
    }

    public function execute() {
        for ($this->pc = 0; $this->pc < $this->code_len; $this->pc += 1) {
            $this->execute_line($this->code[$this->pc]);
        }
    }
}
