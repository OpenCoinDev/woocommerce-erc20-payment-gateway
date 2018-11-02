<?php
/*
 * Plugin Name: WooCommerce ERC20 Payment Gateway
 * Version: 0.0.4
 * Plugin URI: http://www.inkerk.com/woocommerce-erc20-payment-gateway
 * Description: This Plugin will add ERC20 Token Payment Gateway
 * Author: inKerk Blockchain Inc.
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
/**
 * 添加链接到插件的 Meta 信息区域
 */
add_filter('plugin_row_meta', 'inkerk_add_link_to_plugin_meta', 10, 4);

function inkerk_add_link_to_plugin_meta($links_array, $plugin_file_name, $plugin_data, $status) {
	/**
	 * 使用 if 判断当前操作的插件是否是我们自己的插件。
	 */
	if (strpos($plugin_file_name, basename(__FILE__))) {
		// 在数组最后加入对应的链接
		// 如果希望显示在前面，可以参考一下 array_unshift 函数。
		$links_array[] = '<a href="#">FAQ</a>';
	}
	return $links_array;
}
/**
 * 添加插件名称设置
 */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'inkerk_erc20_add_settings_link');
function inkerk_erc20_add_settings_link($links) {
	$settings_link = '<a href="admin.php?page=wc-settings&tab=checkout">' . __('Settings') . '</a>';
	array_push($links, $settings_link);
	return $links;
}
/**
 * 加载 i18n 语言包
 */
add_action('init', 'inkerk_erc20_load_textdomain');
function inkerk_erc20_load_textdomain() {
	/**
	 * 这里的第一个参数为 __($str,'param') 中的 param ，即区分不同语言包域的参数
	 */
	load_plugin_textdomain('woocommerce-erc20-payment-gateway', false, basename(dirname(__FILE__)) . '/lang');
}

/**
 * 添加新的 Gateway
 */
add_filter('woocommerce_payment_gateways', 'inkerk_erc20_add_gateway_class');
function inkerk_erc20_add_gateway_class($gateways) {
	$gateways[] = 'WC_Inkerk_Erc20_Gateway';
	return $gateways;
}
/**
 * 监听插件的支付完成请求
 */
add_action('init', 'inkerk_thankyour_request');
function inkerk_thankyour_request() {
	/**
	 * 判定用户请求是否是特定路径。如果此处路径修改，需要对应修改 payments.js 中的代码
	 */
	if ($_SERVER["REQUEST_URI"] == '/hook/wc_erc20') {
		$data = $_POST;
		$order_id = $data['orderid'];
		$tx = $data['tx'];
		/**
		 * 获取到订单
		 */
		$order = wc_get_order($order_id);
		/**
		 * 标记订单支付完成
		 */
		$order->payment_complete();
		/**
		 * 添加订单备注，并表明 tx 的查看地址
		 */
		$order->add_order_note(__("Order payment completed", 'woocommerce-erc20-payment-gateway') . "Tx:<a target='_blank' href='http://etherscan.io/tx/" . $tx . "'>" . $tx . "</a>");
		/**
		 * 需要退出，不然会显示页面内容。退出就显示空白，也开业在界面打印一段 JSON。
		 */
		exit();
	}

}
/*
 * 插件加载以及对应的 class
 */
