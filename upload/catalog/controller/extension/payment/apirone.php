<?php

class ApironeCurl {
  private $logger;
  private static $instance;
 
  /**
 * @param  object  $registry  Registry Object
 */
  public static function get_instance($registry) {
 if (is_null(static::$instance)) {
 static::$instance = new static($registry);
 }
 
 return static::$instance;
  }
 
  /** 
 * @param  object  $registry  Registry Object
 * 
 */
  protected function __construct($registry) {
 // load the "Log" library from the "Registry"
 $this->logger = $registry->get('log');
  }
 
  /**
 * @param  string  $url Url
 * @param  array  $json_data  Key-value pair
 */
  public function do_request($url, $json_data=array()) {
 // log the request
 //$this->logger->write("Initiated CURL request for: {$url}");
    if(is_array($json_data) && count($json_data)) {
        //query with json data
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($json_data));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($curl, CURLOPT_TIMEOUT, 20);
        $response_wallet = curl_exec($curl);
        $http_status_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if ($http_status_code == 200) {
            $decoded = json_decode($response_wallet, true);
            $wallet = $decoded["wallet"];
            $api_base = "https://apirone.com/api/v2/wallet/". $wallet ."/address";
            $curl = curl_init($api_base);
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            $http_status_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $response = curl_exec($curl);
            curl_close($curl);
            $decoded = json_decode($response, true);
            $result = $decoded;
        } else {
            $result = 0;
        }
    } else {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($ch);
        curl_close($ch);
    }
 
    return $result;

    }
}


class ControllerExtensionPaymentApirone extends Controller {

    public function index() {

        $data['button_confirm'] = $this->language->get('button_confirm');
        $this->load->model('checkout/order');
        $this->load->language('extension/payment/apirone');
        $this->load->model('extension/payment/apirone');

        $order_id = $this->session->data['order_id'];
        $test = $this->config->get('payment_apirone_test_mode');
        //get test mode status
        if ($test){
            /*$apirone_adr =$this->language->get('test_url');*/
            $apirone_adr = $this->language->get('live_url');
        }
        else{
            $apirone_adr = $this->language->get('live_url');
        }
            //lang 
            $data['and_pay'] = $this->language->get('and_pay');
            $data['pay'] = $this->language->get('pay');

            $data['payment_details'] = $this->language->get('payment_details');
            $data['text_wait'] = $this->language->get('text_wait');

            $fullCurrencyName['btc'] = $this->language->get('bitcoin');
            $fullCurrencyName['ltc'] = $this->language->get('litecoin');
            $fullCurrencyName['bch'] = $this->language->get('bitcoin_cash');

        $crypto = '';
        $work_cryptos = array();
        $available_cryptos = array('btc', 'bch', 'ltc');
        foreach ($available_cryptos as $currency) {
            if( $this->abf_getCryptoAddress($currency) != '' ) {
                $checked = false;
                if($crypto == '') {
                    $crypto = $currency;
                    $checked = true;
                }
                $work_cryptos[] = array('name' => $currency, 'fullname' => $fullCurrencyName[$currency], 'checked' => $checked);
            }            
        }

        $data['work_cryptos'] = $work_cryptos;
        $data['error_message'] = false;
        $data['refresh_url'] = $this->url->link('checkout/checkout');
        $data['order_id'] = $order_id;
        $data['url_redirect'] = $this->url->link('extension/payment/apirone/confirm');

        if($crypto != '') {
            $new_crypto = $this->abf_createCryptoAddress($order_id, $crypto);
        } else {
            $data['error_message'] = $this->language->get('no_currencies');;
        }

        if(isset($new_crypto['success'])) {
            $data['crypto'] = $new_crypto['success'];
            $data['cryptocode'] = strtoupper($new_crypto['crypto']);
        } else {
            $data['error_message'] = $new_crypto['error']['warning'];
        }
        $data['crypto_address'] = $new_crypto['address'];

        return $this->load->view('extension/payment/apirone', $data);
    }

