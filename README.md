# The Events Calendar Utilities

## Installation

1. Clone the repository `git clone git@github.com:moderntribe/tec-utils.git`
1. Go to the new directory `cd tec-utils`.
1. Run `composer install`
1. Add the cloned repo into your PATH (edit your `.bashrc` / `.bash_profile` / `.zshrc` or whatever)

```
export PATH=/path/to/tec-utils:$PATH
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

## Scripts

### `tut analyze`: Changelog entries for Hooks and Template changes

On the end of the release the Developer responsible for the release will run this command to add the automated changelog entries for our users.

```
$ tut analyze

event-tickets
-------------

 * Tweak - Changed views: `blocks/rsvp`, `blocks/rsvp/content-inactive`, `blocks/rsvp/content`, `blocks/rsvp/form`, `blocks/rsvp/form/form`, `blocks/rsvp/form/submit-login`, `blocks/rsvp/form/submit`, `blocks/rsvp/status`, `blocks/rsvp/status/going-icon`, `blocks/rsvp/status/going`, `blocks/rsvp/status/not-going`, `blocks/tickets`, `blocks/tickets/content-inactive`, `blocks/tickets/content`, `blocks/tickets/item-inactive`, `blocks/tickets/item`, `registration/button-checkout`
```

### `tut build`: Mass composer/npm/gulp building

Goes into each plugin and runs composer, npm, and webpack where applicable. You can execute this from within the root of a plugin or from the `plugins/` directory.

**Examples**
```bash
tut build
```

### `tut generate-css-override`: Finds CSS rules for the purposes of providing overrides.

Looks through CSS files to find rules that contain the provided search value and generates an override rule with the provided replace value.

**Example**

```bash
tut generate-css-override --search='"Helvetica Neue", Helvetica, -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif' --replace="font-family: inherit;"
```

### `tut get-build-number`: Gets the build number based on recent commit timestamp

Grabs the build number based on the timestamp of the most recent commit.

**Examples**
```bash
tut get-build-number
```

### `tut get-hash`: Gets current repo hash

Grabs the current short hash of whatever repo you are currently in.

**Examples**
```bash
tut get-hash
```

### `tut sync`: Mass Git Syncing

If you wish to globally check out a specific branch across all of the
Tribe plugins in a directory.

**Examples**
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

### `tut version`: Updating version numbers

When updating version numbers in one or more plugins, this script simplifies the task by asking a few questions and executing the needed file updates.


### `mtreleasedate`: Update the release date in the changelog

This script finds the provided version number in the changelog and updates the release date associated with it.

#### Usage

Using terminal, run:

```
mtreleasedate
```

This will walk you through interactive steps for setting version numbers.

### `mtdeploy`: Packaging zips for release

This is a simple script designed to help the Modern Tribe team package WordPress premium plugins for deployment.

#### Usage

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
  -V, --version               Display this application version
      --ansi                  Force ANSI output
      --no-ansi               Disable ANSI output
  -n, --no-interaction        Do not ask any interactive question
  -v|vv|vvv, --verbose        Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug
      --clear                 Remove any untracked file from the current branch of the plugin
```

_Note:_ zip files for packaged plugins will be placed in /tmp unless manually overridden via `--output=`

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

### `tut tbd`: TBD locator

This scripts informs you of all the places you need to update TBD references.

#### Usage

Using terminal, run:

```
tut tbd
```

### `tut template-list`: List of templates

This scripts informs you of all the templates and their short descriptions so you can use them for KB purposes.

#### Usage

Using terminal, run:

```
tut template-list
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
