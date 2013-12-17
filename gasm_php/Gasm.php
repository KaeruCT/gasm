<?php
class Gasm {
    private $code = [];
    private $pc = 0;
    private $current_op;
    private $code_start = 0;
    private $code_len = 0;
    private $vars = [];
    private $labels = [];
    private $stack = [];
    private $comparison = [];

    private function line_number() {
        return 1 + $this->code_start + $this->pc;
    }

    private function error($message) {
        throw new Exception("{$message}, at line {$this->line_number()}");
    }

    private function arg_error($len) {
        $s = $len === 1 ? '' : 's';
        $this->error("instruction '{$this->current_op}' needs {$len} arg{$s}");
    }

    private function var_exists($name) {
        return array_key_exists($name, $this->vars);
    }

    private function set_var($name, $val) {
        if (!preg_match('%[A-Za-z]+\w*%', $name)) {
            $this->error("{$name} is not a valid var name");
        }
        $this->vars[$name] = $val;
    }

    private function get_var($name) {
        if (!$this->var_exists($name)) {
            $this->error("var '{$name}' does not exist");
        }
        return $this->vars[$name];
    }

    private function get_label($name) {
        if (!array_key_exists($name, $this->labels)) {
            $this->error("no such label: '{$name}'");
        }
        return $this->labels[$name];
    }

    private function strip_comments($line) {
        return trim(preg_replace('%;(.*)$%m', '', $line));
    }

    private function is_label($line) {
        return substr($line, -1) === ':';
    }

    private function split_line($line) {
        return array_map('trim', preg_split('%\s%', $line, 2));
    }

    private function parse_line($line) {
        @list($op, $args) = $this->split_line($line);
        return [$op, $this->parse_args($args)];
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
            '\t' => "\t",
        ];

        // match a simple infix notation (ie: 4+2, n*8, 2/a, 1+0.5)
        $w = '[\w\.]';
        $ifx = '/^('.$w.'+)\s*(['.preg_quote(implode('', array_keys($ops)), '/').'])\s*('.$w.'+)$/';

        if (preg_match('%^".*"$%', $exp)) {
            $val = str_replace(array_keys($literals), $literals, substr($exp, 1, -1)); // replace literals in strings
        } else if (preg_match($ifx, $exp, $matches)) { // basic math expression
            list($_, $a, $op, $b) = $matches;
            if ($this->var_exists($a)) $a = $this->get_var($a); // replace if $a exists
            if ($this->var_exists($b)) $b = $this->get_var($b); // replace if $b exists
            $val = $ops[$op]((double)$a, (double)$b);
        } else if ($this->var_exists($exp)) {
            $val = $this->get_var($exp); // replace value with variable
        } else if (is_numeric($exp)) {
            $val = (double)$exp; // force value to be a number
        } else {
            $this->error("could not evaluate expression '{$exp}'");
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
                @list($name, $exp) = $this->split_line($line);
                if (!empty($name)) $this->set_var($name, $this->eval_expression($exp));
            } else { // set up labels
                if ($this->is_label($line)) {
                    $lname = substr($line, 0, -1);
                    $this->labels[$lname] = $i - $this->code_start;
                }
                $this->code[] = $line;
            }
        }
        $this->code_len = count($this->code);
    }

    private function execute_line($line) {
        if ($this->is_label($line)) return;
        @list($op, $args) = $this->parse_line($line);
        if (empty($op)) return;

        $this->current_op = $op = strtolower($op);

        switch($op) {
        case 'println':
            // allow println to be called without arguments
            if (empty($args)) $args[0] = '';
        case 'print':
            if (empty($args)) $this->arg_error(1);
            // print expression
            $val = $this->eval_expression($args[0]);
            if ($op === 'println') $val .= "\n"; // println shortcut
            echo $val;
            break;

        case 'inc':
            if (empty($args)) $this->arg_error(1);
            // increment variable
            $this->set_var($args[0], $this->get_var($args[0]) + 1);
            break;

        case 'dec':
            if (empty($args)) $this->arg_error(1);
            // decrement variable
            $this->set_var($args[0], $this->get_var($args[0]) - 1);
            break;

        case 'push':
            if (empty($args)) $this->arg_error(1);
            // push value into stack
            $this->stack[] = $this->eval_expression($args[0]);
            break;

        case 'pop':
            $val = array_pop($this->stack);
            // store value in var if an argument was passed to pop
            if (!empty($args[0])) $this->set_var($args[0], $val);
            break;

        case 'mov':
            if (count($args) !== 2) $this->arg_error(2);
            // store value in var
            $this->set_var($args[1], $this->eval_expression($args[0]));
            break;

        case 'cmp':
            if (count($args) !== 2) $this->arg_error(2);
            // store comparison args
            $this->comparison = [
                $this->eval_expression($args[0]),
                $this->eval_expression($args[1])
            ];
            break;

        case 'jne':
        case 'je':
        case 'jg':
        case 'jge':
        case 'jl':
        case 'jle':
            if (empty($args)) $this->arg_error(1);
            if (empty($this->comparison)) {
                $this->error("use cmp before using {$op}");
            }
            list($a, $b) = $this->comparison;
            if ($op === 'jne') $jmp = $a != $b;
            if ($op === 'je') $jmp = $a == $b;
            if ($op === 'jg') $jmp = $a > $b;
            if ($op === 'jge') $jmp = $a >= $b;
            if ($op === 'jl') $jmp = $a < $b;
            if ($op === 'jle') $jmp = $a <= $b;

            if ($jmp) {
                $this->pc = $this->get_label($args[0]); // jump to first label
            } else if (array_key_exists(1, $args)) {
                $this->pc = $this->get_label($args[1]); // jump to second label
            }
            break;

        case 'jmp':
            if (empty($args)) $this->arg_error(1);
            $this->pc = $this->get_label($args[0]); // jump to label
            break;

        case 'nop':
            break;

        default:
            $this->error("instruction not recognized: '{$op}'");
            break;
        }
    }

    public function repl_execute_line($line) {
        $this->execute_line($this->strip_comments($line));
    }

    public function execute() {
        for ($this->pc = 0; $this->pc < $this->code_len; $this->pc += 1) {
            $this->repl_execute_line($this->code[$this->pc]);
        }
    }
}
