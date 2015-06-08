<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_Wordpress_Helper_Config extends Fishpig_Wordpress_Helper_Abstract
{
	/**
	 * Retrieve a cached config value
	 *
	 * @param string $key
	 * @return mixed
	 */
	public function getConfigValue($key)
	{
		if ($config = $this->_getAllConfigValues()) {
			return isset($config[$key]) ? $config[$key] : null;
		}
		
		return null;
	}
	
	/**
	 * Retrieves all config values for the extension
	 *
	 * @return array
	 */
	protected function _getAllConfigValues()
	{
		if (!$this->_isCached('config')) {
			$this->_cache('config', array());
			
			$store = Mage::app()->getStore();
			$request	= Mage::app()->getRequest();

			if ($store->getCode() === 'admin') {
				if ($storeCode = $request->getParam('store', false)) {
					$store = Mage::getModel('core/store')->load($storeCode);
					
					if ($store->getId()) {
						$options = array(
							'stores' => $store->getId(), 
							'websites' => $store->getWebsiteId(), 
							'default' => 0
						);
					}
				}
				else if ($websiteCode = $request->getParam('website', false)) {
					$website = Mage::getModel('core/website')->load($websiteCode, 'code');
					
					if ($website->getId()) {
						$options = array(
							'websites' => $website->getId(), 
							'default' => 0
						);
					}
				}
				else {
					$options = array('default' => 0);
				}
			}
			else {
				if (!is_object($store->getWebsite())) {
					return array();
				}
				
				$options = array(
					'stores' => $store->getId(), 
					'websites' => $store->getWebsite()->getId(), 
					'default' => 0
				);
			}

			$resource = Mage::getSingleton('core/resource');
			$db 			= $resource->getConnection('core_read');
			$config	= array();
			
			foreach($options as $scope => $scopeId) {
				$select = $db->select()
					->from($resource->getTableName('core/config_data'), array('path', 'value'))
					->where('path LIKE ?', 'wordpress%')
					->where('scope=?', $scope)
					->where('scope_id=?', $scopeId);
		
				if ($results = $db->fetchAll($select)) {
					foreach($results as $result) {
						if (!isset($config[$result['path']])) {
							$config[$result['path']] = $result['value'];
						}
					}
				}
			}

			$groups = (array)Mage::getConfig()->getNode()->default->wordpress;
				
			foreach($groups as $group => $fields) {
				foreach($fields as $field => $value) {
					$path = 'wordpress/'. $group . '/' . $field;
					
					if (!isset($config[$path])) {
						$config[$path] = (string)$value;
					}
				}
			}

			$this->_cache('config', $config);
		}

		return $this->_cached('config');
	}
}
