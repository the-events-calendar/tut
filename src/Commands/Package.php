<?php

namespace TUT\Commands;

use Graze\ParallelProcess\Pool;
use Graze\ParallelProcess\Display\Lines;
use Symfony\Component\Process\Process;
use Symfony\Component\Stopwatch\Stopwatch;
use TUT\Command as Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Package extends Command {
	/**
	 * @var string The branch in which the version is being prepared
	 */
	protected $branch;

	/**
	 * @var string The branch that will be merged into the $branch being packaged
	 */
	protected $merge;

	/**
	 * @var string The tmp directory
	 */
	protected $tmp_dir;

	/**
	 * @var boolean Whether or not this is the production-ready zip
	 */
	protected $final = false;

	/**
	 * @var boolean Disable view version checks
	 */
	protected $ignore_view_versions = false;

	/**
	 * @var boolean Disable view link checks
	 */
	protected $ignore_view_links = false;

	/**
	 * @var boolean Clear untracked files on directory
	 */
	protected $clear = false;

	/**
	 * @var string Number of the build
	 */
	protected $default_build_number = null;

	/**
	 * @var array Plugins skipped packaging
	 */
	protected $skipped = [];

	/**
	 * @var array Plugins packaged
	 */
	protected $packaged = [];

	/**
	 * @var array Messages to output after script execution
	 */
	protected $messages = [
		'errors'    => [],
		'successes' => [],
		'warnings'  => [],
	];

	/**
	 * @var array Pacakge notes
	 */
	protected $package_notes = [];

	/**
	 * @var Stopwatch Execution timer
	 */
	private $stopwatch;

	/**
	 * @var bool Should the command prompt for plugin selection?
	 */
	public $do_plugin_selection = true;

	/**
	 * The $HOME directory (without trailing slash).
	 *
	 * @return string
	 */
	private static function get_home_dir() {
		return (string) getenv( 'HOME' );
	}

	/**
	 * Whether NVM is detected on the system.
	 *
	 * @return bool
	 */
	private function nvm_exists() {
		return file_exists( $this->get_nvm_path() );
	}

	/**
	 * The expected location of NVM, whether or not it exists.
	 *
	 * @return string
	 */
	private function get_nvm_path() {
		return self::get_home_dir() . '/.nvm/nvm.sh';
	}

	protected function configure() {
		parent::configure();

		$this
			->setName( 'package' )
			->setDescription( 'Package our Plugins' )
			->setHelp( 'This command allows you to package all or some plugins for testing or releasing' )
			->addOption( 'branch', 'b', InputOption::VALUE_REQUIRED, 'Branch to be packaged' )
			->addOption( 'final', '', InputOption::VALUE_NONE, 'Package the zip without a hash in the filename' )
			->addOption( 'ignore-view-versions', '', InputOption::VALUE_NONE, 'Ignore problems that arise from view version updates' )
			->addOption( 'ignore-view-links', '', InputOption::VALUE_NONE, 'Ignore problems that arise from view links' )
			->addOption( 'merge', 'm', InputOption::VALUE_OPTIONAL, 'Branch to merge into the branch being packaged' )
			->addOption( 'output', 'o', InputOption::VALUE_OPTIONAL, 'Directory to dump the zip files' )
			->addOption( 'release', 'r', InputOption::VALUE_OPTIONAL, 'Version to package' )
			->addOption( 'clear', '', InputOption::VALUE_NONE, 'Remove any untracked file' )
			->addOption( 'composer_php_path', '', InputOption::VALUE_OPTIONAL, 'Path to PHP version to use when monkeying with composer' )
			->addOption( 'build_number', '', InputOption::VALUE_OPTIONAL, 'Override the dev version build number. Defaults to commit timestamp.' );
	}

	protected function execute( InputInterface $input, OutputInterface $output ) {
		if ( ! $this->nvm_exists() ) {
			$this->io->error( 'NVM needs to exist within ' . self::get_home_dir() );
			exit( 1 );
		}

		$this->stopwatch = new Stopwatch();

		$this->stopwatch->start( 'execution' );
		if ( $input->getOption( 'dry-run' ) ) {
			return;
		}

		$output_dir = $input->getOption( 'output' );

		$this->tmp_dir              = $output_dir ?: '.' . DIRECTORY_SEPARATOR; // current directory
		$this->branch               = $this->branch ?: $input->getOption( 'branch' );
		$this->merge                = $this->merge ?: $input->getOption( 'merge' );
		$this->ignore_view_versions = $this->ignore_view_versions ?: $input->getOption( 'ignore-view-versions' );
		$this->ignore_view_links    = $this->ignore_view_links ?: $input->getOption( 'ignore-view-links' );
		$this->final                = $this->final ?: $input->getOption( 'final' );
		$this->clear                = $this->clear ?: $input->getOption( 'clear' );
		$this->default_build_number = $this->default_build_number ?: $input->getOption( 'build_number' );
		$this->composer_php_path    = $input->getOption( 'composer_php_path' ) ?: null;
		$this->composer_php_path    = preg_replace( '![^a-z0-9/\-_\.]!', '', $this->composer_php_path );
		$this->composer_php_path    = preg_match( '!php[0-9\.\-]*$!', $this->composer_php_path ) ? $this->composer_php_path : null;

		$version_option = $input->getOption( 'release' );

		foreach ( $this->selected_plugins as $plugin ) {
			$merge      = $this->merge;
			$branch     = $this->branch;
			$has_common = $this->has_common( $plugin );

			$this->package_notes[ $plugin->name ] = [];

			if ( $this->already_in_plugin_dir ) {
				$plugin_dir = "{$this->origin_dir}";
			} else {
				$plugin_dir = "{$this->origin_dir}/{$plugin->name}";
			}

			if ( ! file_exists( $plugin_dir ) ) {
				$this->output->writeln( "<fg=yellow>Could not find the directory: {$plugin->name}/</>", OutputInterface::VERBOSITY_VERBOSE );
				$this->output->writeln( "<fg=yellow;options=bold>Skipping packaging of {$plugin->name}/</>", OutputInterface::VERBOSITY_VERBOSE );

				$this->skipped[] = "{$plugin->name} - Cound not find plugin directory";

				continue;
			}

			$this->io->section( $plugin->name, OutputInterface::VERBOSITY_NORMAL );

			// cd into the plugin directory
			chdir( $plugin_dir );
			$this->output->writeln( "Current path: {$plugin_dir}" );

			$current_branch = $this->get_branch();

			if ( $this->clear ) {
				$this->output->writeln( '<fg=cyan;options=bold>Clearing the git playground of untracked files.!</>' );
				$this->run_process( 'git clean -df', true );
				$this->run_process( 'git reset --hard origin/master', false );

				if ( file_exists( 'common' ) ) {
					chdir( 'common' );
					$this->run_process( 'git clean -df', true );
					$this->run_process( 'git reset --hard origin/master', false );
					chdir( '../' );
				}
			}

			$stashed_work = false;
			$process      = $this->run_process( 'git status -s', false );
			if ( trim( $process->getOutput() ) ) {
				$process              = $this->run_process( 'git rev-parse --short HEAD', false );
				$original_plugin_hash = trim( $process->getOutput() );

				// stash existing work (including untracked files) so we have a clean directory
				$this->run_process( 'git stash save --include-untracked packaging-bookmark-' . $original_plugin_hash );
				$stashed_work = true;
			}

			$package_branch = 'handy-dandy-package-branch';
			$branch_prefix  = preg_match( '/[a-zA-Z\-_\/]/', $branch ) ? 'remotes/origin/' : '';
			$merge_prefix   = preg_match( '/[a-zA-Z\-_\/]/', $merge ) ? 'remotes/origin/' : '';

			// fetch latest from origin including tags
			$this->run_process( 'git fetch --all', false );
			$this->run_process( 'git fetch --tags', false );

			$revparse_process      = $this->run_process( "git rev-parse {$branch}", false );
			$revparse_output       = trim( $revparse_process->getOutput() );
			$remote_branch_process = $this->run_process( "git branch -a | egrep \"\b{$branch_prefix}{$branch}$\"", false );
			$remote_branch_match   = trim( $remote_branch_process->getOutput() );
			$remote_branch_exists  = ! empty( $remote_branch_match ) || ! empty( $revparse_output );

			if ( ! $remote_branch_exists ) {
				$error = "Invalid Branch! Check if it exists on GitHub: `{$branch}`";
				$this->error_and_skip( $error, $plugin );
				continue;
			}

			// checkout a clean branch and create a branch from that clean one
			$this->run_process( "git checkout {$branch_prefix}{$branch}", false );
			$this->run_process( "git branch -D {$package_branch}", false );
			$this->run_process( "git checkout -b {$package_branch}", false );

			$this->output->writeln( "Checkout branch: {$branch_prefix}{$branch}" );

			// merge if necessary (from a clean branch)
			if ( $merge ) {
				$this->output->writeln( "<fg=cyan;options=bold>Merging {$merge} into {$branch}</>", OutputInterface::VERBOSITY_NORMAL );
				// Stash on submodules before merging
				$this->run_process( "git submodule foreach 'git stash'", false );
				$this->run_process( "git merge {$merge_prefix}{$merge}", false );
			}

			$this->run_process( 'git submodule update --init --recursive' );

			if ( $has_common ) {
				// remove the 3rd line (Plugin Name: Yadda yadda) from the bootstrap files (see: https://central.tri.be/issues/41984)
				$common_file = file_get_contents( 'common/tribe-common.php' );
				$common_file = preg_replace( "/Plugin Name:.*[\r\n]+/", '', $common_file );
				file_put_contents( 'common/tribe-common.php', $common_file );
			}

			$json = file_get_contents( 'package.json' );
			preg_match( '/.*"version"\:[^"]*"([^"]*).*/', $json, $matches );
			$json_version = trim( $matches[1] );

			preg_match( '/.*"_zipname"\:[^"]*"([^"]*).*/', $json, $matches );
			$zipname = trim( $matches[1] );

			$readme = file_get_contents( 'readme.txt' );
			preg_match( '/Stable tag\:\s+(.*)/', $readme, $matches );
			$readme_version = trim( $matches[1] );

			$bootstrap_file = file_get_contents( $plugin->bootstrap );

			if ( ! empty( $plugin->main ) ) {
				$main_file    = file_get_contents( $plugin->main );
				$main_version = null;

				// grab the main file's version if possible
				if ( isset( $plugin->version ) ) {
					preg_match( '/.*' . $plugin->version . "[^']*'([^']*)'.*/", $main_file, $matches );
					$main_version = $matches[1];
				}
			}

			// grab the version from the bootstrap file
			preg_match( '/.*Version\:\s*([0-9a-zA-Z.-]*).*/', $bootstrap_file, $matches );
			$bootstrap_version = $matches[1];

			if ( empty( $plugin->main ) ) {
				// if a version wasn't passed in via --release, default to the bootstrap file's version number
				$main_version = $version = $version_option ?: $bootstrap_version;
			} else {
				// if a version wasn't passed in via --release, default to the Main file's version number
				$version = $version_option ?: $main_version;
			}

			$process     = $this->run_process( 'git rev-parse --short HEAD', false );
			$plugin_hash = trim( $process->getOutput() );

			// if the build number isn't set, get it from the latest commit
			if ( empty( $this->default_build_number ) ) {
				$process      = $this->run_process( 'git show -s --format=%ct HEAD', false );
				$build_number = trim( $process->getOutput() );
			} else {
				$build_number = $this->default_build_number;
			}

			if ( $has_common ) {
				$process             = $this->run_process( 'git ls-tree HEAD common', false );
				$current_common_hash = trim( $process->getOutput() );
				chdir( 'common' );

				$common_file          = file_get_contents( $plugin->main );
				$common_version_regex = '/.*' . preg_quote( $plugin->version ) . "[^']*'([^']*)'.*/";
				preg_match( $common_version_regex, $common_file, $matches );
				$current_common_version = trim( $matches[1] );

				$process    = $this->run_process( 'git show-ref --tags | grep "refs/tags/$current_common_version"', false );
				$tag_exists = trim( $process->getOutput() );

				if ( ! $tag_exists ) {
					$process         = $this->run_process( 'git ls-tree ' . $current_common_version . ' common', false );
					$old_common_hash = trim( $process->getOutput() );
				} else {
					$common_file     = file_get_contents( $plugin->main );
					$old_common_hash = trim( $current_common_hash );
					preg_match( $common_version_regex, $common_file, $matches );
					$old_common_version = trim( $matches[1] );
				}

				chdir( '../' );

				if ( $current_common_hash !== $old_common_hash ) {
					chdir( $this->tmp_dir );
					$this->run_process( 'git clone --recursive git@github.com:the-events-calendar/tribe-common.git' );

					chdir( 'tribe-common' );
					$this->run_process( "git checkout {$old_common_hash}" );

					$common_file = file_get_contents( $plugin->main );
					preg_match( $common_version_regex, $common_file, $matches );
					$old_common_version = $matches[1];

					chdir( $plugin_dir );
				}

				if ( $old_common_version !== $current_common_version ) {
					$this->text( $current_common_hash );
					$this->text( $old_common_hash );
					$this->text( $current_common_version );
					$this->text( $old_common_version );

					$error = "Attempting to package version {$version} but the VERSION constant in tribe-common has not been updated.";

					$this->error_and_skip( $error, $plugin );
					continue;
				}
			}

			if ( isset( $plugin->version ) && $main_version !== $version ) {
				if ( $this->final ) {
					$error = "Attempting to package version {$version} but the VERSION constant is {$main_version} in {$plugin->main}";

					$this->error_and_skip( $error, $plugin );
					continue;
				} else {
					$this->package_notes[ $plugin->name ][] = " ({$plugin->main} version is {$main_version})";
				}
			}

			if ( $bootstrap_version !== $version ) {
				if ( $this->final ) {
					$error = "Attempting to package version {$version} but the 'Version' line is {$bootstrap_version} in {$plugin->bootstrap}";

					$this->error_and_skip( $error, $plugin );
					continue;
				} else {
					$this->package_notes[ $plugin->name ][] = " ({$plugin->bootstrap} version is {$bootstrap_version})";
				}
			}

			if ( $readme_version !== $version ) {
				if ( $this->final ) {
					$error = "Attempting to package version {$version} but the 'Stable tag' line is {$readme_version} in readme.txt";

					$this->error_and_skip( $error, $plugin );
					continue;
				} else {
					$this->package_notes[ $plugin->name ][] = " (readme.txt version is {$readme_version})";
				}
			}

			$zip_version             = $this->json_compatible_version( $version, false );
			$json_compatible_version = $this->json_compatible_version( $version );

			if ( $json_version !== $json_compatible_version && $json_version !== $zip_version ) {
				$error = "Attempting to package version {$json_compatible_version} but the 'version' property is {$json_version} in package.json";

				$this->error_and_skip( $error, $plugin );
				continue;
			}

			if ( $changed_views = $this->changed_views( $plugin ) ) {
				$view_problems      = [];
				$view_link_problems = [];
				$has_tbd_issue      = false;
				$has_link_issue     = false;

				foreach ( $changed_views as $changed_file => $info ) {
					$version_to_check = preg_replace( '/([\d\.]+).*/', '$1', $info['bootstrap-version'] );

					if ( ! $info['view-version'] ) {
						$view_problems[] = "{$changed_file} - NO @version SET";
					} elseif ( ! $this->final && 'TBD' === strtoupper( $info['view-version'] ) ) {
						$this->output->writeln( "<fg=magenta;options=bold>WARNING: {$changed_file} - @version is TBD, be sure to change it before release</>" );
						$has_tbd_issue = true;
					} elseif ( $info['view-version'] !== $version_to_check ) {
						$view_problems[] = "{$changed_file} - @version MISMATCH: Plugin version: {$version_to_check} vs. View version: {$info['view-version']}";
					}

					if ( ! $info['view-link'] ) {
						$view_link_problems[] = "{$changed_file} - NO @link SET";
					} elseif ( ! $this->final && '{INSERT_ARTICLE_LINK_HERE}' === strtoupper( $info['view-link'] ) ) {
						$this->output->writeln( "<fg=magenta;options=bold>WARNING: {$changed_file} - @link is {INSERT_ARTICLE_LINK_HERE}, be sure to change it before release</>" );
						$has_link_issue = true;

						$view_link_problems[] = "{$changed_file} - @link is {INSERT_ARTICLE_LINK_HERE}";
					}
				}

				if ( $has_tbd_issue ) {
					$this->package_notes[ $plugin->name ][] = "- @version TBDs exist. Check build output for more information.";
				}

				if ( $has_link_issue ) {
					$this->package_notes[ $plugin->name ][] = "- @link {INSERT_ARTICLE_LINK_HERE} links exist. Check build output for more information.";
				}

				if ( $view_problems ) {
					if ( $this->ignore_view_versions ) {
						$this->messages['warnings'][] = $plugin->name . ' has view version issues.';

						$this->messages['view-issues'][] = "[view-issues|{$plugin->name}]";
						foreach ( $view_problems as $key => $problem ) {
							$this->messages['view-issues'][] = $problem;
						}
						$this->messages['view-issues'][] = "[/view-issues|{$plugin->name}]";
					} else {
						$this->io->newLine();
						$this->output->writeln( "<fg=magenta;options=bold>WARNING: You are attempting to package version {$version} but there are version mismatches in some view templates:</>" );
						$this->io->newLine();
						$this->io->listing( $view_problems );
						$result = $this->ask_for_confirmation( '<fg=yellow>Would you like to ignore those differences and continue?</> (y/n)' );

						if ( ! $result || ! $this->ask_for_confirmation( '<fg=yellow>Are you sure?</> (y/n)' ) ) {
							$this->output->writeln( "<fg=red;options=bold>Bailing out so you can fix the views in {$plugin->name}.</>" );
							exit;
						}

						$this->io->newLine();
						$this->text( 'Alrighty! Moving on!' );
					}
				}

				if ( $view_link_problems ) {
					if ( $this->ignore_view_links ) {
						$this->messages['warnings'][] = $plugin->name . ' has missing view link issues.';

						$this->messages['view-link-issues'][] = "[view-link-issues|{$plugin->name}]";
						foreach ( $view_link_problems as $key => $problem ) {
							$this->messages['view-link-issues'][] = $problem;
						}
						$this->messages['view-link-issues'][] = "[/view-link-issues|{$plugin->name}]";
					} else {
						$this->io->newLine();
						$this->output->writeln( "<fg=magenta;options=bold>WARNING: You are attempting to package version {$version} but there are version links missing in some view templates:</>" );
						$this->io->newLine();
						$this->io->listing( $view_link_problems );
						$result = $this->ask_for_confirmation( '<fg=yellow>Would you like to ignore those differences and continue?</> (y/n)' );

						if ( $this->final && ( ! $result || ! $this->ask_for_confirmation( '<fg=yellow>Are you sure?</> (y/n)' ) ) ) {
							$this->output->writeln( "<fg=red;options=bold>Bailing out so you can fix the view links in {$plugin->name}.</>" );
							exit;
						}

						$this->io->newLine();
						$this->text( 'Alrighty! Moving on!' );
					}
				}
			}

			// assume no suffix is required
			$file_dev_suffix  = null;
			$destination_file = $file = $zipname . '.' . $zip_version . '.zip';

			if ( ! $this->final ) {
				// if we aren't packaging the final version, we'll need a dev suffix
				$file_dev_suffix  = "-dev-{$build_number}-{$plugin_hash}";
				$destination_file = str_replace( '.zip', "{$file_dev_suffix}.zip", $destination_file );

				// if the file already exists, it has already been packaged and doesn't need to be re-packaged
				if ( file_exists( "{$this->tmp_dir}/{$destination_file}" ) ) {
					$this->output->writeln( "<fg=cyan>{$plugin->name}</> <fg=green>was already packaged and placed in <fg=green;options=bold>{$this->tmp_dir}/{$destination_file}</></>\n", OutputInterface::VERBOSITY_NORMAL );
					$this->packaged[ $plugin->name ] = $this->tmp_dir . '/"' . $destination_file . '"';

					if ( ! empty( $this->package_notes[ $plugin->name ] ) ) {
						$this->packaged[ $plugin->name ] .= ' ' . implode( ' ', $this->package_notes[ $plugin->name ] );
					}
					continue;
				}

				// update the version in the bootstrap file
				$file_contents = $bootstrap_file;
				$regexp        = "/(Version:)(.*)([\r\n]+)/";
				$file_contents = preg_replace( $regexp, '$1$2-dev-' . $build_number . '-' . $plugin_hash . '$3', $file_contents );

				// Modified the Original Bootstrap file with the new content
				file_put_contents( $plugin->bootstrap, $file_contents );

				if ( ! empty( $main_file ) ) {
					// Find what to modify on the Main Class file
					$file_contents = $main_file;

					$regexp        = "/([\t ]+)({$plugin->version})( += +)(\'[^']+)/";
					$file_contents = preg_replace( $regexp, '$1$2$3$4-dev-' . $build_number . '-' . $plugin_hash, $file_contents );

					// Modified the class Main File
					file_put_contents( $plugin->main, $file_contents );
				}
			}

			chdir( $plugin_dir );

			/**
			 * Runs yarn install for the current plugin and common
			 */
			$this->run_pre_build_installs( $plugin );

			if (
				file_exists( 'Gulpfile.js' )
				|| file_exists( 'gulpfile.js' )
			) {
				/**
				 * Runs the default gulp tasks for packaging
				 */
				$this->run_gulp_tasks( $plugin );

				$package_json_contents          = json_decode( file_get_contents( 'package.json' ) );
				$package_json_contents->version = $version;
				file_put_contents( 'package.json', json_encode( $package_json_contents ) );

				// package up the zip
				$this->output->writeln( '<fg=cyan>* Zipping content</>', OutputInterface::VERBOSITY_VERBOSE );
				$process = $this->run_process( '. ' . $this->get_nvm_path() . ' && nvm use && yarn run gulp zip' );

				$this->run_process( 'git checkout package.json' );
			}

			$zipped = true;
			if ( ! $this->has_compiled_files( $plugin ) ) {
				$zipped                       = false;
				$this->messages['warnings'][] = "Packaging for {$plugin->name} failed. CSS and/or JS is missing!";
				$this->skipped[]              = "{$plugin->name} - CSS and/or JS failed to build correctly.";
			} elseif ( ! $process->isSuccessful() ) {
				$zipped                       = false;
				$this->messages['warnings'][] = "Packaging for {$plugin->name} failed during zipping.";
				$this->skipped[]              = "{$plugin->name} - Zipping failed for some strange reason.";
			}

			// reset the files we tweaked for packaging
			if ( $has_common ) {
				chdir( 'common' );
				$this->run_process( 'git checkout tribe-common.php' );
				chdir( '../' );
			}

			// go back a directory
			chdir( $this->origin_dir );

			if ( $zipped && ! file_exists( $file ) ) {
				$zipped = false;
				$this->output->writeln( 'ERROR: ' . trim( $process->getOutput() ) );
				$this->messages['warnings'][] = "Packaging for {$plugin->name} failed because the zip file could not be found at {$file}.";
				$this->skipped[]              = "{$plugin->name} - zipping failed and the zip was not created";
			} elseif ( $zipped ) {
				// move the zip file to /tmp
				$this->output->writeln( "<fg=cyan>Moving zip file to {$this->tmp_dir}/{$destination_file}</>", OutputInterface::VERBOSITY_VERBOSE );
				$process = $this->run_process( 'mv ' . $file . ' ' . $this->tmp_dir . '/' . $destination_file, false );

				if ( ! $process->isSuccessful() ) {
					$zipped = false;
					$this->output->writeln( 'ERROR: ' . trim( $process->getOutput() ) );
					$this->messages['warnings'][] = "Packaging for {$plugin->name} failed while moving the zip to its destination.";
					$this->skipped[]              = "{$plugin->name} - zipping succeeded but the file was not moved correctly";
				}
			}
		}

		// go back into the plugin dir to reset changes
		chdir( $plugin_dir );

		// clean house
		$this->run_process( 'git reset --hard HEAD', false );
		$this->run_process( "git checkout {$current_branch}", false );
		$this->run_process( "git branch -D {$package_branch}", false );

		// attempt to re-apply stashed work
		if ( $stashed_work ) {
			$process = $this->run_process( 'git stash pop' );
			if ( preg_match( '/Aborting/', $process->getOutput() ) ) {
				$this->run_process( 'git reset --hard HEAD' );
			}
		}

		// go back a directory
		chdir( $this->origin_dir );

		if ( ! empty( $zipped ) ) {
			$this->output->writeln( "<fg=cyan>{$plugin->name}</> <fg=green>has been packaged and placed in <fg=green;options=bold>{$this->tmp_dir}/{$destination_file}</></>\n", OutputInterface::VERBOSITY_NORMAL );

			$this->packaged[ $plugin->name ] = $this->tmp_dir . '/"' . $destination_file . '"';

			if ( ! empty( $this->package_notes[ $plugin->name ] ) ) {
				$this->packaged[ $plugin->name ] .= ' ' . implode( ' ', $this->package_notes[ $plugin->name ] );
			}
		}

		$this->io->section( 'Packaging results' );

		if ( $this->packaged ) {
			$this->output->writeln( '<fg=green;options=bold>Successfully packaged:</>' );
			$this->io->listing( $this->packaged );
		}

		if ( $this->skipped ) {
			$this->output->writeln( '<fg=red;options=bold>Failed packaging:</>' );
			$this->io->listing( $this->skipped );
		}

		$results = '';

		if ( ! empty( $this->messages['view-issues'] ) ) {
			$results .= "\n\n\n";
			$results .= implode( "\n", (array) $this->messages['view-issues'] ) . "\n\n\n";
			$results .= "\n\n\n";
		}

		if ( ! empty( $this->messages['view-link-issues'] ) ) {
			$results .= "\n\n\n";
			$results .= implode( "\n", (array) $this->messages['view-link-issues'] ) . "\n\n\n";
			$results .= "\n\n\n";
		}

		$results .= 'Results: ';
		$color   = 'green';
		$num     = [];

		if ( $this->packaged ) {
			$num[] = count( $this->packaged ) . ' successfully packaged';
		}

		if ( $this->skipped ) {
			$num[] = count( $this->skipped ) . ' failed';
			$color = $this->packaged ? 'yellow' : 'red';
		}

		$results .= implode( ', ', $num ) . '. ';

		if ( $this->messages['warnings'] ) {
			$results .= '<fg=yellow;><fg=yellow;options=bold>WARNING:</> ' . implode( ' ', $this->messages['warnings'] ) . '</>';
		}

		$timer_results = $this->stopwatch->stop( 'execution' );
		$this->output->writeln( '<info>Completed in ' . number_format( round( $timer_results->getDuration() / 1000 / 60, 2 ), 2 ) . ' minutes</info>' );

		$this->output->writeln( '<fg=' . $color . ';options=bold>' . $results . '</>' );
	}

	/**
	 * Gets composer processes that can be run in parallel
	 *
	 * @param string $command Composer command we want to run, either install or update
	 * @param object $plugin  Plugin we are running this for
	 * @param string $params  Which params we will run the command with
	 *
	 * @return Process
	 */
	private function get_composer_processes( $command, $plugin, $params = '' ) {
		$processes  = [];
		$has_common = $this->has_common( $plugin );
		$command    = in_array( $command, [ 'install', 'update' ] ) ? $command : 'update';

		$processes[] = new Process( "{$this->composer_php_path} composer {$command} --ignore-platform-reqs {$params} && composer dump-autoload {$params} --optimize" );

		if ( $has_common ) {
			$processes[] = new Process( "cd common && {$this->composer_php_path} composer {$command} --ignore-platform-reqs {$params} && composer dump-autoload {$params} --optimize" );
		}

		return $processes;
	}

	/**
	 * Run yarn install and composer update for a given plugin and runs on common
	 *
	 * @param object $plugin Plugin we are running this for
	 *
	 * @return Process
	 */
	private function run_pre_build_installs( $plugin ) {
		$has_common = $this->has_common( $plugin );

		$this->output->writeln( "<fg=cyan;options=bold>Running yarn install and composer install</>" );
		$this->run_process( '. ' . $this->get_nvm_path() . ' && nvm install $(cat .nvmrc)' );

		$pool = new Pool();
		$pool->add( new Process( '. ' . $this->get_nvm_path() . ' && nvm use && yarn install --verbose--mutex file' ), [ 'yarn' ] );

		if ( $has_common ) {
			$pool->add( new Process( 'cd common && . ' . $this->get_nvm_path() . ' && nvm use && yarn install --verbose--mutex file' ), [ 'yarn common' ] );
		}

		$composer_processes = $this->get_composer_processes( 'install', $plugin, '--no-dev' );
		foreach ( $composer_processes as $process ) {
			$pool->add( $process, [ 'composer' ] );
		}

		$lines = new Lines( $this->output, $pool );

		return $lines->run();
	}

	/**
	 * Run gulp tasks for a given plugin and runs on common
	 *
	 * @param object $plugin Plugin we are running this for
	 *
	 * @return Process
	 */
	private function run_gulp_tasks( $plugin ) {
		$has_common = $this->has_common( $plugin );
		$plugin_dir = "{$this->origin_dir}/{$plugin->name}";

		$this->output->writeln( "Current path: {$plugin_dir}" );

		$this->output->writeln( 'Gulp packaging!' );

		$pool = new Pool();

		// fetch language files
		$this->output->writeln( '<fg=cyan;options=bold>Fetching lang files</>', OutputInterface::VERBOSITY_NORMAL );
		$this->output->writeln( '<fg=cyan>* Compiling PostCSS</>', OutputInterface::VERBOSITY_VERBOSE );
		$this->output->writeln( '<fg=cyan>* Compressing JS</>', OutputInterface::VERBOSITY_VERBOSE );

		$pool->add( new Process( '. ' . $this->get_nvm_path() . ' && nvm use && yarn run gulp glotpress' ), [ 'glotpress' ] );
		$pool->add( new Process( '. ' . $this->get_nvm_path() . ' && nvm use && yarn run gulp postcss' ), [ 'postcss' ] );
		$pool->add( new Process( '. ' . $this->get_nvm_path() . ' && nvm use && yarn run gulp compress-js' ), [ 'compress-js' ] );

		if ( $has_common ) {
			$this->output->writeln( '<fg=cyan;options=bold>Fetching common lang files</>', OutputInterface::VERBOSITY_NORMAL );
			$this->output->writeln( '<fg=cyan>* Compiling common PostCSS</>', OutputInterface::VERBOSITY_VERBOSE );
			$this->output->writeln( '<fg=cyan>* Compressing common JS</>', OutputInterface::VERBOSITY_VERBOSE );
			$pool->add( new Process( 'cd common && . ' . $this->get_nvm_path() . ' && nvm use && yarn run gulp glotpress' ), [ 'glotpress common' ] );
			$pool->add( new Process( 'cd common && . ' . $this->get_nvm_path() . ' && nvm use && yarn run gulp postcss' ), [ 'postcss common' ] );
			$pool->add( new Process( 'cd common && . ' . $this->get_nvm_path() . ' && nvm use && yarn run gulp compress-js' ), [ 'compress-js common' ] );
		}

		$lines = new Lines( $this->output, $pool );
		$lines->run();

		$this->output->writeln( '<fg=cyan>* Compressing CSS</>', OutputInterface::VERBOSITY_VERBOSE );

		$process = $this->run_process( '. ' . $this->get_nvm_path() . ' && nvm use && yarn run gulp compress-css' );

		if ( $has_common ) {
			$this->output->writeln( '<fg=cyan>* Compressing common CSS</>', OutputInterface::VERBOSITY_VERBOSE );
			$process = $this->run_process( 'cd common && . ' . $this->get_nvm_path() . ' && nvm use && yarn run gulp compress-css' );
		}

		if ( file_exists( 'webpack.config.js' ) ) {
			// running webpack
			$this->output->writeln( '<fg=cyan>* Webpack build</>', OutputInterface::VERBOSITY_VERBOSE );
			// gulp webpack with a timeout of 15 minutes
			$process = $this->run_process( '. ' . $this->get_nvm_path() . ' && nvm use && yarn run gulp webpack', true, 900 );
		}

		return $process;
	}

	public function error_and_skip( $error, $plugin ) {
		$this->messages['errors'] = array_merge( $this->messages['errors'], (array) $error );
		$this->io->error( "Error during packaging of {$plugin->name}:\n{$error}" );
		$this->output->writeln( "<fg=yellow;options=bold>Skipping packaging of {$plugin->name}</>", OutputInterface::VERBOSITY_NORMAL );

		if ( $this->output->getVerbosity() >= OutputInterface::VERBOSITY_NORMAL ) {
			$this->io->newLine();
		}

		$this->skipped[] = "{$plugin->name} - {$error}";
	}

	/**
	 * Returns whether or not the given plugin has common.
	 *
	 * @param $plugin
	 *
	 * @return bool
	 */
	protected function has_common( $plugin ) {
		return in_array( $plugin->name, [ 'the-events-calendar', 'event-tickets' ] );
	}

	/**
	 * Returns whether or not the plugin has built files.
	 *
	 * @param $plugin
	 */
	protected function has_compiled_files( $plugin ) {
		$has_common = $this->has_common( $plugin );
		$plugin_dir = "{$this->origin_dir}/{$plugin->name}";

		if (
			! $this->has_compiled_files_in_path( 'css', $plugin_dir )
			|| ! $this->has_compiled_files_in_path( 'js', $plugin_dir )
		) {
			return false;
		}

		if (
			$has_common
			&& (
				! $this->has_compiled_files_in_path( 'css', "{$plugin_dir}/common" )
				|| ! $this->has_compiled_files_in_path( 'js', "{$plugin_dir}/common" )
			)
		) {
			return false;
		}

		return true;
	}

	/**
	 * Returns whether or not the plugin has built files in the given path root.
	 *
	 * @param string $type
	 * @param string $path
	 *
	 * @return bool
	 */
	protected function has_compiled_files_in_path( $type, $path ) {
		$file_path = "{$path}/src/resources/{$type}";

		if ( ! file_exists( $file_path ) ) {
			return false;
		}

		$files = glob( "{$file_path}/*.{$type}" );

		if ( ! count( $files ) ) {
			return false;
		}

		$min_files = glob( "{$file_path}/*.min.{$type}" );

		if ( ! count( $min_files ) ) {
			return false;
		}

		return true;
	}
}
