<?php

namespace TUT\Commands\Git;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class LabelSync extends GitAbstract {

	/**
	 * Configure the command
	 */
	public function configure() {
		parent::configure();

		$this->setName( 'git:label-sync' )
			->setDescription( 'Ensures that the given repo has all of the appropriate labels' )
			->setHelp( 'Ensures that the given repo has all of the appropriate labels' )
			->addOption( 'repo', null, InputOption::VALUE_REQUIRED, 'The name of the repo' );
	}

	/**
	 * Execute the command
	 *
	 * @param InputInterface  $input
	 * @param OutputInterface $output
	 */
	protected function execute( InputInterface $input, OutputInterface $output ) {
		$this->repo = empty( $input->getOption( 'repo' ) ) ? null : urldecode( $input->getOption( 'repo' ) );

		list( $this->org, $this->repo ) = $this->parse_repo_string();

		$this->github = GitHub::factory( $this->org );
		$label_config = $this->config_loader->get_config( 'product-labels.yml' );

		$current_labels = $this->github->repo_labels( $this->repo );
		$current_map    = [];

		foreach ( $current_labels as $label ) {
			$current_map[ $label->name ] = $label;
		}

		$results = [
			'created' => [],
			'updated' => [],
			'deleted' => [],
		];

		foreach ( $label_config['add'] as $label => $details ) {
			if ( ! empty( $current_map[ $label ] ) ) {
				if (
					$current_map[ $label ]->color === $details['color']
					&& $current_map[ $label ]->description === $details['description']
				) {
					continue;
				}

				$this->github->update_label( $this->repo, $label, [
					'name'        => $label,
					'color'       => $details['color'],
					'description' => $details['description'],
				] );

				$results['updated'][] = $label;
				continue;
			}

			$this->github->create_label( $this->repo, [
				'name'        => $label,
				'color'       => $details['color'],
				'description' => $details['description'],
			] );

			// we need to do an update right afterward to ensure descriptions are set
			$this->github->update_label( $this->repo, $label, [
				'name'        => $label,
				'color'       => $details['color'],
				'description' => $details['description'],
			] );

			$results['created'][] = $label;
		}

		foreach ( $label_config['remove'] as $label ) {
			if ( empty( $current_map[ $label ] ) ) {
				continue;
			}

			$this->github->delete_label( $this->repo, $label );

			$results['deleted'][] = $label;
		}

		$io = new SymfonyStyle( $input, $output );

		$io->title( 'Results' );
		$io->section( 'Created' );
		$io->listing( $results['created'] ?: [ 'N/A' ] );
		$io->section( 'Updated' );
		$io->listing( $results['updated'] ?: [ 'N/A' ] );
		$io->section( 'Deleted' );
		$io->listing( $results['deleted'] ?: [ 'N/A' ] );
	}
}
