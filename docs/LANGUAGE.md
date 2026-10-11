# gasm language reference

This file describes the gasm language. It tells an agent how to write a
correct gasm program. It describes every instruction and the expression rules.

gasm is a toy assembly-like language. A program holds variables, a stack, and
a comparison register. It moves values between variables, does one arithmetic
operation at a time, and jumps between labels. It prints to standard output.

The interpreter is written in PHP, in `gasm_php/`. The same directory has a
compiler.

## Run a program

Run a `.gasm` file with the PHP interpreter:

```sh
php gasm_php/interpreter.php examples/hello_world.gasm
```

Start the interactive REPL:

```sh
php gasm_php/interpreter.php
```

Pipe a program into the PHP interpreter:

```sh
printf 'CODE\nprintln "hi"\n' | php gasm_php/interpreter.php
```

Compile a program to a standalone PHP script:

```sh
php gasm_php/compiler.php examples/hello_world.gasm hello
php hello
```

The compiler bundles the interpreter and the already-parsed program into one
PHP file. The output still needs PHP to run. It does not produce a native
binary.

## Program structure

A program has two optional parts, in this order:

```asm
DATA
; variable declarations

CODE
; instructions and labels
```

The `DATA` line starts the data section. The `CODE` line starts the code
section.

The data section declares variables and their starting values. The code
section holds instructions and labels.

Every program needs a `CODE` line. Without it, the interpreter treats the
whole file as data and runs nothing.

The data section is optional. A program that declares no variables can omit
`DATA` and start with `CODE`.

```asm
CODE
println "hello"
```

### Data section

Each line declares one variable:

```asm
DATA
n    0
name "Ada"
two  1+1
```

The line has the variable name, then whitespace, then a value. The value is an
expression. It can be a number, a string, a math expression, or a variable that
was declared earlier in the data section.

The line splits on the first whitespace. The name is the first token. The rest
of the line is the value. A string value can contain spaces:

```asm
DATA
msg "hello world"
```

Order matters. A data line can only read a variable declared above it:

```asm
DATA
a 5
b a+1    ; ok, a exists
c z      ; error, z does not exist yet
```

### Code section

The code section holds instructions and labels. Blank lines and lines that
start with `;` are ignored. Leading whitespace is ignored. One instruction per
line.

```asm
CODE
    mov 1, x
    println x
```

### Comments

A semicolon `;` starts a comment. The comment runs to the end of the line.

```asm
mov 1, x   ; this sets x to 1
```

The interpreter removes the comment before it parses the line. A semicolon
inside a string also starts a comment. Do not put `;` in a string.

## Syntax rules

### Identifiers

A variable or label name starts with a letter or underscore, then continues
with letters, digits, or underscores. The name cannot start with a digit.

```asm
x        ; valid
_x       ; valid
count2   ; valid
2x       ; invalid, starts with a digit
```

Variables and labels live in separate namespaces. A variable and a label can
share a name. This is legal and sometimes useful:

```asm
DATA
end 100
CODE
cmp n, end
je end
end:
nop
```

Here `end` is both a variable holding `100` and a jump target.

### Case

Instruction names are case-insensitive. The interpreter lowercases them.
Variable and label names are case-sensitive.

### Strings

A string is text inside double quotes:

```asm
print "hello"
```

Two escape sequences work inside strings:

- `\n` becomes a newline.
- `\t` becomes a tab.

```asm
print "line one\nline two"
print "\t"     ; a tab
```

Strings must not contain these characters:

- `;` starts a comment.
- `,` splits arguments.

Both break the line before the string is read. Use short strings without
commas or semicolons.

### Numbers

All numbers are floats. There is no integer type. Write `1` or `1.0`. Both
mean the same value.

A negative number is allowed as a standalone literal:

```asm
mov -1, x
```

