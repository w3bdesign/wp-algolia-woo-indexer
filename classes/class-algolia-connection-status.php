<?php
/**
 * Algolia connection checking and settings-page status notices.
 *
 * @package algolia-woo-indexer
 */

namespace Algowoo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check Algolia search access and render safe connection diagnostics.
 */
final class AlgoliaConnectionStatus {

	/**
	 * Connection status cached only for the current settings-page request.
	 *
	 * @var array|null
	 */
	private $connection_status = null;

	/**
	 * Remove the secret key from diagnostic text before it can be displayed.
	 *
	 * @param string   $message  Diagnostic text from WordPress or Algolia.
	 * @param string[] $api_keys Configured API keys to redact.
	 * @return string Plain text with the secret redacted and a bounded length.
	 */
	private function sanitize_connection_diagnostic( $message, $api_keys ) {
		foreach ( $api_keys as $api_key ) {
			$message = str_replace( array( $api_key, rawurlencode( $api_key ) ), '[redacted]', $message );
		}
		return wp_html_excerpt( sanitize_text_field( $message ), 500, '…' );
	}

	/**
	 * Check authenticated index access once per request, without changing records.
	 *
	 * An empty search requires search permission and returns no product records.
	 * This uses the Search API Key, never the separate Write API Key.
	 *
	 * @param array $settings Saved connection settings from the main plugin wrapper.
	 * @return array Notice type, emoji, title, and explanatory message.
	 */
	public function get_algolia_connection_status( $settings ) {
		if ( null !== $this->connection_status ) {
			return $this->connection_status;
		}

		$status = array(
			'type'    => 'warning',
			'emoji'   => '⚙️',
			'title'   => __( 'Algolia connection not checked', 'algolia-woo-indexer' ),
			'message' => __( 'Save your Application ID, Search API Key, Write API Key, and index name to check the connection automatically when this page opens.', 'algolia-woo-indexer' ),
		);

		// Do not allow this public method to expose connection details to other users.
		if ( ! current_user_can( 'manage_options' ) ) {
			return $status;
		}

		$settings                = $this->normalize_algolia_connection_settings( $settings );
		$this->connection_status = $status;
		if ( null !== $settings ) {
			$this->connection_status = $this->check_algolia_connection( $settings );
		}

		return $this->connection_status;
	}

	/**
	 * Normalize complete settings, rejecting missing values and placeholders.
	 *
	 * @param array $settings Saved connection settings.
	 * @return array|null Trimmed settings, or null when configuration is incomplete.
	 */
	private function normalize_algolia_connection_settings( $settings ) {
		foreach ( $settings as $name => $value ) {
			if ( ! is_string( $value ) || '' === trim( $value ) || 0 === strcasecmp( trim( $value ), CHANGE_ME ) ) {
				return null;
			}
			$settings[ $name ] = trim( $value );
		}

		return $settings;
	}

	/**
	 * Make one connection-check request using normalized settings.
	 *
	 * @param array $settings Complete, trimmed connection settings.
	 * @return array Connection notice, including diagnostics when needed.
	 */
	private function check_algolia_connection( $settings ) {
		$status = array(
			'type'    => 'error',
			'emoji'   => '❌',
			'title'   => __( 'Not connected to Algolia', 'algolia-woo-indexer' ),
			'message' => __( 'The connection check failed. Check your Application ID, Search API Key, index name, and server connectivity, then reload this page.', 'algolia-woo-indexer' ),
		);

		// Constrain the hostname to an Algolia application, never an arbitrary URL.
		if ( ! preg_match( '/^[a-zA-Z0-9]+$/', $settings['application_id'] ) ) {
			$status['message'] = __( 'The Application ID must contain only letters and numbers. Correct it, save the settings, and reload this page.', 'algolia-woo-indexer' );
			return $status;
		}

		$url = 'https://' . $settings['application_id'] . '.algolia.net/1/indexes/' . rawurlencode( $settings['index_name'] ) . '/query';

		// One bounded request, with no redirects that could forward the secret key.
		$response = wp_remote_post(
			$url,
			array(
				'timeout'             => 5,
				'redirection'         => 0,
				'limit_response_size' => 65536,
				'headers'             => array(
					'X-Algolia-Application-Id' => $settings['application_id'],
					'X-Algolia-API-Key'        => $settings['search_api_key'],
					'Content-Type'             => 'application/json',
				),
				'body'                => wp_json_encode(
					array(
						'query'       => '',
						'hitsPerPage' => 0,
						'analytics'   => false,
					)
				),
			)
		);

		return $this->get_algolia_response_status( $response, $settings, $status );
	}

