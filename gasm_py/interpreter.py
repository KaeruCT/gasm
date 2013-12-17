#!/usr/bin/env python2

import sys
from gasm import Gasm

if __name__ == "__main__":
    if len(sys.argv) > 1:
        filename = sys.argv[1]
        f = open(filename, 'r')
        Gasm().executeFile(f)
    
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
            except Exception:
                pass
                #potato 
