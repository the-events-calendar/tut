# `tut git:file`

**Fetches a file from a repository and echoes out the contents**

## Usage

```
tut git:file --repo=the-events-calendar --org=moderntribe --path=readme.txt --ref=master
```

## Args

```
Usage:
  git:file [options]

Options:
      --repo=REPO       The name of the plugin or repo
      --path=PATH       Path and file to get
      --org=ORG         Org for the repo [default: "moderntribe"]
      --ref=REF         The name of the ref (branch, commit, tag, etc)
  -h, --help            Display this help message

Help:
  Fetches a file from a repository.
```
