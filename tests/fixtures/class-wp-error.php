<?php
/**
 * Minimal stand-in for WordPress's WP_Error.
 *
 * @package algolia-woo-indexer
 */

/**
 * Holds one error code and message, which is all the plugin reads.
 */
class WP_Error {

	/**
	 * Error code.
	 *
	 * @var string
	 */
	private $code;

	/**
	 * Error message.
	 *
	 * @var string
	 */
	private $message;

	/**
	 * Create the error.
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 */
	public function __construct( $code = '', $message = '' ) {
		$this->code    = $code;
		$this->message = $message;
	}

	/**
	 * Return the error code.
	 *
	 * @return string
	 */
	public function get_error_code() {
		return $this->code;
	}

	/**
	 * Return the error message.
	 *
	 * @return string
	 */
	public function get_error_message() {
		return $this->message;
	}
}
