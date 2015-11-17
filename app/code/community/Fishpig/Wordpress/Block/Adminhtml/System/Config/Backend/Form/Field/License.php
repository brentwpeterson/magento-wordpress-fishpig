<?php
/**
 * @category Fishpig
 * @package Fishpig_Wordpress
 * @license http://fishpig.co.uk/license.txt
 * @author Ben Tideswell <help@fishpig.co.uk>
 */
	
class Fishpig_Wordpress_Block_Adminhtml_System_Config_Backend_Form_Field_License extends Mage_Adminhtml_Block_System_Config_Form_Field
{
	/**
	 * Get the element HTML
	 *
	 * @param Varien_Data_Form_Element_Abstract $element
	 * @return string
	 */
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
		return Mage::helper(
			str_replace('wordpress_license_', '', $element->getHtmlId()) . '/license'
		)->getImageHtml('sconfig.png');
    }
}
