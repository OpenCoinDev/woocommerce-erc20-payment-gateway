<?php
/**
 * 创建一个新的 Token
 */
add_filter('woocommerce_currencies', 'inkerk_add_my_currency');

function inkerk_add_my_currency($currencies) {
	$currencies['ERC20'] = 'ERC20';
	return $currencies;
}

add_filter('woocommerce_currency_symbol', 'inkerk_add_my_currency_symbol', 10, 2);

function inkerk_add_my_currency_symbol($currency_symbol, $currency) {
	switch ($currency) {
	case 'ERC20':$currency_symbol = '𝘾';
		break;
	}
	return $currency_symbol;
}