    public function confirm(){

        $this->load->model('checkout/order');
        $this->load->language('extension/payment/apirone');
        $this->load->model('extension/payment/apirone');    

        if($this->config->get('payment_apirone_merchantname') != ''){
            $data['merchantname'] = $this->config->get('payment_apirone_merchantname');
        } else {
            $data['merchantname'] = $this->config->get('config_name');
        }

        $data['please_send'] = $this->language->get('please_send');
        $data['to_address'] = $this->language->get('to_address');
        $data['merchant'] = $this->language->get('merchant');
        $data['amount_to_pay'] = $this->language->get('amount_to_pay');
        $data['arrived_amount'] = $this->language->get('arrived_amount');
        $data['remains_to_pay'] = $this->language->get('remains_to_pay');
        $data['date'] = $this->language->get('date');
        $data['transactions'] = $this->language->get('transactions');
        $data['no_tx_yet'] = $this->language->get('no_tx_yet');
        $data['status'] = $this->language->get('status');
        $data['loading_data'] = $this->language->get('loading_data');
        $data['status'] = $this->language->get('status');
        $data['if_you_unable_complete'] = $this->language->get('if_you_unable_complete');
        $data['you_can_pay_partially'] = $this->language->get('you_can_pay_partially');
        $data['payment_complete'] = $this->language->get('payment_complete');
        $data['tx_in_network'] = $this->language->get('tx_in_network');
        $data['waiting_payment'] = $this->language->get('waiting_payment');
        $data['confirmations_count'] = $this->language->get('confirmations_count');
        $data['thank_you'] = $this->language->get('thank_you');
        $data['go_to_cart'] = $this->language->get('go_to_cart');
        $data['order_not_completed'] = $this->language->get('order_not_completed');
        $data['minutes'] = $this->language->get('minutes');
        $data['seconds_remains'] =  $this->language->get('seconds_remains');

        $fullCurrencyName['btc'] = 'bitcoin';
        $fullCurrencyName['ltc'] = 'litecoin';
        $fullCurrencyName['bch'] = 'bitcoincash';

        if (isset($this->request->get['order'])) {
        $safe_order = $this->request->get['order'];
            if ( strlen( $safe_order ) > 64 ) {
               $safe_order = substr( $safe_order, 0, 64 );
            }
        }

        if (isset($this->request->get['address'])) {
        $safe_address = $this->request->get['address'];
            if ( strlen( $safe_address ) > 64 ) {
               $safe_address = substr( $safe_address, 0, 64 );
            }
        }
        if ( !isset($safe_address) ) {
            $safe_address = '';
        }

        if (isset($this->request->get['currency'])) {
        $safe_crypto = $this->request->get['currency'];
            if ( strlen( $safe_crypto ) > 3 ) {
               $safe_crypto = substr( $safe_crypto, 0, 3 );
            }
        }
        if ( !isset($safe_crypto) ) {
            $safe_crypto = '';
        }

        $timeout = $this->config->get('payment_apirone_timeout');
        $test = $this->config->get('payment_apirone_test_mode');

        //get test mode status
        if ($test){
            /*$apirone_adr =$this->language->get('test_url');*/
            $apirone_adr = $this->language->get('live_url');
        }
        else{
            $apirone_adr = $this->language->get('live_url');
        }

        $order_id = $safe_order;
        $input_address = $safe_address;
        $crypto = $safe_crypto;
        $order = $this->model_checkout_order->getOrder($order_id);
        $response_crypto = $this->abf_convert_to_crypto($order['currency_code'], $order['total'], $order['currency_value'], $this->abf_getCryptoToCode($crypto));
        if ($this->abf_is_valid_for_use($order['currency_code']) && $response_crypto > 0) {
            $sales = $this->model_extension_payment_apirone->abf_getSales($order_id, $this->abf_getCryptoToCode($crypto), $input_address);
            $data['error_message'] = false;
            if ($sales == null) {
                $this->response->redirect($this->url->link('checkout/cart'));
                return;
            } else {
                $response_create['input_address'] = $sales['address'];
            }

            if($sales['crypto'] != $this->abf_getCryptoToCode($crypto)) {
                $this->response->redirect($this->url->link('checkout/cart'));
                return;
            }

            $now_time = $this->model_extension_payment_apirone->abf_getNowDate();
            $time = strtotime($sales['timer_on']);
            $conditions_complete = $sales['conditions_complete'];
            if($sales['finished'] == 1) {
                $this->response->redirect($this->url->link('checkout/success'));
                return;
            }

            if($time < 0 && $conditions_complete == 0) { //timer didn't set
                $conditions_complete = 1; //set condititon status
                $amount = $response_crypto;
                $this->model_extension_payment_apirone->abf_updateSale($sales['id'] , $conditions_complete, $response_crypto*1E8);
                $time = $this->model_extension_payment_apirone->abf_getNowDate();
            } else {
                $amount = round($sales['crypto_amount'] / 1E8, 8);
            }

            if(($timeout - ($now_time - $time)) <= 0) {
                $conditions_complete = 2;
                $this->model_extension_payment_apirone->abf_updateSale($sales['id'] , $conditions_complete); //to failded status
            }
            
            $message = urlencode($fullCurrencyName[$crypto].":" . $response_create['input_address'] . "?amount=" . $amount . "&label=BTCA");
            $data['response_btc'] = number_format($amount, 8, '.', '');
            $data['message'] = $message;
            $data['input_address'] = $response_create['input_address'];
            $data['order'] = $order_id;
            $data['current_date'] = date('Y-m-d');
            $data['key'] = $input_address;
            $data['order'] = $order_id;
            $data['crypto'] = strtoupper($crypto);
            $data['fullCurrencyName'] = $fullCurrencyName[$crypto];
            if($conditions_complete < 2){
                $data['deltatime'] = $timeout - ($now_time - $time);
            } else {
                $data['deltatime'] = -1;
                $notes = 'Order closed by timer';
                $voided_order_status = $this->config->get('payment_apirone_voided_status_id');
                if($order['order_status_id'] == 0)
                    $this->model_checkout_order->addOrderHistory($order_id, $voided_order_status, $notes, true);
            }

            $data['back_to_cart'] = $this->url->link('checkout/cart');

        } else {
            $this->response->redirect($this->url->link('checkout/cart'));
            return;
        } 
        if($input_address == $response_create['input_address']){
            $data['script'] = $this->language->get('script');
            $data['style'] =  $this->language->get('style');
            $this->response->setOutput($this->load->view('extension/payment/apirone_invoice', $data));
            return;
        } else {
            $this->response->redirect($this->url->link('checkout/cart'));
            return;
        }
    }

