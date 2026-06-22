<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

use Firebase\JWT\JWT;

/**
 * JWT Authentication for Coinbase CDP API.
 *
 * Generates ES256-signed JWTs for authenticating with the Checkouts API.
 */
class Coinbase_JWT_Auth {

	/**
	 * @var string CDP API key name/identifier.
	 */
	private $key_name;

	/**
	 * @var string ECDSA private key in PEM format.
	 */
	private $private_key;

	/**
	 * @param string $key_name       CDP API key name.
	 * @param string $private_key_pem ECDSA private key in PEM format.
	 */
	public function __construct( $key_name, $private_key_pem ) {
		$this->key_name    = $key_name;
		$this->private_key = str_replace( '\\n', "\n", $private_key_pem );
	}

	/**
	 * Generate a JWT token for API authentication.
	 *
	 * @param string $method HTTP method (GET, POST, etc.).
	 * @param string $path   API path (e.g., /api/v1/checkouts).
	 * @return string Signed JWT token.
	 * @throws Exception If private key is invalid.
	 */
	public function generate_token( $method, $path ) {
		$private_key_resource = openssl_pkey_get_private( $this->private_key );
		if ( ! $private_key_resource ) {
			throw new Exception( 'Invalid private key: ' . openssl_error_string() );
		}

		$time  = time();
		$nonce = bin2hex( random_bytes( 16 ) );

		// URI format: METHOD hostname/path (no https://)
		$uri = $method . ' business.coinbase.com' . $path;

		$payload = array(
			'sub' => $this->key_name,
			'iss' => Coinbase_Constants::JWT_ISSUER,
			'nbf' => $time,
			'exp' => $time + Coinbase_Constants::JWT_EXPIRY_SECONDS,
			'uri' => $uri,
		);

		$headers = array(
			'typ'   => 'JWT',
			'alg'   => 'ES256',
			'kid'   => $this->key_name,
			'nonce' => $nonce,
		);

		return JWT::encode( $payload, $private_key_resource, 'ES256', $this->key_name, $headers );
	}
}
