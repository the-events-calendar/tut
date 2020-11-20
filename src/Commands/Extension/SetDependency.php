<?php
namespace TUT\Commands\Extension;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output;
use TUT\Command as Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SetDependency extends Command {
	/**
	 * @var bool Should the command prompt for plugin selection?
	 */
	public $do_plugin_selection = false;

	/**
	 * @var string The org in which to create the repo.
	 */
	protected $org = 'mt-support';

	/**
	 * @var string The repo to create.
	 */
	protected $repo;

	protected function configure() {
		parent::configure();

		$this
			->setName( 'extension:set-dependency' )
			->setDescription( 'Set a plugin dependency to an extension repo on GitHub.' )
			->setHelp( 'Modifies or adds a dependency to an extension repo on GitHub.' )
			->addOption( 'repo', '', InputOption::VALUE_REQUIRED, 'Extension slug name of the .', null )
			->addOption( 'dependency', '', InputOption::VALUE_REQUIRED, 'Which dependency we are adding.', null )
			->addOption( 'ver', '', InputOption::VALUE_REQUIRED, 'To which version we are setting the dependency to.', null )
			->addOption( 'org', '', InputOption::VALUE_REQUIRED, 'Org in which to look for the extension.', null )
		;
	}

	protected function execute( InputInterface $input, OutputInterface $output ) {
		$repo       = $input->getOption( 'repo' );
		$dependency = $input->getOption( 'dependency' );
		$version    = $input->getOption( 'ver' );
		$org        = $input->getOption( 'org' );

		if ( ! $dependency = $this->get_plugin( $dependency ) ) {
			$output->writeln( '<fg=red;options=bold>Invalid dependency passed.</>' );
			return 1;
		}

		if ( false === strpos( $repo, 'tribe-ext-' ) ) {
			// Make sure the repo is prefixed.
			$this->repo = "tribe-ext-{$repo}";
		}

		if ( $org ) {
			$this->org = $org;
		}

		$output->write( '<fg=cyan>Checking out the extension to tmp...</>' );
		$path = $this->clone_repo();
		$output->write( '<fg=green>Done!</>' . "\n" );

		$plugin_register_file = $path . '/src/Tribe/Plugin_Register.php';
		if ( ! file_exists( $plugin_register_file ) ) {
			$output->writeln( '<fg=red;options=bold>Invalid dependency file, this extension doesnt follow the correct standards.</>' );
			return 1;
		}

		$output->write( '<fg=cyan>Setting dependency...</>' );
		$this->inject_dependency_value( $dependency, $version, $plugin_register_file );
		$output->write( '<fg=green>Done!</>' . "\n" );

		chdir( $path );

		$output->write( '<fg=cyan>Committing and pushing changes...</>' );
		$this->run_process( 'git commit -a -m ":link: Include dependency to \`' . $dependency->class_name . '\` version \`' . $version . '\`."' );
		$this->run_process( 'git push origin head' );
		$output->write( '<fg=green>Done!</>' . "\n" );

		$output->write( '<fg=cyan>Purging the temporary clone...</>' );
		$this->run_process( 'rm -rf ' . escapeshellarg( $path ) );
		$output->write( '<fg=green>Done!</>' . "\n" );

		$output->writeln( "<fg=green>Extension dependency set:</> https://github.com/{$this->org}/{$this->repo} - {$dependency->class_name}:{$version}" );
	}

	/**
	 * Shallow clones a repo with a specific branch
	 *
	 * @return string
	 */
	private function clone_repo() : string {
		$command = $this->getApplication()->find( 'git:clone' );

		$args = [
			'--repo'          => "{$this->org}/{$this->repo}",
			'--ref'           => 'master',
			'--path'          => sys_get_temp_dir() . '/' . uniqid( 'tribe-ext-', true ),
			'--single-branch' => true,
		];

		$output = new Output\BufferedOutput();

		$command->run( new ArrayInput( $args ), $output );

		$results = explode( "\n", $output->fetch() );

		// toss out the final line break
		array_pop( $results );

		return array_pop( $results );
	}

	protected function inject_dependency_value( $dependency, $version, $path ) {
		chdir( $path );

		$contents = file_get_contents( $path );
		preg_match_all( '/\'(?<plugin>[^\']*)\' ?=> ?\'(?<version>[^\']*)\',?/mi', $contents, $matches );

		$plugins      = $matches['plugin'];
		$versions     = $matches['version'];
		$dependencies = array_combine( $plugins, $versions );

		$dependencies[ $dependency->class_name ] = $version . '-dev';
		ksort( $dependencies );

		$replacement = [ "'parent-dependencies' => [" ];
		foreach ( $dependencies as $plugin => $version ) {
			$replacement[] = "\t\t\t'{$plugin}' => '$version',";
		}
		$replacement[] = "\t\t],";

		$contents = preg_replace( '/\'parent-dependencies\' => \[([^\]]*)\],/im', implode( "\n", $replacement ), $contents );

		file_put_contents( $path, $contents );
	}
}
