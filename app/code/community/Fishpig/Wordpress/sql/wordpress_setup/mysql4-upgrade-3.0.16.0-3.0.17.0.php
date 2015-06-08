<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */
	
	$this->startSetup();

	try {
		$this->getConnection()->query("DROP TABLE IF EXISTS {$this->getTable('wordpress_autologin')}");
	}
	catch (Exception $e) {
		Mage::helper('wordpress')->log($e);
	}

	$this->endSetup();
