<?php

class ControllerExtensionPaymentApirone extends Controller {
	private $error = array();

	//need for plugin update, otherwise you will get old cached page
	private function clear_cache(){
        if ($this->user->hasPermission('modify', 'common/developer')) {
			$directories = glob(DIR_CACHE . '*', GLOB_ONLYDIR);

			if ($directories) {
				foreach ($directories as $directory) {
					$files = glob($directory . '/*');
					
					foreach ($files as $file) { 
						if (is_file($file)) {
							unlink($file);
						}
					}
					
					if (is_dir($directory)) {
						rmdir($directory);
					}
				}
			}
		}
	}

	public function index() {
		$this->load->language('extension/payment/apirone');
		$this->load->model('extension/payment/apirone');

		$chkinputthash = $this->model_extension_payment_apirone->check_tx();
		if (!array_key_exists('input_thash', $chkinputthash->row)){
			if($chkinputthash->num_rows != 0){
				$this->model_extension_payment_apirone->update_to_v2();
        	} else{
        		$this->model_extension_payment_apirone->delete_tx_table();
        		$this->model_extension_payment_apirone->install_tx_table();
        	}
		}

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('payment_apirone', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
		}

		$data['heading_title'] = $this->language->get('heading_title');

		$data['text_edit'] = $this->language->get('text_edit');
		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');
		$data['text_all_zones'] = $this->language->get('text_all_zones');

		$data['entry_merchant'] = $this->language->get('entry_merchant');

		$data['entry_order_status'] = $this->language->get('entry_order_status');
		$data['entry_pending_status'] = $this->language->get('entry_pending_status');

		$data['entry_geo_zone'] = $this->language->get('entry_geo_zone');
		$data['entry_status'] = $this->language->get('entry_status');
		$data['entry_sort_order'] = $this->language->get('entry_sort_order');
		$data['entry_merchantname'] = $this->language->get('entry_merchantname');
		$data['entry_test_mode'] = $this->language->get('entry_test_mode');
		$data['entry_confirmation'] = $this->language->get('entry_confirmation');

		$data['button_save'] = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->error['merchant'])) {
			$data['error_merchant'] = $this->error['merchant'];
		} else {
			$data['error_merchant'] = '';
		}


		$data['breadcrumbs'] = array();

		$data['confirmations'] = array(1,2,3,4,5,6);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/payment/apirone', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/payment/apirone', 'user_token=' . $this->session->data['user_token'], true);

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);
		$secret = $this->config->get('payment_apirone_secret');


		if ($this->config->get('payment_apirone_secret') == null) {
			$data['payment_apirone_secret'] = $secret = md5(time().$this->session->data['user_token']);
		} else {
			$data['payment_apirone_secret'] = $this->config->get('payment_apirone_secret');
		}

		if (isset($this->request->post['payment_apirone_merchant'])) {
			$data['payment_apirone_merchant'] = $this->request->post['payment_apirone_merchant'];
		} else {
			$data['payment_apirone_merchant'] = $this->config->get('payment_apirone_merchant');
		}

		if (isset($this->request->post['payment_apirone_order_status_id'])) {
			$data['payment_apirone_order_status_id'] = $this->request->post['payment_apirone_order_status_id'];
		} else {
			$data['payment_apirone_order_status_id'] = $this->config->get('payment_apirone_order_status_id');
		}
			
		if (isset($this->request->post['payment_apirone_pending_status_id'])) {
			$data['payment_apirone_pending_status_id'] = $this->request->post['payment_apirone_pending_status_id'];
		} else {
			$data['payment_apirone_pending_status_id'] = $this->config->get('payment_apirone_pending_status_id');
		}

		$this->load->model('localisation/order_status');

		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
		
		if (isset($this->request->post['payment_apirone_geo_zone_id'])) {
			$data['payment_apirone_geo_zone_id'] = $this->request->post['payment_apirone_geo_zone_id'];
		} else {
			$data['payment_apirone_geo_zone_id'] = $this->config->get('payment_apirone_geo_zone_id');
		}

		$this->load->model('localisation/geo_zone');

		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		if (isset($this->request->post['payment_apirone_test_mode'])) {
			$data['payment_apirone_test_mode'] = $this->request->post['payment_apirone_test_mode'];
		} else {
			$data['payment_apirone_test_mode'] = $this->config->get('payment_apirone_test_mode');
		}
		if (isset($this->request->post['payment_apirone_test_mode'])) {
			$data['payment_apirone_confirmation'] = $this->request->post['payment_apirone_confirmation'];
		} else {
			$data['payment_apirone_confirmation'] = $this->config->get('payment_apirone_confirmation');
		}

		if (isset($this->request->post['payment_apirone_status'])) {
			$data['payment_apirone_status'] = $this->request->post['payment_apirone_status'];
		} else {
			$data['payment_apirone_status'] = $this->config->get('payment_apirone_status');
		}

		if (isset($this->request->post['payment_apirone_sort_order'])) {
			$data['payment_apirone_sort_order'] = $this->request->post['payment_apirone_sort_order'];
		} else {
			$data['payment_apirone_sort_order'] = $this->config->get('payment_apirone_sort_order');
		}

		if (isset($this->request->post['payment_apirone_merchantname'])) {
			$data['payment_apirone_merchantname'] = $this->request->post['payment_apirone_merchantname'];
		} else {
			$data['payment_apirone_merchantname'] = $this->config->get('payment_apirone_merchantname');
		}

		if (isset($this->request->post['payment_apirone_sort_canceled'])) {
			$data['payment_apirone_sort_canceled'] = $this->request->post['payment_apirone_sort_canceled'];
		} else {
			$data['payment_apirone_sort_canceled'] = $this->config->get('payment_apirone_sort_canceled');
		}
		
		if (isset($this->request->post['payment_apirone_sort_pending'])) {
			$data['payment_apirone_sort_pending'] = $this->request->post['payment_apirone_sort_pending'];
		} else {
			$data['payment_apirone_sort_pending'] = $this->config->get('payment_apirone_sort_pending');
		}

		echo $this->config->get('payment_apirone_sort_pending');
		
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/payment/apirone', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/payment/apirone')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (!$this->request->post['payment_apirone_merchant']) {
			$this->error['merchant'] = $this->language->get('error_merchant');
		} else{
        	$this->clear_cache();
		}

		return !$this->error;
	}

	public function install() {
		$this->load->model('extension/payment/apirone');
		$this->load->model('setting/setting');
		$data = array('payment_apirone_test_mode' => '0', 'payment_apirone_pending_status_id' => '1', 'payment_apirone_order_status_id' => '5');
		$this->model_setting_setting->editSetting('payment_apirone', $data);		
		$this->model_extension_payment_apirone->install_tx_table();
		$this->model_extension_payment_apirone->install_sales_table();
	}

	public function uninstall() {
		$this->load->model('setting/setting');
		$this->load->model('extension/payment/apirone');
		$data = array();
		$this->model_setting_setting->editSetting('payment_apirone', $data);	
		$this->model_extension_payment_apirone->delete_tx_table();
		$this->model_extension_payment_apirone->delete_sales_table();
	}
}