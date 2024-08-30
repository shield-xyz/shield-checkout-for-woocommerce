<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class Shield_Gateway_Blocks extends AbstractPaymentMethodType
{

    private $gateway;
    protected $name = 'shield_gateway';

    public function initialize()
    {
        $this->settings = get_option('woocommerce_shield_gateway_settings', []);
        $this->gateway = new Shield_Gateway();
    }

    public function is_active()
    {
        return $this->gateway->is_available();
    }

    public function get_payment_method_script_handles()
    {

        wp_register_script(
            'shield_gateway-blocks-integration',
            plugin_dir_url(__FILE__) . 'checkout.js',
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ],
            '1.0.1',
            true
        );
        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations('shield_gateway-blocks-integration');
        }
        return ['shield_gateway-blocks-integration'];
    }

    public function get_payment_method_data()
    {
        return [
            'title' => $this->gateway->title,
        ];
    }
}
