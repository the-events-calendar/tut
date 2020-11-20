# TEC Utilities

**T**EC **UT**ilities (or `tut`) are a collection of tools for managing plugins.

```
                              /^\
           L L               /   \               L L
        __/|/|_             /  .  \             _|\|\__
       /_| [_[_\           /     .-\           /_]_] |_\
      /__\  __`-\_____    /    .    \    _____/-`__  /__\
     /___] /=@>  _   {>  /-.         \  <}   _  <@=\ [___\
    /____/     /` `--/  /      .      \  \--` `\     \____\
   /____/  \____/`-._> /               \ <_.-`\____/  \____\
  /____/    /__/      /-._     .   _.-  \      \__\    \____\
 /____/    /__/      /         .         \      \__\    \____\
|____/_  _/__/      /          .          \      \__\_  _\____|
 \__/_ ``_|_/      /      -._  .        _.-\      \_|_`` _\___/
   /__`-`__\      <_         `-;           _>      /__`-`__\
      `-`           `-._       ;       _.-`           `-`
                        `-._   ;   _.-`
                            `-._.-`
```

## Installation

1. Clone the repository `git clone git@github.com:moderntribe/tut.git`
1. Go to the new directory `cd tut`.
1. Run `composer install`
1. Add the cloned repo into your PATH (edit your `.bashrc` / `.bash_profile` / `.zshrc` or whatever)

```
export PATH=/path/to/tut:$PATH
```

## Typical use cases

* [Deploy workflow](docs/deploy-workflow.md)

## Commands

| Command | Description |
|--|--|
| [`analyze`](docs/commands/analyze.md) | Changelog entries for Hooks and Template changes |
| [`build`](docs/commands/build.md) | Mass composer/npm/gulp building |
| [`generate-css-override`](docs/commands/generate-css-override.md) | Finds CSS rules for the purposes of providing overrides. |
| [`get-build-number`](docs/commands/get-build-number.md) | Gets the build number based on recent commit timestamp |
| [`get-hash`](docs/commands/get-hash.md) | Gets current repo hash |
| [`package`](docs/commands/package.md) | Package zips for release |
| [`reset`](docs/commands/reset.md) | Resets repo back to main/master |
| [`sync`](docs/commands/sync.md) | Git branch synchronization |
| [`tbd`](docs/commands/tbd.md) | TBD Locator |
| [`template-list`](docs/commands/template-list.md) | List templates in plugin |
| [`version`](docs/commands/version.md) | Update version numbers |


### `mtreleasedate`: Update the release date in the changelog

This script finds the provided version number in the changelog and updates the release date associated with it.

#### Usage

Using terminal, run:

```
mtreleasedate
```

This will walk you through interactive steps for setting version numbers.

### `mtviews`: Find all view changes since last tagged release

This script prints out a report of all changed views since the last tagged release.

#### Usage

Using terminal, run:

```
mtviews release/4.1
```

### `mtsvndiff`: Compare WP.org zips with packaged zip

This script compares the directory structure and file names of a packaged zip with a WordPress zip.

#### Usage

Using terminal, run:

```
mtsvndiff
```

