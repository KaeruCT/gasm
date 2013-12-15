#!/usr/bin/env python2

import sys
import re

registry = {}
labels = {}
stack = []
cmpReg = False

# math operators
def add(a, b): return a + b
def sub(a, b): return a - b
def mul(a, b): return a * b
def dev(a, b): return a / b
def mod(a, b): return a % b
mathOps = {
    '+': add,
    '-': sub,
    '*': mul,
    '/': dev,
    '%': mod
}

def parseMath(s):
    split = re.split('([\+\-*/%])', s)
    if (len(split) > 2):
        # math expression
        a, op, b = split
        a = getVar(a)
        b = getVar(b)
        return mathOps[op](a, b)
    
    if len(split) == 1:
        v = getVar(split[0])
        return v

def parseLine(s):
    opcode, _, args = s.partition(' ')
    args = [x.strip() for x in args.split(',')]

    return opcode, args
  
# parses a literal into an
# internal string/numeric representation
def parseLiteral(v):
    # string
    if v.startswith('"') and v.endswith('"'):
        v = v.strip('"')
    # num
    elif v.isdigit():
        v = int(v)

    return v

def stripComments(s):
    return s.split(';')[0]

def printVars(*args):
    s = ''
    for v in args:
        s += str(getVar(v)).replace("\\n", "\n")

    sys.stdout.write(s)

def getVar(s):
    if s in registry:
        return registry[s]
    return parseLiteral(s)

def main():
    global stack

    if len(sys.argv) > 1:
        filename = sys.argv[1]
        f = open(filename, 'r')
    else:
        f = sys.stdin

    parseData = False

    i = 0
    idxCode = 0
    lines = f.readlines()
    while i < len(lines):
        line = lines[i].strip()
        line = stripComments(line)

        i += 1

        if len(line) == 0:
            continue

        if line.startswith(';'):
            continue

        if line == "CODE":
            idxCode = i
            parseData = False
            continue
        
        if line == "DATA":
            parseData = True
            continue

        if parseData:
            # parse data section
            k, v = line.split()
            registry[k] = parseLiteral(v)

        if not parseData:
            # parse labels
            if line.endswith(':'):
                labels[line[:-1]] = i

    i = idxCode
    while i < len(lines):
        line = lines[i].strip()
        line = stripComments(line)
        #print stack, i, line, registry
        i += 1

        if len(line) == 0 or line.startswith(';') or line.endswith(':'):
            continue

        opcode, args = parseLine(line)
        if opcode == "inc":
            registry[args[0]] += 1
        elif opcode == "dec":
            registry[args[0]] -= 1
        elif opcode == "mov":
            registry[args[1]] = parseMath(args[0])
        elif opcode == "push":
            val = parseMath(args[0])
            stack.append(val)
        elif opcode == "cmp":
            if getVar(args[0]) == getVar(args[1]):
                cmpReg = True
            else:
                cmpReg = False
        elif opcode == "je":
            if cmpReg:
                i = labels[args[0]]
            elif len(args) > 1:
                i = labels[args[1]]
        elif opcode == "jne":
            if not cmpReg:
                i = labels[args[0]]
            elif len(args) > 1:
                i = labels[args[1]]
        elif opcode == "jmp":
            i = labels[args[0]]
        elif opcode == "pop":
            stack = stack[:-int(args[0])]
        elif opcode == "print":
            printVars(args[0])
        elif opcode == "println":
            printVars(args[0], "\n")
        elif opcode == "nop":
            continue
        else:
            print "INSTRUCTION NOT RECOGNIZED: {0}, at line {1}".format(opcode, i)
            sys.exit(1)

if __name__ == "__main__":
    main()
