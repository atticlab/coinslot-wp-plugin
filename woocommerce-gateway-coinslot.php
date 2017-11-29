<?php
/**
 * Plugin Name: WooCommerce Coinslot Gateway
 * Plugin URI: https://coinslot.io
 * Description: Pay with coinslot
 * Author: Atticlab (atticlab.net) (Vladimir Voznyi)
 * Author URI: http://coinslot.io
 * Version: 1.0.0
 * Text Domain: wc-gateway-coinslot
 * Domain Path: /i18n/languages/
 *
 * Copyright: (c) coinslot and WooCommerce
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package   WC-Gateway-Coinslot
 * @author    Atticlab
 * @category  Admin
 * @copyright Copyright (c) 2015-2016, Coinslot, Inc. and WooCommerce
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 *
 * This coinslot gateway forks the WooCommerce core "Cheque" payment gateway to create another coinslot payment method.
 */


require_once ('woocommerce-gateway-coinslot-currency-converter.php');
require_once ('woocommerce-gateway-coinslot-callback-handler.php');

defined( 'ABSPATH' ) or exit;


// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    return;
}

/**
 * Add the gateway to WC Available Gateways
 *
 * @since 1.0.0
 * @param array $gateways all available WC gateways
 * @return array $gateways all WC gateways + coinslot gateway
 */
function wc_coinslot_add_to_gateways( $gateways ) {
    $gateways[] = 'WC_Gateway_Coinslot';
    return $gateways;
}

add_filter( 'woocommerce_payment_gateways', 'wc_coinslot_add_to_gateways' );


/**
 * Adds plugin page links
 *
 * @since 1.0.0
 * @param array $links all plugin links
 * @return array $links all plugin links + our custom links (i.e., "Settings")
 */
function wc_coinslot_gateway_plugin_links( $links ) {

    $plugin_links = array(
        '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=coinslot_gateway' ) . '">' . __( 'Configure', 'wc-gateway-coinslot' ) . '</a>'
    );

    return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_coinslot_gateway_plugin_links' );


/**
 * Coinslot Payment Gateway
 *
 * Provides an Coinslot Payment Gateway; mainly for testing purposes.
 * We load it later to ensure WC is loaded first since we're extending it.
 *
 * @class 		WC_Gateway_Coinslot
 * @extends		WC_Payment_Gateway
 * @version		1.0.0
 * @package		WooCommerce/Classes/Payment
 * @author 		Atticlab (atticlab.net) (Vladimir Voznyi)
 */
add_action( 'plugins_loaded', 'wc_coinslot_gateway_init', 11 );


