# Overview

This script takes a list of URLs and checks to see if they both run the WooCo and ET Plus plugins.  If so, it pulls the events and prints them conditionally.

It can be adapted to fit any needs.

It runs with a parallel execution of 5 concurrent threads.  At the time of writing, 1200 URLs processed in 14 minutes.

# Prerequisites

1. a system with python3
2. a python3 virtual environment:

On Debian/Ubuntu we can simply `apt install python3` and then:

```
$ python3 -m venv et-plus-crit-issue
$ cd et-plus-crit-issue
$ . bin/activate
$ cp ~/path/to/tec-utils/scripts/et-plus-scanner/* .
$ pip install -r requirements.txt
```

Then you'll need to create a text file called urls.txt with one URL per line for the base site, i.e http://example.com/

# Executing

Once the URL file is ready, simply run like so:

```
$ python check.py urls.txt > check.csv 2> check.log
```

# Disclaimers

This script was written quick and dirty, please improve it!
