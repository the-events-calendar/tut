<?php

namespace TUT;

class CLI {
	/**
	 * Product plugins
	 */
	public $available_plugins = [
		'the-events-calendar'      => [
			'bootstrap' => 'the-events-calendar.php',
			'main' => 'src/Tribe/Main.php',
			'version' => 'const VERSION',
		],
		'events-community'         => [
			'bootstrap' => 'tribe-community-events.php',
			'main' => 'src/Tribe/Main.php',
			'version' => 'const VERSION',
		],
		'events-community-tickets' => [
			'bootstrap' => 'events-community-tickets.php',
			'main' => 'src/Tribe/Main.php',
			'version' => 'const VERSION',
		],
		'events-eventbrite'        => [
			'bootstrap' => 'tribe-eventbrite.php',
			'main' => 'src/Tribe/Main.php',
			'version' => 'pluginVersion',
		],
		'events-facebook'          => [
			'bootstrap' => 'the-events-calendar-facebook-importer.php',
			'main' => 'src/Tribe/Importer.php',
			'version' => 'const VERSION',
		],
		'events-filterbar'         => [
			'bootstrap' => 'the-events-calendar-filter-view.php',
			'main' => 'src/Tribe/View.php',
			'version' => 'const VERSION',
		],
		'events-importer-ical'     => [
			'bootstrap' => 'the-events-calendar-ical-importer.php',
			'main' => 'src/Tribe/Main.php',
			'version' => 'currentVersion',
		],
		'events-pro'               => [
			'alias'     => [ 'events-calendar-pro' ],
			'bootstrap' => 'events-calendar-pro.php',
			'main'      => 'src/Tribe/Main.php',
			'version'   => 'const VERSION',
		],
		'events-virtual'               => [
			'alias'     => [ 'events-virtual' ],
			'bootstrap' => 'events-virtual.php',
			'main'      => 'src/Tribe/Plugin.php',
			'version'   => 'const VERSION',
		],
		'event-tickets'            => [
			'bootstrap' => 'event-tickets.php',
			'main' => 'src/Tribe/Main.php',
			'version' => 'const VERSION',
		],
		'event-tickets-plus'       => [
			'bootstrap' => 'event-tickets-plus.php',
			'main' => 'src/Tribe/Main.php',
			'version' => 'const VERSION',
		],
		'advanced-post-manager'    => [
			'bootstrap' => 'tribe-apm.php',
			'main' => 'tribe-apm.php',
		],
		'image-widget'             => [
			'bootstrap' => 'image-widget.php',
			'main' => 'image-widget.php',
			'version' => 'const VERSION',
		],
		'image-widget-plus'        => [
			'bootstrap' => 'image-widget-plus.php',
			'main' => 'src/Tribe/Main.php',
			'version' => 'const VERSION',
		],
		'gigpress' => [
			'bootstrap' => 'gigpress.php',
			'main' => 'gigpress.php',
			'version' => "define('GIGPRESS_VERSION')",
		]
	];

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
		'long' => [],
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
	 * @var bool
	 */
	public $quiet = false;

	public function __construct() {
		$this->origin_dir = getcwd();
		$this->register_common_args();
		$this->args = getopt( $this->supported_args['short'], $this->supported_args['long'] );
		$this->parse_common_args();
		$this->parse_args();
		$this->welcome();

		// run this only if no plugins have been specified on the command line
		if ( empty( $this->selected_plugins ) ) {
			if ( $this->auto_select_plugins ) {
				$this->selected_plugins = array_keys( $this->available_plugins );
			} else {
				$this->select_plugins();
			}
		}
	}

	/**
	 * Parse command line arguments.
	 */
	public function parse_args() {
	}

	/**
	 * Outputs a welcome message
	 */
	public function welcome() {
		echo "******************************************************\n";
		echo "*             Welcome to the handy-dandy             *\n";
		echo "*" . str_pad( $this->friendly_script_name, 52, ' ', STR_PAD_BOTH ) . "*\n";
		echo "*                                                    *\n";
		echo "* I'm going to ask some questions as we get rolling. *\n";
		echo "*       And then things are going to get real.       *\n";
		echo "******************************************************\n";
		echo "\n";
	}

