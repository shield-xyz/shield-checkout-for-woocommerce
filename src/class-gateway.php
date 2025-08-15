<?php
class Shield_Gateway extends WC_Payment_Gateway
{
  private $logger;
  public function __construct()
  {
    $this->id = 'shield_gateway';
    $this->title = __('Shield Checkout', 'shield_gateway');
    $this->method_title = __('Shield Checkout for WooCommerce', 'shield_gateway');
    $this->method_description = __('Accept crypto payments through Shield Checkout', 'shield_gateway');
    $this->icon = plugin_dir_url(__FILE__) . '../assets/shield.png';
    $this->has_fields = false;
    // WooCommerce logger instance
    $this->logger = wc_get_logger();

    $this->init_form_fields();
    $this->init_settings();

    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    add_action('woocommerce_thankyou', array($this, 'handle_return_url'), 10, 1);
  }

  public function init_form_fields()
  {
    $this->form_fields = array(
      'enabled' => array(
        'title' => __('Enable/Disable', 'shield_gateway'),
        'type' => 'checkbox',
        'label' => __('Enable Shield Checkout', 'shield_gateway'),
        'default' => 'yes',
      ),
      'debug' => array(
        'title' => __('Debug log', 'shield_gateway'),
        'type' => 'checkbox',
        'label' => __('Enable logging', 'shield_gateway'),
        'default' => 'no',
        'desc_tip' => true,
      ),
      'api_key' => array(
        'title' => __('Shield Checkout API KEY', 'shield_gateway'),
        'type' => 'text',
        'description' => __('You can get your API KEY from your Shield Checkout account.', 'shield_gateway'),
        'default' => '',
        'desc_tip' => true,
      ),
      'api_base_url' => array(
        'title' => __('API Base URL', 'shield_gateway'),
        'type' => 'text',
        'description' => __('This is the base URL of the Shield Checkout API.', 'shield_gateway'),
        'default' => __('https://checkout-api.getshield.xyz', 'shield_gateway'),
        'desc_tip' => true,
      ),
      'checkout_base_url' => array(
        'title' => __('Checkout Base URL', 'shield_gateway'),
        'type' => 'text',
        'description' => __('This is the base URL of the Shield Checkout Page.', 'shield_gateway'),
        'default' => __('https://checkout.getshield.xyz', 'shield_gateway'),
        'desc_tip' => true,
      ),
      'expiration_time' => array(
        'title' => __('Expiration Time', 'shield_gateway'),
        'type' => 'number',
        'description' => __('This is the expiration time of the checkout in seconds.', 'shield_gateway'),
        'default' => 1800, // 30 minutes
        'desc_tip' => true,
      ),
    );
  }

  public function get_api_base_url()
  {
    $api_base_url = $this->get_option('api_base_url');
    return rtrim($api_base_url, '/');
  }

  public function get_checkout_base_url()
  {
    $checkout_base_url = $this->get_option('checkout_base_url');
    return rtrim($checkout_base_url, '/');
  }

  /**
   * Helper to write to WooCommerce log when debug is enabled.
   *
   * @param string $message
   * @param string $level   info | notice | warning | error | critical | alert | emergency
   */
  private function log_message($message, $level = 'info')
  {
    if ('yes' === $this->get_option('debug') && $this->logger) {
      $this->logger->log($level, $message, array('source' => $this->id));
    }
  }