    public function send(){
        $this->load->language('extension/payment/apirone');

        if (isset($this->request->post['currency'])) {
            $safe_crypto = $this->request->post['currency'];
            if ( strlen( $safe_crypto ) > 3 ) {
               $safe_crypto = substr( $safe_crypto, 0, 3 );
            }
        }
        if ( !isset($safe_crypto) ) {
            $safe_crypto = '';
        }

        $crypto = $safe_crypto;

        $test = $this->config->get('payment_apirone_test_mode');

        $order_id = $this->session->data['order_id'];

        $new_crypto = $this->abf_createCryptoAddress($order_id, $crypto);

/*        $data['crypto'] = $new_crypto['success'];*/
            
        echo json_encode($new_crypto);

        return 0;
    }


    private function abf_createCryptoAddress($order_id, $crypto){

        $this->load->model('checkout/order');
        $this->load->language('extension/payment/apirone');
        $this->load->model('extension/payment/apirone');

        $order = $this->model_checkout_order->getOrder($order_id);

        $response_crypto = $this->abf_convert_to_crypto($order['currency_code'], $order['total'], $order['currency_value'], $this->abf_getCryptoToCode($crypto));

        if ($this->abf_is_valid_for_use($order['currency_code']) && $this->abf_is_valid_crypto_for_use($crypto) && $response_crypto > 0) {

            $sales = $this->model_extension_payment_apirone->abf_getSales($order_id, $this->abf_getCryptoToCode($crypto));
                $error_message = false;
                if ($sales == null) {
                    $order_id = $this->session->data['order_id'];
                    $secret = $this->abf_getKey($order_id);

                    $json_data = array (
                        'type' => "forwarding",
                        'currency' => $crypto,
                        'callback' => array(
                        'url' => $this->url->link('extension/payment/apirone/callback'),
                        'data' => array(
                            'secret' => $secret,
                            'order_id' => $order_id
                            )
                        ),
                        'destinations' => array (        
                            array('address'=> $this->abf_getCryptoAddress($crypto))
                        )
                    );
                    $api_endpoint = "https://apirone.com/api/v2/wallet";   
                    $obj_curl = ApironeCurl::get_instance($this->registry);
                    $response_create = $obj_curl->do_request($api_endpoint, $json_data);
                    if ($response_create['address'] != null){
                        $this->model_extension_payment_apirone->abf_addSale($order_id, $response_create['address'], $this->abf_getCryptoToCode($crypto));
                    } else{
                        $error_message =  $this->language->get('no_input_address');
                    }
                } else {
                    $response_create['address'] = $sales['address'];
                }             

                //$this->abf_logger("Secret: {$secret} Order: {$order_id}, Response: {$response_crypto}");
            } else {
                $error_message = $this->language->get('not_exchange') . " " . $order['currency_code'] . " ". $this->language->get('to') . " " . strtoupper($crypto) ." :(";
            }

            $success = number_format($response_crypto, 8, '.', '');

            if(!$error_message) {
                $output = array('success' => $success, 'address' => $response_create['address'], 'crypto' => $crypto);
            } else {
                $output = array('error' => array('warning' => $error_message, $crypto => 1) );
            }
            return $output;
    }

    private function abf_getCryptoAddress($crypto){
        switch ($crypto) {
            case 'btc':
                $merchant_address = $this->config->get('payment_apirone_merchant');
                break;
            case 'ltc':
                $merchant_address = $this->config->get('payment_apirone_ltc');
                break;
            case 'bch':
                $merchant_address = $this->config->get('payment_apirone_bch');
                break;
            default:
               $merchant_address = '';
        }
        return $merchant_address;
    }

    private function abf_getCryptoToCode($crypto){
        $crypto = strtolower($crypto);
         switch ($crypto) {
            case 'btc':
                $crypto_code = 0;
                break;
            case 'ltc':
                $crypto_code = 1;
                break;
            case 'bch':
                $crypto_code = 2;
                break;
            default:
               $crypto_code = -1;
        }
        return $crypto_code;       
    }

    private function abf_getCodeToCrypto($crypto){
        $crypto = strtolower($crypto);
         switch ($crypto) {
            case '0':
                $crypto_code = 'btc';
                break;
            case '1':
                $crypto_code = 'ltc';
                break;
            case '2':
                $crypto_code = 'bch';
                break;
            default:
               $crypto_code = '';
        }
        return $crypto_code;       
    }

    private function abf_convert_to_crypto($currency, $value, $currency_value, $crypto){
           if ($currency == strtoupper($this->abf_getCryptoToCode($crypto))) {
                return $value * $currency_value;
            } else { 
                $rate = $this->abf_getTickerAmount($currency, $crypto);
                if($rate > 0) {
                    return round(($value * $currency_value) / $rate, 8);
                } else {
                    return 0;
                }   
            }           
    }

