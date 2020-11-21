# `tut submodule-sync`

**Synchronizes submodules between repositories**

This command leverages the submodules and plugins defined in `tut.json` to synchronize across repos, ensuring the latest
hash is committed for branches that exist in both the submodules and the repos that have submodules.

*Note: `tut submodule-sync` does not synchronize `master` or `main` branches.*

## Usage

```
# Synchronize all branches
tut submodule-sync

# Synchronize a specific branch
tut submodule-sync --branch=BRANCH
```

## Args

```
Usage:
  submodule-sync

Options:
      --branch=BRANCH   Limit synchronization to a specific branch
  -h, --help            Display this help message

Help:
  This command ensures submodules for feature/release buckets are in sync

```
