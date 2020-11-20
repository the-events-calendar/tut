# `tut reset`

**Resets repo back to main/master branch**

This script ensures the repository is on the main branch and optionally stashes or resets changes.

## Usage

Using terminal, run:

```
# Reset repo to master and do nothing with changes.
tut reset

# Specify a different branch other than master.
tut reset --branch=main

# Stash changes.
tut reset --stash

# Toss out changes.
tut reset --hard
```

## Args 

```
Usage:
  reset [options]

Options:
      --dry-run          Whether the command should really execute or not.
  -p, --plugin=PLUGIN    A comma separated list of plugins that will be pushed (multiple values allowed)
      --hard             Perform a hard reset (recursively)
      --stash            Perform a stash (recursively)
      --branch[=BRANCH]  Perform a hard reset (recursively) [default: "master"]
  -h, --help             Display this help message

Help:
  Change repository branch back to main/master
```
