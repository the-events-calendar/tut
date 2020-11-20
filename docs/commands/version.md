### `tut version`

**Updating version numbers**

When updating version numbers in one or more plugins, this script simplifies the task by asking a few questions and executing the needed file updates.

## Usage

```sh
tut version
```

## Args

```
Usage:
  version [options] [--] [<version>] [<branch>]

Arguments:
  version               The version that's being prepared. [default: false]
  branch                The branch in which the version is being prepared. [default: false]

Options:
      --dry-run         Whether the command should really execute or not.
  -p, --plugin=PLUGIN   A comma separated list of plugins that will be pushed (multiple values allowed)
  -h, --help            Display this help message

Help:
  This command allows you to set and check the plugin versions and requirements in the relevant files.
```
