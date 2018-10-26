<?php
function inkerk_erc20_add_settings_link($links) {
	$settings_link = '<a href="admin.php?page=wc-settings&tab=checkout">' . __('Settings') . '</a>';
	array_push($links, $settings_link);
	return $links;
}
add_filter("plugin_action_links_woocommerce-erc20-payment-gateway/woocommerce-erc20-payment-gateway.php", 'inkerk_erc20_add_settings_link');