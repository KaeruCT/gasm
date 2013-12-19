#!/usr/bin/env python2

import sys
from gasm import Gasm, ParseError

def die(msg):
    print msg
    sys.exit(1)

if __name__ == "__main__":
    if len(sys.argv) > 1:
        filename = sys.argv[1]
        f = open(filename, 'r')

        try:
            Gasm().executeFile(f)
        except ParseError as e:
            die(e)
    
    elif not sys.stdin.isatty():
        print "piped"

    else:
        # no file to process and nothing being piped in, do repl
        g = Gasm()
        while (True):
            sys.stdout.write('>>> ')
            line = sys.stdin.readline()

            if line == '':
                break

            try:
                g.executeLine(line)
            except ParseError as e:
                print e
