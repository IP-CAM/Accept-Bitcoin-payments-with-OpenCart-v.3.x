<?php

class ModelExtensionPaymentApirone extends Model {

	public function install_tx_table() {
	$this->db->query("
    CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "apirone_transactions` (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        paid bigint DEFAULT '0' NOT NULL,
        confirmations int DEFAULT '0' NOT NULL,
        thash text NOT NULL,
        input_thash text NOT NULL,
        order_id int DEFAULT '0' NOT NULL,
        crypto INT(1) NOT NULL,
        PRIMARY KEY  (id)
    ) ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci;");
	}

    public function install_sales_table() {
    $this->db->query("
    CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "apirone_sale` (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        address text NOT NULL,
        order_id int DEFAULT '0' NOT NULL,
        crypto INT(1) NOT NULL,
        timer_on datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        crypto_amount bigint DEFAULT '0' NOT NULL,
        finished INT(1) DEFAULT 0,
        conditions_complete INT(1) DEFAULT 0,
        PRIMARY KEY  (id)
    ) ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci;");
    }

    public function install_rates_table() {
    $this->db->query("
    CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "apirone_rates` (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        code text NOT NULL,
        amount float DEFAULT '0' NOT NULL,
        crypto INT(1) NOT NULL,
        time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        PRIMARY KEY  (id)
    ) ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci;");
    }

	public function delete_sales_table() {
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "apirone_sale`;");
	}   

    public function delete_tx_table(){
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "apirone_transactions`;");
    }

    public function delete_rates_table(){
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "apirone_rates`;");
    }

    public function delete_altcoin_table(){
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "apirone_altcoin`;");
    }
    
    public function check_tx() {
        return $this->db->query("SELECT * FROM `" . DB_PREFIX . "apirone_transactions` LIMIT 1");
    }

    public function check_sale() {
        return $this->db->query("SELECT * FROM `" . DB_PREFIX . "apirone_sale` LIMIT 1");
    }

    public function update_to_v2() {
            $this->db->query("ALTER TABLE `" . DB_PREFIX . "apirone_transactions` ADD `input_thash` text NOT NULL AFTER thash;");
    }

    public function update_to_v3() {
            $this->db->query("ALTER TABLE `" . DB_PREFIX . "apirone_sale` ADD `crypto` INT(1) NOT NULL, ADD `timer_on` datetime DEFAULT '0000-00-00 00:00:00' NOT NULL, ADD `crypto_amount` bigint DEFAULT '0' NOT NULL, ADD `finished` INT(1) DEFAULT 0, ADD `conditions_complete` INT(1) DEFAULT 0;");
            $this->db->query("ALTER TABLE `" . DB_PREFIX . "apirone_transactions` ADD `crypto` INT(1) NOT NULL;");
    }
}