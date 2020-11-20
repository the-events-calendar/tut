# `tut changed-views`

**Find all view changes in a branch**

## Usage

```sh
# Get changed views for a given plugin.
tut changed-views events-pro

# Specify the branch on which to look for changed views
tut changed-views events-pro --branch=release/B20.01
```

## Args

```
Usage:
  changed-views [options] [--] <repo>

Arguments:
  repo                   Repo on which to set the release date

Options:
      --branch[=BRANCH]  Branch from which to list views
  -h, --help             Display this help message

Help:
  List out the views that have been changed in the given branch
```
