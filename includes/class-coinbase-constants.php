<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Constants for Coinbase Business Checkouts API.
 */
class Coinbase_Constants {

	const API_BASE_URL = 'https://business.coinbase.com';
	const API_PATH         = '/api/v1/checkouts';
	const SANDBOX_API_PATH = '/sandbox/api/v1/checkouts';

	const JWT_ISSUER         = 'cdp';
	const JWT_EXPIRY_SECONDS = 120;

	const SIGNATURE_HEADER = 'x-hook0-signature';

	const EVENT_PAYMENT_SUCCESS = 'checkout.payment.success';
	const EVENT_PAYMENT_FAILED  = 'checkout.payment.failed';
	const EVENT_PAYMENT_EXPIRED = 'checkout.payment.expired';

	const PAYMENT_CURRENCY = 'USDC';
	const PAYMENT_NETWORK  = 'base';

	const METADATA_SOURCE    = 'source';
	const METADATA_ORDER_ID  = 'order_id';
	const METADATA_ORDER_KEY = 'order_key';

	/**
	 * Map GET response status values to event types.
	 */
	const STATUS_MAP = array(
		'COMPLETED' => 'checkout.payment.success',
		'FAILED'    => 'checkout.payment.failed',
		'EXPIRED'   => 'checkout.payment.expired',
	);

	/**
	 * Map a GET response status to an event type.
	 *
	 * @param  string $status Status from GET response (COMPLETED, FAILED, EXPIRED, etc.).
	 * @return string|null Event type, or null for non-terminal statuses.
	 */
	public static function status_to_event_type( $status ) {
		return isset( self::STATUS_MAP[ $status ] ) ? self::STATUS_MAP[ $status ] : null;
	}

	/**
	 * Normalize a webhook event type, mapping legacy payment_link.* events
	 * to checkout.* equivalents for dual webhook support during transition.
	 *
	 * @param  string $event_type Event type from webhook payload.
	 * @return string Normalized event type.
	 */
	public static function normalize_event_type( $event_type ) {
		if ( strpos( $event_type, 'payment_link.' ) === 0 ) {
			return str_replace( 'payment_link.', 'checkout.', $event_type );
		}
		return $event_type;
	}
}
