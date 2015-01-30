<?php
/*
Plugin Name: WooCommerce Pay4App Payment Gateway
Plugin URI: https://pay4app.com
Description: Pay4App Payments for WooCommerce
Version: 0.3
Author: Pay4App
Author URI: https://pay4app.com
*/


function woocommerce_pay4app_init() 
{
	
	
    if (class_exists('WC_Payment_Gateway'))
    {	
    	include_once('pay4app.php');
    }
    
}

add_action('plugins_loaded', 'woocommerce_pay4app_init', 0);