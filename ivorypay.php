<?php
/**
 * Plugin Name: Ivorypay
 * Plugin URI: https://www.ivorypay.io/
 * Description: Integrate Ivorypay as payment gateway in Woocommerce.
 * Author: Ivorypay Team
 * Author URI: https://www.ivorypay.io/
 * Version: 1.0.2
 * Text Domain: wc-gateway-ivorypay
 * Domain Path: /i18n/languages/
 *
 */
defined('ABSPATH') or exit;
// Make sure WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}
/**
 * Add the gateway to WC Available Gateways
 *
 * @since 1.0.0
 * @param array $gateways all available WC gateways
 * @return array $gateways all WC gateways + pt gateway
 */
 if (!function_exists('ivorypay_add_to_gateways')) {
function ivorypay_add_to_gateways($gateways) {
    $gateways[] = 'WC_Gateway_ivorypay';
    return $gateways;
}
}
add_filter('woocommerce_payment_gateways', 'ivorypay_add_to_gateways');
/**
 * Adds plugin page links
 *
 * @since 1.0.0
 * @param array $links all plugin links
 * @return array $links all plugin links + our custom links (i.e., "Settings")
 */
 if (!function_exists('ivorypay_gateway_plugin_links')) {
function ivorypay_gateway_plugin_links($links) {
    $plugin_links = array('<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=ivorypay_gateway') . '">' . __('Configure', 'wc-gateway-iv') . '</a>');
    return array_merge($plugin_links, $links);
}
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'ivorypay_gateway_plugin_links');
/**
 * ivorypay Payment Gateway
 *
 * Provides an ivorypay Payment Gateway; mainly for testing purposes.
 * We load it later to ensure WC is loaded first since we're extending it.
 *
 * @class 		WC_Gateway_ivorypay
 * @extends		WC_Payment_Gateway
 * @version		1.0.0
 * @package		WooCommerce/Classes/Payment
 * @author 		SkyVerge
 */
