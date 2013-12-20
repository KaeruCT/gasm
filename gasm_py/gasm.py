#!/usr/bin/env python2
import sys
import re

# math operators
mathOps = {
    '+': lambda a, b: a + b,
    '-': lambda a, b: a - b,
    '*': lambda a, b: a * b,
    '/': lambda a, b: a / b,
    '%': lambda a, b: a % b
}

# jump conditionals
jumpOps = {
    'je':  lambda a, b: a == b,
    'jne': lambda a, b: a != b,
    'jg':  lambda a, b: a > b,
    'jge': lambda a, b: a >= b,
    'jl':  lambda a, b: a < b,
    'jle': lambda a, b: a <= b,
}

# regexes
VAR_REX = re.compile("[A-Za-z_]+\w*")
MATH_REX = re.compile("^([\w\.]+)\s*([{0}])\s*([\w\.]+)$".format(str.join('', [re.escape(x) for x in mathOps.keys()])))

class ParseError(Exception):
    def __init__(self, message, line, data=None):
        self.message = message
        self.data = data
        self.line = line

    def __str__(self):
        if self.data is not None:
            return '{0}: {1} at line {2}'.format(self.message, self.data, self.line)
        else:
            return '{0} on line {1}'.format(self.message, self.line)

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
        split = [x.strip() for x in MATH_REX.split(s)]
        if (len(split) > 2):
            # math expression
            a, op, b = split[1:-1]
            a = self.getVar(a)
            b = self.getVar(b)

            return mathOps[op](a, b)

    # cleans up raw code lines into an opcode and arguments
    def parseLine(self, s):
        opcode, _, args = s.strip().partition(' ')
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
                pas
        
        return v

    def isValidVarName(self, s):
        return VAR_REX.match(s) is not None

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
        # special case for internal calls from e.g. parseMath
        if isinstance(s, float): 
            return s

        # match strings first
        if s.startswith('"') and s.endswith('"'):
            return s.strip('"')

        # match math expressions
        if MATH_REX.match(s):
            return self.parseMath(s)

        # finally try and convert to float
        try:
            return float(s)
        except ValueError:
            # wasn't a float, try to porse as a variable
            if s in self.registry:
                return self.registry[s]
            
            raise ParseError("No such variable", self.currentLine, s)

    def setVar(self, k, v):
        if self.isValidVarName(k):
           self.registry[k] = self.getVar(v)
        else:
            raise ParseError("Invalid var name", self.currentLine, k)

    def getLabel(self, s):
        if s in self.labels:
            return self.labels[s]
        else:
            raise ParseError("No such label", self.currentLine, s)

    def executeFile(self, f):
        parseData = False

        codeLine = 0

        self.currentLine = 0
        lines = [x.strip() for x in f.readlines()]
        for self.currentLine in range(0, len(lines)):
            line = self.stripComments(lines[self.currentLine])

            if len(line) == 0:
                continue

            if line.startswith(';'):
                continue

            if line == "CODE":
                self.codeLine = self.currentLine  + 1
                parseData = False
                continue
            
            if line == "DATA":
                parseData = True
                continue

            if parseData:
                # parse data section
                k, v = [x.strip() for x in line.split()]
                self.setVar(k, v)

            if not parseData:
                # parse labels
                if line.endswith(':'):
                    self.labels[line[:-1]] = self.currentLine
       
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
            self.executeLine("mov {0}+1, {0}".format(args[0]))
        elif opcode == "dec":
            self.executeLine("mov {0}-1, {0}".format(args[0]))
        elif opcode == "mov":
            self.setVar(args[1], self.getVar(args[0]))
        elif opcode == "push":
            val = self.getVar(args[0])
            self.stack.append(val)
        elif opcode == "cmp":
            self.cmpReg = (self.getVar(args[0]), self.getVar(args[1]))
        elif opcode in jumpOps:
            if self.cmpReg:
                # FIXME: should we enclose this in a try/finally block?
                if jumpOps[opcode](*self.cmpReg):
                    self.currentLine = self.getLabel(args[0])
                elif len(args) > 1:
                    self.currentLine = self.getLabel(args[1])
                self.cmpReg = ()
            else:
                raise ParseError("No comparison made for instruction", self.currentLine, opcode)
        elif opcode == "jmp":
            self.currentLine = self.labels[args[0]]
        elif opcode == "pop":
            self.stack = self.stack[:-int(args[0])]
        elif opcode == "print":
            self.printVars(args[0])
        elif opcode == "println":
            self.printVars(args[0], "\"\n\"")
        elif opcode == "nop":
            return
        else:
            raise ParseError("Instruction not recognized", self.currentLine, opcode)
            sys.exit(1)