    private function abf_is_valid_crypto_for_use($crypto = NULL) {
        if (!in_array($crypto, array('btc', 'bch', 'ltc'))) {
            return false;
        }
        if ($this->abf_getCryptoAddress($crypto) == '') {
            return false;
        }
        return true;
    }       

        private function abf_is_valid_for_use($currency = NULL) {
            if($currency != NULL){
                $check_currency = $currency;
            } else {
                $check_currency = $_SESSION['currency'];
            }
            
            if (!in_array($check_currency, array(
                'AFN', 'ALL', 'AMD', 'ANG', 'AOA', 'ARS', 'AUD', 'AWG', 'AZN', 'BAM', 'BBD', 'BCH', 'BDT', 'BGN', 'BHD', 'BIF', 'BMD', 'BND', 'BOB', 'BRL', 'BSD', 'BTC', 'BTN', 'BWP', 'BYN', 'BYR', 'BZD', 'CAD', 'CDF', 'CHF', 'CLF', 'CLP', 'CNH', 'CNY', 'COP', 'CRC', 'CUC', 'CVE', 'CZK', 'DJF', 'DKK', 'DOP', 'DZD', 'EEK', 'EGP', 'ERN', 'ETB', 'ETH', 'EUR', 'FJD', 'FKP', 'GBP', 'GEL', 'GGP', 'GHS', 'GIP', 'GMD', 'GNF', 'GTQ', 'GYD', 'HKD', 'HNL', 'HRK', 'HTG', 'HUF', 'IDR', 'ILS', 'IMP', 'INR', 'IQD', 'ISK', 'JEP', 'JMD', 'JOD', 'JPY', 'KES', 'KGS', 'KHR', 'KMF', 'KRW', 'KWD', 'KYD', 'KZT', 'LAK', 'LBP', 'LKR', 'LRD', 'LSL', 'LTC', 'LTL', 'LVL', 'LYD', 'MAD', 'MDL', 'MGA', 'MKD', 'MMK', 'MNT', 'MOP', 'MRO', 'MTL', 'MUR', 'MVR', 'MWK', 'MXN', 'MYR', 'MZN', 'NAD', 'NGN', 'NIO', 'NOK', 'NPR', 'NZD', 'OMR', 'PAB', 'PEN', 'PGK', 'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RSD', 'RUB', 'RWF', 'SAR', 'SBD', 'SCR', 'SEK', 'SGD', 'SHP', 'SLL', 'SOS', 'SRD', 'SSP', 'STD', 'SVC', 'SZL', 'THB', 'TJS', 'TMT', 'TND', 'TOP', 'TRY', 'TTD', 'TWD', 'TZS', 'UAH', 'UGX', 'USD', 'UYU', 'UZS', 'VEF', 'VND', 'VUV', 'WST', 'XAF', 'XAG', 'XAU', 'XCD', 'XDR', 'XOF', 'XPD', 'XPF', 'XPT', 'YER', 'ZAR', 'ZMK', 'ZMW', 'ZWL'
            ))) {
                return false;
            }
            return true;
        }

        //checks that order has sale
        private function abf_sale_exists($order_id, $input_address, $currency)
        {   $this->load->model('extension/payment/apirone');
            $sales = $this->model_extension_payment_apirone->abf_getSales($order_id, $currency, $input_address);
            if ($sales['address'] == $input_address) {return true;} else {return false;};
        }

        // function that checks what user complete full payment for order
        private function abf_check_remains($order_id, $currency)
        {
            $this->load->model('checkout/order');
            $this->load->model('extension/payment/apirone');
            $order = $this->model_checkout_order->getOrder($order_id);
            //$total = $this->abf_convert_to_crypto($order['currency_code'], $order['total'], $order['currency_value']);

            $sales = $this->model_extension_payment_apirone->abf_getSales($order_id, $currency);
            $total = round($sales['crypto_amount'] / 1E8, 8);

            $transactions = $sales['transactions'];
            $remains = 0;
            $total_paid = 0;
            $total_empty = 0;
            if($transactions != '')
                foreach ($transactions as $transaction) {
                    if ($transaction['thash'] == "empty") $total_empty+=$transaction['paid'];
                    $total_paid+=$transaction['paid'];
                }
            $total_paid/=1E8;
            $total_empty/=1E8;
            $remains = $total - $total_paid;
            $remains_wo_empty = $remains + $total_empty;
            if ($remains_wo_empty > 0) {
                return false;
            } else {
                return true;
            };
        }

        private function abf_logger($message)
        {
            if ($this->config->get('payment_apirone_test_mode')) {
                $this->log->write($message);
            }
        }