add_action('plugins_loaded', 'ivorypay_gateway_init', 11);
function ivorypay_gateway_init() {
    class WC_Gateway_ivorypay extends WC_Payment_Gateway {
        /**
         * Constructor for the gateway.
         */
        public function __construct() {
            $this->id = 'ivorypay_gateway';
            $this->icon = plugins_url('assets/logo.png', __FILE__);
            $this->has_fields = false;
            $this->method_title = __('Ivorypay Payment Gateway', 'wc-gateway-ivorypay');
            $this->method_description = __('Integration with Ivorypay Payment.', 'wc-gateway-ivorypay');
            // Add refund functio for product
            $this->supports = array('products', 'refunds');
            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->authorization = $this->get_option('authorization');
            $this->sandbox = $this->get_option('enabled_san');
            //Url for Live Mode or sandbox Mode
            if ($this->sandbox == 'Yes') {
                /**Url for sandbox**/
                $this->api_url = 'https://api.ivorypay.io/v1/payment-links';
                $this->api_countries = 'https://api.ivorypay.io/v1/countries/all';
                $this->api_currencies = 'https://api.ivorypay.io/v1/currencies';
            } else {
                /**Change with live api**/
                $this->api_url = 'https://api.ivorypay.io/v1/payment-links';
                $this->api_countries = 'https://api.ivorypay.io/v1/countries/all';
                $this->api_currencies = 'https://api.ivorypay.io/v1/currencies';
            }
            //	$this->return_url = get_site_url().'/?ivorypay_return=WC_Gateway_ivorypay';
            $url = get_site_url();
            $domain_name = str_replace('www.', '', parse_url($url, PHP_URL_HOST));
            $path = parse_url($url, PHP_URL_PATH);
            $new_url = $domain_name . $path;
            $this->return_url = '' . $new_url . '/?ivorypay_return=WC_Gateway_ivorypay';
            if (isset($_GET['section']) && $_GET['section'] == 'pt_gateway') {
                //$this->method_description = __( '<h2>Callback URL : '.$this->callback_url.'</h2><h2>Return URL : '.$this->return_url.'</h2>', 'wc-gateway-ivorypay' );
                
            } else {
                $this->method_description = __('Ivorypay', 'wc-gateway-ivorypay');
            }
            // Actions
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
        }
        public function thankyou_page() {
            /*if ( $this->instructions ) {
            wpautop( wptexturize( $this->instructions ) );
            }*/
        }
        /**
         * Create Hash and return the secureHash
         *
         * @param array $order, int $timestamp
         * @return string
         */
        public function ivorypay_generateSecureHash($order, $timestamp) {
            $string = $order->get_total() . ',' . $timestamp . ',' . $this->authorization;
            return $secureHash = trim((md5($string)));
        }
        /**
         * Initialize Gateway Settings Form Fields
         */
        public function init_form_fields() {
            $this->form_fields = apply_filters('wc_ivorypay_form_fields', array('enabled' => array('title' => __('Enable/Disable', 'wc-gateway-ivorypay'), 'type' => 'checkbox', 'label' => __('Enable Ivorypay Payment', 'wc-gateway-ivorypay'), 'default' => 'yes'), 'title' => array('title' => __('Title', 'wc-gateway-ivorypay'), 'type' => 'text', 'description' => __('This controls the title for the payment method the customer sees during checkout.', 'wc-gateway-ivorypay'), 'default' => __('Ivorypay', 'wc-gateway-ivorypay'), 'desc_tip' => true,), 'authorization' => array('title' => __('Authorization', 'wc-gateway-ivorypay'), 'type' => 'text', 'description' => __('This controls the API token.', 'wc-gateway-ivorypay'), 'default' => __('', 'wc-gateway-ivorypay'), 'desc_tip' => true,), 'enabled_san' => array('title' => __('Ivorypay Sandbox', 'wc-gateway-ivorypay'), 'type' => 'checkbox', 'label' => __('Enable Ivorypay Sandbox', 'wc-gateway-ivorypay'), 'default' => 'yes'), 'description' => array('title' => __('Description', 'wc-gateway-ivorypay'), 'type' => 'textarea', 'description' => __('Payment method description that the customer will see on your checkout.', 'wc-gateway-ivorypay'), 'default' => __('Ivorypay Description.', 'wc-gateway-ivorypay'), 'desc_tip' => true,),));
        }
        /**
         * Process the payment and return the result
         *
         * @param int $order_id
         * @return array
         */
        public function process_payment($order_id) {
            $error_msg = '';
            $order = wc_get_order($order_id);
            $timestamp = time();
            // $billing_country = $order->get_billing_country();
            $currency = $order->get_currency();
            $all_country = $this->ivorypay_curl_request($this->api_countries);
            $all_c = array();
            foreach ($all_country['data'] as $all_countryx) {
                $all_c[] = $all_countryx['code'];
            }
            // if (!in_array($billing_country, $all_c)) {
            //     wc_add_notice("Sorry currently ivorypay not support in $billing_country Country !  , please contact support@ivorypay.com.", 'error');
            //     return false;
            // }
            $all_currencies = $this->ivorypay_curl_request($this->api_currencies);
            $all_cur = array();
            foreach ($all_currencies['data'] as $all_currenciesx) {
                $all_cur[] = $all_currenciesx['code'];
            }
            if (!in_array($currency, $all_cur)) {
                $supported_currency = implode(' , ', $all_cur);
                wc_add_notice("Sorry currently ivorypay only support $supported_currency currency  !  , please contact support@ivorypay.io.", 'error');
                return false;
            }
            $hash = $this->ivorypay_generateSecureHash($order, $timestamp);
            $response = $this->ivorypay_submit_order_pt($order, $order_id, $hash);
            if (isset($response['success']) && $response['success'] == 1) {
                if (isset($response['data']['redirectLink']) && isset($response['data']['reference'])) {
                    update_post_meta($order_id, 'ivorypay_order_ref', $response['data']['reference']);
                    update_post_meta($order_id, 'ivorypay_order_hash', $hash);
                    $firstname = $order->get_billing_first_name();
                    $lastname = $order->get_billing_last_name();
                    $email = $order->get_billing_email();
                    $info = "firstname=$firstname&lastname=$lastname&email=$email";
                    $redirect_to = "https://checkout.ivorypay.io/checkout/" . $response['data']['reference'] . "?" . $info;
                    return array('result' => 'success', 'redirect' => $redirect_to);
                }
            } elseif ($response['success'] == false) {
                if ($response['statusCode'] == 401) {
                    wc_add_notice('Order cannot be placed due to incorrect merchant credentials. Please check the credentials and try again.', 'error');
                    return false;
                }
                if ($response['statusCode'] == 400) {
                    wc_add_notice('Order  cannot be placed If the issue persists, please contact support@ivorypay.io.', 'error');
                    return false;
                }
            } else {
                wc_add_notice('The server is currently unable to process this request. Please try again in a few minutes. If the issue persists, please contact support@ivorypay.com.', 'error');
                return false;
            }
        }
        /**
         * Submit Order detail to Payment Gateway and get Return Url
         *
         * @param array $order, int $order_id
         * @return array
         */
        public function ivorypay_submit_order_pt($order, $order_id, $hash) {
            $timestamp = time();
            $order_data = $order->get_data(); // The Order data
            $id = $order_id;
            $items = $order->get_items();
            $product_data = '';
            foreach ($items as $item) {
                $product_name = $item->get_name();
                $product_id = $item->get_product_id();
                $product = wc_get_product($product_id);
                $price = $product->get_price();
                $quantity = $item->get_quantity();
                $variation_id = $item->get_variation_id();
                $subtotal = $item->get_subtotal();
                $total = $item->get_total();
                $tax = $item->get_subtotal_tax();
                $product_data.= '{"name":"' . $product_name . '","quantity":"' . $quantity . '","cost":"' . $price . '","price":' . $price . ',"tax":' . $tax . ',"variation_id":' . $variation_id . ',"subtotal":' . $subtotal . ',"total":' . $total . '},';
            }
            $r_url = $this->return_url . "&ref=$hash";
            $dtasd = get_bloginfo('name');
            $inputString = array('name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(), 'description' => 'Order_id -' . $order->get_id() . ' Store_name -' . $dtasd . 'platform - Wordpress', 'baseFiat' => $order->get_currency(), 'amount' => $order->get_total(), 'redirectLink' => $r_url);
            return $this->ivorypay_curl_request($this->api_url, "POST", $inputString);
        }
        /**
         * Refund Process
         *
         * @param int $order_id, int $amount, str $reason
         * @return true or false
         */
        public function process_refund($order_id, $amount = null, $reason = '') {
        }
        public function ivorypay_curl_request($url, $method = 'GET', $data = array()) {
            $AUTH = $this->authorization;
            if ($method == "POST") {
                $response = wp_remote_post($url, array('body' => $data, 'headers' => array('Authorization' => "$AUTH")));
                $http_code = wp_remote_retrieve_response_code($response);
                $body = wp_remote_retrieve_body($response);
            } else {
                $response = wp_remote_get($url, array('headers' => array('Authorization' => "$AUTH")));
                $http_code = wp_remote_retrieve_response_code($response);
                $body = wp_remote_retrieve_body($response);
            }
            $output = json_decode($body, true);
            return $output;
        }
    } // end \WC_Gateway_ivorypay class
    
}
/**
 * Add log
 *
 * @param str $message
 */
if (!function_exists('ivorypay_add_query_vars_filter')) {
    function ivorypay_add_query_vars_filter($vars) {
        $vars[] = "ivorypay_return";
        return $vars;
    }
}
if (!function_exists('ivorypay_read_query_var')) {
    function ivorypay_read_query_var() {
        if (get_query_var('ivorypay_return') == 'WC_Gateway_ivorypay') {
            if (isset($_GET['ref'])) {
                global $wpdb;
                $order_hash = sanitize_text_field($_GET['ref']);
                $results = $wpdb->get_results($wpdb->prepare("SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = 'ivorypay_order_hash' AND meta_value = %s", $order_hash));
                foreach ($results as $result) {
                    $order_id = $result->post_id;
                }
                if ($order_id > 0) {
                    $order_key = get_post_meta($order_id, '_order_key', true);
                    if ($order_key) {
                        $order = wc_get_order($order_id);
                        $order->update_status('completed');
                        $return_url = wc_get_checkout_url() . '/order-received/' . $order_id . '/?key=' . $order_key;
                        wp_redirect($return_url);
                        exit;
                    }
                }
            }
        } // Return ends
        
    }
}
add_filter('query_vars', 'ivorypay_add_query_vars_filter');
add_action('template_redirect', 'ivorypay_read_query_var');
