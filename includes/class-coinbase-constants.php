<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Constants for Coinbase Business Payment Links API.
 */
class Coinbase_Constants {

	const API_BASE_URL = 'https://business.coinbase.com';
	const API_PATH     = '/api/v1/payment-links';

	const JWT_ISSUER         = 'cdp';
	const JWT_EXPIRY_SECONDS = 120;

	const SIGNATURE_HEADER = 'x-hook0-signature';

	const EVENT_PAYMENT_SUCCESS = 'payment_link.payment.success';
	const EVENT_PAYMENT_FAILED  = 'payment_link.payment.failed';
	const EVENT_PAYMENT_EXPIRED = 'payment_link.payment.expired';

	const PAYMENT_CURRENCY = 'USDC';
	const PAYMENT_NETWORK  = 'base';

	const METADATA_SOURCE    = 'source';
	const METADATA_ORDER_ID  = 'order_id';
	const METADATA_ORDER_KEY = 'order_key';
}