	/**
	 * Gets a user's input
	 */
	public function read() {
		$handle = fopen( 'php://stdin', 'r' );
		$input = trim( fgets( $handle ) );
		return $input;
	}

	/**
	 * Fetches the current branch
	 */
	public function get_branch() {
		// get the branch info for the repo
		$branch_info = shell_exec( 'git branch' );

		// parse the branch info to get the current working branch
		preg_match( '/\*\s+([^\s]+)/', $branch_info, $matches );

		return $matches[1];
	}

	/**
	 * Provides an interactive plugin selector
	 */
	public function select_plugins() {
		$choice = null;
		$plugin_keys = array_keys( $this->available_plugins );

		while ( 'done' !== $choice ) {
			if ( null === $choice ) {
				echo "Select the plugins you want to run this script on:\n";
			} else {
				echo "----------------------------------------\n";
				echo "Would you like to version more plugins?\n";
			}

			$counter = 1;
			foreach ( $plugin_keys as $plugin ) {
				$selected = '';

				if ( in_array( $plugin, $this->selected_plugins ) ) {
					$selected = '(selected)';
				}

				$prefix = '   ';
				if ( strlen( $counter ) > 1 ) {
					$prefix = '  ';
				}

				echo "{$prefix}{$counter}. {$plugin} {$selected}\n";
				$counter++;
			}//end foreach

			echo " all. Select all plugins\n";
			echo "done. I'm finished selecting plugins\n";

			$choice = $this->read();

			if ( 'all' === $choice ) {
				$this->selected_plugins = $plugin_keys;
			} elseif ( 'done' !== $choice ) {
				if ( ! is_numeric( $choice ) ) {
					continue;
				}

				$index = array_search( $plugin_keys[ $choice - 1 ], $this->selected_plugins );
				if ( false !== $index ) {
					unset( $this->selected_plugins[ $index ] );

					// re-index the array
					$this->selected_plugins = array_values( $this->selected_plugins );
				} else {
					$this->selected_plugins[] = $plugin_keys[ $choice - 1];
				}
			}//end elseif
		}//end while

		if ( empty( $this->selected_plugins ) ) {
			echo "*******************************************\n";
			echo "You must select at least one plugin!\n";
			echo "Bailing.\n";
			echo "*******************************************\n";
			exit;
		}
	}

	/**
	 * Prompts user for a simple input
	 */
	public function prompt( $question ) {
		echo "\n$question\n";
		return $answer = $this->read();
	}

	/**
	 * Prompts user for a confirmation
	 */
	public function confirm( $confirm ) {
		echo "\n{$confirm} (y/n)\n";
		$answer = $this->read();
		return 'y' === strtolower( $answer );
	}

	/**
	 * Prompts user for a simple input and confirmation
	 */
	public function prompt_and_confirm( $question, $confirm ) {
		$satisfied = false;
		while ( ! $satisfied ) {
			$choice = $this->prompt( $question );
			$satisfied = $this->confirm(
				sprintf(
					$confirm,
					$choice
				)
			);
		}

		return $choice;
	}

	/**
	 * Prompts user for a version number
	 */
	public function version_prompt( $question = 'What version?' ) {
		return $this->prompt_and_confirm(
			$question,
			'Are you sure that %s is the correct version?'
		);
	}

	/**
	 * Prompts user for a branch
	 */
	public function branch_prompt( $question = 'What branch?' ) {
		return $this->prompt_and_confirm(
			$question,
			'Are you sure that %s is the correct branch?'
		);
	}

	/**
	 * Convert a version number to JSON-compatible version
	 *
	 * @param string $version Version number to convert
	 *
	 * @return string
	 */
	public function json_compatible_version( $version ) {
		$json_version = $version;

		if ( substr_count( $version, '.' ) < 2 ) {
			preg_match( '/([0-9]*\.[0-9]*)(.*)/', $version, $matches );
			$json_version = "{$matches[1]}.0{$matches[2]}";
		}

		return $json_version;
	}

	/**
	 * Checks out, pulls, and updates submodules for the given branch
	 *
	 * @param string $branch Branch name to pull/sync
	 */
	public function sync_branch( $branch ) {
		// checkout the desired branch
		echo shell_exec( "git checkout {$branch}" );

		// pull the latest changes from the branch
		echo shell_exec( 'git pull' );

		$this->update_submodules();
	}

