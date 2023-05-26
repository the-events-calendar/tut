<?php
namespace TUT\Commands\I18n;

use Orhanerday\OpenAi\OpenAi;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Gettext\Loader\PoLoader;
use Gettext\Generator\PoGenerator;
use TUT\Command as Command;

class Translate extends Command {
	/**
	 * @var string The branch in which the version is being prepared
	 */
	protected string $branch;

	/**
	 * @var bool Has a common/ directory
	 */
	private bool $has_common = false;

	protected int $retries = 5;

	protected string $lang = '';

	protected string $model = 'gpt-3.5-turbo';

	protected $loader;
	protected $generator;
	protected $translations;

	/**
	 * @var bool Should the command prompt for plugin selection?
	 */
	public $do_plugin_selection = true;

	/**
	 * Returns the package.json data of the current plugin.
	 *
	 * @return null|mixed
	 */
	private function get_plugin_package_data() {
		if ( ! $this->plugin_package_exists() ) {
			return null;
		}

		$json_string = file_get_contents( 'package.json' );

		if ( ! $this->is_json( $json_string ) ) {
			return null;
		}

		return json_decode( $json_string );
	}

	/**
	 * Determines if a given string is JSON or not.
	 *
	 * @param string $value
	 *
	 * @return bool
	 */
	protected function is_json( string $value ): bool {
		json_decode( $value );
		return json_last_error() === JSON_ERROR_NONE;
	}

	/**
	 * Returns the source command based on OS.
	 *
	 * @return string The source command for the OS PHP is running on.
	 */
	private function plugin_package_exists() {
		return file_exists( 'package.json' );
	}

	protected function configure() {
		parent::configure();

		$this->setName( 'i18n:translate' )
				 ->setDescription( 'Translates a pot file with GPT-3.5-turbo.' )
				 ->setHelp( 'Translates a pot file with GPT-3.5-turbo.' )
				 ->addOption( 'file', null, InputOption::VALUE_REQUIRED, 'Path to pot file.' )
				 ->addOption( 'lang', null, InputOption::VALUE_REQUIRED, 'Language to translate to.' );
	}

	protected function execute( InputInterface $input, OutputInterface $output ) {
		$this->lang = $this->lang ?: $input->getOption( 'lang' );
		$this->file = $this->file ?: $input->getOption( 'file' );

		foreach ( $this->selected_plugins as $plugin ) {
			$this->io->title( $plugin->name );

			$plugin_dir = $this->get_plugin_dir( $plugin );

			if ( ! file_exists( $plugin_dir ) ) {
				$this->io->warning( "The {$plugin->name} directory doesn't exist here!" );
				continue;
			}

			$open_ai_key = getenv('OPENAI_API_KEY');
			$this->open_ai = new OpenAi( $open_ai_key );

			// cd into the plugin directory
			chdir( $plugin_dir );

			$this->parse_pot_file();
			$this->translate();
			//$this->chunk_and_translate( $this->file );

			// go back up to the plugins directory
			chdir( '../' );

			$output->writeln( '<info>-------------------</info>' . "\n" );
		}

		$this->io->success( 'DONE' );
	}

	public function parse_pot_file() {
		$this->loader = new PoLoader();
		$this->translations = $this->loader->loadFile( $this->file );
	}

	public function translate() {
		$count = 0;
		$until = PHP_INT_MAX;
		foreach ( $this->translations as $translation ) {
			$original = $translation->getOriginal();
			$plural   = $translation->getPlural();

			if ( $original ) {
				$translation->translate( $this->translate_item( $translation, $original ) );
			}

			if ( $plural ) {
				$translation->translatePlural( $this->translate_item( $translation, $plural ) );
			}

			$count++;
			if ( $count > $until ) {
				break;
			}
		}

		$this->generator = new PoGenerator();
		$this->generator->generateFile( $this->translations, $this->file . '.' . $this->lang );
	}

	protected function translate_item( $translation, $item ) {
		$message = 'I will provide you a string. ';
		if ( $translation->getContext() ) {
			$message .= 'The string has the following context: "' . $translation->getContext() . '". ';
		}

		if ( $translation->getExtractedComments() ) {
			$message .= 'The string has the following comments: ' . "\n* " . implode( "\n *", $translation->getExtractedComments()->toArray() ) .  "\n";
		}

		$message .= "Don't include commentary, just translate the string.\n";

		$message .= "\n" . 'Here is the string that you need to translate from English to ' . $this->lang . ':' . "\n" . $item;

		$result = $this->open_ai->chat([
			'model' => $this->model,
			'messages' => [
				[
					'role' => 'system',
					'content' => 'You are an expert WordPress .pot file translator.',
				],
				[
					'role' => 'user',
					'content' => $message,
				],
			],
		]);

		$result = json_decode( $result );

		return $result->choices[0]->message->content;
	}

