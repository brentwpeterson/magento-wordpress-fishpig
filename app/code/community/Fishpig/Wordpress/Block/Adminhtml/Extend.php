<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */

 	// Include the FPAdmin Extend Block class
	require_once(Mage::getModuleDir('', 'Fishpig_Wordpress') . DS . implode(DS, array('FPAdmin', 'Block', 'Adminhtml', 'Extend.php')));

class Fishpig_Wordpress_Block_Adminhtml_Extend extends Fishpig_FPAdmin_Block_Adminhtml_Extend
{
	/**
	 * Get a list of extensions for Extend
	 *
	 * @param int $count = 0
	 * @param array $pref = array
	 * @param bool $rand = false
	 * @return array
	 */
	public function getExtensions($count = 0, array $pref = array(), $rand = false)
	{
		return parent::getExtensions($count, $pref, true);
	}
}
