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
 * @param  array  $params  Key-value pair
 */
  public function do_request($url, $params=array()) {
 // log the request
 $this->logger->write("Initiated CURL request for: {$url}");
 
 // init curl object
 $ch = curl_init();
 curl_setopt($ch, CURLOPT_URL, $url);
 curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
 
 // prepare post array if available
 $params_string = '';
 if (is_array($params) && count($params)) {
 foreach($params as $key=>$value) {
 $params_string .= $key.'='.$value.'&';
 }
 rtrim($params_string, '&');
 
 curl_setopt($ch, CURLOPT_POST, count($params));
 curl_setopt($ch, CURLOPT_POSTFIELDS, $params_string);
 }
 
 // execute request
 $result = curl_exec($ch);
 
 // close connection
 curl_close($ch);
 
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
        $order = $this->model_checkout_order->getOrder($order_id);
            
        $test = $this->config->get('payment_apirone_test_mode');
        //get test mode status
        if ($test){
            /*$apirone_adr =$this->language->get('test_url');*/
            $apirone_adr = $this->language->get('live_url');
        }
        else{
            $apirone_adr = $this->language->get('live_url');
        }
            $response_btc = $this->abf_convert_to_btc($order['currency_code'], $order['total'], $order['currency_value']);

            if ($this->abf_is_valid_for_use($order['currency_code']) && $response_btc > 0) {
                /**
                 * Args for Forward query
                 */           
    
                $sales = $this->model_extension_payment_apirone->getSales($order_id);
                $data['error_message'] = false;
                
                if ($sales == null) {

                    $secret = md5($this->config->get('payment_apirone_secret'));
                    $refnum = $this->session->data['order_id'];

                    $args = array(
                        'address' => $this->config->get('payment_apirone_merchant'),
                        'callback' => urlencode(HTTP_SERVER . 'index.php?route=extension/payment/apirone/callback&secret='. $secret .'&refnum='.$refnum)
                    );
                    $apirone_create = $apirone_adr . '?method=create&address=' . $args['address'] . '&callback=' . $args['callback'];

                    $obj_curl = ApironeCurl::get_instance($this->registry);                
                    $response_create = $obj_curl->do_request( $apirone_create );
                    $response_create = json_decode($response_create, true);
                    if ($response_create['input_address'] != null){
                        $this->model_extension_payment_apirone->addSale($order_id, $response_create['input_address']);
                    } else{
                        $data['error_message'] =  "No Input Address from Apirone :(";
                    }
                } else {
                    $response_create['input_address'] = $sales[0]->address;
                }
                if ($response_create['input_address'] != null){

                $message = urlencode("bitcoin:" . $response_create['input_address'] . "?amount=" . $response_btc . "&label=Apirone");
                $data['response_btc'] = $response_btc;
                $data['message'] = $message;
                $data['input_address'] = $response_create['input_address'];
                $data['order'] = $order_id;
                }               

                if ($test && !is_null($response_create)) {
                    $this->log->write("Request: {$apirone_create} , Response: {$response_btc}");
                }
            } else {
                $data['error_message'] = "Apirone couldn't exchange " . $order['currency_code'] . " to BTC :(";
            }

        $data['button_confirm'] = $this->language->get('button_confirm');

        $this->load->model('checkout/order');

        return $this->load->view('extension/payment/apirone', $data);
    }

    private function abf_convert_to_btc($currency, $value, $currency_value){
           if ($currency == 'BTC') {
                return $value * $currency_value;
            } else { 
                if ($currency == 'USD' || $currency == 'EUR' || $currency == 'GBP') {
                    //$apirone_tobtc = $apirone_adr . 'tobtc?currency='.$args['currency'].'&value='.$args['value'];
                    $apirone_tobtc = 'https://apirone.com/api/v1/tobtc?currency='.$currency.'&value='.$value;
                    $obj_curl = ApironeCurl::get_instance($this->registry);
                    $response_btc = $obj_curl->do_request($apirone_tobtc);
                    $response_btc = json_decode($response_btc, true);
                    return round($response_btc * $currency_value, 8);
                } else {
                if($this->abf_is_valid_for_use($currency)){
                    $obj_curl = ApironeCurl::get_instance($this->registry);
                    $response_coinbase = $obj_curl->do_request('https://api.coinbase.com/v2/prices/BTC-'. $currency .'/buy');
                    $response_coinbase = json_decode($response_coinbase, true);
                    $response_coinbase = $response_coinbase['data']['amount'];
                    if (is_numeric($response_coinbase)) {
                       return round(($value  * $currency_value) / $response_coinbase, 8);
                    } else {
                        return 0;
                    }                   
                } else {
                    return 0;
                }
                }     
            }           
    }


        private function abf_is_valid_for_use($currency = NULL)
        {
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
        private function abf_sale_exists($order_id, $input_address)
        {   $this->load->model('extension/payment/apirone');
            $sales = $this->model_extension_payment_apirone->getSales($order_id, $input_address);
            if ($sales['address'] == $input_address) {return true;} else {return false;};
        }

        // function that checks what user complete full payment for order
        private function abf_check_remains($order_id)
        {
            $this->load->model('checkout/order');
            $this->load->model('extension/payment/apirone');
            $order = $this->model_checkout_order->getOrder($order_id);
            $total = $this->abf_convert_to_btc($order['currency_code'], $order['total'], $order['currency_value']);

            $sales = $this->model_extension_payment_apirone->getSales($order_id);
            $transactions = $sales['transactions'];
            $remains = 0;
            $total_paid = 0;
            $total_empty = 0;
            if($transactions != '')
                foreach ($transactions as $transaction) {
                    if ($transaction['thash'] == "empty") $total_empty+=$transaction['paid'];
                    $total_paid+=$transaction['paid'];
                }
            $total_paid/=100000000;
            $total_empty/=100000000;
            $remains = $total - $total_paid;
            $remains_wo_empty = $remains + $total_empty;
            if ($remains_wo_empty > 0) {
                return false;
            } else {
                return true;
            };
        }

        private function abf_remains_to_pay($order_id)
        {   
            $this->load->model('checkout/order');
            $this->load->model('extension/payment/apirone');
            $order = $this->model_checkout_order->getOrder($order_id);

            $sales = $this->model_extension_payment_apirone->getSales($order_id);
            $transactions = $sales['transactions'];

            $total_paid = 0;
            if($transactions != '')
            foreach ($transactions as $transaction) {
                $total_paid+=$transaction['paid'];
            }
            $response_btc = $this->abf_convert_to_btc($order['currency_code'], $order['total'], $order['currency_value']);
            $remains = $response_btc - $total_paid/100000000;
            if($remains < 0) $remains = 0;  
            return $remains;
        }

    /**
     * Check response
     */
    public function callback()
    {   define("ABF_COUNT_CONFIRMATIONS", $this->config->get('payment_apirone_confirmation')); // number of confirmations
        define("ABF_MAX_CONFIRMATIONS", 150); // max confirmations count

        $this->load->model('checkout/order');
        $this->load->model('extension/payment/apirone');

        $test = $this->config->get('payment_apirone_test_mode');

        $abf_api_output = 0; //Nothing to do (empty callback, wrong order Id or Input Address)
        if ($test) {
            $this->log->write("Callback: {$_SERVER['REQUEST_URI']}");
        }
        if (isset($this->request->get['secret'])) {
            $safe_key = $this->request->get['secret'];
        }

        if ( ! $safe_key ) {
            $safe_key = '';
        }

        if ( strlen( $safe_key ) > 32 ) {
            $safe_key = substr( $safe_key, 0, 32 );
        }

        if (isset($this->request->get['refnum'])) {
            $safe_order_id = intval( $this->request->get['refnum'] );
        } else {
            $safe_order_id = '';
        }
        

        if ( $safe_order_id == 'undefined') {
             $safe_order_id = '';
        }

        if ( strlen( $safe_order_id ) > 25 ) {
            $safe_order_id = substr( $safe_order_id, 0, 25 );
        }
        if (isset($this->request->get['confirmations'])) {
            $safe_confirmations = intval( $this->request->get['confirmations'] );

            if ( strlen( $safe_confirmations ) > 5 ) {
             $safe_confirmations = substr( $safe_confirmations, 0, 5 );
            }
        }

        if ( !isset($safe_confirmations) ) {
            $safe_confirmations = 0;
        }

        if (isset($this->request->get['value'])) {
            $safe_value = intval( $this->request->get['value'] );
            if ( strlen( $safe_value ) > 16 ) {
              $safe_value = substr( $safe_value, 0, 16 );
            }
        }

        if ( !isset($safe_value) ) {
               $safe_value = '';
        }

        if (isset($this->request->get['input_address'])) {
        $safe_input_address = $this->request->get['input_address'];
            if ( strlen( $safe_input_address ) > 64 ) {
               $safe_input_address = substr( $safe_input_address, 0, 64 );
            }
        }
        if ( !isset($safe_input_address) ) {
            $safe_input_address = '';
        }
        if (isset($this->request->get['transaction_hash'])) {
        $safe_transaction_hash = $this->request->get['transaction_hash'];
            if ( strlen( $safe_transaction_hash ) > 65 ) {
                $safe_transaction_hash = substr( $safe_transaction_hash, 0, 65 );
            }
        }
        if ( !isset($safe_transaction_hash) ) {
            $safe_transaction_hash = '';
        }
        $apirone_order = array(
            'confirmations' => $safe_confirmations,
            'orderId' => $safe_order_id, // order id
            'key' => $safe_key,
            'value' => $safe_value,
            'transaction_hash' => $safe_transaction_hash,
            'input_address' => $safe_input_address
        );
        if (($safe_confirmations >= 0) AND !empty($safe_value) AND $this->abf_sale_exists($safe_order_id, $safe_input_address)) {
            $abf_api_output = 1; //transaction exists
            //get test mode status
            if ($test){
                /*$apirone_adr =$this->language->get('test_url');*/
                $apirone_adr = $this->language->get('live_url');
            }
            else{
                $apirone_adr = $this->language->get('live_url');
            }
            if (!empty($apirone_order['value']) && !empty($apirone_order['input_address']) && empty($apirone_order['transaction_hash'])) {
                $order = $this->model_checkout_order->getOrder($apirone_order['orderId']);
                if ($apirone_order['key'] == md5($this->config->get('payment_apirone_secret'))) {
                $sales = $this->model_extension_payment_apirone->getSales($apirone_order['orderId']);
                $transactions = $sales['transactions'];
                $flag = 1; //no simular transactions
                if($transactions != ''){
                    foreach ($transactions as $transaction) {
                    if(($transaction['thash'] == 'empty') && ($transaction['paid'] == $apirone_order['value'])){
                        $flag = 0; //simular transaction detected
                        break;
                    }
                    }  
                }
                if($flag){
                    $empty = "empty";
                    $this->model_extension_payment_apirone->addTransaction($apirone_order['orderId'], $empty, $apirone_order['value'], $apirone_order['confirmations']);
                    $abf_api_output = 2; //insert new transaction in DB without transaction hash
                } else {
                    $this->model_extension_payment_apirone->updateTransaction($apirone_order['value'], $apirone_order['confirmations']);
                    $abf_api_output = 3; //update existing transaction
                    }
                }
            }

                if (!empty($apirone_order['value']) && !empty($apirone_order['input_address']) && !empty($apirone_order['transaction_hash'])) {
                $abf_api_output = 4; // callback with transaction_hash
                $sales = $this->model_extension_payment_apirone->getSales($apirone_order['orderId']);
                $transactions = $sales['transactions'];
                $order = $this->model_checkout_order->getOrder($apirone_order['orderId']);
                if ($sales == null) $abf_api_output = 5; //no such information about input_address
                $flag = 1; //new transaction
                $empty = 0; //unconfirmed transaction
                   if ($apirone_order['key'] == md5($this->config->get('payment_apirone_secret'))) {
                        $abf_api_output = 6; //WP key is valid but confirmations smaller that value from config or input_address not equivalent from DB
                        if (($apirone_order['confirmations'] >= ABF_COUNT_CONFIRMATIONS) && ($apirone_order['input_address'] == $sales['address'])) {
                            $abf_api_output = 7; //valid transaction
                            $payamount = 0;
                            if($transactions != '')
                                foreach ($transactions as $transaction) {
                                if($transaction['thash'] != 'empty')
                                        $payamount += $transaction['paid'];                                 
                                    $abf_api_output = 8; //finding same transaction in DB
                                    if($apirone_order['transaction_hash'] == $transaction['thash']){
                                        $abf_api_output = 9; // same transaction was in DB
                                        $flag = 0; // same transaction was in DB
                                        break;
                                    }
                                    if(($apirone_order['value'] == $transaction['paid']) && ($transaction['thash'] == 'empty')){
                                        $empty = 1; //empty find
                                    }
                                }
                        }
                    }

                $response_btc = $this->abf_convert_to_btc($order['currency_code'], $order['total'], $order['currency_value']);                 
                if($flag && $apirone_order['confirmations'] >= ABF_COUNT_CONFIRMATIONS && $response_btc > 0){
                    $abf_api_output = 10; //writing into DB, taking notes
                    $notes        = 'Input Address: ' . $apirone_order['input_address'] . '; Transaction Hash: ' . $apirone_order['transaction_hash'] . '; Payment: ' . number_format($apirone_order['value']/1E8, 8, '.', '') . ' BTC; ';
                    $notes .= 'Total paid: '.number_format(($payamount + $apirone_order['value'])/1E8, 8, '.', '').' BTC; ';
                    if (($payamount + $apirone_order['value'])/1E8 < $response_btc)
                        $notes .= 'User trasfrer not enough money in your shop currency. Waiting for next payment; ';
                    if (($payamount + $apirone_order['value'])/1E8 > $response_btc)
                        $notes .= 'User trasfrer more money than You need in your shop currency; ';


                    if($empty){
                        $this->model_extension_payment_apirone->updateTransaction($apirone_order['value'], $apirone_order['confirmations'], $apirone_order['transaction_hash'], $apirone_order['orderId']);
                    } else {
                        $this->model_extension_payment_apirone->addTransaction($apirone_order['orderId'], $apirone_order['transaction_hash'], $apirone_order['value'], $apirone_order['confirmations']);
                    } 
                    $notes .= 'Order total: '.$response_btc . ' BTC; ';                     
                    if ($this->abf_check_remains($apirone_order['orderId'])){ //checking that payment is complete, if not enough money on payment it's not completed 
                        $notes .= 'Successfully paid.';   
                        $this->model_checkout_order->addOrderHistory($apirone_order['orderId'], $this->config->get('payment_apirone_order_status_id'), $notes, true);
                    } else {
                        $this->model_checkout_order->addOrderHistory($apirone_order['orderId'], $this->config->get('payment_apirone_pending_status_id'), $notes, true);
                    }
                    

                    $abf_api_output = '*ok*';
                } else {
                    $abf_api_output = '11'; //No currency or small confirmations count or same transaction in DB
                }
            }
        }

        if(($apirone_order['confirmations'] >= ABF_MAX_CONFIRMATIONS) && (ABF_MAX_CONFIRMATIONS != 0)) {// if callback's confirmations count more than ABF_MAX_CONFIRMATIONS we answer *ok*
            $abf_api_output="*ok*";
            if($test) {
                $this->log->write("Skipped transaction: {$apirone_order['transaction_hash']} with confirmations: {$apirone_order['confirmations']}");
            };
        };
        if($test) {
        print_r($abf_api_output);//global output
        } else{
            if($abf_api_output === '*ok*') echo '*ok*';
        }
        exit;
    }



   public function check_payment(){
        $this->load->model('checkout/order');
        $this->load->model('extension/payment/apirone');

        $safe_order = intval( $this->request->get['order'] );

        if ( $safe_order == 'undefined') {
             $safe_order = '';
        }

        if ( strlen( $safe_order ) > 25 ) {
            $safe_order = substr( $safe_order, 0, 25 );
        }
        if (!empty($safe_order)) {
            $order = $this->model_checkout_order->getOrder($safe_order);
            /*print_r( $order );*/
            if (!empty($safe_order)) {
                $sales = $this->model_extension_payment_apirone->getSales($safe_order);
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
            if($transactions != '')
            foreach ($transactions as $transaction) {
                if($transaction['thash'] == 'empty') {
                            $status = 'innetwork';
                            $innetwotk_pay = $transaction['paid'];
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
            }
            if ($order == '') {
                echo '';
                exit;
            }
            $response_btc = $this->abf_convert_to_btc($order['currency_code'], $order['total'], $order['currency_value']);
            if ($order['order_status_id'] == $this->config->get('payment_apirone_order_status_id') && $this->abf_check_remains($safe_order)) {
                $status = 'complete';
            }
                $remains_to_pay = number_format($this->abf_remains_to_pay($safe_order), 8, '.', '');
                $last_transaction = $confirmed;
                $payamount = number_format($payamount/100000000, 8, '.', '');
                $innetwotk_pay = number_format($innetwotk_pay/100000000, 8, '.', '');


            echo '{"innetwork_amount": "' .$innetwotk_pay. '" , "arrived_amount": "' .$payamount. '" , "remains_to_pay": "' .$remains_to_pay. '" , "last_transaction": "' .$last_transaction. '", "Status": "' .$status. '"}';

            exit;
        }  
    }
}