add_action('plugins_loaded', 'inkerk_erc20_init_gateway_class');
function inkerk_erc20_init_gateway_class() {
	/**
	 * 定义 class
	 */
	class WC_Inkerk_Erc20_Gateway extends WC_Payment_Gateway {

		/**
		 * Class constructor, more about it in Step 3
		 */
		public function __construct() {
			/**
			 * 定义所需内容
			 * @var string
			 */
			$this->id = 'inkerk_erc20';
			/**
			 * 设置 - 付款 - 支付方式界面展示的支付方式名称
			 * @var [type]
			 */
			$this->method_title = __('Pay with ERC20 Token', 'woocommerce-erc20-payment-gateway');
			/**
			 * 用户下单时显示的按钮的文字
			 */
			$this->order_button_text = __('Use Token Payment', 'woocommerce-erc20-payment-gateway');
			/**
			 * 设置 - 付款 - 支付方式界面展示的支付方式介绍
			 */
			$this->method_description = __('If you want to use this Payment Gateway, We suggest you read <a href="#">our guide </a> before.', 'woocommerce-erc20-payment-gateway');

			$this->supports = array(
				'products',
			); // 仅支持购买

			/**
			 * 初始化设置及后台设置界面
			 */
			$this->init_settings();
			$this->init_form_fields();

			// 使用 foreach 将设置都赋值给对象，方便后续调用。
			foreach ($this->settings as $setting_key => $value) {
				$this->$setting_key = $value;
			}

			/**
			 * 各种 hook
			 */
			add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
			add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
			add_action('woocommerce_api_compete', array($this, 'webhook'));
			add_action('admin_notices', array($this, 'do_ssl_check'));
			add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
			add_action('woocommerce_thankyou', array($this, 'thankyou_page'));

		}

		/**
		 * 插件设置项目
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
					'description' => __('Symbol will show on site,before price.', 'woocommerce-erc20-payment-gateway'),
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
				'gas_notice' => array(
					'title' => __('Gas Notice', 'woocommerce-erc20-payment-gateway'),
					'type' => 'textarea',
					'default' => __('Set a High Gas Price to speed up your transaction.', 'woocommerce-erc20-payment-gateway'),
					'description' => __('Tell Custome set a high gas price to speed up transaction.', 'woocommerce-erc20-payment-gateway'),
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
		/**
		 * 加载前台的支付用的 JavaScript
		 */
		public function payment_scripts() {
			wp_enqueue_script('inkerk_web3', plugins_url('assets/web3.min.js', __FILE__), array('jquery'), 1.1, true);
			wp_register_script('inkerk_payments', plugins_url('assets/payments.js', __FILE__), array('jquery', 'inkerk_web3'));
			wp_enqueue_script('inkerk_payments');
		}

		/**
		 * 不做表单验证，因为结算页面没有设置表单。
		 */
		public function validate_fields() {
			return true;
		}

		/**
		 * 用户结算页面的下一步操作
		 */
		public function process_payment($order_id) {
			global $woocommerce;
			$order = wc_get_order($order_id);
			/**
			 * 标记订单为未支付。
			 */
			$order->add_order_note(__('create order ,wait for payment', 'woocommerce-erc20-payment-gateway'));
			/**
			 * 设置订单状态为 unpaid ,后续可以使用 needs_payments 监测到
			 */
			$order->update_status('unpaid', __('Wait For Payment', 'woocommerce-erc20-payment-gateway'));
			/**
			 * 减少库存
			 */
			$order->reduce_order_stock();
			/**
			 * 清空购物车
			 */
			WC()->cart->empty_cart();
			/**
			 * 支付成功，进入 thank you 页面
			 */
			return array(
				'result' => 'success',
				'redirect' => $this->get_return_url($order),
			);
		}
		/**
		 * 检查是否使用了 SSL，确保安全。
		 */
		public function do_ssl_check() {
			if ($this->enabled == "yes") {
				if (get_option('woocommerce_force_ssl_checkout') == "no") {
					echo "<div class=\"error\"><p>" . sprintf(__("<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>"), $this->method_title, admin_url('admin.php?page=wc-settings&tab=checkout')) . "</p></div>";
				}
			}
		}
		/**
		 * thank you 页面配置
		 * 需要在此提醒用户支付。
		 */
		public function thankyou_page($order_id) {
			/**
			 * 如果未传入 order_id， 就返回。
			 */
			if (!$order_id) {
				return;
			}

			$order = wc_get_order($order_id);
			/**
			 * 监测订单是否需要支付
			 */
			if ($order->needs_payment()) {
				/**
				 * 如果需要支付，就输出订单信息。
				 */
				echo '<script>var order_id = ' . $order_id . ';var contract_address = "' . (string) $this->contract_address . '";var abiArray = ' . $this->abi_array . '; var target_address = "' . $this->target_address . '"; </script>';
				echo __('<h2 class="h2thanks">Use Metamask Pay this Order</h2>', 'woocommerce-erc20-payment-gateway');
				echo __('Click Button Below, Pay this order.<br>', 'woocommerce-erc20-payment-gateway');
				echo '<span style="margin:5px 0px;">' . $this->gas_notice . "</span><br>";
				echo '<div><button onclick="requestPayment(' . (string) $order->get_total() . ')">' . __('Open Metamask', 'woocommerce-erc20-payment-gateway') . '</button></div>';

			} else {
				/**
				 * 不需要支付就显示不需要支付。
				 */
				echo __('<h2>Your Order is already Payment done.</h2>', 'woocommerce-erc20-payment-gateway');
			}
		}
	}
}