	/**
	 * Runs build commands in parallel
	 */
	private function chunk_and_translate( $pot_file ) {
		$open_ai_key = getenv('OPENAI_API_KEY');
		$open_ai = new OpenAi( $open_ai_key );

		// Read the contents of the .pot file into a string
		$potFileContents = file_get_contents( $pot_file );

		$new_file = $pot_file . '.' . $this->lang;
		// Clear current file.
		unlink( $new_file );

		// Split the string into an array of lines
		$lines = explode( "\n", $potFileContents );

		// Initialize an array to store the sections
		$sections = [];

		// Initialize a counter to keep track of the current section
		$sectionCounter = 0;

		$groupCounter = 0;
		$chunkSize = 15;
		$tracking_msgstr = false;
		$finished_capturing_header = false;
		$index = 0;
		$inner_index = '';
		$reset_index = false;
		$indexes = [];

		// Loop through the lines and process each one
		foreach ( $lines as $line ) {
			if ( ! $finished_capturing_header && ! preg_match( '/\#[:\.] .*$/', $line ) ) {
				file_put_contents( $new_file, $line . "\n", FILE_APPEND );
				continue;
			} elseif ( ! $finished_capturing_header ) {
				$finished_capturing_header = true;
			}

			if ( $reset_index && preg_match( '/\#[:\.] .*$/', $line ) ) {
				$sections[ $index ]['item'] = $indexes;
				$indexes = [];
				$index++;
				$reset_index = false;
			}

			if ( preg_match( '/\#[:\.] .*$/', $line ) ) {
				$indexes[] = $line;
				continue;
			}

			if ( preg_match( '/^msgctxt /', $line ) ) {
				$reset_index = true;
				$inner_index = 'msgctxt';
				$line = str_replace( "$inner_index ", '', $line );
				$line = trim( $line, '"' );
				$sections[ $index ][ $inner_index ][] = $line;
				continue;
			}

			if ( preg_match( '/^msgid /', $line ) ) {
				$reset_index = true;
				$inner_index = 'msgid';
				$line = str_replace( "$inner_index ", '', $line );
				$line = trim( $line, '"' );
				$sections[ $index ][ $inner_index ][] = $line;
				continue;
			}

			if ( preg_match( '/^msgid_plural /', $line ) ) {
				$reset_index = true;
				$inner_index = 'msgid_plural';
				$line = str_replace( "$inner_index ", '', $line );
				$line = trim( $line, '"' );
				$sections[ $index ][ $inner_index ][] = $line;
				continue;
			}

			if ( preg_match( '/^msgstr\[0\] /', $line ) ) {
				$reset_index = true;
				$inner_index = 'msgstr[0]';
				$line = str_replace( "$inner_index ", '', $line );
				$line = trim( $line, '"' );
				$sections[ $index ][ $inner_index ][] = $line;
				continue;
			}

			if ( preg_match( '/^msgstr\[1\] /', $line ) ) {
				$reset_index = true;
				$inner_index = 'msgstr[1]';
				$line = str_replace( "$inner_index ", '', $line );
				$line = trim( $line, '"' );
				$sections[ $index ][ $inner_index ][] = $line;
				continue;
			}

			if ( preg_match( '/^msgstr /', $line ) ) {
				$reset_index = true;
				$inner_index = 'msgstr';
				$line = str_replace( "$inner_index ", '', $line );
				$line = trim( $line, '"' );
				$sections[ $index ][ $inner_index ][] = $line;
				continue;
			}

			if ( ! empty( $line ) ) {
				$reset_index = true;
				$line = trim( $line, '"' );
				$sections[ $index ][ $inner_index ][] = $line;
			}

			/*
			// If the line is 'msgstr ""', start a new section
			if ( $line === 'msgstr ""' ) {
				$tracking_msgstr = true;
			}

			if ( $tracking_msgstr && $line === '' ) {
				$tracking_msgstr = false;
				$groupCounter++;
				if ( $groupCounter >= $chunkSize ) {
					$groupCounter = 0;
					$sectionCounter++;
				}
			}

			// Add the current line to the current section
			$sections[ $sectionCounter ][] = $line;
			*/
		}
		$sections[ $index ]['item'] = $indexes;
		var_dump( $sections );

		foreach ( $sections as $section => $parts ) {
			file_put_contents( $new_file, implode( "\n", $parts['item'] ) . "\n", FILE_APPEND );

			if ( ! empty( $parts['msgctxt'] ) ) {
				file_put_contents( $new_file, 'msgctxt "' . implode( "\n", $parts['msgctxt'] ) . '"' . "\n", FILE_APPEND );
			}

			if ( ! empty( $parts['msgid'] ) ) {
				$did_heading = false;
				foreach ( $parts['msgid'] as $msgid ) {
					if ( ! $did_heading ) {
						file_put_contents( $new_file, 'msgid "' . $msgid . '"' . "\n", FILE_APPEND );
						$did_heading = true;
						continue;
					}
					file_put_contents( $new_file, '"' . $msgid . '"' . "\n", FILE_APPEND );
				}
			}

			if ( ! empty( $parts['msgid_plural'] ) ) {
				$did_heading = false;
				foreach ( $parts['msgid_plural'] as $msgid_plural ) {
					if ( ! $did_heading ) {
						file_put_contents( $new_file, 'msgid_plural "' . $msgid_plural . '"' . "\n", FILE_APPEND );
						$did_heading = true;
						continue;
					}
					file_put_contents( $new_file, '"' . $msgid_plural . '"' . "\n", FILE_APPEND );
				}
			}

			if ( ! empty( $parts['msgstr[0]'] ) ) {
				$did_heading = false;
				foreach ( $parts['msgstr[0]'] as $msgstr ) {
					if ( ! $did_heading ) {
						file_put_contents( $new_file, 'msgstr[0] "' . $msgstr . '"' . "\n", FILE_APPEND );
						$did_heading = true;
						continue;
					}
					file_put_contents( $new_file, '"' . $msgstr . '"' . "\n", FILE_APPEND );
				}
			}

			if ( ! empty( $parts['msgstr[1]'] ) ) {
				$did_heading = false;
				foreach ( $parts['msgstr[1]'] as $msgstr ) {
					if ( ! $did_heading ) {
						file_put_contents( $new_file, 'msgstr[1] "' . $msgstr . '"' . "\n", FILE_APPEND );
						$did_heading = true;
						continue;
					}
					file_put_contents( $new_file, '"' . $msgstr . '"' . "\n", FILE_APPEND );
				}
			}


			if ( empty( $parts['msgstr[0]'] ) && empty( $parts['msgstr[1]'] ) ) {
				$did_heading = false;
				foreach ( $parts['msgstr'] as $msgstr ) {
					if ( ! $did_heading ) {
						file_put_contents( $new_file, 'msgstr "' . $msgstr . '"' . "\n", FILE_APPEND );
						$did_heading = true;
						continue;
					}
					file_put_contents( $new_file, '"' . $msgstr . '"' . "\n", FILE_APPEND );
				}
			}

			file_put_contents( $new_file, "\n", FILE_APPEND );
		}

		die;

		$start = microtime( true );
		// Loop through the sections and process each one
		$section_start = microtime( true );
		$section_count = 0;
		foreach ( $sections as $index => $section ) {
			$section_string = implode( "\n", $section );

			if ( $section_count >= 1 ) {
				$section_count = 0;
				$current_time = microtime( true );
				if ( $current_time - $section_start < 30 ) {
					sleep( 30 );
				}
				$section_start = microtime( true );
			}

			/*
			$result = $open_ai->createEdit( [
				'model' => $this->model,
				'input' => $section_string,
				//'instruction' => 'This is a section of a .pot file. Translate all msgid values from English to ' . $this->lang . ' and place it in the corresponding msgstr. Be sure not to translate currencies or URLs. Retain all of the other text. Do not translate the strings \"The Events Calendar\" or \"Event Tickets\" (case matters).',
				'instruction' => 'Translate this .pot file to ' . $this->lang . '.',
				//'temperature' => 0.3, // How much creative liberty should be used. Higher values means more creative license.
			] );
			*/
			$result = $open_ai->chat([
				'model' => $this->model,
				'messages' => [
					[
						'role' => 'system',
						'content' => 'You are an expert WordPress .pot file translator.',
					],
					[
						'role' => 'user',
						'content' => 'The .pot file I am giving you has English values in the msgid strings. DO NOT CHANGE THE msgid strings. However, translate the msgid values to ' . $this->lang . ' and place the translated text in the corresponding msgstr strings. Do not translate currencies or URLs. Here is the .pot file:' . "\n" . $section_string,
					],
				],
			]);

			$section_count++;

			$result = json_decode( $result );

			$sections[ $index ] = $result->choices[0]->message->content;
			file_put_contents( $new_file, $result->choices[0]->message->content . "\n", FILE_APPEND );
			die;
		}
		$end = microtime( true );

		echo "\nMade $index requests to OpenAI in \n";

		$new_pot_file = implode( "\n", $sections );
		//file_put_contents( $pot_file . '.' . $this->lang, $new_pot_file );
	}
}
