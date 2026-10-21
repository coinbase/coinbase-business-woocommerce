# Coinbase Business for WooCommerce

A WooCommerce payment gateway that allows your customers to pay with USDC via Coinbase Business Checkouts API.

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

Your CDP API key name from the [Coinbase Business Account API settings].

### CDP API Private Key

Your ECDSA private key in PEM format. Paste the full key including BEGIN/END lines.

### Webhook Secret

Your webhook secret from the [Coinbase Business Account API settings].

The secret is required before the plugin will process webhook events. If it is not configured, webhook requests are rejected and the hourly payment-status poller remains in use.

Using webhooks allows Coinbase Business to send payment confirmation messages to the website. To fill this out:

Follow the instructions at https://docs.cdp.coinbase.com/coinbase-business/checkout-apis/webhooks to set up webhooks. Note that the url for your store would be `https://[your-store-website.com]/?wc-api=WC_Gateway_Coinbase`

### Debug log

Whether or not to store debug logs.

If this is checked, these are saved within your `wp-content/uploads/wc-logs/` folder in a .log file prefixed with `coinbase-`

## Prerequisites

To use this plugin with your WooCommerce store you will need:

- [WordPress] (tested up to 6.5.3)
- [WooCommerce] (tested up to 8.9.1)
- PHP 8.2.35+ on the 8.2 branch, 8.3.36+ on the 8.3 branch, or 8.4.27+ on the 8.4 branch
- Store currency set to USD

### PHP security upgrade

The PHP version requirements above exclude upstream releases affected by [CVE-2026-109618 and CVE-2026-109619](https://www.php.net/archive/2026.php). PHP 8.1 and older, and PHP 8.5 or newer, are outside this plugin's declared PHP support. The WordPress and WooCommerce versions listed above are existing test coverage; validate your complete store, including checkout and webhooks, on the patched PHP build before deploying.

**Updating this plugin does not patch your server's PHP runtime.** Before installing or updating the plugin, ask your hosting provider to upgrade the PHP build serving WordPress requests (Apache or PHP-FPM), plus any CLI and cron runtimes, to a patched release on one of the supported branches. Restart or replace the affected web workers and long-running processes, then confirm the PHP version/build serving the website. A CLI `php -v` check alone does not verify the web runtime.

Some operating-system and hosting vendors backport security fixes without changing the upstream PHP version number. For such builds, obtain the vendor advisory and exact package/build version confirming both CVE fixes, and review plugin compatibility before deployment. These upstream version requirements cannot identify vendor backports; do not bypass them based only on a package being marked up to date.

Composer installations can run `composer check-platform-reqs --lock` to check the CLI runtime against the complete per-branch requirements. ZIP uploads and copied plugin directories do not perform Composer's installation checks. WordPress's `Requires PHP` metadata and the generated Composer autoload check express only a minimum version, so they do not reject every excluded release on a newer branch. Successful activation is not proof that the host is patched: verify the actual web runtime/build with your hosting provider before using the plugin.

### Local Docker development

The Compose configuration requires `COINBASE_WORDPRESS_IMAGE` and has no default WordPress image. On 2026-10-21, the official `wordpress:php8.2-apache`, `wordpress:php8.3-apache`, and `wordpress:php8.4-apache` image configurations still reported PHP 8.2.34, 8.3.35, and 8.4.26 respectively, below the required security floors. A mutable `php8.x` tag does not establish that an image is patched.

Choose an image containing a supported patched PHP release. Replace the placeholder below with its immutable digest, export the value, then pull it and confirm the resolved digest:

```sh
export COINBASE_WORDPRESS_IMAGE='wordpress@sha256:<verified-image-digest>'
docker pull "$COINBASE_WORDPRESS_IMAGE"
docker image inspect --format '{{json .RepoDigests}}' "$COINBASE_WORDPRESS_IMAGE"
```

Verify the PHP binary in that exact image before starting the development stack. This command starts Compose only when the runtime falls within one of the declared supported ranges:

```sh
docker run --rm --entrypoint php "$COINBASE_WORDPRESS_IMAGE" -r '
$v = PHP_VERSION_ID;
$supported = ($v >= 80235 && $v < 80300)
    || ($v >= 80336 && $v < 80400)
    || ($v >= 80427 && $v < 80500);
printf("PHP %s: %s\n", PHP_VERSION, $supported ? "supported" : "upgrade required");
exit($supported ? 0 : 1);
' && docker compose up -d
```

Confirm the PHP build serving the WordPress site after startup as well. The development image and these checks do not update merchant hosting environments.

## Frequently Asked Questions

**What payment method does this plugin support?**

This plugin supports USDC payments via Coinbase Business Checkouts.

**What currency must my store use?**

Your WooCommerce store currency must be set to USD.

## License

This project is licensed under the Apache 2.0 License

## Changelog

## 2.1.0

- Migrated from Payment Links API to Checkouts API
- Updated API endpoints from /api/v1/payment-links to /api/v1/checkouts
- Updated webhook events from payment_link.* to checkout.*
- Added backward compatibility for existing orders and legacy webhook events
- Updated error response handling for new API format

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
[Coinbase Business Account API settings]: https://www.coinbase.com/settings/api
[WooCommerce]: https://woocommerce.com/
[WordPress]: https://wordpress.org/
[WordPress.org plugin repository]: https://wordpress.org/plugins/coinbase-commerce/
