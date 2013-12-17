#!/usr/bin/env python2

import sys
from gasm import Gasm

if __name__ == "__main__":
    if len(sys.argv) > 1:
        filename = sys.argv[1]
        f = open(filename, 'r')
    else:
        f = sys.stdin

    Gasm().executeFile(f)
