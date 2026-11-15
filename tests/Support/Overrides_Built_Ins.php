<?php
/**
 * Test helpers for overriding PHP built-in functions.
 *
 * @package algolia-woo-indexer
 */

namespace Algowoo\Tests\Support;

/**
 * Gives tests $this-> access to the shared Built_In_Functions instance.
 */
trait Overrides_Built_Ins {

	/**
	 * The shared registry the namespaced shims delegate to.
	 *
	 * @return Built_In_Functions
	 */
	private function built_in_functions() {
		global $algowoo_built_ins;

		return $algowoo_built_ins;
	}

	/**
	 * Replace a built-in function for plugin code until the test ends.
	 *
	 * @param string   $name     Built-in function name, without a namespace.
	 * @param callable $callback Replacement receiving the original arguments.
	 * @return void
	 */
	protected function override_built_in( $name, callable $callback ) {
		$this->built_in_functions()->override( $name, $callback );
	}

	/**
	 * Make filter_input( INPUT_POST, ... ) read from the given array.
	 *
	 * Mirrors the real behaviour for FILTER_DEFAULT: a missing field is null,
	 * and a value whose shape does not match FILTER_REQUIRE_ARRAY is false.
	 *
	 * @param array $post Simulated POST data.
	 * @return void
	 */
	protected function set_post_data( array $post ) {
		$this->override_built_in(
			'filter_input',
			static function ( $type, $var_name, $filter = FILTER_DEFAULT, $options = 0 ) use ( $post ) {
				unset( $filter );
				if ( INPUT_POST !== $type || ! array_key_exists( $var_name, $post ) ) {
					return null;
				}

				$value         = $post[ $var_name ];
				$require_array = is_int( $options ) && ( $options & FILTER_REQUIRE_ARRAY );
				if ( is_array( $value ) !== (bool) $require_array ) {
					return false;
				}

				return $value;
			}
		);
	}

	/**
	 * Remove every built-in override.
	 *
	 * @return void
	 */
	protected function reset_built_ins() {
		$this->built_in_functions()->reset();
	}
}
