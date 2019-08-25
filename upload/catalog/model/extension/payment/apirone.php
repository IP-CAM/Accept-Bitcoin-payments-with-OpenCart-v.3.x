<?php
class ModelExtensionPaymentApirone extends Model {
	public function getMethod($address, $total) {
		$this->load->language('extension/payment/apirone');

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$this->config->get('apirone_geo_zone_id') . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");

		if ($this->config->get('apirone_total') > 0 && $this->config->get('apirone_total') > $total) {
			$status = false;
		} elseif (!$this->config->get('apirone_geo_zone_id')) {
			$status = true;
		} elseif ($query->num_rows) {
			$status = true;
		} else {
			$status = false;
		}

		$method_data = array();
		$work_cryptos = '';
		$available_cryptos = array('btc', 'bch', 'ltc');
        foreach ($available_cryptos as $currency) {
            if( $this->abf_getCryptoAddress($currency) != '' ) {
                $work_cryptos = $work_cryptos.' <img src="'.$_SERVER['REQUEST_SCHEME']."://".$_SERVER['SERVER_NAME'].'/catalog/view/theme/default/image/btca_'.$currency.'_logo.svg" alt="" width="16" style="margin-top:-3px;">';
            }            
        }

		if ($status) {
			$method_data = array(
				'code'       => 'apirone',
				'title'      => $this->language->get('text_title').$work_cryptos,
				'terms'      => '',
				'sort_order' => $this->config->get('payment_apirone_sort_order')
			);
		}

		return $method_data;
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

	public function abf_getSales($order_id, $crypto, $address = NULL) {

			if (is_null($address)) {
				$qry = $this->db->query("SELECT * FROM `" . DB_PREFIX . "apirone_sale` WHERE `order_id` = '" . (int)$order_id . "' AND `crypto` = '" . (int)$crypto . "'"); 
			} else {
				$qry = $this->db->query("SELECT * FROM `" . DB_PREFIX . "apirone_sale` WHERE `order_id` = '" . (int)$order_id . "' AND `address` = '" . (string)$address . "' AND `crypto` = '" . (int)$crypto . "'");
			}

			if ($qry->num_rows) {
				$order = $qry->row;
				$order['transactions'] = $this->abf_getTransactions($order_id, $crypto);
				return $order;
			} else {
				return false;
			}
	}

	public function abf_getCurrency($order_id, $address) {

			$qry = $this->db->query("SELECT * FROM `" . DB_PREFIX . "apirone_sale` WHERE `order_id` = '" . (int)$order_id . "' AND `address` = '" . (string)$address . "'");
			if ($qry->num_rows) {
				$order = $qry->row;
				return $order['crypto'];
			} else {
				return -1;
			}
	}

	public function abf_getTransactions($order_id, $crypto) {
		$qry = $this->db->query("SELECT * FROM `" . DB_PREFIX . "apirone_transactions` WHERE `order_id` = '" . (int)$order_id . "' AND `crypto` = '" . (int)$crypto . "'");

		if ($qry->num_rows) {
			return $qry->rows;
		} else {
			return false;
		}
	}

	public function abf_getRate($code, $crypto) {
		$qry = $this->db->query("SELECT * FROM `" . DB_PREFIX . "apirone_rates` WHERE `code` = '" . (string)$code . "' AND `crypto` = '" . (int)$crypto . "'");
		if ($qry->num_rows) {
			return $qry->rows[0];
		} else {
			return false;
		}
	}

	public function abf_clearRates(){
		$qry = $this->db->query("TRUNCATE TABLE `" . DB_PREFIX . "apirone_rates`");
	}

	public function abf_getNowDate() {
		$qry = $this->db->query("SELECT NOW() FROM `" . DB_PREFIX . "apirone_rates`");
		if ($qry->num_rows) {
			return strtotime($qry->rows[0]['NOW()']);
		} else {
			return false;
		}
	}

	public function abf_setRate($code, $amount, $crypto) {
		$qry = $this->abf_getRate($code, $crypto);
		if ($qry) {
			$this->db->query("UPDATE `" . DB_PREFIX . "apirone_rates` SET `amount` = '" . (float)$amount . "', `time` = NOW() WHERE `code` = '" . (string)$code .  "' AND `crypto` = '" . (int)$crypto . "'");
		} else {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "apirone_rates` SET `code` = '" . (string)$code . "', `time` = NOW(), `amount` = '" . (float)$amount . "', `crypto` = '" . (int)$crypto . "'");
		}
		return true;
	}

	public function abf_updateTransaction($where_input_thash, $where_paid, $confirmations, $thash = NULL, $where_order_id = NULL, $where_thash = 'empty') {
		if (is_null($thash) || is_null($where_order_id)) {

		$this->db->query("UPDATE `" . DB_PREFIX . "apirone_transactions` SET `time` = NOW(), `confirmations` = '" . (int)$confirmations . "' WHERE `paid` = '" . (int)$where_paid . "'AND `input_thash` = '" .(string)$where_input_thash .  "'");

		} else{

		$this->db->query("UPDATE `" . DB_PREFIX . "apirone_transactions` SET `thash` = '" . (string)$thash . "', `time` = NOW(), `confirmations` = '" . (int)$confirmations . "' WHERE `order_id` = '" . (int)$where_order_id . "'AND `paid` = '" . (int)$where_paid . "'AND `thash` = '" . (string)$where_thash . "'AND `input_thash` = '" . (string)$where_input_thash .  "'");

		}
	}

	public function abf_addSale($order_id, $address, $crypto = 0) {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "apirone_sale` SET `order_id` = '" . (int)$order_id . "', `time` = NOW(), `address` = '" . $this->db->escape($address) . "', `crypto` = '" . (int)$crypto . "'");
	}

	public function abf_updateSale($where_sale_id, $conditions, $amount = NULL) {
		if($amount != NULL) {
			$amountQuery = ", `crypto_amount` = '" . (int)$amount . "'";
		} else {
			$amountQuery = "";
		}
		$this->db->query("UPDATE `" . DB_PREFIX . "apirone_sale` SET `timer_on` = NOW(), `conditions_complete` = '" . (int)$conditions . "'" . $amountQuery ." WHERE `id` = '" . (int)$where_sale_id . "'");
	}

	public function abf_finishSale($order_id, $crypto) {
		$conditions = 3;
		$finished = 1;
		$this->db->query("UPDATE `" . DB_PREFIX . "apirone_sale` SET `timer_on` = NOW(), `finished` = '" . (int)$finished . "', `conditions_complete` = '" . (int)$conditions . "'" ." WHERE `order_id` = '" . (int)$order_id . "' AND `crypto` = '" . (int)$crypto . "'");
	}

	public function abf_addTransaction($order_id, $thash, $input_thash, $paid, $confirmations, $crypto) {

		$this->db->query("DELETE FROM `" . DB_PREFIX . "apirone_transactions` WHERE `input_thash` = '" . $this->db->escape($input_thash) . "'");

		$this->db->query("INSERT INTO `" . DB_PREFIX . "apirone_transactions` SET `order_id` = '" . (int)$order_id . "', `time` = NOW(), `thash` = '" . $this->db->escape($thash)  . "', `input_thash` = '" . $this->db->escape($input_thash)  .  "', `paid` = '" . $this->db->escape($paid) . "', `confirmations` = '" . (int)$confirmations . "', `crypto` = '" . (int)$crypto . "'");
	}

}