# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.11] - 2023-12-14

- Fix: Add some delay to the translation requests to avoid a .org lock-out.
- Fix: Correct code for the `svn:release` command.

## [1.2.10] - 2023-09-13

- Enhancement: Add `svn:release` command to release a given SVN tag for a plugin.

## [1.2.9] - 2023-08-15

- Fix: On `svn:tag` command to ensure cleanup doesn't remove the destination tag on success.

## [1.2.8] - 2023-07-12

- Enhancement: Add `svn:tag` command to create a new SVN tag for a plugin.

## [1.2.7] - 2022-03-22

- Enhancement: Create extensions as `tec-labs-` rather than `tribe-ext-`

## [1.2.6] - 2021-06-03
### Updated

- Add support for the `ical-tec` feature plugin.

## [1.2.5] - 2021-05-26
### Updated

- Update `node` and `gulp` related commands.

## [1.2.4] - 2020-12-28
### Updated

- Updated default GitHub org to `the-events-calendar`.

## [1.2.3] - 2020-12-02
### Updated

- Ported @lucatume's fix for excluding deleted views from the changed view checks.

## [1.2.2] - 2020-11-21
### Updated

- Updated the submodule-sync command to use API calls for updating submodule hashes.

## [1.2.1] - 2020-11-21
### Updated

- Better output for the submodule-sync command.

## [1.2.0] - 2020-11-21
### Updated

- Added support for passing a specific branch to `tut submodule-sync` via the `--branch` option.

## [1.1.1] - 2020-11-20
### Updated

- Fixed issue where the upgrade check was happening too early.

## [1.1.0] - 2020-11-20
### Added

- Added `tut upgrade`

## [1.0.0] - 2020-11-20
### Added

- Initial version