	/**
	 * Fetches changed views
	 */
	public function changed_views( $plugin ) {
		$tag_hash = $this->get_revision_hash( $this->last_tag() );
		$views = trim( shell_exec( 'git diff --name-only ' . $tag_hash . ' HEAD|grep "src/views"|grep -v admin-views' ), "\n" );

		if ( $views ) {
			$views = explode( "\n", $views );

			$view_data = array();

			foreach ( $views as &$view ) {
				if ( ! preg_match( '!\.php$!', $view ) ) {
					continue;
				}

				$bootstrap = file_get_contents( $this->available_plugins[ $plugin ]['bootstrap'] );
				preg_match( '/Version:\s*(.*)/', $bootstrap, $matches );

				$bootstrap_version = $matches[1];

				$file = file_get_contents( $view );
				preg_match( '/@version\s*(.*)/', $file, $matches );

				$view_version = empty( $matches[1] ) ? null : $matches[1];

				$view_data[ $view ] = array(
					'plugin'            => $plugin,
					'bootstrap-version' => $bootstrap_version,
					'view-version'      => $view_version,
				);
			}

			return $view_data;
		}
	}

	/**
	 * Returns the most recent reachable tag.
	 *
	 * @return string
	 */
	public function last_tag() {
		return trim( shell_exec( 'git describe --tags $(git rev-list --tags --max-count=1)' ), "\n" );
	}

	/**
	 * Given a branch or tag name, returns the corresponding hash.
	 *
	 * @param $reference
	 *
	 * @return string
	 */
	public function get_revision_hash( $reference ) {
		return trim( shell_exec( 'git rev-list -n 1 ' . $reference ), "\n" );
	}

	/**
	 * Diffs and prompts for a commit/rollback
	 */
	public function commit_prompt() {
		// diff the plugin
		echo shell_exec( 'git diff' );

		// status the plugin
		echo shell_exec( 'git status' );

		echo "\n";
		$do_commit = $this->confirm( 'Do you wish to commit this version change?' );
		if ( $do_commit ) {
			$commit_message = $this->prompt_and_confirm(
				'What do you want to use as the commit message?',
				'Are you sure you want to use: %s'
			);

			$commit_message = escapeshellarg( $commit_message );

			echo shell_exec( "git commit -a -m {$commit_message}" );
		} else {
			$do_revert = $this->confirm( 'Do you wish to revert this version change?' );

			if ( $do_revert ) {
				echo shell_exec( 'git reset --hard' );
			}
		}
	}

	/**
	 * Updates the submodules in the current directory
	 */
	public function update_submodules() {
		// if there are submodules, recursively update them
		if ( file_exists( '.gitmodules' ) ) {
			echo shell_exec( 'git submodule update --recursive --init' );
		}
	}

	/**
	 * Whether a plugin slug is among the available ones or not.
	 *
	 * @param string $plugin_slug
	 * @param bool   $echo Whether a message should be echoed to the user to report the missing slug.
	 *
	 * @return bool
	 */
	public function is_available_plugin( $plugin_slug ) {
		$available = array_key_exists( $plugin_slug, $this->available_plugins );
		if ( ! $available && ! $this->quiet ) {
			echo "Plugin'$plugin_slug' is not available for this command.";
		}

		return $available;
	}

	/**
	 * Registers the args common to all commands.
	 */
	protected function register_common_args() {
		$this->supported_args['long'][] = 'plugins::';
	}

	/**
	 * @param array $args
	 */
	public function set_args( array $args = array() ) {
		$this->args = $args;
	}

	/**
	 * @return array
	 */
	public function get_selected_plugins() {
		return $this->selected_plugins;
	}

	/**
	 * @param bool $quiet
	 */
	public function quiet($quiet = true) {
		$this->quiet = $quiet;
	}

	public function parse_common_args() {
		if ( ! empty( $this->args['plugins'] ) ) {
			// expect something like `--plugins=event-tickets,event-tickets-plus`
			$this->selected_plugins = explode( ',', $this->args['plugins'] );
			// prune unavailable plugins from the CLI selection
			$this->selected_plugins = array_filter( $this->selected_plugins, [ $this, 'is_available_plugin' ] );
		}
	}
}
