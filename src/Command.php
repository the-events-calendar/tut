<?php
namespace TUT;

use __;
use Github;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

class Command extends \Symfony\Component\Console\Command\Command {

	public $config;

	/**
	 * Plugins to take action on
	 */
	public $selected_plugins = [];

	/**
	 * Indicates whether to interactively select plugins or to operate against all of them
	 */
	public $auto_select_plugins = false;

	/**
	 * Friendly plugin name
	 */
	public $friendly_script_name;

	/**
	 * Arguments for the script
	 */
	public $supported_args = [
		'short' => '',
		'long'  => [],
	];

	/**
	 * Parsed args
	 */
	public $args = [];

	/**
	 * Directory that processing began at
	 */
	public $origin_dir;

	/**
	 * Whether the command should output any messages or not.
	 *
	 * @var bool
	 */
	public $quiet = false;

	/**
	 * @var bool Should the command prompt for plugin selection?
	 */
	public $do_plugin_selection = false;

	/**
	 * @var bool Holds whether or not the command began in the plugin directory already
	 */
	protected $already_in_plugin_dir = false;

	/**
	 * @var GitHub\Client GitHub Client object.
	 */
	protected $github_client;

	/**
	 * If custom local `tut.json` exists and is readable, load it. Else, load default JSON that ships with this repo.
	 *
	 * @return string
	 */
	protected function get_config_file() {
		if ( is_readable( __TUT_DIR__ . '/tut.local.json' ) ) {
			return __TUT_DIR__ . '/tut.local.json';
		}

		return __TUT_DIR__ . '/tut.json';
	}

	protected function initialize( InputInterface $input, OutputInterface $output ) {
		$this->io              = new SymfonyStyle( $input, $output );
		$this->question_helper = $this->getHelper( 'question' );
		$this->origin_dir      = getcwd();

		$this->input  = $input;
		$this->output = $output;

		// Fetch Contents of configuration
		$this->config = file_get_contents( $this->get_config_file() );

		// Convert to json
		$this->config = (object) json_decode( $this->config, true );

		$this->maybe_select_plugins_from_option();
	}

	protected function interact( InputInterface $input, OutputInterface $output ) {
		if ( $this->do_plugin_selection ) {
			$this->select_plugins();
		}
	}