  private function api_request($url, $method, $data = null)
  {
    // Log the API request details
    $parsed_url = parse_url($url);
    $path = $parsed_url['path'] ?? '';
    $query = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
    $full_path = $path . $query;
    
    $this->log_message("API Request: {$method} {$url}", 'info');
    $this->log_message("API Request Path: {$full_path}", 'info');
    if ($data !== null) {
      $this->log_message("API Request Data: " . wp_json_encode($data), 'info');
    }

    $args = array(
      'headers' => array(
        'Content-Type' => 'application/json',
        'x-api-key' => $this->get_option('api_key'),
      ),
      'timeout' => 30,
    );

    if ($data !== null) {
      $args['body'] = wp_json_encode($data);
    }

    $response = ($method === 'POST') ? wp_remote_post($url, $args) : wp_remote_get($url, $args);

    // Network or transport error.
    if (is_wp_error($response)) {
      $this->log_message("API Request Failed: " . $response->get_error_message(), 'error');
      return $response;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $this->log_message("API Response Status: {$status_code}", 'info');
    
    if ($status_code < 200 || $status_code >= 300) {
      $this->log_message("API HTTP Error: {$status_code}", 'error');
      return new WP_Error('shield_api_http_error', 'Shield API HTTP error: ' . $status_code);
    }

    $body = wp_remote_retrieve_body($response);
    $this->log_message("API Response Body: " . $body, 'info');
    
    $decoded = json_decode($body, true);

    if (null === $decoded && json_last_error() !== JSON_ERROR_NONE) {
      $this->log_message("API JSON Decode Error: " . json_last_error_msg(), 'error');
      return new WP_Error('shield_api_json_error', 'Invalid JSON received from Shield API');
    }

    return $decoded;
  }

  private function create_checkout($price, $return_url, $order_id, $currency)
  {
    $api_base_url = $this->get_api_base_url();
    $url = "{$api_base_url}/checkouts";
    $data = array(
      'amount' => $price,
      'reference' => $order_id,
      'redirect' => $return_url,
      'currency' => strtolower($currency),
      'expiration_time' => (int) $this->get_option('expiration_time', 1800),
    );
    return $this->api_request($url, 'POST', $data);
  }

  private function create_checkout_session($id)
  {
    $api_base_url = $this->get_api_base_url();
    $url = "{$api_base_url}/checkouts/{$id}/session";
    return $this->api_request($url, 'POST');
  }

  private function get_checkout_status($checkout_id)
  {
    $api_base_url = $this->get_api_base_url();
    $url = "{$api_base_url}/checkouts/" . $checkout_id;

    return $this->api_request($url, 'GET');
  }

  public function process_checkout($order_id)
  {
    $this->log_message('Starting checkout process for order ' . $order_id);
    $order = wc_get_order($order_id);

    $return_url = $this->get_return_url($order);

    $response = $this->create_checkout($order->get_total(), $return_url, $order_id, "usd");
    $this->log_message('Create checkout response: ' . wp_json_encode($response), 'info');

    if (is_wp_error($response) || !isset($response['id'])) {
      $this->log_message('Checkout creation failed: ' . (is_wp_error($response) ? $response->get_error_message() : wp_json_encode($response)), 'error');
      return array(
        'result' => 'error',
        'redirect' => '',
      );
    }

    $checkout_id = $response['id'];

    $session_response = $this->create_checkout_session($checkout_id);

    $this->log_message('Create checkout session response: ' . wp_json_encode($session_response), 'info');

    if (is_wp_error($session_response) || !isset($session_response['token'])) {
      $this->log_message('Checkout session creation failed: ' . (is_wp_error($session_response) ? $session_response->get_error_message() : wp_json_encode($session_response)), 'error');
      return array(
        'result' => 'error',
        'redirect' => '',
      );
    }

    $session_token = $session_response['token'];

    $order->update_status('pending-payment');
    $order->update_meta_data('shield_checkout_id', $checkout_id);
    $order->save();

    $checkout_url = "{$this->get_checkout_base_url()}?session={$session_token}";

    return array(
      'result' => 'success',
      'redirect' => $checkout_url,
    );
  }

  /**
   * WooCommerce callback required by the gateway API.
   *
   * @param int $order_id
   * @return array
   */
  public function process_payment($order_id)
  {
    return $this->process_checkout($order_id);
  }

  public function handle_return_url($order_id)
  {
    $order = wc_get_order($order_id);

    if ($order->get_payment_method() !== $this->id) {
      return; // Exit if the payment method is not Shield Gateway
    }

    $checkout_id = $order->get_meta('shield_checkout_id', true);

    if (!$checkout_id) {
      $order->update_status('failed');
      $order->save();
      return;
    }

    $response = $this->get_checkout_status($checkout_id);

    // Log the full API response for troubleshooting.
    $this->log_message('Checkout status: ' . wp_json_encode($response), 'info');

    // Handle network / transport errors early.
    if (is_wp_error($response) || !isset($response['transaction'])) {
      $this->log_message('Error retrieving checkout status: ' . (is_wp_error($response) ? $response->get_error_message() : wp_json_encode($response)), 'error');
      $order->update_status('failed');
      $order->save();
      return;
    }

    $checkout_status = $response['status'] ?? '';
    $transaction_status = $response['transaction']['status'] ?? '';

    if ($checkout_status === 'CANCELED') {
      $order->update_status('cancelled');
      $order->save();
      return;
    }

    if ($transaction_status === 'PROCESSED') {
      $order->payment_complete();
      $order->update_status('completed');
    } elseif ($transaction_status === 'INITIATED') {
      $order->update_status('processing');
    } elseif ($transaction_status === 'FAILED') {
      $order->update_status('failed');
    } else {
      $order->update_status('on-hold');
    }

    $order->save();
    return;
  }
}