	/**
	 * Translate an HTTP or transport response into a connection notice.
	 *
	 * @param array|\WP_Error $response WordPress HTTP response or transport error.
	 * @param array           $settings Connection settings containing the keys to redact.
	 * @param array           $status   Default error notice.
	 * @return array Connection notice with redacted diagnostic details on failure.
	 */
	private function get_algolia_response_status( $response, $settings, $status ) {
		if ( is_wp_error( $response ) ) {
			$status['message'] = __( 'Your server could not reach Algolia within the connection check. Check DNS, HTTPS access, and firewall settings, then reload this page.', 'algolia-woo-indexer' );
			$status['details'] = $this->sanitize_connection_diagnostic(
				sprintf(
					/* translators: 1: WordPress HTTP error code, 2: Transport error message. */
					__( 'Transport error (%1$s): %2$s', 'algolia-woo-indexer' ),
					$response->get_error_code(),
					$response->get_error_message()
				),
				array( $settings['api_key'], $settings['search_api_key'] )
			);
			return $status;
		}

		$code              = wp_remote_retrieve_response_code( $response );
		$body              = json_decode( wp_remote_retrieve_body( $response ), true );
		$status['details'] = $this->sanitize_connection_diagnostic(
			sprintf(
				/* translators: 1: HTTP response code, 2: HTTP reason phrase, 3: API error or response diagnostic. */
				__( 'HTTP %1$d (%2$s): %3$s', 'algolia-woo-indexer' ),
				$code,
				wp_remote_retrieve_response_message( $response ),
				$this->get_algolia_response_reason( $body, $code )
			),
			array( $settings['api_key'], $settings['search_api_key'] )
		);

		if ( 200 === $code && is_array( $body ) && isset( $body['hits'] ) && is_array( $body['hits'] ) ) {
			$status = array(
				'type'    => 'success',
				'emoji'   => '✅',
				'title'   => __( 'Connected to Algolia', 'algolia-woo-indexer' ),
				'message' => __( 'Your Algolia search connection is working.', 'algolia-woo-indexer' ),
			);
		} elseif ( 400 === $code ) {
			$status['message'] = __( 'Algolia rejected the connection-check request as invalid. See the diagnostic below for the exact reason. Check the saved Application ID and index name.', 'algolia-woo-indexer' );
		} elseif ( 401 === $code || 403 === $code ) {
			$status['message'] = __( 'Algolia rejected the Search API Key or search access. Use the Search API Key from the same Algolia application and check that it can search the configured index. See the diagnostic below for any additional key restrictions.', 'algolia-woo-indexer' );
		} elseif ( 404 === $code ) {
			$status['type']    = 'warning';
			$status['emoji']   = '⚠️';
			$status['title']   = __( 'Algolia connection not verified', 'algolia-woo-indexer' );
			$status['message'] = __( 'Algolia responded, but the configured index was not found. Check the index name. If the index is new, send products to create it, then reload this page.', 'algolia-woo-indexer' );
		} elseif ( 429 === $code ) {
			$status['message'] = __( 'Algolia rate-limited the connection check. Wait a moment, then reload this page to try again.', 'algolia-woo-indexer' );
		} elseif ( $code >= 500 ) {
			$status['message'] = __( 'Algolia or an upstream service returned a server error. See the diagnostic below and try again shortly.', 'algolia-woo-indexer' );
		}

		return $status;
	}

	/**
	 * Describe the API error or unexpected response without exposing raw HTML.
	 *
	 * @param mixed $body Decoded JSON response body.
	 * @param int   $code HTTP response code.
	 * @return string Diagnostic explanation before secret redaction.
	 */
	private function get_algolia_response_reason( $body, $code ) {
		$reason = __( 'The response did not include an Algolia error message.', 'algolia-woo-indexer' );
		if ( is_array( $body ) && isset( $body['message'] ) && is_string( $body['message'] ) ) {
			$reason = $body['message'];
		} elseif ( ! is_array( $body ) ) {
			$reason = __( 'The response was not valid JSON. A proxy, firewall, or upstream service may have returned it instead of Algolia.', 'algolia-woo-indexer' );
		} elseif ( 200 === $code ) {
			$reason = __( 'Algolia returned an unexpected search response, so index access could not be verified.', 'algolia-woo-indexer' );
		}
		return $reason;
	}

	/**
	 * Display the connection status before the settings forms.
	 *
	 * @param array $settings Saved connection settings from the main plugin wrapper.
	 * @return void
	 */
	public function display_algolia_connection_status( $settings ) {
		$status = $this->get_algolia_connection_status( $settings );
		?>
		<div class="notice notice-<?php echo esc_attr( $status['type'] ); ?> inline">
			<p><strong><?php echo esc_html( $status['title'] ); ?></strong></p>
			<p><?php echo esc_html( $status['message'] ); ?></p>
			<?php if ( ! empty( $status['details'] ) ) : ?>
				<p><strong><?php echo esc_html__( 'Diagnostic details:', 'algolia-woo-indexer' ); ?></strong> <?php echo esc_html( $status['details'] ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}
}
