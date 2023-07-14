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

1. Clone the repository `git clone git@github.com:the-events-calendar/tut.git`
1. Go to the new directory `cd tut`.
2. Copy `.env.sample` to `.env`. (you don't have to fill in the values for most commands to work)
3. Run `composer install`
4. Add the cloned repo into your PATH (edit your `.bashrc` / `.bash_profile` / `.zshrc` or whatever)

```
export PATH=/path/to/tut:$PATH
```

## Typical use cases

* [Deploy workflow](docs/deploy-workflow.md)

## Top-level Commands

| Command | Description |
|--|--|
| [`analyze`](docs/commands/analyze.md) | Changelog entries for Hooks and Template changes |
| [`build`](docs/commands/build.md) | Mass composer/npm/gulp building |
| [`changed-views`](docs/commands/changed-views.md) | Find all view changes in a branch |
| [`generate-css-override`](docs/commands/generate-css-override.md) | Finds CSS rules for the purposes of providing overrides. |
| [`get-build-number`](docs/commands/get-build-number.md) | Gets the build number based on recent commit timestamp |
| [`get-hash`](docs/commands/get-hash.md) | Gets current repo hash |
| [`get-zip-filename`](docs/commands/get-zip-filename.md) | Gets the expected zip filename for the plugin |
| [`list-templates`](docs/commands/list-templates.md) | List templates in plugin |
| [`package`](docs/commands/package.md) | Package zips for release |
| [`release-date`](docs/commands/release-date.md) | Updates the release date in the readme.txt changelog |
| [`reset`](docs/commands/reset.md) | Resets repo back to main/master |
| [`sync`](docs/commands/sync.md) | Git branch synchronization |
| [`submodule-sync`](docs/commands/submodule-sync.md) | Synchronizes submodules between repositories |
| [`tbd`](docs/commands/tbd.md) | TBD Locator |
| [`upgrade`](docs/commands/upgrade.md) | Upgrades `tut`! |
| [`version`](docs/commands/version.md) | Update version numbers |

## Git Subcommands

| Command | Description |
|--|--|
| [`git:clone`](docs/commands/git/clone.md) | Clone a repository |
| [`git:file`](docs/commands/git/file.md) | Fetch a file from a repository |


## Extension Subcommands

| Command | Description |
|--|--|
| [`extension:create`](docs/commands/extension/create.md) | Create a TEC/ET extension |
| [`extension:set-dependency`](docs/commands/extension/set-dependency.md) | Modifies or adds a dependency to an extension repo on GitHub |

## SVN Subcommands

| Command                               | Description                                  |
|---------------------------------------|----------------------------------------------|
| [`svn:tag`](docs/commands/svn/tag.md) | Create a tag on WordPress.org SVN repository |