        private function abf_remains_to_pay($order_id, $crypto)
        {   
            $this->load->model('checkout/order');
            $this->load->model('extension/payment/apirone');
            $order = $this->model_checkout_order->getOrder($order_id);

            $sales = $this->model_extension_payment_apirone->abf_getSales($order_id, $crypto);
            $transactions = $sales['transactions'];

            $total_paid = 0;
            if($transactions != '')
            foreach ($transactions as $transaction) {
                $total_paid+=$transaction['paid'];
            }
            $response_btc = round($sales['crypto_amount'] / 1E8, 8);
            $remains = $response_btc - $total_paid/1E8;
            if($remains < 0) $remains = 0;  
            return $remains;
        }

private function abf_getKey($order_id){
    $key = $this->config->get('payment_apirone_secret');
    return md5($key . $order_id);
}
private function abf_check_data($apirone_order){
    $abf_check_code = 201; //All data is ready
    if (empty($apirone_order['value'])) {
        $abf_check_code = 100; //No value
    }            
    if (empty($apirone_order['input_address'])) {
        $abf_check_code = 101; //No input address
    }
    if (empty($apirone_order['orderId'])) {
         $abf_check_code = 102; //No order_id
    }
    if (empty($apirone_order['secret'])) {
            $abf_check_code = 103; //No secret
    }
    if ($apirone_order['confirmations']<=0) {
        $abf_check_code = 104; //No confirmations
    }                    
    if (empty($apirone_order['input_transaction_hash'])) {
        $abf_check_code = 106; //No input transaction hash
    }       
    if ($apirone_order['currency'] < 0) {
        $abf_check_code = 107; //no currency
    }
    if (empty($apirone_order['transaction_hash'])){
        $abf_check_code = 200; //No transaction hash
    }  
    return $abf_check_code;
}
private function abf_transaction_exists($thash, $order_id, $crypto){
    $this->load->model('extension/payment/apirone');
    $transactions = $this->model_extension_payment_apirone->abf_getTransactions($order_id, $crypto);
    $flag = false;
    if($transactions != '')
        foreach ($transactions as $transaction) {
        if($thash == $transaction['thash']){
            $flag = true; // same transaction was in DB
            break;
        }
    }
    return $flag;
}
private function abf_input_transaction_exists($input_thash, $order_id, $crypto){
    $this->load->model('extension/payment/apirone');
    $transactions = $this->model_extension_payment_apirone->abf_getTransactions($order_id, $crypto);
    $flag = false;
    if($transactions != '')
        foreach ($transactions as $transaction) {
        if($input_thash == $transaction['input_thash']){
            $flag = true; // same transaction was in DB
            break;
        }
    }
    return $flag;
}
private function secret_is_valid($secret, $order_id){
    $flag = false;
    if($secret == $this->abf_getKey($order_id)){
        $flag = true;
    }
    return $flag;
}
private function confirmations_is_ok($confirmations){
    define("ABF_COUNT_CONFIRMATIONS", $this->config->get('payment_apirone_confirmation'));
    $flag = false;
    if($confirmations >= ABF_COUNT_CONFIRMATIONS) {
        $flag = true;
    }
    return $flag;
}

private function abf_validate_data($apirone_order){
    $abf_check_code = 300; //No sale exists
    if ($this->abf_sale_exists($apirone_order['orderId'], $apirone_order['input_address'], $apirone_order['currency'])) {
        $abf_check_code = 302; //secret is invalid
            if ($this->secret_is_valid($apirone_order['secret'], $apirone_order['orderId'])) {
                $abf_check_code = 400; //validate complete
            }
    }
    return $abf_check_code;
}

private function abf_empty_transaction_hash($apirone_order){
    $this->load->model('extension/payment/apirone');
    if ($this->abf_input_transaction_exists($apirone_order['input_transaction_hash'],$apirone_order['orderId'],$apirone_order['currency'])) {
        $this->model_extension_payment_apirone->abf_updateTransaction(
            $apirone_order['input_transaction_hash'],
            $apirone_order['value'],
            $apirone_order['confirmations']
        );
        $abf_check_code = 500; //update existing transaction
    } else {
        $this->model_extension_payment_apirone->abf_addTransaction(
            $apirone_order['orderId'],
            'empty',
            $apirone_order['input_transaction_hash'],
            $apirone_order['value'],
            $apirone_order['confirmations'],
            $apirone_order['currency']
        );
        $abf_check_code = 501; //insert new transaction in DB without transaction hash
    }
    return $abf_check_code;
}
private function abf_calculate_payamount($apirone_order){
    $this->load->model('extension/payment/apirone');
    $transactions = $this->model_extension_payment_apirone->abf_getTransactions($apirone_order['orderId'], $apirone_order['currency']);
    $payamount = 0;
    if($transactions != '')
    foreach ($transactions as $transaction) {
        if($transaction['thash'] != 'empty')
            $payamount += $transaction['paid'];
    }
    return $payamount;
}
private function abf_skip_transaction($apirone_order){
    define("ABF_COUNT_CONFIRMATIONS", $this->config->get('payment_apirone_confirmation')); // number of confirmations
    define("ABF_MAX_CONFIRMATIONS", 150); // max confirmations count
    $abf_check_code = NULL;
    if(($apirone_order['confirmations'] >= ABF_MAX_CONFIRMATIONS) && (ABF_MAX_CONFIRMATIONS != 0)) {// if callback's confirmations count more than ABF_MAX_CONFIRMATIONS we answer *ok*
        $abf_check_code="*ok*";
        $this->abf_logger('[Info] Skipped transaction: ' .  $apirone_order['transaction_hash'] . ' with confirmations: ' . $apirone_order['confirmations']);
        };
        return $abf_check_code;
}
private function abf_take_notes($apirone_order){
    $this->load->model('checkout/order');
    $this->load->model('extension/payment/apirone');
    $sale = $this->model_extension_payment_apirone->abf_getSales($apirone_order['orderId'], $apirone_order['currency']);
    $order = $this->model_checkout_order->getOrder($apirone_order['orderId']);
    $response_btc = round($sale['crypto_amount'] / 1E8, 8);
    $payamount = $this->abf_calculate_payamount($apirone_order);
    $notes  = 'Input Address: ' . $apirone_order['input_address'] . '; Transaction Hash: ' . $apirone_order['transaction_hash'] . '; Payment: ' . number_format($apirone_order['value']/1E8, 8, '.', '') . strtoupper($this->abf_getCodeToCrypto($apirone_order['currency']));
    $notes .= '. Total paid: '.number_format(($payamount)/1E8, 8, '.', '').' '. strtoupper($this->abf_getCodeToCrypto($apirone_order['currency'])) . '; ';
    if (($payamount)/1E8 < $response_btc)
        $notes .= 'User transferred not enough money in your shop currency. Waiting for next payment; ';
    if (($payamount)/1E8 > $response_btc)
        $notes .= 'User transferred more money than You need in your shop currency; ';
    $notes .= 'Order total: '.$response_btc . ' '. strtoupper($this->abf_getCodeToCrypto($apirone_order['currency'])) . '; ';
    if ($this->abf_check_remains($apirone_order['orderId'], $apirone_order['currency'])){ //checking that payment is complete, if not enough money on payment it's not completed 
        $notes .= 'Successfully paid.';
    }
    return $notes;
}

private function abf_TickerAmountQuery($code, $crypto){
        $crypto_code = $this->abf_getCodeToCrypto($crypto);
        $obj_curl = ApironeCurl::get_instance($this->registry);
        if ($code == 'USD' || $code == 'EUR' || $code == 'GBP' || $code == 'RUB') {
            $apirone_tobtc = 'https://apirone.com/api/v1/ticker?currency='.strtoupper($crypto_code);
            $response = $obj_curl->do_request($apirone_tobtc); 
            $response = json_decode($response, true);
            $response = (float)$response[$code]['last'];
        } else {
            if($this->abf_is_valid_for_use($code)){
                if($crypto_code == 'bch' || $crypto_code == 'btc') {
                    $response = $obj_curl->do_request('https://bitpay.com/api/rates/'. strtoupper($crypto_code).'/'. $code);
                    $response = json_decode($response, true);
                    $response = (float)$response['rate'];                       
                }
                if($crypto_code == 'ltc') {
                    $response = $obj_curl->do_request('https://api.coingecko.com/api/v3/simple/price?ids=litecoin&vs_currencies='. strtolower($code));
                    $response = json_decode($response, true);
                    $response = (float)$response['litecoin'][strtolower($code)];   
                }      
            } else {
                return 0;
            }
        }  
            if(is_null($response))
                return 0;
        return $response;
}

private function abf_getTickerAmount($code, $crypto){
    $this->load->model('extension/payment/apirone');
    $rate = $this->model_extension_payment_apirone->abf_getRate($code, $crypto);
    if(!$rate) {
        $response = $this->abf_TickerAmountQuery($code, $crypto);
        $this->model_extension_payment_apirone->abf_setRate($code, $response, $crypto);
    } else {
        if($rate['amount'] == 0) {
            $this->model_extension_payment_apirone->abf_clearRates();
            $response = $this->abf_TickerAmountQuery($code, $crypto);
            if($response > 0) {
                $this->model_extension_payment_apirone->abf_setRate($code, $response, $crypto);
            } else {
                return 0;
            }
        }
        $time = strtotime($rate['time']);
        $now_time = $this->model_extension_payment_apirone->abf_getNowDate();
        if($now_time - $time <= 3600){ 
           $response = $rate['amount'];
        } else {
            $response = $this->abf_TickerAmountQuery($code, $crypto);
            $this->model_extension_payment_apirone->abf_setRate($code, $response, $crypto);
            if($rate['amount'] <= 0 || $response <= 0)
                return 0;
            if( $response / $rate['amount'] < 0.6 )
            return 0;
        }        
    }
        return $response;
}

private function abf_filled_transaction_hash($apirone_order){
    $this->load->model('extension/payment/apirone');
    $this->load->model('checkout/order');
    $order = $this->model_checkout_order->getOrder($apirone_order['orderId']);
        if($this->abf_transaction_exists($apirone_order['transaction_hash'],$apirone_order['orderId'],$apirone_order['currency'])){
            //$abf_check_code = 600; update transaction
            $abf_check_code = '*ok*';// answer ok because we have tx in DB
            $this->model_extension_payment_apirone->abf_updateTransaction(
                $apirone_order['input_transaction_hash'],
                $apirone_order['value'],
                $apirone_order['confirmations'],
                $apirone_order['transaction_hash'],
                $apirone_order['orderId']
            ); 
        } else {
            $abf_check_code = 601; //small confirmations count for update tx
            if ($this->confirmations_is_ok($apirone_order['confirmations'])) {
            $this->model_extension_payment_apirone->abf_addTransaction(
                $apirone_order['orderId'],
                $apirone_order['transaction_hash'],
                $apirone_order['input_transaction_hash'],
                $apirone_order['value'],
                $apirone_order['confirmations'],
                $apirone_order['currency']
            );
            $notes = $this->abf_take_notes($apirone_order);
            $abf_check_code = '*ok*';//insert new TX with transaction_hash
            if ($this->abf_check_remains($apirone_order['orderId'], $apirone_order['currency'])){ //checking that payment is complete, if not enough money on payment is not completed
                $this->model_extension_payment_apirone->abf_finishSale($apirone_order['orderId'], $apirone_order['currency']);  
                $complete_order_status = $this->config->get('payment_apirone_order_status_id');
                $this->model_checkout_order->addOrderHistory($apirone_order['orderId'], $complete_order_status, $notes, true);
            } else {
                $partiallypaid_order_status = $this->config->get('payment_apirone_pending_status_id');
                $this->model_checkout_order->addOrderHistory($apirone_order['orderId'], $partiallypaid_order_status, $notes, true);
            }
        } else { //small confirmations count
            $this->model_extension_payment_apirone->abf_updateTransaction(
            $apirone_order['input_transaction_hash'],
            $apirone_order['value'],
            $apirone_order['confirmations']
            );
            if($order['order_status_id'] == 0) {
            $payamount = $this->abf_calculate_payamount($apirone_order);
            $sale = $this->model_extension_payment_apirone->abf_getSales($apirone_order['orderId'], $apirone_order['currency']);
                if($payamount + $apirone_order['value'] >= $sale['crypto_amount']) {
                    $notes = 'Total amount of the order was paid. Waiting for ' . $this->config->get('payment_apirone_confirmation') . ' confirmation(s).';
                    $partiallypaid_order_status = $this->config->get('payment_apirone_pending_status_id');
                    $this->model_checkout_order->addOrderHistory($apirone_order['orderId'], $partiallypaid_order_status, $notes, true);                    
                }
            }
        }
    }
    return $abf_check_code;
}

