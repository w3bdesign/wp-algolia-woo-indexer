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
			 * Verify nonces before we update options and settings           *
			 *
			 * @return void
			 */
		public static function verify_settings_nonce() {
			/**
			 * Filter incoming nonces and values
			 */
			$settings_nonce = filter_input( INPUT_POST, 'algolia_woo_indexer_admin_api_nonce_name', FILTER_DEFAULT );

			/**
			 * Return boolean depending on if the nonce has been set
			 */
			if ( ! isset( $settings_nonce ) ) {
				return;
			}

		}

			/**
			 * Check if we are sending products to Algolia
			 *
			 * @return bool
			 */
		public static function verify_send_products_nonce() {
			/**
			 * Filter incoming nonces and values
			 */
			$send_products_nonce      = filter_input( INPUT_POST, 'send_products_to_algolia_nonce_name', FILTER_DEFAULT );
			$send_products_to_algolia = filter_input( INPUT_POST, 'send_products_to_algolia', FILTER_DEFAULT );

			/**
			 * Display error and die if nonce is not verified and does not pass security check
			 * Also check if the hidden value field send_products_to_algolia is set
			 */

			if ( ! wp_verify_nonce( $send_products_nonce, 'send_products_to_algolia_nonce_action' ) && isset( $send_products_to_algolia ) ) {
				wp_die( esc_html__( 'Action is not allowed.', 'algolia-woo-indexer' ), esc_html__( 'Error!', 'algolia-woo-indexer' ) );
			}

			/**
			 * If we have verified the send_products_nonce and the send_products hidden field is set, return true
			 */
			if ( wp_verify_nonce( $send_products_nonce, 'send_products_to_algolia_nonce_action' ) && isset( $send_products_to_algolia ) ) {
				return true;
			}
		}
	}
}
