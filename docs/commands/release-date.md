# `tut release-date`

**Updates the release date in the readme.txt changelog**

Changes the release date of a version entry in the changelog.

## Usage

```sh
# Set the release date for a plugin
tut release-date events-pro

# Specify the release date
tut release-date events-pro --release-date=2020-01-01

# Specify the release version
tut release-date events-pro --release-version=1.2

# Specify the branch on which to change the version
tut release-date events-pro --branch=release/B20.01
```

## Args

```
Usage:
  release-date [options] [--] <repo>

Arguments:
  repo                                     Repo on which to set the release date

Options:
      --release-date[=RELEASE-DATE]        Release date of version
      --release-version[=RELEASE-VERSION]  Version you are setting the date on
      --branch[=BRANCH]                    Branch on which to commit the release date
  -h, --help                               Display this help message

Help:
  Set the release date for a specific version
```
