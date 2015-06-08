<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_Wordpress_Model_Option extends Fishpig_Wordpress_Model_Abstract
{
	public function _construct()
	{
		$this->_init('wordpress/option');
	}
	
	/**
	 * Loads an option based on it's name
	 *
	 * $param string $name
	 * @return $this
	 */
	public function loadByName($name)
	{
		$this->load($name, 'option_name');
		return $this;
	}
}