A negative number is not allowed inside a math expression. See
[Math expressions](#math-expressions).

Whole numbers print without a decimal point. A value of `5.0` prints as `5`.

### Math expressions

A math expression is one operation between two operands:

```asm
<operand> <operator> <operand>
```

The operators are `+`, `-`, `*`, `/`, and `%`. `%` is modulo.

Each operand is a single token made of letters, digits, underscores, and dots.
An operand is a number literal or a variable name. Whitespace is allowed
around the operator, not inside an operand.

```asm
mov 1 / n, n        ; valid
mov w * h, product  ; valid
mov 1+1, x          ; valid, no spaces inside operands
mov n % 3, rem      ; valid
```

These expressions are not supported:

- No parentheses.
- No nesting. `(1+2)*3` and `1+2*3` do not work.
- No unary minus in an operand. `-1+2` does not work. Write `0-1` or use a
  standalone `mov -1, x` first.
- No strings as operands. `"a"+"b"` does not work.

Division is float division. `1/2` is `0.5`.

Modulo truncates its operands to integers first. Use whole numbers with `%`.

## Values and expressions

Many instructions take an expression. The interpreter resolves an expression
in this order:

1. A string literal, if it starts and ends with `"`.
2. A math expression, if it matches one operator between two operands.
3. A variable name.
4. A number literal.

A value that is not a string, not math, not a variable, and not a number
causes an error.

## Variables

A variable holds one value: a number or a string.

A variable is created when it is first assigned. Assignments happen in the
data section, with `mov`, or with `inc` and `dec` on a variable that already
exists.

The `mov` instruction can create a variable:

```asm
CODE
mov w * h, area    ; creates area
```

`inc` and `dec` cannot create a variable. The variable must already hold a
value:

```asm
DATA
n 0
CODE
inc n              ; ok
inc missing        ; error, missing does not exist
```

Reading a variable that does not exist is an error.

## Labels

A label marks a line as a jump target. A label is a name followed by a colon:

```asm
loop:
    inc n
    jmp loop
```

The label line itself does nothing. Jumping to a label resumes execution on
the line after the label.

A label can be the last line of the program. Jumping to it ends the program.

```asm
CODE
jmp end
println "not printed"
end:
```

## The stack

gasm has a stack of values. Use `push` and `pop`:

```asm
push 5
push x
pop y       ; y gets the top value, 5 is gone
pop         ; drops the next value
```

`push` puts a value on top of the stack. `pop` removes the top value. `pop`
with a variable stores the removed value into that variable. `pop` without a
variable just drops the value.

## The comparison register

`cmp` stores two values in a comparison register. A conditional jump reads
that register and decides where to jump.

```asm
cmp a, b
je equal
```

The register holds the pair until the next `cmp` writes it. A conditional jump
does not clear it, so one `cmp` can feed several jumps. It is still clearest
to write `cmp` before every conditional jump.

## Instruction reference

This table lists every instruction. `expr` means any expression. `label`
means a label name. `var` means a variable name.

| Instruction | Arguments | Effect |
| --- | --- | --- |
| `mov src, dst` | 2 | Store the value of `src` into variable `dst`. Creates `dst` if needed. |
| `inc var` | 1 | Add 1 to `var`. `var` must exist. |
| `dec var` | 1 | Subtract 1 from `var`. `var` must exist. |
| `push expr` | 1 | Put the value of `expr` on top of the stack. |
| `pop [var]` | 0 or 1 | Remove the top value, store it in `var` if given. |
| `cmp a, b` | 2 | Store the pair `(a, b)` in the comparison register. |
| `je label [, else]` | 1 or 2 | Jump to `label` if `a == b`, else to `else` if given. |
| `jne label [, else]` | 1 or 2 | Jump to `label` if `a != b`. |
| `jg label [, else]` | 1 or 2 | Jump to `label` if `a > b`. |
| `jge label [, else]` | 1 or 2 | Jump to `label` if `a >= b`. |
| `jl label [, else]` | 1 or 2 | Jump to `label` if `a < b`. |
| `jle label [, else]` | 1 or 2 | Jump to `label` if `a <= b`. |
| `jmp label` | 1 | Jump to `label`, always. |
| `print expr` | 1 | Print the value of `expr`. No newline. |
| `println [expr]` | 0 or 1 | Print the value of `expr`, then a newline. |
| `nop` | 0 | Do nothing. |

Arguments split on commas. Whitespace inside an argument is allowed around a
math operator.

```asm
mov 1 / n, n     ; two args: "1 / n" and "n"
```

An instruction with the wrong number of arguments is an error. An unknown
instruction is an error.

### mov

`mov` stores a value into a variable. The order is source first, destination
second.

```asm
mov 1, x          ; x is 1
mov x, y          ; y is the value of x
mov x + 1, x      ; x is now x + 1
mov "hi", msg     ; msg is the string "hi"
```

The source is any expression. The destination must be a valid identifier, not
an expression.

`mov` creates the destination variable if it does not exist. This is the only
way to create a variable outside the data section.

### inc and dec

`inc` and `dec` change an existing variable by 1. They need the variable to
exist already.

```asm
DATA
n 0
CODE
inc n    ; n is 1
inc n    ; n is 2
dec n    ; n is 1
```

### print and println

`print` writes a value with no newline. `println` writes a value and then a
newline.

```asm
print "value: "
println n
```

`print` takes exactly one argument. It does not concatenate. To print two
things, use two instructions:

```asm
print a
print " "
print b
```

`println` works with no argument and prints just a newline. Write `println ""`
to print a blank line.

### cmp and conditional jumps

Use `cmp` then a conditional jump to branch.

```asm
cmp n, 10
je done
println "not done"
done:
nop
```

The jump takes one label for the true branch. It takes an optional second
label for the false branch:

```asm
cmp n, 10
je done, continue
```

`je` jumps when equal, `jne` when not equal, `jg` when greater, `jge` when
greater or equal, `jl` when less, `jle` when less or equal.

A conditional jump without a prior `cmp` is an error.

## Control flow patterns

### Loop

A loop is a label plus a conditional jump back to it:

```asm
DATA
i 0
max 5
CODE
loop:
    inc i
    println i
    cmp i, max
    jne loop
```

This prints 1 through 5.

### If and else

`cmp` plus a two-label jump gives if/else:

```asm
cmp n, 0
je even, odd
even:
println "even"
jmp done
odd:
println "odd"
done:
nop
```

### Counter with a limit

The fizzbuzz style keeps a counter and checks it at the end of the loop body:

```asm
DATA
n 0
limit 100
CODE
next:
    inc n
    ; body
    cmp n, limit
    je done, next
done:
nop
```

## Annotated example

This program prints the Fibonacci numbers below 100:

```asm
DATA
a 0
b 1
next 0

CODE
loop:
    mov a + b, next
    mov b, a
    mov next, b
    println next

    cmp next, 100
    jl loop
```

Walk through the loop:

1. `mov a + b, next` computes the next value.
2. `mov b, a` shifts `b` into `a`.
3. `mov next, b` shifts the new value into `b`.
4. `println next` prints it.
5. `cmp next, 100` and `jl loop` repeat while the value is below 100.

See `examples/` for more complete programs: `maze.gasm`, `primes.gasm`,
`perfect_numbers.gasm`, `collatz.gasm`, `gcd.gasm`, `fibonacci.gasm`,
`fizzbuzz.gasm`, `multiplication_table.gasm`, and `etaylor.gasm`.

## Limits and gotchas

- No functions, no arrays, no objects, no user input, no file input.
- No boolean type. Conditions use numeric comparison through `cmp`.
- One math operation per expression. No parentheses and no nesting.
- No negative operands in math expressions.
- Strings cannot contain `,` or `;`.
- The data section cannot read a variable declared below it.
- `inc` and `dec` need the variable to already exist.
- `print` prints one expression only.
- Whole numbers print without a decimal point because all numbers are floats.

## Common errors

| Error | Cause |
| --- | --- |
| `instruction not recognized` | Unknown instruction. |
| `var 'x' does not exist` | Reading or incrementing an undefined variable. |
| `no such label` | Jumping to a label that is not defined. |
| `not a valid var name` | Assigning to a name that starts with a digit. |
| `could not evaluate expression` | The expression is not a string, math, variable, or number. |
| `use cmp before using je` | A conditional jump ran with no prior `cmp`. |
