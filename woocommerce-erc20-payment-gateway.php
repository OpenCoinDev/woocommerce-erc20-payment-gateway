<?php
/*
 * Plugin Name: WooCommerce ERC20 Payment Gateway
 * Version: 0.0.1
 * Plugin URI: http://www.inkerk.com/woocommerce-erc20-payment-gateway
 * Description: This Plugin will add ERC20 Token Payment Gateway
 * Author: Inkerk Inc.
 * Author URI: http://www.inkerk.com/
 * Requires at least: 4.7.0
 * Tested up to: 4.9.8
 *
 * Text Domain: woocommerce-erc20-payment-gateway
 * Domain Path: /lang/
 */

if (!defined('ABSPATH')) {
	exit;
}

function inkerk_erc20_load_textdomain() {
	load_plugin_textdomain('woocommerce-erc20-payment-gateway', false, basename(dirname(__FILE__)) . '/lang');
}
add_action('init', 'inkerk_erc20_load_textdomain');
add_filter('woocommerce_payment_gateways', 'inkerk_erc20_add_gateway_class');
function inkerk_erc20_add_gateway_class($gateways) {
	$gateways[] = 'WC_Inkerk_Erc20_Gateway';
	return $gateways;
}
add_action('init', 'inkerk_thankyour_request');
function inkerk_thankyour_request() {

	if ($_SERVER["REQUEST_URI"] == '/hook/wc_erc20') {
		$data = $_POST;
		$order_id = $data['orderid'];
		$tx = $data['tx'];
		$order = wc_get_order($order_id);
		$order->payment_complete();
		$order->add_order_note(__("Order payment completed", 'woocommerce-erc20-payment-gateway') . "Tx:<a target='_blank' href='http://etherscan.io/tx/" . $tx . "'>" . $tx . "</a>");
		exit();
	}

}
/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action('plugins_loaded', 'inkerk_erc20_init_gateway_class');
function inkerk_erc20_init_gateway_class() {

	class WC_Inkerk_Erc20_Gateway extends WC_Payment_Gateway {

		/**
		 * Class constructor, more about it in Step 3
		 */
		public function __construct() {
			$this->id = 'inkerk_erc20';
			$this->has_fields = true;
			$this->method_title = __('Pay with ERC20 Token', 'woocommerce-erc20-payment-gateway');
			$this->order_button_text = __('Use Token Payment', 'woocommerce-erc20-payment-gateway');
			$this->method_description = __('If you want to use this Payment Gateway, We suggest you read <a href="#">our guide </a> before.', 'woocommerce-erc20-payment-gateway');

			$this->supports = array(
				'products',
			); // 仅支持购买

			$this->init_settings();
			$this->init_form_fields();

			// Turn these settings into variables we can use
			foreach ($this->settings as $setting_key => $value) {
				$this->$setting_key = $value;
			}

			add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
			add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
			add_action('woocommerce_api_compete', array($this, 'webhook'));
			add_action('admin_notices', array($this, 'do_ssl_check'));
			add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
			add_filter('the_title', array($this, 'title_order_received'), 10, 2);
			add_action('woocommerce_thankyou', array($this, 'thankyou_page'));
			add_filter('woocommerce_currencies', array($this, 'inkerk_add_my_currency'));
			add_filter('woocommerce_currency_symbol', array($this, 'inkerk_add_my_currency_symbol'), 10, 2);
			add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'inkerk_erc20_add_settings_link');
		}

		/**
		 * Plugin options, we deal with it in Step 3 too
		 */
		public function init_form_fields() {

			$this->form_fields = array(
				'enabled' => array(
					'title' => __('Enable/Disable', 'woocommerce-erc20-payment-gateway'),
					'label' => __('Enable ERC20 Payment Gateway', 'woocommerce-erc20-payment-gateway'),
					'type' => 'checkbox',
					'default' => 'no',
				),
				'symbol' => array(
					'title' => __('Symbol', 'woocommerce-erc20-payment-gateway'),
					'type' => 'text',
					'description' => __('Symbol will show on site,before price.'),
					'default' => '$',
				),
				'title' => array(
					'title' => __('Title', 'woocommerce-erc20-payment-gateway'),
					'type' => 'text',
					'description' => __('Title Will Show at Checkout Page', 'woocommerce-erc20-payment-gateway'),
					'default' => 'ERC20 Payment Gateway',
					'desc_tip' => true,
				),
				'description' => array(
					'title' => __('Description', 'woocommerce-erc20-payment-gateway'),
					'type' => 'textarea',
					'description' => __('Description  Will Show at Checkout Page', 'woocommerce-erc20-payment-gateway'),
					'default' => __('Please make sure you already install Metamask && enable it.', 'woocommerce-erc20-payment-gateway'),
				),
				'target_address' => array(
					'title' => __('Wallet Address', 'woocommerce-erc20-payment-gateway'),
					'type' => 'text',
					'description' => __('Token Will Transfer into this Wallet', 'woocommerce-erc20-payment-gateway'),
				),
				'abi_array' => array(
					'title' => __('Contract ABI', 'woocommerce-erc20-payment-gateway'),
					'type' => 'textarea',
					'description' => __('You Can get ABI From Etherscan.io', 'woocommerce-erc20-payment-gateway'),
				),
				'contract_address' => array(
					'title' => __('Contract Address', 'woocommerce-erc20-payment-gateway'),
					'type' => 'text',
				),
				'icon' => array(
					'title' => __('Payment icon', 'woocommerce-erc20-payment-gateway'),
					'type' => 'text',
					'default' => 'https://postimg.aliavv.com/newmbp/eb9ty.png',
					'description' => __('Image Height:25px', 'woocommerce-erc20-payment-gateway'),
				),
			);
			$this->form_fields += array(

				'ad1' => array(
					'title' => '发币咨询服务',
					'type' => 'title',
					'description' => '我们为用户提供专业的发币咨询服务',
				),
				'ad2' => array(
					'title' => '安全审计服务',
					'type' => 'title',
					'description' => '我们为用户提供专业的安全审计服务',
				),
				'ad3' => array(
					'title' => '联系我们',
					'type' => 'title',
					'description' => '您可以通过 <a href="mailto:contact@inkerk.com">contact@inkerk.com</a> 联系我们',
				),

			);
		}

		public function payment_scripts() {
			wp_enqueue_script('inkerk_web3', plugins_url('assets/web3.min.js', __FILE__), array('jquery'), 1.1, true);
			wp_register_script('inkerk_payments', plugins_url('assets/payments.js', __FILE__), array('jquery', 'inkerk_web3'));
			wp_enqueue_script('inkerk_payments');
		}

		/*
			         * Fields validation, more in Step 5
		*/
		public function validate_fields() {
			return true;
		}

		/*
			         * We're processing the payments here, everything about it is in Step 5
		*/
		public function process_payment($order_id) {
			global $woocommerce;
			$order = wc_get_order($order_id);
			$order->add_order_note(__('create order ,wait for payment', 'woocommerce-erc20-payment-gateway'));
			$order->update_status('unpaid', __('Wait For Payment', 'woocommerce-erc20-payment-gateway'));
			$order->reduce_order_stock();
			WC()->cart->empty_cart();
			return array(
				'result' => 'success',
				'redirect' => $this->get_return_url($order),
			);
		}
		public function do_ssl_check() {
			if ($this->enabled == "yes") {
				if (get_option('woocommerce_force_ssl_checkout') == "no") {
					echo "<div class=\"error\"><p>" . sprintf(__("<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>"), $this->method_title, admin_url('admin.php?page=wc-settings&tab=checkout')) . "</p></div>";
				}
			}
		}
		public function thankyou_page($order_id) {
			if (!$order_id) {
				return;
			}

			$order = wc_get_order($order_id);
			if ($order->needs_payment()) {
				echo '<script>var order_id = ' . $order_id . ';var contract_address = "' . (string) $this->contract_address . '";var abiArray = ' . $this->abi_array . '; var target_address = "' . $this->target_address . '"; </script>';
				echo __('<h2 class="h2thanks">Use Metamask Pay this Order</h2>', 'woocommerce-erc20-payment-gateway');
				echo __('Click Button Below, Pay this order.<br>', 'woocommerce-erc20-payment-gateway');
				echo '<button onclick="requestPayment(' . (string) $order->get_total() . ')">' . __('Open Metamask', 'woocommerce-erc20-payment-gateway') . '</button>';

			} else {
				echo __('<h2>Your Order is already Payment done.</h2>', 'woocommerce-erc20-payment-gateway');
			}

		}
		public function title_order_received($title, $id) {
			if (function_exists('is_order_received_page') &&
				is_order_received_page() && get_the_ID() === $id) {
				$title = __('Please Pay for you order at bottom :)', 'woocommerce-erc20-payment-gateway');
			}
			return $title;

		}
		public function inkerk_add_my_currency($currencies) {
			$currencies['ERC20'] = 'ERC20';
			return $currencies;
		}
		public function inkerk_add_my_currency_symbol($currency_symbol, $currency) {
			switch ($currency) {
			case 'ERC20':$currency_symbol = $this->symbol;
				break;
			}
			return $currency_symbol;
		}
		public function inkerk_erc20_add_settings_link($links) {
			$settings_link = '<a href="admin.php?page=wc-settings&tab=checkout">' . __('Settings') . '</a>';
			array_push($links, $settings_link);
			return $links;
		}

	}
}
