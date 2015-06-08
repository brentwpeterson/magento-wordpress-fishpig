<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */

	$this->startSetup();

	try {	
		Mage::getResourceModel('wordpress/user')->cleanDuplicates();
	}
	catch (Exception $e) {
		Mage::helper('wordpress')->log($e);
	}

	$this->endSetup();
