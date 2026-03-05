# Coinbase Business for WooCommerce

A WooCommerce payment gateway that allows your customers to pay with USDC via Coinbase Business Payment Links API.

**Note: This plugin requires your WooCommerce store currency to be set to USD.**

## Installation

Within the Github repository, click the Clone or Download button and Download a zip file of the repository, or clone it directly via command line.

Within your WordPress administration panel, go to Plugins > Add New and click the Upload Plugin button on the top of the page.

Alternatively, you can move the zip file into the `wp-content/plugins` folder of your website and unzip.

You will then need to go to your WordPress administration Plugins page, and activate the plugin.

## Configuring Coinbase Business

You will need to set up an account on [Coinbase Business].

Within the WordPress administration area, go to the WooCommerce > Settings > Payments page and you will see Coinbase in the table of payment gateways.

Clicking the Manage button on the right hand side will take you into the settings page, where you can configure the plugin for your store.

**Note: If you are running version of WooCommerce older than 3.4.x your Coinbase tab will be underneath the WooCommerce > Settings > Checkout tab**

## Settings

### Enable / Disable

Turn the Coinbase Business payment method on / off for visitors at checkout.

### Title

Title of the payment method on the checkout page

### Description

Description of the payment method on the checkout page

### CDP API Key Name

Your CDP API key name from the [Coinbase Business dashboard].

### CDP API Private Key

Your EC private key in PEM format. Paste the full key including BEGIN/END lines.

### Webhook Secret

Your webhook secret from the [Coinbase Business dashboard].

Using webhooks allows Coinbase Business to send payment confirmation messages to the website. To fill this out:

Follow the instructions at https://docs.cdp.coinbase.com/coinbase-business/payment-link-apis/webhooks to set up webhooks.

### Debug log

Whether or not to store debug logs.

If this is checked, these are saved within your `wp-content/uploads/wc-logs/` folder in a .log file prefixed with `coinbase-`

## Prerequisites

To use this plugin with your WooCommerce store you will need:

- [WordPress] (tested up to 6.5.3)
- [WooCommerce] (tested up to 8.9.1)
- PHP 8.1 or higher
- Store currency set to USD

## Frequently Asked Questions

**What payment method does this plugin support?**

This plugin supports USDC payments via Coinbase Business Payment Links.

**What currency must my store use?**

Your WooCommerce store currency must be set to USD.

## License

This project is licensed under the Apache 2.0 License

## Changelog

## 2.0.0

- Migrated from Coinbase Commerce Charge API to Coinbase Business Payment Links API
- Authentication changed from API key to ES256 JWT (CDP API credentials)
- Payment method changed to USDC only
- Store currency must be USD
- Updated webhook signature verification (x-hook0-signature with timestamp-based HMAC)
- Reduced order timeout from 3 days to 1 day
- Rebranded to Coinbase Business throughout

## 1.4.1

- Tested against WordPress 6.5.3
- Tested against WooCommerce 8.9.1

## 1.4

- Declare HPOS Compatibility
- Remove deprecated Charge status mappings
- Fix order_id incorrect format error
- Update coinbase_charge_id to be charge.id

## 1.3

- Adds HPOS support

## 1.2

- Tested against WordPress 6.0
- Tested against WooCommerce 6.5.1

## 1.1.4

- Bug fix: Sending order confirmation emails in WooCommerce.

## 1.1.0

- Added support for charge cancel url.
- Handle cancelled events from API.
- Add option to disable icons on checkout page.
- Add Coinbase Commerce transaction ID to WooCommerce order output (Admin order page, Customer order page, email confirmation).
- Updated README.md

## 1.0.2

- Tested against WordPress 4.9.4

## 1.0.1

- Tested against WordPress 4.9.7
- Tested against WooCommerce 3.4.3
- Updated README.md
- Updated plugin meta in coinbase-commerce.php

## 1.0.0

- Coinbase Commerce

[//]: # "Comments for storing reference material in. Stripped out when processing the markdown"
[Coinbase Business]: https://coinbase.com/business
[Coinbase Business dashboard]: https://coinbase.com/business
[WooCommerce]: https://woocommerce.com/
[WordPress]: https://wordpress.org/
[WordPress.org plugin repository]: https://wordpress.org/plugins/coinbase-commerce/
