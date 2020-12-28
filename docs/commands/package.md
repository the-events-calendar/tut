# `tut package`

**Package zips for release**

This is a simple script designed to help the TEC team package WordPress premium plugins for deployment.

## Usage

Using terminal, run:

```
tut package
```

Packages zip files for release.

```
Usage:
  package [options]

Options:
      --dry-run               Whether the command should really execute or not.
  -p, --plugin=PLUGIN         A comma separated list of plugins that will be pushed (multiple values allowed)
  -b, --branch=BRANCH         Branch to be packaged (must exist on GitHub.com)
      --final                 Package the zip without a hash in the filename
      --ignore-view-versions  Ignore problems that arise from view version updates
  -m, --merge[=MERGE]         Branch to merge into the branch being packaged
  -o, --output[=OUTPUT]       Directory to dump the zip files (defaults to current directory)
  -r, --release[=RELEASE]     Version to package
  -h, --help                  Display this help message
  -q, --quiet                 Do not output any message
      --clear                 Remove any untracked file from the current branch of the plugin
```

_Note:_ zip files for packaged plugins will be placed in /tmp unless manually overridden via `--output=`

