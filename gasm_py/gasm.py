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
        
        self.currentLine = 0
        self.codeLine = 0


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
            v = self.getVar(v)
            
            # cast whole integers to int so they print neatly 
            if isinstance(v, float) and v.is_integer():
                v = int(v)

            s += str(v).replace("\\n", "\n").replace("\\t", "\t")

        sys.stdout.write(s)

    def getVar(self, s):
        if s in self.registry:
            return self.registry[s]
        return self.parseLiteral(s)

    def executeFile(self, f):
        parseData = False

        codeLine = 0

        i = 0
        lines = [x.strip() for x in f.readlines()]
        for i in range(0, len(lines)):
            line = self.stripComments(lines[i])

            if len(line) == 0:
                continue

            if line.startswith(';'):
                continue

            if line == "CODE":
                self.codeLine = i + 1
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
       
        self.currentLine = self.codeLine
        while self.currentLine < len(lines):
            self.executeLine(lines[self.currentLine])
            self.currentLine += 1

    def executeLine(self, line):
        line = self.stripComments(line)
        #print self.stack, self.currentLine, line, self.registry

        if len(line) == 0 or line.startswith(';') or line.endswith(':'):
            return

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
                self.cmpReg = True
            else:
                self.cmpReg = False
        elif opcode == "je":
            if self.cmpReg:
                self.currentLine = self.labels[args[0]]
            elif len(args) > 1:
                self.currentLine = self.labels[args[1]]
        elif opcode == "jne":
            if not self.cmpReg:
                self.currentLine = self.labels[args[0]]
            elif len(args) > 1:
                self.currentLine = self.labels[args[1]]
        elif opcode == "jmp":
            self.currentLine = self.labels[args[0]]
        elif opcode == "pop":
            self.stack = self.stack[:-int(args[0])]
        elif opcode == "print":
            self.printVars(args[0])
        elif opcode == "println":
            self.printVars(args[0], "\n")
        elif opcode == "nop":
            return
        else:
            print "INSTRUCTION NOT RECOGNIZED: {0}, at line {1}".format(opcode, i)
            sys.exit(1)
