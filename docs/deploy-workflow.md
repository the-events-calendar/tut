# Typical deploy workflow

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
