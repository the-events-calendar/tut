# `tut extension:create`

**Create a TEC/ET extension.**

## Usage

Unless the `--org` argument is specified, the extension is created on the `mt-support` GitHub Org.

```
# Create a TEC extension.
tut extension:create --name=NAME --base=tec

# Create an ET extension.
tut extension:create --name=NAME --base=et
```

## Args

```
Usage:
  extension:create [options]

Options:
      --name=NAME              The name of the extension.
      --slug[=SLUG]            The slug of the extension.
      --base=BASE              The plugin on which to base this extension on.
      --namespace[=NAMESPACE]  The namespace for the extension.
      --org=ORG                Org in which to create the extension repo.
      --no-create              If provided, prevents running the create action.
  -h, --help                   Display this help message

Help:
  Creates an extension repo on GitHub.
```
