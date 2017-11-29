<?php

add_action( 'init', 'wc_coinslot_callback_handler', 11 );

function wc_coinslot_callback_handler () {

    class CoinslotCallbackHandler extends WC_Settings_API
    {
        protected $error_message = 'Coinslot bot sent transaction info';

        public function checkForCallback()
        {
            if (!empty($_GET['coinslot_callback'])) {
                $this->handleCallback();
            }
        }

        protected function handleCallback()
        {
            $this->id = 'coinslot_gateway';
            $this->secret_key = $this->get_option('secret_key');
            $this->required_confirmations = $this->get_option('required_confirmations');

            $this->init_settings();

            // check sign
            $sign = $_SERVER['HTTP_SIGN'];
            $hmac = hash_hmac("sha256", implode($_POST), $this->secret_key);

            if ($sign != $hmac) {
                $this->returnError(', but incorrect SIGN, sign - ' . $sign);
            }

            $order_id = $_GET['order_id'];
            $order = wc_get_order($order_id);
            $this->error_message .= ' for order_id = ' . $order_id . ', but ';

            if (empty($order)) {
                $this->returnError('can not find it in a shop DB');
            }

            try {
                $order_amount_in_cryptocurrency = CurrencyConverter::convertToCryptocurrency($order->get_total(), $order->get_currency(), $_POST['currency']);
            } catch (\Exception $e) {
                $this->returnError($e->getMessage());
            }

            if ($_POST['confirmations'] < $this->required_confirmations) {
                $this->returnError('not enough confirmations (' . $_POST['confirmations'] . ' from ' . $this->required_confirmations . ')');
            }


            if ($_POST['amount'] < $order_amount_in_cryptocurrency) {
                $this->returnError('but not enough money (required = ' . $order_amount_in_cryptocurrency . ' ' . $_POST['currency'] . ', had got = ' . $_POST['amount'] . ')');
            }

            $order->payment_complete();

            echo $_POST['tx_hash'];
            die;
        }

        protected function returnError($message)
        {
            $message = $this->error_message . $message;
            error_log($message);
            wp_send_json_error();
            die;
        }
    }

    $handler = new CoinslotCallbackHandler;
    $handler->checkForCallback();
}
