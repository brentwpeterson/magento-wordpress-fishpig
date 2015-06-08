<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_Wordpress_Model_Resource_Option extends Fishpig_Wordpress_Model_Resource_Abstract
{
	public function _construct()
	{
		$this->_init('wordpress/option', 'option_id');
	}
}