function wc_coinslot_gateway_init() {

    class WC_Gateway_Coinslot extends WC_Payment_Gateway {

//        protected $coinslot_base_url = 'coinslot.io';
//        protected $x_api_key = '';

//        protected $coinslot_base_url = 'http://jh.demo-ico.tk';
//        protected $x_api_key = 'Lv2rc72jNnyAi1neKjJqKN';

        protected $coinslot_base_url = 'http://192.168.1.141:4004';
//        protected $x_api_key = 'CLavBDxLcQuRVdoswWhnsf';

        protected $ipn_url_base = 'http://192.168.1.113:8080';
//        protected $ipn_url_base = '';

        public function __construct() {

//            $this->ipn_url_base = get_site_url();

            $this->id                 = 'coinslot_gateway';
            $this->icon               = apply_filters('woocommerce_coinslot_icon', '');
            $this->method_title       = __( 'Coinslot', 'wc-gateway-coinslot' );
            $this->method_description = __( 'Allows coinslot payments. Very handy if you use your cheque gateway for another payment method, and can help with testing. Orders are marked as "on-hold" when received.', 'wc-gateway-coinslot' );

            $this->init_form_fields();
            $this->init_settings();

            $this->title        = $this->get_option( 'title' );
            $this->description  = $this->get_option( 'description' );
            $this->instructions = $this->get_option( 'instructions', $this->description );

            $this->x_api_key = $this->get_option('x_api_key');
            $this->required_confirmations = $this->get_option('required_confirmations', 12);

            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );

            add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
        }

        /**
         * Initialize Gateway Settings Form Fields
         */
        public function init_form_fields() {

            $this->form_fields = apply_filters( 'wc_coinslot_form_fields', [
                'enabled' => array(
                    'title'   => __( 'Enable/Disable', 'wc-gateway-coinslot' ),
                    'type'    => 'checkbox',
                    'label'   => __( 'Enable Coinslot Payment', 'wc-gateway-coinslot' ),
                    'default' => 'yes'
                ),

                'title' => array(
                    'title'       => __( 'Title', 'wc-gateway-coinslot' ),
                    'type'        => 'text',
                    'description' => __( 'This controls the title for the payment method the customer sees during checkout.', 'wc-gateway-coinslot' ),
                    'default'     => __( 'Coinslot Payment', 'wc-gateway-coinslot' ),
                    'desc_tip'    => true,
                ),

                'description' => array(
                    'title'       => __( 'Description', 'wc-gateway-coinslot' ),
                    'type'        => 'textarea',
                    'description' => __( 'Payment method description that the customer will see on your checkout.', 'wc-gateway-coinslot' ),
                    'default'     => __( 'Pay with cryptocurrency.', 'wc-gateway-coinslot' ),
                    'desc_tip'    => true,
                ),

                'instructions' => array(
                    'title'       => __( 'Instructions', 'wc-gateway-coinslot' ),
                    'type'        => 'textarea',
                    'description' => __( 'Instructions that will be added to the thank you page and emails.', 'wc-gateway-coinslot' ),
                    'default'     => '',
                    'desc_tip'    => true,
                ),

                'x_api_key' => array(
                    'title' => __('x_api_key', 'wc-gateway-coinslot'),
                    'type' => 'text',
                    'description' => __('x_api_key', 'wc-gateway-coinslot'),
                    'desc_tip' => true,
                ),

                'secret_key' => array(
                    'title' => __('secret_key', 'wc-gateway-coinslot'),
                    'type' => 'text',
                    'description' => __('secret_key', 'wc-gateway-coinslot'),
                    'desc_tip' => true,
                ),

                'required_confirmations' => array(
                    'title' => __('required_confirmations', 'wc-gateway-coinslot'),
                    'type' => 'text',
                    'description' => __('required_confirmations', 'wc-gateway-coinslot'),
                    'desc_tip' => true,
                ),
            ]);
        }

        public function payment_fields() {

            $currencies = CurrencyConverter::getCryptocurrenciesList();

            $fields = '<select name="selected_currency">';

            foreach ($currencies as $currency) {
                $fields .= '<option value="' . $currency . '">' . $currency . '</option>';
            }

            $fields .= '</select>';

            echo $fields;
        }


        /**
         * Output for the order received page.
         */
        public function thankyou_page() {

            $cryptoaddress = str_replace(' ', '+', $_GET['cryptoaddress']);

            $total = 'Total in &nbsp;&nbsp;<b>' . $_GET['cryptocurrency'] . '</b>: &nbsp;&nbsp;' . $_GET['cryptocurrency_amount'];
            $address = '<table><tr><td>Address - </td><td><img src="data:image/png;base64, ' . $cryptoaddress .'" /></td></tr></table>';

            echo $total . $address;
        }


        /**
         * Add content to the WC emails.
         *
         * @access public
         * @param WC_Order $order
         * @param bool $sent_to_admin
         * @param bool $plain_text
         */
        public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {

            if ( $this->instructions && ! $sent_to_admin && $this->id === $order->payment_method && $order->has_status( 'on-hold' ) ) {
                echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
            }
        }

        /**
         * Process the payment and return the result
         *
         * @param int $order_id
         * @return array
         */
        public function process_payment( $order_id )
        {
            $order = wc_get_order( $order_id );

            $cryptocurrency = $_POST['selected_currency'];

            try {
                $address = $this->get_crypto_address($order_id, $cryptocurrency);
                error_log('It was received order with id - ' . $order_id . ' and cryptoaddress - ' . $address);
                $qr_address_string = $this->get_qr_string($address);
            } catch (\Exception $e) {
                error_log($e->getMessage());
                return ['result' => 'error'];
            }

            try {
                $cryptocurrency_amount = CurrencyConverter::convertToCryptocurrency($order->get_total(), $order->get_currency(), $cryptocurrency);
            } catch (\Exception $e) {
                error_log($e->getMessage());
                return ['result' => 'error'];
            }

            $order->update_status( 'on-hold', __( 'Awaiting coinslot payment', 'wc-gateway-coinslot' ) );

            $order->reduce_order_stock();

            WC()->cart->empty_cart();

            return array(
                'result' 	=> 'success',
                'redirect'	=> $this->get_return_url( $order ) .
                    '&cryptocurrency_amount=' . $cryptocurrency_amount .
                    '&cryptocurrency=' . $cryptocurrency .
                    '&cryptoaddress=' . $qr_address_string
            );
        }

        protected function get_crypto_address($order_id, $cryptocurrency) {

            $url = $this->coinslot_base_url . '/ipn';

            $data = [
                "ipn_url" => $this->ipn_url_base . '/?coinslot_callback=true&order_id=' . $order_id,
                "confirmations" => $this->required_confirmations,
                "currency" => $cryptocurrency
            ];

            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));

            curl_setopt($curl, CURLOPT_HTTPHEADER, [
                'x-api-key: ' . $this->x_api_key,
                'Content-Type: application/x-www-form-urlencoded'
            ]);

            $response = curl_exec($curl);
            $errors = curl_error($curl);

            curl_close($curl);

            if ($response === false || !empty($errors)) {
                error_log($response);
                error_log($errors);
                throw new Exception('cURL error: ' . $errors); // ?
            }

            $response = json_decode($response, true);

            if (empty($response) || empty($response['address'])) {
                throw new Exception('Unable to parse response result (' . json_last_error() . ')');
            }

            return $response['address'];
        }

        protected function get_qr_string($address) {

            include "phpqrcode/qrlib.php";

            ob_start();
            QRCode::png($address, null, QR_ECLEVEL_L, 4);
            $qr_string = base64_encode( ob_get_contents() );
            ob_end_clean();

            return $qr_string;
        }
    }

}