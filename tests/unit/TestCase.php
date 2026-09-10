<?php
/**
 * Base test case.
 *
 * @package WordPressVIP\AiProviderForAskSage
 */

declare( strict_types=1 );

namespace WordPressVIP\AiProviderForAskSage\Tests\Unit;

use PHPUnit\Framework\TestCase as PHPUnit_TestCase;

/**
 * Resets credential env vars and filter stubs between tests.
 *
 * @since 1.1.1
 */
abstract class TestCase extends PHPUnit_TestCase {

	/**
	 * {@inheritDoc}
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->reset_test_state();
	}

	/**
	 * {@inheritDoc}
	 */
	protected function tearDown(): void {
		$this->reset_test_state();
		parent::tearDown();
	}

	/**
	 * Clears process-global credential and filter state.
	 */
	private function reset_test_state(): void {
		putenv( 'ASK_SAGE_API_KEY' );
		putenv( 'ASK_SAGE_BASE_URL' );
		$GLOBALS['ai_provider_for_ask_sage_test_filters'] = array();
	}
}
