#!/usr/bin/env python2
import sys
import re

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

class Gasm(object):
    def __init__(self):
        self.registry = {}
        self.labels = {}
        self.stack = []
        self.cmpReg = False
    # parsing functions

    # parses a math expression, expanding variables
    def parseMath(self, s):
        split = [x.strip() for x in re.split('([\+\-*/%])', s)]
        if (len(split) > 2):
            # math expression
            a, op, b = split
            a = self.getVar(a)
            b = self.getVar(b)

            return mathOps[op](a, b)
        
        if len(split) == 1:
            v = self.getVar(split[0])
            return v

    # cleans up raw code lines into an opcode and arguments
    def parseLine(self, s):
        opcode, _, args = s.partition(' ')
        args = [x.strip() for x in args.split(',')]

        return opcode, args
      
    # parses a literal into an
    # internal string/numeric representation
    def parseLiteral(self, v):
        # string
        if v.startswith('"') and v.endswith('"'):
            v = v.strip('"')
        # num
        else:
            try:
                v = float(v)
            except ValueError:
                # TODO: handle error
                pass
        
        return v

    def isComment(self, s):
        return s.startswith(';')

    def stripComments(self, s):
        return s.split(';')[0]

    def printVars(self, *args):
        s = ''
        for v in args:
            s += str(self.getVar(v)).replace("\\n", "\n").replace("\\t", "\t")

        sys.stdout.write(s)

    def getVar(self, s):
        if s in self.registry:
            return self.registry[s]
        return self.parseLiteral(s)

    def executeFile(self, f):
        parseData = False

        i = 0
        idxCode = 0
        lines = [x.strip() for x in f.readlines()]
        while i < len(lines):
            line = self.stripComments(lines[i])

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
                k, v = [x.strip() for x in line.split()]
                self.registry[k] = self.parseLiteral(v)

            if not parseData:
                # parse labels
                if line.endswith(':'):
                    self.labels[line[:-1]] = i

        i = idxCode
        while i < len(lines):
            line = self.stripComments(lines[i])
            #print self.stack, i, line, self.registry
            i += 1

            if len(line) == 0 or line.startswith(';') or line.endswith(':'):
                continue

            opcode, args = self.parseLine(line)
            if opcode == "inc":
                self.registry[args[0]] += 1
            elif opcode == "dec":
                self.registry[args[0]] -= 1
            elif opcode == "mov":
                self.registry[args[1]] = self.parseMath(args[0])
            elif opcode == "push":
                val = self.parseMath(args[0])
                self.stack.append(val)
            elif opcode == "cmp":
                if self.parseMath(args[0]) == self.parseMath(args[1]):
                    cmpReg = True
                else:
                    cmpReg = False
            elif opcode == "je":
                if cmpReg:
                    i = self.labels[args[0]]
                elif len(args) > 1:
                    i = self.labels[args[1]]
            elif opcode == "jne":
                if not cmpReg:
                    i = self.labels[args[0]]
                elif len(args) > 1:
                    i = self.labels[args[1]]
            elif opcode == "jmp":
                i = self.labels[args[0]]
            elif opcode == "pop":
                self.stack = self.stack[:-int(args[0])]
            elif opcode == "print":
                self.printVars(args[0])
            elif opcode == "println":
                self.printVars(args[0], "\n")
            elif opcode == "nop":
                continue
            else:
                print "INSTRUCTION NOT RECOGNIZED: {0}, at line {1}".format(opcode, i)
                sys.exit(1)
