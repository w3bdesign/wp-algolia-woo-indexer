<?php
/**
 * Exception thrown by the wp_die() test stub.
 *
 * @package algolia-woo-indexer
 */

namespace Algowoo\Tests\Support;

/**
 * Carries the arguments wp_die() was called with, so tests can assert on them.
 */
final class Wp_Die_Exception extends \RuntimeException {

	/**
	 * Title passed to wp_die().
	 *
	 * @var string
	 */
	public $title;

	/**
	 * Arguments passed to wp_die().
	 *
	 * @var array
	 */
	public $args;

	/**
	 * Create the exception.
	 *
	 * @param string $message Message passed to wp_die().
	 * @param string $title   Title passed to wp_die().
	 * @param array  $args    Arguments passed to wp_die().
	 */
	public function __construct( $message, $title, array $args ) {
		parent::__construct( $message );
		$this->title = $title;
		$this->args  = $args;
	}
}
