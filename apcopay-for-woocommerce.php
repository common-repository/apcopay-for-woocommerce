<?php

/**
 * @link              https://www.apcopay.com/
 * @since             1.0.0
 * @package           ApcoPay_for_WooCommerce
 * 
 * @wordpress-plugin
 * Plugin Name:       ApcoPay for WooCommerce
 * Description: Adds the functionality to pay with ApcoPay to WooCommerce
 * Version:           1.6.4
 * Author:            ApcoPay
 * Author URI:        https://www.apcopay.com/
 * License:           GPLv3
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * WC tested up to:   6.5.1
 */

$apcopay_for_woocommerce_active_plugins = apply_filters('active_plugins', get_option('active_plugins'));
if (in_array('woocommerce/woocommerce.php', $apcopay_for_woocommerce_active_plugins)) {
	add_filter('woocommerce_payment_gateways', 'apcopay_for_woocommerce_add_payment_gateway');
	function apcopay_for_woocommerce_add_payment_gateway($gateways)
	{
		$gateways[] = 'WC_Gateway_ApcoPay';
		return $gateways;
	}

	add_action('plugins_loaded', 'apcopay_for_woocommerce_init');
	function apcopay_for_woocommerce_init()
	{
		require 'includes/class-wc-gateway-apcopay.php';
	}

	add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'apcopay_for_woocommerce_add_action_links');
	function apcopay_for_woocommerce_add_action_links($links)
	{
		$mylinks = array(
			'<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=apcopay') . '">Settings</a>',
		);
		return array_merge($links, $mylinks);
	}

	add_action('wp_ajax_apcopay_for_woocommerce_extra_charge', 'apcopay_for_woocommerce_extra_charge_handler');
	function apcopay_for_woocommerce_extra_charge_handler()
	{
		check_ajax_referer('apcopay-for-woocommerce-admin-extra-charge');

		$gateway = new WC_Gateway_ApcoPay();
		$gateway->extra_charge_handler();

		wp_die();
	}

	add_action('wp_ajax_apcopay_for_woocommerce_capture', 'apcopay_for_woocommerce_capture_handler');
	function apcopay_for_woocommerce_capture_handler()
	{
		check_ajax_referer('apcopay-for-woocommerce-admin-capture');

		$gateway = new WC_Gateway_ApcoPay();
		$gateway->capture_handler();

		wp_die();
	}

	add_action('admin_enqueue_scripts', 'apcopay_for_woocommerce_admin_enqueue_scripts');
	function apcopay_for_woocommerce_admin_enqueue_scripts()
	{
		wp_enqueue_script(
			'apcopay-for-woocommerce-admin-order',
			plugins_url('/assets/js/admin/order.js', __FILE__),
			array('jquery', 'jquery-blockui')
		);
		wp_localize_script(
			'apcopay-for-woocommerce-admin-order',
			'apcopay_for_woocommerce_admin_order_data',
			array(
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce_extra_charge'    => wp_create_nonce('apcopay-for-woocommerce-admin-extra-charge'),
				'nonce_capture'    => wp_create_nonce('apcopay-for-woocommerce-admin-capture'),
				'messages' => array(
					'enter_extra_charge_amount' => __('Enter extra charge amount', 'woocommerce'),
					'error_processing_request' => __('Error processing request', 'woocommerce'),
					'extra_charge_success' => __('Successfully processed extra charge', 'woocommerce'),
					'enter_capture_amount' => __('Enter capture amount', 'woocommerce'),
					'capture_success' => __('Successfully processed capture', 'woocommerce'),
				)
			)
		);
	}

	add_action('wp_ajax_apcopay_for_woocommerce_generate_token', 'apcopay_for_woocommerce_generate_token_handler');
	add_action('wp_ajax_nopriv_apcopay_for_woocommerce_generate_token', 'apcopay_for_woocommerce_generate_token_handler');
	function apcopay_for_woocommerce_generate_token_handler()
	{
		check_ajax_referer('apcopay-for-woocommerce-generate-token');

		$gateway = new WC_Gateway_ApcoPay();
		$gateway->generate_token();

		wp_die();
	}
}