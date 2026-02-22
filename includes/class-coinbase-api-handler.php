<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends API requests to Coinbase Business Payment Links API.
 */
class Coinbase_API_Handler {

	/**
	 * Log variable function.
	 *
	 * @var string/array Log variable function.
	 */
	public static $log;

	/**
	 * Call the $log variable function.
	 *
	 * @param string $message Log message.
	 * @param string $level   Optional. Default 'info'.
	 *     emergency|alert|critical|error|warning|notice|info|debug
	 */
	public static function log( $message, $level = 'info' ) {
		return call_user_func( self::$log, $message, $level );
	}

	/**
	 * CDP API Key Name.
	 *
	 * @var string
	 */
	public static $cdp_key_name;

	/**
	 * CDP API Private Key (PEM).
	 *
	 * @var string
	 */
	public static $cdp_private_key;

	/**
	 * Get the response from an API request.
	 *
	 * @param  string $path   API path (e.g., /api/v1/payment-links).
	 * @param  array  $params Request body parameters.
	 * @param  string $method HTTP method.
	 * @return array  [bool $success, mixed $data]
	 */
	public static function send_request( $path, $params = array(), $method = 'GET' ) {
		// phpcs:ignore
		self::log( 'Coinbase Request Args for ' . $method . ' ' . $path . ': ' . print_r( $params, true ) );

		try {
			$jwt_auth = new Coinbase_JWT_Auth( self::$cdp_key_name, self::$cdp_private_key );
			$token    = $jwt_auth->generate_token( $method, $path );
		} catch ( Exception $e ) {
			self::log( 'JWT generation failed: ' . $e->getMessage(), 'error' );
			return array( false, 'JWT generation failed: ' . $e->getMessage() );
		}

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			),
		);

		$url = Coinbase_Constants::API_BASE_URL . $path;

		if ( in_array( $method, array( 'POST', 'PUT' ), true ) ) {
			$args['body'] = wp_json_encode( $params );
		} else {
			$url = add_query_arg( $params, $url );
		}

		$response = wp_remote_request( esc_url_raw( $url ), $args );

		if ( is_wp_error( $response ) ) {
			self::log( 'WP response error: ' . $response->get_error_message() );
			return array( false, $response->get_error_message() );
		}

		$result = json_decode( $response['body'], true );
		$code   = $response['response']['code'];

		if ( in_array( $code, array( 200, 201 ), true ) ) {
			return array( true, $result );
		}

		$e      = empty( $result['error']['message'] ) ? '' : $result['error']['message'];
		$errors = array(
			400 => 'Error response from API: ' . $e,
			401 => 'Authentication error, please check your CDP API key and private key.',
			429 => 'Coinbase API rate limit exceeded.',
		);

		if ( array_key_exists( $code, $errors ) ) {
			$msg = $errors[ $code ];
		} else {
			$msg = 'Unknown response from API: ' . $code;
		}
		self::log( $msg );

		return array( false, $code );
	}

	/**
	 * Create a new payment link.
	 *
	 * @param  string $amount      Payment amount in USD.
	 * @param  array  $metadata    Order metadata.
	 * @param  string $success_url Redirect URL on success.
	 * @param  string $fail_url    Redirect URL on failure.
	 * @param  string $description Optional description (max 500 chars).
	 * @return array  [bool $success, mixed $data]
	 */
	public static function create_payment_link( $amount, $metadata, $success_url, $fail_url, $description = null ) {
		$body = array(
			'amount'             => (string) $amount,
			'currency'           => Coinbase_Constants::PAYMENT_CURRENCY,
			'network'            => Coinbase_Constants::PAYMENT_NETWORK,
			'metadata'           => $metadata,
			'successRedirectUrl' => $success_url,
			'failRedirectUrl'    => $fail_url,
		);

		if ( ! empty( $description ) ) {
			$body['description'] = mb_substr( $description, 0, 500 );
		}

		return self::send_request( Coinbase_Constants::API_PATH, $body, 'POST' );
	}

	/**
	 * Retrieve a payment link by ID.
	 *
	 * @param  string $id Payment link ID.
	 * @return array  [bool $success, mixed $data]
	 */
	public static function get_payment_link( $id ) {
		$path = Coinbase_Constants::API_PATH . '/' . $id;
		return self::send_request( $path );
	}
}
