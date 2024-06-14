<?php
class Shield_Gateway extends WC_Payment_Gateway
{

  const ID = 'shield_gateway';
  const TITLE = 'Shield Payments';
  const METHOD_DESCRIPTION = 'Accept crypto payments through Shield Payments';
  const ICON = 'assets/shield.png';

  public function __construct()
  {
    $this->id = self::ID;
    $this->title = __(self::TITLE, self::ID);
    $this->method_title = __(self::TITLE, self::ID);
    $this->method_description = __(self::METHOD_DESCRIPTION, self::ID);
    $this->icon = plugin_dir_url(__FILE__) . self::ICON;
    $this->has_fields = false;

    $this->init_form_fields();
    $this->init_settings();

    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    add_action('woocommerce_thankyou', array($this, 'handle_return_url'), 10, 1);
  }

  public function init_form_fields()
  {
    $this->form_fields = array(
      'enabled' => array(
        'title'   => __('Enable/Disable', self::ID),
        'type'    => 'checkbox',
        'label'   => __('Enable Shield Payments', self::ID),
        'default' => 'yes',
      ),
      'api_key' => array(
        'title'       => __('Shield API KEY', self::ID),
        'type'        => 'text',
        'description' => __('You can get your API KEY from your Shield Payments account.', self::ID),
        'default'     => '',
        'desc_tip'    => true,
      ),
      'api_base_url' => array(
        'title'       => __('API Base URL', self::ID),
        'type'        => 'text',
        'description' => __('This is the base URL of the Shield Payments API.', self::ID),
        'default'     => __('https://paybackend.getshield.xyz', self::ID),
        'desc_tip'    => true,
      ),
      'payment_base_url' => array(
        'title'       => __('Payment Base URL', self::ID),
        'type'        => 'text',
        'description' => __('This is the base URL of the Shield Payments Page.', self::ID),
        'default'     => __('https://woo-comm-front.vercel.app', self::ID),
        'desc_tip'    => true,
      ),
    );
  }

  public function get_api_base_url()
  {
    $api_base_url = $this->get_option('api_base_url');
    return rtrim($api_base_url, '/');
  }

  public function get_payment_base_url()
  {
    $payment_base_url = $this->get_option('payment_base_url');
    return rtrim($payment_base_url, '/');
  }

  private function api_request($url, $method, $data = null)
  {
    $args = array(
      'headers' => array(
        'Content-Type' => 'application/json',
        'x-api-key' => $this->get_option('api_key')
      ),
    );

    if ($data !== null) {
      $args['body'] = json_encode($data);
    }

    $response = ($method === 'POST') ? wp_remote_post($url, $args) : wp_remote_get($url, $args);

    if (is_wp_error($response)) {
      return $response;
    }

    $body = wp_remote_retrieve_body($response);
    return json_decode($body, true);
  }

  private function create_payment($price, $return_url)
  {
    $api_base_url = $this->get_api_base_url();
    $url = "{$api_base_url}/api/payments";
    $data = array(
      'status' => 'created',
      'base_amount' => $price,
      'return_url' => $return_url
    );
    return $this->api_request($url, 'POST', $data);
  }

  private function get_payment_status($payment_id)
  {
    $api_base_url = $this->get_api_base_url();
    $url = "{$api_base_url}/api/payments/get/" . $payment_id;

    return $this->api_request($url, 'GET');
  }

  public function process_payment($order_id)
  {
    $order = wc_get_order($order_id);

    $return_url = $this->get_return_url($order);

    $api_base_url = $this->get_api_base_url();

    $response = $this->create_payment($order->get_total(), $return_url);

    if (is_wp_error($response) || $response['status'] !== 'success') {
      return array(
        'result'   => 'error',
        'redirect' => '',
      );
    }

    $payment_id = $response['response']['_id'];

    $order->update_status('pending-payment');
    $order->update_meta_data('shield_payment_id', $payment_id);
    $order->save();

    $payment_url = "{$this->get_payment_base_url()}/pay/{$payment_id}";

    return array(
      'result'   => 'success',
      'redirect' => $payment_url,
    );
  }

  public function handle_return_url($order_id)
  {
    $order = wc_get_order($order_id);

    $payment_id = $order->get_meta('shield_payment_id', true);

    if (!$payment_id) {
      $order->update_status('failed');
      $order->save();
      return;
    }

    $response = $this->get_payment_status($payment_id);

    if (is_wp_error($response) || !$response['status'] === 'success') {
      $order->update_status('on-hold');
      $order->save();
      return;
    }

    $payment_status = $response['response']['status'];

    if ($payment_status === 'success') {
      $order->payment_complete();
      $order->update_status('completed');
      $order->save();
      return;
    }

    $order->update_status('pending-payment');
    $order->save();
  }
}
