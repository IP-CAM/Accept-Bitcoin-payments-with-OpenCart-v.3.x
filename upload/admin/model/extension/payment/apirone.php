<?php

class ModelExtensionPaymentApirone extends Model {

	public function install() {

	$this->db->query("
	CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "apirone_sale` (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        address text NOT NULL,
        order_id int DEFAULT '0' NOT NULL,
        PRIMARY KEY  (id)
    ) ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci;");

	$this->db->query("
    CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "apirone_transactions` (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        paid bigint DEFAULT '0' NOT NULL,
        confirmations int DEFAULT '0' NOT NULL,
        thash text NOT NULL,
        order_id int DEFAULT '0' NOT NULL,
        PRIMARY KEY  (id)
    ) ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci;");

	}

	public function uninstall() {
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "apirone_sale`;");
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "apirone_transactions`;");
	}   
}