	protected function configure() {
		if ( $this->do_plugin_selection ) {
			$this
				->addOption( 'dry-run', '', InputOption::VALUE_NONE, 'Whether the command should really execute or not.' )
				->addOption( 'plugin', 'p', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A comma separated list of plugins that will be pushed', [] );
		}
	}

	public function get_plugin( $search ) {
		if ( ! is_string( $search ) ) {
			$plugins = __::where( $this->config->plugins, $search );
			if ( ! empty( $plugins ) ) {
				return reset( $plugins );
			}

			return null;
		}

		$plugins = __::where( $this->config->plugins, [ 'name' => $search ] );
		if ( ! empty( $plugins ) ) {
			return (object) reset( $plugins );
		}

		$plugins = array_filter(
			$this->config->plugins,
			function ( $plugin ) use ( $search ) {
				return in_array( $search, (array) $plugin['alias'], true );
			}
		);

		if ( ! empty( $plugins ) ) {
			return (object) reset( $plugins );
		}

		return null;
	}

	/**
	 * Given a Plugin object, find its directory.
	 *
	 * If already in the plugin's directory, will return current directory.
	 * If the plugin name directory exists 1 level deep, will return that directory.
	 * If the plugin has alias(es) and that directory exists 1 level deep, will return the first alias-matched
	 * directory (in the order entered into $available_plugins, no sorting in this method).
	 *
	 * @see get_plugin() How the $plugin object gets built before using this method.
	 * @see \TUT_Product_Util_CLI::$available_plugins Where the plugin name/aliases come from.
	 *
	 * @param object $plugin
	 *
	 * @return string Full directory path to found plugin, else an empty string.
	 */
	public function get_plugin_dir( $plugin ) {
		if ( $this->already_in_plugin_dir ) {
			return "{$this->origin_dir}";
		}

		if ( $plugin->name == basename( getcwd() ) ) {
			return "{$this->origin_dir}";
		}

		$plugin_dir = "{$this->origin_dir}/{$plugin->name}";

		if (
			! file_exists( $plugin_dir )
			&& ! empty( $plugin->alias )
		) {
			foreach ( (array) $plugin->alias as $alias ) {
				$try_dir = "{$this->origin_dir}/{$alias}";
				if ( file_exists( $try_dir ) ) {
					$plugin_dir = $try_dir;
					break;
				}
			}
		}

		if ( ! file_exists( $plugin_dir ) ) {
			$plugin_dir = '';
		}

		return $plugin_dir;
	}

	protected function execute( InputInterface $input, OutputInterface $output ) {
		foreach ( $this->selected_plugins as $plugin ) {
			$this->io->section( $plugin->name );

			$plugin_dir = "{$this->origin_dir}/{$plugin->name}";

			if ( ! file_exists( $plugin_dir ) ) {
				$this->io->error( "The {$plugin->name} directory doesn't exist here!" );
				exit;
			}

			// cd into the plugin directory
			chdir( $plugin_dir );

			$this->execute_on_plugin( $plugin );

			// go back a directory
			chdir( '../' );
		}

		$this->io->newline();
		$this->io->writeln( '<info>-------------------</info>' );
	}

	public function execute_on_plugin( $plugin ) {

	}

	public function maybe_select_plugins_from_option() {
		$available_plugins = __::pluck( $this->config->plugins, 'name' );

		if ( $this->do_plugin_selection ) {
			$plugins = $this->input->getOption( 'plugin' );
		}

		if ( empty( $plugins ) ) {
			return;
		}

		if ( 'all' === reset( $plugins ) ) {
			$plugins = $available_plugins;
		} elseif ( strpos( reset( $plugins ), ',' ) !== false ) {
			$plugins = array_map( 'trim', explode( ',', reset( $plugins ) ) );
		}

		// Translate Alias and Clean
		$this->selected_plugins = array_filter( array_map( [ $this, 'get_plugin' ], $plugins ) );
	}

	/**
	 * Attempt to select the plugin based on the current working directory
	 */
	public function maybe_select_plugins_from_working_dir() {
		$plugin = basename( getcwd() );

		// Translate Alias and Clean
		$this->selected_plugins = array_filter( array_map( [ $this, 'get_plugin' ], [ $plugin ] ) );

		if ( ! empty( $this->selected_plugins ) ) {
			$this->already_in_plugin_dir = true;
		}
	}

	/**
	 * Attempt to select the plugin based on the current working directory's git checkout
	 */
	public function maybe_select_plugins_from_git_checkout() {
		if ( ! file_exists( '.git/config' ) ) {
			return false;
		}

		$config = file_get_contents( '.git/config' );
		if ( ! preg_match( '!git@github.com:moderntribe/(.*)\.git!', $config, $matches ) ) {
			return false;
		}

		$plugin = $matches[1];

		// Translate Alias and Clean
		$this->selected_plugins = array_filter( array_map( [ $this, 'get_plugin' ], [ $plugin ] ) );

		if ( ! empty( $this->selected_plugins ) ) {
			$this->already_in_plugin_dir = true;
		}
	}

	/**
	 * Provides an interactive plugin selector
	 */
	public function select_plugins() {
		$available_plugins = __::pluck( $this->config->plugins, 'name' );

		if ( empty( $this->selected_plugins ) ) {
			$this->maybe_select_plugins_from_option();
		}

		if ( empty( $this->selected_plugins ) ) {
			$this->maybe_select_plugins_from_working_dir();
		}

		if ( empty( $this->selected_plugins ) ) {
			$this->maybe_select_plugins_from_git_checkout();
		}

		if ( ! empty( $this->selected_plugins ) ) {
			return;
		}

		$selected_marker = ' <comment>(selected)</comment>';

		// make sure choices starts with 1
		$choices = [ '' ];
		$choices = array_merge( $choices, __::pluck( $this->config->plugins, 'name' ) );

		// remove fake zero element
		unset( $choices[0] );

		$choices['all']  = 'Select all plugins';
		$choices['done'] = "I'm finished selecting plugins";

		while ( ! $this->selected_plugins ) {
			$choice = null;
			while ( 'done' !== $choice ) {
				if ( null === $choice ) {
					$choice = $this->ask_for_list( 'Select the plugins you want to run this script on:', $choices, [] );
				} else {
					$choice = $this->ask_for_list( 'Would you like to select more plugins?', $choices, [] );
				}

				if ( 'done' === $choice ) {
					break;
				}

				if ( 'all' === $choice ) {
					$this->selected_plugins = $available_plugins;
					foreach ( $choices as $key => $item ) {
						if ( false !== strpos( $item, $selected_marker ) ) {
							continue;
						}

						if ( ! is_numeric( $key ) ) {
							continue;
						}

						$choices[ $key ] .= $selected_marker;
					}
					break;
				}

				if ( false === strpos( $choices[ $choice ], $selected_marker ) ) {
					$clean_key                = $choices[ $choice ];
					$choices[ $choice ]       .= $selected_marker;
					$this->selected_plugins[] = $clean_key;
				} else {
					$clean_key          = str_replace( $selected_marker, '', $choices[ $choice ] );
					$choices[ $choice ] = $clean_key;
					$remove             = array_search( $clean_key, $this->selected_plugins );
					unset( $this->selected_plugins[ $remove ] );

					// re-index the array
					$this->selected_plugins = array_values( $this->selected_plugins );
				}

				if ( ! in_array( $clean_key, $this->selected_plugins ) ) {
					$this->selected_plugins[] = $clean_key;
				}
			}

			if ( empty( $this->selected_plugins ) ) {
				$this->output->writeln( '<fg=yellow>You must select at least one plugin</>' );
			}
		}

		// Fetch the objects
		$this->selected_plugins = array_filter( array_map( [ $this, 'get_plugin' ], $this->selected_plugins ) );
	}

	/**
	 * Convert a version number to JSON-compatible version
	 *
	 * @param string $version Version number to convert
	 *
	 * @return string
	 */
	public function json_compatible_version( $version, $trim_for_js = true ) {
		$json_version = $version;

		if ( substr_count( $version, '.' ) < 2 ) {
			preg_match( '/([0-9]*\.[0-9]*)(.*)/', $version, $matches );
			$json_version = "{$matches[1]}.0{$matches[2]}";
		}

		if ( $trim_for_js ) {
			$json_version = explode( '.', $json_version );

			if ( 3 < count( $json_version ) ) {
				$key   = count( $json_version ) - 1;
				$final = $json_version[ $key ];

				// if there are non-numeric parts to the final part, grab that so we can append it to the 3rd part
				preg_match( '/[^0-9]/', $final, $matches );
				$json_version = array_slice( $json_version, 0, 3 );

				// if there were non-numeric parts, append them to the 3rd part
				if ( ! empty( $matches[1] ) ) {
					$json_version[2] .= $matches[1];
				}
			}

			$json_version = implode( '.', $json_version );
		}

		return $json_version;
	}

	/**
	 * Fetches the current branch
	 */
	public function get_branch() {
		// get the branch info for the repo
		$process = new Process( 'git rev-parse --abbrev-ref HEAD' );
		$process->run();

		return trim( $process->getOutput() );
	}

	/**
	 * Checkouts a Git Branch
	 *
	 * @param string $branch Branch name to pull/sync
	 */
	public function checkout( $branch, $print = true ) {
		// pull the latest changes from the branch
		$process = new Process( "git checkout {$branch}" );
		$process->run();

		if ( $print ) {
			$this->output->writeln( "<fg=cyan;options=bold>Checkout {$branch}</>", OutputInterface::VERBOSITY_NORMAL );
			$this->io->text( $process->getOutput(), OutputInterface::VERBOSITY_NORMAL );
		}

		return $this;
	}

	/**
	 * Pushed latest commits to a branch
	 *
	 * @param string $branch Branch name to pull/sync
	 */
	public function push( $branch, $print = true ) {
		// pull the latest changes from the branch
		$process = new Process( "git push origin {$branch} --verbose" );
		$process->run();

		if ( $print ) {
			$this->output->writeln( "<fg=cyan;options=bold>Pushing {$branch} to GitHub</>", OutputInterface::VERBOSITY_NORMAL );
			$this->io->text( $process->getOutput(), OutputInterface::VERBOSITY_NORMAL );
		}

		return $this;
	}

	/**
	 * Pull Latest from a Branch
	 *
	 * @param string $branch Branch name to pull/sync
	 */
	public function pull( $branch, $print = true ) {
		// pull the latest changes from the branch
		$process = new Process( "git pull origin {$branch} --verbose" );
		$process->run();

		if ( $print ) {
			$this->output->writeln( "<fg=cyan;options=bold>Pulling {$branch} from GitHub</>", OutputInterface::VERBOSITY_NORMAL );
			$this->io->text( $process->getOutput(), OutputInterface::VERBOSITY_NORMAL );
		}

		return $this;
	}

	/**
	 * Updates the submodules in the current directory
	 */
	public function update_submodules( $print = true ) {
		// Bail if .gitmodules doesn't exist or is empty
		if ( ! file_exists( '.gitmodules' ) || 0 === filesize( '.gitmodules' ) ) {
			return $this;
		}

		$process = $this->run_process( 'git submodule update --recursive --init --remote', $print );

		if ( $print ) {
			$this->output->writeln( '<fg=cyan;options=bold>Updating Submodules</>', OutputInterface::VERBOSITY_NORMAL );

			$this->text( $process->getOutput(), OutputInterface::VERBOSITY_VERBOSE );
		}

		return $this;
	}

	/**
	 * Given a branch or tag name, returns the corresponding hash.
	 *
	 * @param $reference
	 *
	 * @return string
	 */
	public function get_revision_hash( $reference ) {
		$process = $this->run_process( 'git rev-list -n 1 ' . $reference );

		return trim( $process->getOutput() );
	}

	/**
	 * Returns the most recent reachable tag.
	 *
	 * @return string
	 */
	public function last_tag() {
		$process = $this->run_process( 'git describe --tags $(git rev-list --tags --max-count=1)' );

		return trim( $process->getOutput() );
	}

	/**
	 * Fetches changed views
	 */
	public function changed_views( $plugin ) {
		if ( ! is_object( $plugin ) ) {
			$plugin = $this->get_plugin( $plugin );
		}

		$tag_hash = $this->get_revision_hash( $this->last_tag() );
		$process  = $this->run_process( 'git diff --name-only ' . $tag_hash . ' HEAD|grep "src/views/"' );
		$views    = trim( $process->getOutput() );

		if ( $views ) {
			$views = explode( "\n", $views );

			$view_data = [];

			foreach ( $views as &$view ) {
				if ( ! preg_match( '!\.php$!', $view ) ) {
					continue;
				}

				$bootstrap = file_get_contents( $plugin->bootstrap );
				preg_match( '/Version:\s*(.*)/', $bootstrap, $matches );

				$bootstrap_version = $matches[1];

				$file = file_get_contents( $view );

				preg_match( '/@version\s*(.*)/', $file, $matches );

				$view_version = empty( $matches[1] ) ? null : $matches[1];

				preg_match( '/@link\s*(.*)/', $file, $matches );

				$view_link = empty( $matches[1] ) ? null : $matches[1];

				$view_data[ $view ] = array(
					'plugin'            => $plugin->name,
					'bootstrap-version' => $bootstrap_version,
					'view-version'      => $view_version,
					'view-link'         => $view_link,
				);
			}

			return $view_data;
		}
	}

	/**
	 * Diffs and prompts for a commit/rollback
	 */
	public function commit_prompt() {
		// diff the plugin
		$process = $this->run_process( 'git diff' );
		$output  = $process->getOutput();
		if ( $this->output->getVerbosity() >= OutputInterface::VERBOSITY_NORMAL ) {
			$this->output->writeln( $this->formatDebug( $output ) );
		}

		// status the plugin
		$process = $this->run_process( 'git status' );
		$output  = $process->getOutput();
		if ( $this->output->getVerbosity() >= OutputInterface::VERBOSITY_NORMAL ) {
			$lines      = $this->formatDebug( $output );
			$bad_staged = [];
			foreach ( $lines as $line ) {
				if ( ! preg_match( '!modified:[ \t]+(common|(vendor/[^ \t]*))!', $line, $matches ) ) {
					continue;
				}

				$bad_staged[] = $matches[1];
			}

			if ( $bad_staged ) {
				$this->io->error( 'The following would also be committed with this changeset:' );
				$this->io->listing( $bad_staged );
				$this->output->writeln( '<fg=red>Please resolve this issue and try again.</>' );
				die;
			}

			$this->output->writeln( $lines );
			$this->io->newline();
		}

		$do_commit      = $this->ask_for_confirmation( 'Do you wish to commit this version change?' );
		$commit_message = '';
		if ( $do_commit ) {
			do {
				$commit_message = $this->ask_for_string( 'What do you want to use as the commit message?', '' );
			} while ( ! $this->ask_for_confirmation( 'Are you sure you want to use: ' . $commit_message ) );

			$commit_message = escapeshellarg( $commit_message );

			$process = $this->run_process( "git commit -a -m {$commit_message}" );
			$output  = $process->getOutput();
			if ( $this->output->getVerbosity() >= OutputInterface::VERBOSITY_NORMAL ) {
				$this->output->writeln( $this->formatDebug( $output ) );
			} else {
				$this->output->writeln( $this->formatDebug( 'Commit done.' ) );
			}
		} else {
			$do_revert = $this->ask_for_confirmation( 'Do you wish to revert this version change?' );

			if ( $do_revert ) {
				$process = $this->run_process( 'git reset --hard' );
				$output  = $process->getOutput();
				if ( $this->output->getVerbosity() >= OutputInterface::VERBOSITY_NORMAL ) {
					$this->output->writeln( $this->formatDebug( $output ) );
				} else {
					$this->output->writeln( $this->formatDebug( 'Commit reverted.' ) );
				}
			}
		}
	}

	private function formatDebug( $output ) {
		$output = explode( "\n", $output );

		$skipping_until_next_section = false;
		$skipping_until_modified     = false;

		$lines = [];

		foreach ( $output as $line ) {
			if ( $skipping_until_next_section ) {
				if ( preg_match( '/^[ \t]/', $line ) || ! $line ) {
					continue;
				}

				$skipping_until_next_section = false;
			}

			if ( $skipping_until_modified ) {
				if (
					(
						preg_match( '/^[ \t]/', $line )
						|| ! $line
					)
					&& ! preg_match( '/^[ \t]+modified/', $line )
				) {
					continue;
				}

				$skipping_until_next_section = false;
			}

			if ( preg_match( '/(^---.*$)/', $line ) ) {
				$line = $line;
			} elseif ( preg_match( '/(^\+{3}.*$)/', $line ) ) {
				$line = $line;
			} elseif ( preg_match( '/(^\-.*$)/', $line ) ) {
				$line = "<fg=red>{$line}</>";
			} elseif ( preg_match( '/(^\+.*$)/', $line ) ) {
				$line = "<fg=green>{$line}</>";
			} elseif (
				preg_match( '/(^diff .*)/', $line )
				|| preg_match( '/(^index .*)/', $line )
				|| preg_match( '/^[ \t]modified:/', $line )
			) {
				$line = "<fg=yellow>{$line}</>";
			} elseif (
				preg_match( '/^Untracked files:/', $line )
				|| preg_match( '/^no changes added to commit/', $line )
				|| preg_match( '/^You are currently bisecting/', $line )
			) {
				$skipping_until_next_section = true;
				continue;
			} elseif (
			preg_match( '/^Changes not staged for commit:/', $line )
			) {
				$skipping_until_modified = true;
				$line                    = '<fg=yellow>Changes to commit:</>';
			}

			$lines[] = $line;
		}

		return $lines;
	}

	public function ask_for_string( $question, $default ) {
		$question = new Question( $question . ' ', $default );
		$value    = $this->question_helper->ask( $this->input, $this->output, $question );
		$this->io->newline();

		return $value;
	}

	public function ask_for_confirmation( $question, $default = false ) {
		$question = new ConfirmationQuestion( $question . ' ', $default );
		$value    = $this->question_helper->ask( $this->input, $this->output, $question );
		$this->io->newline();

		return $value;
	}

	public function ask_for_list( $question, $items, $default = null ) {
		$question = new ChoiceQuestion( $question . ' ', $items, $default );
		$question->setErrorMessage( 'Choice %s is invalid.' );
		$question->setAutocompleterValues( array( 'all', 'done' ) );
		$value = $this->question_helper->ask( $this->input, $this->output, $question );
		$this->io->newline();

		return $value;
	}

	public function text( $text ) {
		if ( ! $text ) {
			return;
		}

		$this->io->text( explode( "\n", $text ) );
	}

	/**
	 * Runs a process and returns the process object
	 */
	public function run_process( $command, $output_if_verbose = true, $timeout = 180 ) {
		$process = new Process( $command );
		$process->setTimeout( $timeout );

		// if we have verbose output, output the command as it is run
		if ( $this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE && $output_if_verbose ) {
			$process->run( function( $type, $buffer ) use ( $command ) {
				// Prints on the console what is command we are running (helps on logs)
				echo $command . "\n";
				if ( Process::ERR === $type ) {
					echo 'ERR > ' . $buffer;
				} else {
					echo $buffer;
				}
			} );
		} else {
			$process->run();
		}

		return $process;
	}

	/**
	 * Returns whether or not the current path has uncommitted git changes.
	 *
	 * @return bool
	 */
	protected function has_changes_in_current_path() {
		$process = $this->run_process( 'git diff-index HEAD --' );

		return (bool) $process->getOutput();
	}

	/**
	 * Gets the paths for all recursive submodules.
	 *
	 * @param bool $recursive Recurse into submodules.
	 *
	 * @return bool
	 */
	protected function get_submodule_paths( $recursive = true ) {
		if ( $recursive ) {
			$recursive = '--recursive';
		}

		$process = $this->run_process( 'git submodule status ' . $recursive . ' | awk \'{ print $2 }\'' );

		return explode( "\n", $process->getOutput() );
	}

	/**
	 * Gets a GitHub client object.
	 *
	 * @return Github\Client
	 */
	protected function get_github_client() {
		$github_user  = getenv( 'GITHUB_USER' );
		$github_token = getenv( 'GITHUB_OAUTH_TOKEN' );

		if ( empty( $github_user ) || empty( $github_token ) ) {
			$this->io->error( 'In order to use this command, you must have GITHUB_USER and GITHUB_OAUTH_TOKEN set in your ' . __TUT_DIR__ . '/.env file' );
		}

		if ( empty( $this->github_client ) ) {
			$this->github_client = new GitHub\Client();
			$this->github_client->authenticate(
				$github_user,
				$github_token,
				GitHub\Client::AUTH_CLIENT_ID
			);
		}

		return $this->github_client;
	}
}
