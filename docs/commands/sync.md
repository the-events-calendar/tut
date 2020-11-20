# `tut sync`

**Git branch synchronization**

If you wish to globally check out a specific branch across all of the Tribe plugins in a directory.

## Usage

```sh
# Pulls Current Release
tut sync master

# Pulls Maintenance Release
tut sync release/B20.01

tut sync release/B20.01 -p tec,pro

tut sync release/B20.01 --plugin tec --plugin pro

// Pushes Current Release
tut sync release/B20.01 -d up
```

## Args

```
Usage:
  sync [options] [--] <branch>

Arguments:
  branch                     Which branch will be Syncd

Options:
      --dry-run              Whether the command should really execute or not.
  -p, --plugin=PLUGIN        A comma separated list of plugins that will be pushed (multiple values allowed)
  -d, --direction=DIRECTION  In which direction we should sync the plugins [default: "down"]
  -h, --help                 Display this help message

Help:
  This command allows you to sync all or some plugins to GitHub
```
