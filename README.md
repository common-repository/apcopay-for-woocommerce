=== ApcoPay for WooCommerce ===
Contributors: apcoadriancamilleri
Tags: apcopay, woocommerce, credit card, payment, pay, payment gateway, payment request
Requires at least: 4.4
Tested up to: 6.0
Requires PHP: 5.6
Stable tag: 1.6.4
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Adds the functionality to pay with ApcoPay to WooCommerce.

== Description ==

This plugin adds the functionality to pay with [ApcoPay](https://www.apcopay.com/) to WooCommerce.
* Adds ApcoPay to WooCommerce as a payment option in the checkout page. 
* Updates the order in WooCommerce on transaction success or fail
* Refunds
* Extra charge
* Authorisation and Capture
* Checkout iframe or redirect payment

== Installation ==
1. Move the folder 'apcopay-for-woocommerce' inside the '/wp-content/plugins/' directory. 
1. Activate the plugin through the "Plugins" menu in WordPress.
1. Navigate to 'WooCommerce > Settings > Payments > ApcoPay'. 
1. Input the credentials received from ApcoPay in the settings fields.
   - MerchID
   - MerchPass
   - ProfileID
   - SecretWord

== Frequently Asked Questions ==

= How do I set up a merchant account with ApcoPay?

Send an email to [hello@apcopay.com](mailto:hello@apcopay.com) to set up a merchant account with ApcoPay.

== Screenshots ==

1. The settings panel found at 'WooCommerce > Settings > Payments > ApcoPay'.
2. Checkout iframe.

== Changelog ==

= 1.6.4 =
* Added description field

= 1.6.3 =
* Updated tested up to version

= 1.6.2 =
* Fixed hosted form submit on pending order pay from admin

= 1.6.1 =
* Fixed order not updating due to special characters

= 1.6.0 =
* Added support for HUB02
* Added refund with payment page

= 1.5.1 =
* Added billing address data in redirect payment flow

= 1.5.0 =
* Added checkout iframe

= 1.4.3 =
* Added authorisation and capture order status options

= 1.4.2 =
* Added option add extra charge amount to order

= 1.4.1 =
* Changed order status of authorisation and capture

= 1.4.0 =
* Added capture

= 1.3.2 =
* Added missing file

= 1.3.1 =
* Fixed admin scripts not always loading

= 1.3.0 =
* Added extra charge

= 1.2.0 =
* Added refunds

= 1.1.0 =
* Added bank reference in order note

= 1.0.1 =
* Changed Apcopay domain extension
* Fixed status not updating due to magic qoutes

= 1.0 =
* Initial release.