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

