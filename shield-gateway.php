<?php
/*
 * Plugin Name: Shield Payments for WooCommerce
 * Description: A crypto payment gateway for WooCommerce.
 * Version: 1.0.0
 * Author: Shield
 * Author URI: https://www.getshield.xyz/
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: shield-payments
 * Domain Path: /languages
 * Tested up to: 6.5.4
 * Requires at least: 6.0
 * Requires PHP: 7.4
*/

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use Automattic\WooCommerce\Utilities\FeaturesUtil;

add_action('plugins_loaded', function () {
    if (!class_exists('WC_Payment_Gateway'))
        return;
    include(plugin_dir_path(__FILE__) . 'src/class-gateway.php');
}, 0);

add_filter('woocommerce_payment_gateways', function ($gateways) {
    $gateways[] = 'Shield_Gateway';
    return $gateways;
});

add_action('before_woocommerce_init', function () {
    if (class_exists(FeaturesUtil::class)) {
        FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

add_action('woocommerce_blocks_loaded', function () {
    if (!class_exists(AbstractPaymentMethodType::class)) {
        return;
    }

    require_once plugin_dir_path(__FILE__) . 'src/class-block.php';

    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function (PaymentMethodRegistry $payment_method_registry) {
            $payment_method_registry->register(new Shield_Gateway_Blocks);
        }
    );
});
