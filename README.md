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

## Typical deploy workflow

1. `cd` to your `wp-content/plugins` directory
1. Run `npm install` and `npm update` in each of the plugins you'll be deploying
1. Get the latest branches for all of the plugins that you are going to deploy via `tut sync --branch "branchname"`
1. Make sure the version numbers are set via `mtversion`
1. Make sure the release dates in the changelog are accurate via `mtreleasedate`
1. Locate all TBD references with `mt tbd` and update them with the appropriate date
1. If you made commits for the version number or release date scripts, you'll want to push those changes up to GitHub via `mtpush`
1. Make sure all the relevant `master` branches are nice and clean by running `mtmaster`
1. Build the zip files (which also verifies version numbers) using [the bot](https://inside.tri.be/plugins-packaging-with-the-bot/) (preferred) or using `tut package --branch "branchname" --plugin "event-tickets" --final`
1. Once you've pushed the packaged files to svn, run `mtsvndiff` to verify the svn repo has all of the required files and directories.

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

### `mtdocs`: Generate code docs for tec.com

This script leverages wp parser to generate docs for the tec.com database

#### Usage

Using terminal, run:

```
mtdocs
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

## Credits

This tool leverages the great innovations and work in the following libraries and scripts:

* [gulp](http://gulpjs.com/)
* [WP-Parser](https://github.com/rmccue/WP-Parser)

## Copyright & License

Copyright (C) 2013-2015 Peter Chester of [Modern Tribe, Inc.](http://tri.be) under the [GPL3 license](http://www.gnu.org/licenses/gpl-3.0.txt)
