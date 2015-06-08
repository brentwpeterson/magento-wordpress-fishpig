<?php
/**
 * @category    Fishpig
 * @package     Fishpig_AttributeSplash
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_Wordpress_Model_System_Config_Source_Page_Template
{
	public function toOptionArray()
	{
		$options = Mage::getModel('page/source_layout')->toOptionArray(false);
		
		array_unshift($options, array(
			'value'=>'', 
			'label'=> '-- ' . Mage::helper('wordpress')->__('Default Template') . ' --'
		));

		return $options;
	}
}
