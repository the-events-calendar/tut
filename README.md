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

### `mtmaster`: Reset master back to latest upstream

This script ensures there's no stray commits or unstaged changes in master.

#### Usage

Using terminal, run:

```
mtmaster
```

### `mtpush`: Pushes plugin(s) to origin

This script pushes the provided branch to the git origin.

#### Usage

Using terminal, run:

```
mtpush release/122
```

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

## Helpful tips

1. Place the path to tribe-plugin-packger in your path so you can just type `gitdeploy.sh`!
1. Execute this from the `wp-content/plugins` directory where the various plugins this script packages are already checked out! If you do that, `npm` is assumed to have already been run and it executes more quickly.

## Example usage

When it comes time to prepping a release, here are the steps that _I_ go through with those scripts (all of these steps are done from the `/plugins/` directory and assumes you have the tribe-plugin-packager in your `$PATH`:

### 1. Updating plugin codebase

First I make sure I have the latest and greatest.

`tut sync develop`

### 2. Updating the version numbers in the appropriate plugins

This script goes to all the relevant files and updates the version numbers to the values that you specify when prompted.  It then shows you a diff and asks if you want to commit the version changes.

`tut version`

### 3. Merge stuff into master and create the zip files

This script optionally merges a branch into master and then does a version number sanity check before packaging a zip file (which it stores in `/tmp`)

`mtdeploy`

The `tut version` and `mtdeploy` scripts prompt you for the plugin(s) you want to twiddle and package.
