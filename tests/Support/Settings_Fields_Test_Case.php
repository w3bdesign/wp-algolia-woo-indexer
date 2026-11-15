<?php
/**
 * Shared helper for the AlgoliaSettingsFields tests.
 *
 * @package algolia-woo-indexer
 */

namespace Algowoo\Tests\Support;

use Algowoo\AlgoliaSettingsFields;

/**
 * Renders a settings field and returns its markup.
 */
abstract class Settings_Fields_Test_Case extends Test_Case {

	/**
	 * Render a field method and return its output.
	 *
	 * @param string $method Renderer method name.
	 * @return string
	 */
	protected function render( $method ) {
		return $this->capture_output( array( AlgoliaSettingsFields::class, $method ) );
	}
}
