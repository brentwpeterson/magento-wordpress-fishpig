<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_Wordpress_Model_Resource_Page_Collection extends Fishpig_Wordpress_Model_Resource_Post_Collection_Abstract
{
	/**
	 * Name prefix of events that are dispatched by model
	 *
	 * @var string
	*/
	protected $_eventPrefix = 'wordpress_page_collection';
	
	/**
	 * Name of event parameter
	 *
	 * @var string
	*/
	protected $_eventObject = 'pages';
	
	public function _construct()
	{
		$this->_init('wordpress/page');
	}
}

