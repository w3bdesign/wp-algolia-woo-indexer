<?php
/**
 * Main Algolia Verify Nonces class
 * Called from main plugin file class-algolia-woo-indexer.php
 *
 * @package algolia-woo-indexer
 */

namespace Algowoo;

/**
 * Abort if this file is called directly
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Algolia_Verify_Nonces' ) ) {
	/**
	 * Verify submitted nonces
	 */
	class Algolia_Verify_Nonces {

		/**
		 * Verify the settings form nonce before options are updated.
		 *
		 * Fails closed: returns false unless the request is a POST request
		 * containing a valid settings nonce.
		 *
		 * @return bool True if the settings nonce is present and valid, false otherwise.
		 */
		public static function verify_settings_nonce() {
			if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
				return false;
			}

			if ( ! isset( $_POST['algolia_woo_indexer_admin_api_nonce_name'] ) ) {
				return false;
			}

			$nonce = sanitize_text_field( wp_unslash( $_POST['algolia_woo_indexer_admin_api_nonce_name'] ) );

			return (bool) wp_verify_nonce( $nonce, 'algolia_woo_indexer_admin_api_nonce_action' );
		}

		/**
		 * Verify the "send products to Algolia" form nonce.
		 *
		 * Fails closed: returns false unless the request is a POST request
		 * containing both the hidden send_products_to_algolia field and a
		 * valid nonce.
		 *
		 * @return bool True if the send products nonce is present and valid, false otherwise.
		 */
		public static function verify_send_products_nonce() {
			if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
				return false;
			}

			if ( ! isset( $_POST['send_products_to_algolia'] ) || ! isset( $_POST['send_products_to_algolia_nonce_name'] ) ) {
				return false;
			}

			$nonce = sanitize_text_field( wp_unslash( $_POST['send_products_to_algolia_nonce_name'] ) );

			return (bool) wp_verify_nonce( $nonce, 'send_products_to_algolia_nonce_action' );
		}
	}
}