    public function abf_order_with_currency($order_id, $input_address){
        $this->load->model('extension/payment/apirone');
        $currency = $this->model_extension_payment_apirone->abf_getCurrency($order_id, $input_address); 
        return $currency;
    }

    public function callback(){

    $data = file_get_contents('php://input');

    if ($data) {
        $params = json_decode($data, true);
    }

    $this->abf_logger('[Info] Callback' . $_SERVER['REQUEST_URI']);
    if (isset($params['data']['secret'])) {
        $safe_secret = $params['data']['secret'];
    } else {
        $safe_secret = '';
    }
    if (isset($params['data']['order_id'])) {
        $safe_order_id = intval( $params['data']['order_id'] );
    } else {
        $safe_order_id = '';
    }
    if (isset($params['confirmations'])) {
        $safe_confirmations = intval( $params['confirmations'] );
    } else {
        $safe_confirmations = '';
    }
    if (isset($params['value'])) {
        $safe_value = intval( $params['value'] );
    } else {
        $safe_value = '';
    }
    if (isset($params['input_address'])) {
        $safe_input_address = $params['input_address'];
    } else {
        $safe_input_address = '';
    }
    if (isset($params['transaction_hash'])) {
        $safe_transaction_hash = $params['transaction_hash'];
    } else {
        $safe_transaction_hash = '';
    }
    if (isset($params['input_transaction_hash'])) {
        $safe_input_transaction_hash = $params['input_transaction_hash'];
    } else {
        $safe_input_transaction_hash = '';
    }

    if ( strlen( $safe_secret ) > 32 ) {
        $safe_secret = substr( $safe_secret, 0, 32 );
    }
    if ( $safe_order_id == 'undefined' ) {
         $safe_order_id = '';
    }
    if ( strlen( $safe_order_id ) > 25 ) {
        $safe_order_id = substr( $safe_order_id, 0, 25 );
    }
    if ( strlen( $safe_confirmations ) > 5 ) {
        $safe_confirmations = substr( $safe_confirmations, 0, 5 );
    }
    if ( ! $safe_confirmations ) {
        $safe_confirmations = 0;
    }
    if ( strlen( $safe_value ) > 16 ) {
        $safe_value = substr( $safe_value, 0, 16 );
    }
    if ( ! $safe_value ) {
        $safe_value = '';
    }
    if ( strlen( $safe_input_address ) > 64 ) {
        $safe_input_address = substr( $safe_input_address, 0, 64 );
    }
    if ( ! $safe_input_address ) {
        $safe_input_address = '';
    }
    if ( strlen( $safe_transaction_hash ) > 65 ) {
        $safe_transaction_hash = substr( $safe_transaction_hash, 0, 65 );
    }
    if ( ! $safe_transaction_hash ) {
        $safe_transaction_hash = '';
    }
    if ( strlen( $safe_input_transaction_hash ) > 65 ) {
        $safe_input_transaction_hash = substr( $safe_input_transaction_hash, 0, 65 );
    }
    if ( ! $safe_input_transaction_hash ) {
        $safe_input_transaction_hash = '';
    }
    $apirone_order = array(
        'value' => $safe_value,
        'input_address' => $safe_input_address,
        'orderId' => $safe_order_id, // order id
        'secret' => $safe_secret,
        'confirmations' => $safe_confirmations,
        'input_transaction_hash' => $safe_input_transaction_hash,
        'transaction_hash' => $safe_transaction_hash,
        'currency' => $this->abf_order_with_currency($safe_order_id, $safe_input_address)
    );

    $check_data_score = $this->abf_check_data($apirone_order);
    $abf_api_output = $check_data_score;
    if( $check_data_score >= 200 ){
        $validate_score = $this->abf_validate_data($apirone_order);
        $abf_api_output = $validate_score;
        if ($validate_score == 400) {
            if($check_data_score == 200){
                $data_action_code = $this->abf_empty_transaction_hash($apirone_order);
            }
            if($check_data_score == 201){
                $data_action_code = $this->abf_filled_transaction_hash($apirone_order);
            }
            $abf_api_output = $data_action_code;
        }
    }
    if($this->config->get('payment_apirone_test_mode')) {
        print_r($abf_api_output);//global output
    } else {
        if($abf_api_output === '*ok*') {
            echo '*ok*';   
        } else{
            echo $this->abf_skip_transaction($apirone_order);
        }
    }
    exit;
    }


