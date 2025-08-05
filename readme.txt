=== Shield Checkout for WooCommerce ===
Contributors: shield
Tags: payments, shield, cryptocurrency, payment gateway
Requires at least: 6.0
Tested up to: 6.5.4
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept crypto payment using Shield gateway for WooCommerce. 

== Description ==

== Shield Payment Gateway plugin for WooCommerce ==

= Key features =

* Accept cryptocurrency payments from your customers.

= Customer journey =

1. The customer is adding items to his shopping card and proceeds to checkout. Let's say the total order amount is $100 USD as an example.
2. The customer selects Shield Payments as checkout method.
3. A Shield invoice is generated, the customer selects one of the supported cryptocurrency to complete the payment. The invoice will display an amount to pay in the selected cryptocurrency, at an exchange rate locked for 15 minutes.
4. The customer completes the payment using his cryptocurrency wallet within the 15 min window.
5. Once the transaction is fully confirmed on the blockchain, Shield notifies the merchant and the corresponding amount is credited to the Shield merchant account.

== Installation ==

= Requirements =

* This plugin requires [WooCommerce](https://wordpress.org/plugins/woocommerce/).
* A Shield merchant account

= Plugin installation =

1. Get started by signing up for a Shield account. Please read our [Terms of Use](https://www.getshield.xyz/terms) before using our service. It’s essential to understand the rules and guidelines that govern your interactions with our platform.
2. Select **Shield Checkout for WooCommerce** and click on **Install Now** and then on **Activate Plugin**

After the plugin is activated, Shield will appear in the WooCommerce > Settings > Payments section.

= Plugin configuration =

After you have installed the BitPay plugin, the configuration steps are:

1. Create an API token from your Shield merchant dashboard:
2. Log in to your WordPress admin panel, select WooCommerce > Payments and click on the **Set up** button next to the Shield Payment methods
	* Paste the token value into the appropriate field: **Shield API KEY**

== Changelog ==

= v2.0.0 =
* Initial release