# `build`

**Mass composer/npm/gulp building**

Goes into each plugin and runs composer, npm, and webpack where applicable. You can execute this from within the root of a plugin or from the `plugins/` directory.

## Usage
```bash
tut build
```

## Args

```
Usage:
  build [options]

Options:
      --dry-run         Whether the command should really execute or not.
  -p, --plugin=PLUGIN   A comma separated list of plugins that will be pushed (multiple values allowed)
  -h, --help            Display this help message

Help:
  This command allows you to run the build processes across all or some plugins in a directory
```
