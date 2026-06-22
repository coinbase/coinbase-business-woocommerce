=== Coinbase Business Payment Gateway for WooCommerce ===
Contributors: pragbarrett, eddhurst, omidahourai, robbybarton
Plugin URL: https://coinbase.com/business
Tags: coinbase, woocommerce, usdc, base, crypto
Requires at least: 3.0
Requires PHP: 8.1+
Tested up to: 6.5.3
Stable tag: 2.1.0
License: GPLv2 or later

== Description ==

Accept USDC payments through Coinbase Business Checkouts API on your WooCommerce store.

**Note: This plugin requires your WooCommerce store currency to be set to USD.**

== Installation ==

= From your WordPress dashboard =

1. Visit 'Plugins > Add New'
2. Search for 'coinbase commerce'
3. Activate Coinbase Business from your Plugins page.

= From WordPress.org =

1. Download Coinbase Business.
2. Upload to your '/wp-content/plugins/' directory, using your favorite method (ftp, sftp, scp, etc...)
3. Activate Coinbase Business from your Plugins page.

= Once Activated =

1. Go to WooCommerce > Settings > Payments
2. Configure the plugin for your store

= Configuring Coinbase Business =

* You will need to set up an account on https://coinbase.com/business
* Within the WordPress administration area, go to the WooCommerce > Settings > Payments page and you will see Coinbase in the table of payment gateways.
* Clicking the Manage button on the right hand side will take you into the settings page, where you can configure the plugin for your store.

**Note: If you are running version of WooCommerce older than 3.4.x your Coinbase tab will be underneath the WooCommerce > Settings > Checkout tab**

= Enable / Disable =

Turn the Coinbase Business payment method on / off for visitors at checkout.

= Title =

Title of the payment method on the checkout page

= Description =

Description of the payment method on the checkout page

= CDP API Key Name =

Your CDP API key name from the Coinbase Business dashboard at https://coinbase.com/business

= CDP API Private Key =

Your ECDSA private key in PEM format. Paste the full key including BEGIN/END lines.

= Webhook Secret =

Your webhook secret from the Coinbase Business dashboard.

Using webhooks allows Coinbase Business to send payment confirmation messages to the website. To fill this out:

Follow the instructions at https://docs.cdp.coinbase.com/coinbase-business/checkout-apis/webhooks to set up webhooks.

= Debug log =

Whether or not to store debug logs.

If this is checked, these are saved within your `wp-content/uploads/wc-logs/` folder in a .log file prefixed with `coinbase-`


== Frequently Asked Questions ==

= What payment method does this plugin support?

This plugin supports USDC payments via Coinbase Business Checkouts.

= What currency must my store use?

Your WooCommerce store currency must be set to USD.

= Prerequisites=

To use this plugin with your WooCommerce store you will need:
* WooCommerce plugin
* PHP 8.1 or higher
* Store currency set to USD



== Upgrade Notice ==

= 2.1.0 =
Migrates from Payment Links API to Checkouts API. No configuration changes required.

= 2.0.0 =
Major upgrade: migrates from Coinbase Commerce (Charge API) to Coinbase Business (Payment Links API). Requires new CDP API credentials from coinbase.com/business. Store currency must be USD. USDC only.


== Screenshots ==

1. Admin panel
2. Coinbase Business payment gateway on checkout page
3. USDC payment screen


== Changelog ==

= 2.1.0 =
* Migrated from Payment Links API to Checkouts API
* Updated API endpoints from /api/v1/payment-links to /api/v1/checkouts
* Updated webhook events from payment_link.* to checkout.*
* Added backward compatibility for existing orders and legacy webhook events
* Updated error response handling for new API format

= 2.0.0 =
* Migrated from Coinbase Commerce Charge API to Coinbase Business Payment Links API
* Authentication changed from API key to ES256 JWT (CDP API credentials)
* Payment method changed to USDC only
* Store currency must be USD
* Updated webhook signature verification (x-hook0-signature with timestamp-based HMAC)
* Reduced order timeout from 3 days to 1 day
* Rebranded to Coinbase Business throughout

= 1.4.1 =
* Tested against WordPress 6.5.3
* Tested against WooCommerce 8.9.1

= 1.4 =
* Declare HPOS Compatibility
* Remove deprecated Charge status mappings
* Fix order_id incorrect format error
* Update coinbase_charge_id to be charge.id

= 1.3 =
* Adds HPOS support

= 1.2 =
* Tested against WordPress 6.0
* Tested against WooCommerce 6.5.1

= 1.1.4 =
* Fix to send order emails when transitioning from "New" to "Processing"

= 1.1.3 =
* Tested against WordPress 5.2
* Tested against WooCommerce 3.6.3

= 1.1.2 =
* Add support for USDC
* Do not cancel pending orders when charges expire

= 1.1.1 =
* Add support for OVERPAID
* Update Woo order statuses
* Add Coinbase meta data to backend order details

= 1.1 =
* Added support for charge cancel url.
* Handle cancelled events from API.
* Add option to disable icons on checkout page.
* Add Coinbase Commerce transaction ID to WooCommerce order output (Admin order page, Customer order page, email confirmation).
* Updated README.md

= 1.0.1 =
* Tested against WordPress 4.9.7
* Tested against WooCommerce 3.4.3
* Updated README.md
* Updated plugin meta in coinbase-commerce.php

= 1.0.0 =
* Coinbase Commerce
