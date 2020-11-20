<?php

use TUT\Commands\VersionCommand;

class VersionCommandTest extends CliTestCase {

	/**
	 * @test
	 * it should ask for version and branch if not passed as arguments
	 */
	public function it_should_ask_for_version_and_branch_if_not_passed_as_arguments() {
		$answers = [
			'some-version',
			'release/some-branch',
			'y',
			'y',
			'the-events-calendar',
			'y',
		];

		$commandTester = $this->execCommand( new VersionCommand( 'version' ), 'version', $answers, [ '--dry-run' => true ] );

		$this->assertRegExp( '/(W|w)hat version.*?/', $commandTester->getDisplay() );
		$this->assertRegExp( '/which branch.*?/', $commandTester->getDisplay() );
	}

	/**
	 * @test
	 * it should ask for confirmation about version and branch answers
	 */
	public function it_should_ask_for_confirmation_about_version_and_branch_answers() {
		$answers = [
			'some-version',
			'release/some-branch',
			'y',
			'y',
			'the-events-calendar',
			'y',
		];

		$commandTester = $this->execCommand( new VersionCommand( 'version' ), 'version', $answers, [ '--dry-run' => true ] );

		$this->assertRegExp( '/sure.*version.*correct.*?/', $commandTester->getDisplay() );
		$this->assertRegExp( '/sure.*branch.*correct.*?/', $commandTester->getDisplay() );
	}

	/**
	 * @test
	 * it should allow for a version input if not sure
	 */
	public function it_should_allow_for_a_version_input_if_not_sure() {
		$answers = [
			'some-version',
			'release/some-branch',
			'n',
			'some-other-version',
			'y',
			'the-events-calendar',
			'y',
		];

		$commandTester = $this->execCommand( new VersionCommand( 'version' ), 'version', $answers, [ '--dry-run' => true ] );

		$this->assertRegExp( '/sure.*version.*correct.*?/', $commandTester->getDisplay() );
		$this->assertRegExp( '/(W|w)hat version then.*?/', $commandTester->getDisplay() );
	}

	/**
	 * @test
	 * it should allow for a branch input if not sure
	 */
	public function it_should_allow_for_a_branch_input_if_not_sure() {
		$answers = [
			'some-version',
			'release/some-branch',
			'y',
			'n',
			'some/other-branch',
			'y',
			'the-events-calendar',
			'y',
		];

		$commandTester = $this->execCommand( new VersionCommand( 'version' ), 'version', $answers, [ '--dry-run' => true ] );

		$this->assertRegExp( '/sure.*branch.*correct.*?/', $commandTester->getDisplay() );
		$this->assertRegExp( '/(W|w)hich branch then.*?/', $commandTester->getDisplay() );
	}

	/**
	 * @test
	 * it should allow choosing more than one plugin if not passed as option
	 */
	public function it_should_allow_choosing_more_than_one_plugin_if_not_passed_as_option() {
		$answers = [
			'some-version',
			'release/some-branch',
			'y',
			'n',
			'some/other-branch',
			'y',
			'the-events-calendar',
			'events-pro',
			'events-community',
			'y'
		];

		$commandTester = $this->execCommand( new VersionCommand( 'version' ), 'version', $answers, [ '--dry-run' => true ] );

		$this->assertRegExp( '/sure.*plugins.*the-events-calendar, events-pro, events-community.*?/', $commandTester->getDisplay() );
	}

	/**
	 * @test
	 * it should allow resetting the target plugins if not sure
	 */
	public function it_should_allow_resetting_the_target_plugins_if_not_sure() {
		$answers = [
			'some-version',
			'release/some-branch',
			'y',
			'n',
			'some/other-branch',
			'y',
			'the-events-calendar',
			'events-pro',
			'events-community',
			'n',
			'the-events-calendar',
			'events-eventbrite',
			'y',
		];

		$commandTester = $this->execCommand( new VersionCommand( 'version' ), 'version', $answers, [ '--dry-run' => true ] );

		$this->assertRegExp( '/sure.*plugins.*the-events-calendar, events-eventbrite.*?/', $commandTester->getDisplay() );
	}

	/**
	 * @test
	 * it should prune invalid plugins from answers
	 */
	public function it_should_prune_invalid_plugins_from_answers() {
		$answers = [
			'some-version',
			'release/some-branch',
			'y',
			'n',
			'some/other-branch',
			'y',
			'the-events-calendar',
			'events-pro',
			'foo',
			'bar',
			'y',
		];

		$commandTester = $this->execCommand( new VersionCommand( 'version' ), 'version', $answers, [ '--dry-run' => true ] );

		$this->assertRegExp( '/sure.*plugins.*the-events-calendar, events-pro.*?/', $commandTester->getDisplay() );
		$this->assertNotRegExp( '/sure.*plugins.*foo.*?/', $commandTester->getDisplay() );
		$this->assertNotRegExp( '/sure.*plugins.*bar.*?/', $commandTester->getDisplay() );
	}
}