   public function check_payment(){
        $this->load->model('checkout/order');
        $this->load->model('extension/payment/apirone');

        define("ABF_COUNT_CONFIRMATIONS", $this->config->get('payment_apirone_confirmation')); // number of confirmations

        $safe_order = intval( $this->request->get['order'] );

        if ( $safe_order == 'undefined') {
             $safe_order = '';
        }

        if ( strlen( $safe_order ) > 25 ) {
            $safe_order = substr( $safe_order, 0, 25 );
        }

        $safe_key = $this->request->get['key'];
        if ( strlen( $safe_key ) > 64 ) {
           $safe_key = substr( $safe_key, 0, 64 );
        }
        if ( !isset($safe_key) ) {
            $safe_key = '';
        }

        if (isset($this->request->get['currency'])) {
        $safe_crypto = $this->request->get['currency'];
            if ( strlen( $safe_crypto ) > 3 ) {
               $safe_crypto = substr( $safe_crypto, 0, 3 );
            }
        }
        if ( !isset($safe_crypto) ) {
            $safe_crypto = '';
        }

        $crypto = $safe_crypto;
        $input_address = $safe_key;


        if (!empty($safe_order) && !empty($safe_key)) {
            $order = $this->model_checkout_order->getOrder($safe_order);
            /*print_r( $order );*/
            if (!empty($safe_order)) {
                $sales = $this->model_extension_payment_apirone->abf_getSales($safe_order, $this->abf_getCryptoToCode($crypto));
                $transactions = $sales['transactions'];
            }
            $empty = 0;
            $value = 0;
            $paid_value = 0;

            $payamount = 0;
            $innetwotk_pay = 0;
            $last_transaction = '';
            $confirmed = '';
            $status = 'waiting';
            //print_r($sales);
            if($transactions != '') {
                $alltransactions = array();
            foreach ($transactions as $transaction) {
                if($transaction['thash'] == 'empty') {
                            $status = 'innetwork';
                            $innetwotk_pay += $transaction['paid'];
                }
                if($transaction['thash'] != 'empty') 
                    $payamount += $transaction['paid'];      
               //print_r($transaction);

                if ($transaction['thash'] == "empty"){
                    $empty = 1; // has empty value in thash
                    $value = $transaction['paid'];
                } else{
                    $paid_value = $transaction['paid'];
                    $confirmed = $transaction['thash'];
                }
                $alltransactions[] = array('thash' => $transaction['thash'], 'input_thash' => $transaction['input_thash'], 'confirmations' => $transaction['confirmations']);             
            }
            } else {
                $alltransactions = "";
            }
            if ($order == '') {
                echo '';
                exit;
            }
            $response_btc = round($sales['crypto_amount'] / 1E8, 8);
            if (isset($sales['finished']) && $sales['finished'] == 1) {
                $status = 'complete';
            }

            $partiallypaid_order_status = $this->config->get('payment_apirone_pending_status_id');
            if($order['order_status_id'] == $partiallypaid_order_status) {
                if($innetwotk_pay + $payamount >= $response_btc) {
                    $status = 'complete';
                }
            }

                $remains_to_pay = number_format($this->abf_remains_to_pay($safe_order, $this->abf_getCryptoToCode($crypto)), 8, '.', '');
                $last_transaction = $confirmed;
                $payamount = number_format($payamount/1E8, 8, '.', '');
                $innetwotk_pay = number_format($innetwotk_pay/1E8, 8, '.', '');
                $response_btc = number_format($response_btc, 8, '.', '');

            if($sales['address'] == $safe_key){
            $ouput = array('total_crypto' => $response_btc, 'innetwork_amount' => $innetwotk_pay, 'arrived_amount' => $payamount, 'remains_to_pay' => $remains_to_pay, 'transactions' => $alltransactions, 'status' => $status, 'count_confirmations' => ABF_COUNT_CONFIRMATIONS);
            echo json_encode($ouput);
            } else {
                echo '';
            }

            exit;
        }  
    }
}