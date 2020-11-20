# `tut extension:set-dependency`

**Modifies or adds a dependency to an extension repo on GitHub**

## Usage

```
# Set a dependency of TEC on a repo.
tut extension:set-dependency --repo=REPO --dependency=tec --ver=5.0
```

## Args

```
Usage:
  extension:set-dependency [options]

Options:
      --repo=REPO              Extension slug name of the .
      --dependency=DEPENDENCY  Which dependency we are adding.
      --ver=VER                To which version we are setting the dependency to.
      --org=ORG                Org in which to look for the extension.
  -h, --help                   Display this help message

Help:
  Modifies or adds a dependency to an extension repo on GitHub.
```
