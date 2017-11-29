<?php

class CurrencyConverter
{
    protected $curry_base_url = 'http://curry.atticlab.net/';

    public static function getCryptocurrenciesList()
    {
        $converter = new self();

        try {
            $rates = $converter->getCryptocurrenciesRates();
        } catch (\Exception $e) {
            return null;
        }

        $currencies = array_keys($rates);

        return $currencies;
    }

    public static function convertToCryptocurrency($amount, $currency_from, $currency_to)
    {
        if ($currency_from !== 'USD') {
            if ( ! in_array( 'woo-multi-currency/woo-multi-currency.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
                throw new Exception('Only USD is allowed for coinslot payment, because woo-multi-currency plugin is not enabled');
            }

            $multi_currency = new WOOMULTI_CURRENCY_F_Data();
            $rate = (float) $multi_currency->get_exchange($currency_from, 'USD')['USD'];

            $amount *= $rate;
        }

        $converter = new self();

        $crypto_rates = $converter->getCryptocurrenciesRates();

        if (empty($crypto_rates[$currency_to])) {
            throw new Exception('Can not get cryptocurrency rate for ' . $currency_to . ' from ' . $converter->curry_base_url);
        }

        $amount = $amount / $crypto_rates[$currency_to]; // todo: precision ? coinslot per cent ?

        return $amount;
    }

    protected function getCryptocurrenciesRates()
    {
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $this->curry_base_url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $exchange_rates = curl_exec($curl);

        $errors = curl_error($curl);

        curl_close($curl);

        $exchange_rates = json_decode($exchange_rates, true);

        if ($exchange_rates == false || !empty($errors) || (json_last_error() !== 0)) {
            error_log($exchange_rates);
            error_log($errors);
            throw new Exception('cURL error: ' . $errors);
        }

        return $exchange_rates;
    }
}