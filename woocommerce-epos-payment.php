<?php
/*
Plugin Name: WooCommerce Epos Payment
Plugin URI:  https://github.com/nadjafzadeh/woocommerce-epos-payment
Description: Epos Payment Gateway for WooCommerce.
Version:     1.0.0
Author:      Kamran Nadjafzadeh
Author URI:  https://facebook.com/nadjafzadeh1
License:     GPL v3
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Text Domain: woocommerce-epos-payment
Domain Path: /languages
*/

//Exit if accessed directly.
if(!defined('ABSPATH')) exit;

$active_plugins = apply_filters('active_plugins', get_option('active_plugins'));
if(in_array('woocommerce/woocommerce.php', $active_plugins)){
    add_filter('woocommerce_payment_gateways', 'add_epos_payment');
    function add_epos_payment($gateways){
        $gateways[] = 'WC_Epos_Payment';
        return $gateways; 
    }
    add_action('plugins_loaded', 'init_epos_payment');
    function init_epos_payment(){
        require 'class-woocommerce-epos-payment.php';
    }
}

//Payment status checking
add_action('wp', function(){
    $epos_options = new WC_Epos_Payment();
    $success_url = $epos_options->success_url;
    $success_url = substr($success_url, strrpos($success_url, '/') + 1);
    if(isset($_SERVER['REQUEST_URI']) && isset($_GET['orderId'])){
        
        global $wpdb;
        //Get order_id if reference isset
        $transaction = $wpdb->get_results($wpdb->prepare( "SELECT * FROM " . $wpdb->prefix . "woocommerce_epos WHERE mdOrder = '%s' LIMIT 1", $_GET['orderId']));
      
        //If reference isset
        if($transaction == true){
            $order_id = $transaction[0]->order_id;
            $control_sum = $transaction[0]->control_sum;
            //Get data from plugin settings
            $public_key = $epos_options->public_key;
            $payment_status = $epos_options->payment_status;
            //Get payment status from Epos
            $url = $payment_status.'?key='.$public_key.'&sum='.$control_sum;
            $json = file_get_contents($url);
            $json_obj = json_decode($json);
            $status = $json_obj->result;
            //Change payment status
            $order = new WC_Order($order_id);
            if($status == 'success'){
                $order->update_status('processing', __('<b>Successful payment!</b>', 'woocommerce-epos-payment'));
            }else{
                $order->update_status('failed', __('<b>Payment failed!</b> Get more information from <a target="_blank" href="https://epos.az">Epos Account</a> or contact to your operator in Epos', 'woocommerce-epos-payment'));
            }
            //Get checkout ID
            $default_checkout_id = wc_get_page_id('checkout');
            //Check if WPML or Polylang installed
            if (function_exists('icl_object_id')){
                //If checkout in other lang, get it ID
                $lang_post_id = icl_object_id( $default_checkout_id , 'page', true, $language );
            }else{
                $lang_post_id = $default_checkout_id;
            }
            //Get order received URL
            $order_received_url = wc_get_endpoint_url('order-received', $order->id, get_permalink($lang_post_id));
            $order_received_url = add_query_arg('key', $order->order_key, $order_received_url);
            header("Location: ".$order_received_url);
        }else{
            header("Location: ".get_option('home'));
        }
    }
});

//Creating file and tabel for Epos
global $epos_db_version;
$epos_db_version = "1.0";
function epos_install(){
    global $wpdb;
    global $epos_db_version;
    //Creat table in mysql for references
    $table_name = $wpdb->prefix . "woocommerce_epos";
    if($wpdb->get_var("show tables like '$table_name'") != $table_name){
        $sql = "CREATE TABLE " . $table_name . " (
            id mediumint(9) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id mediumint(9) UNSIGNED NOT NULL,
            payment_id VARCHAR(55) NOT NULL,
            payment_url TEXT NOT NULL,
            mdOrder TEXT NOT NULL,
            control_sum TEXT NOT NULL,
            description TEXT NOT NULL,
            date DATETIME NOT NULL,
            PRIMARY KEY  (id)
        );";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        add_option("epos_db_version", $epos_db_version);
    }
}
register_activation_hook(__FILE__,'epos_install');

// Add Settings link
function epos_settings_link($links) {
  $settings_link = '<a href="'.admin_url( 'admin.php?page=wc-settings&tab=checkout&section=epos_payment').'">'.__('Settings', 'woocommerce-epos-payment').'</a>';
  array_unshift($links, $settings_link);
  return $links;
}
add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'epos_settings_link');
