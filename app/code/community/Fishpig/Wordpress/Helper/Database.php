<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_Wordpress_Helper_Database extends Fishpig_Wordpress_Helper_Abstract
{
	/**
	 * Maps a table name with a prefix
	 * This allows us to keep track of what's been mapped
	 * and allows WordPress MU to map different blog table names
	 *
	 * @param string $entity
	 * @param string $table
	 * @return $this
	 */
	public function mapTable($entity, $table)
	{
		$mappedTables = $this->_cached('wordpress_database_mapped_tables', array());

		if (!isset($mappedTables[$entity])) {
			$mappedTables[$entity] = $table;
			
			Mage::getSingleton('core/resource')->setMappedTableName($entity, $table);
			
			$this->_cache('wordpress_database_mapped_tables', $mappedTables);
		}
		
		return $this;
	}	
	
	/**
	 * Returns true if Magento/Wordpress are installed in the same DB
	 * This can be configured in the Magento admin
	 *
	 * @return bool
	 */
	public function isSameDatabase()
	{
		return Mage::helper('wordpress/config')->getConfigFlag('wordpress/database/is_shared');
	}
	
	/*
	 * Returns the table prefix used by Wordpress
	 *
	 * @return string
	 */
	public function getTablePrefix()
	{
		return Mage::helper('wordpress/config')->getConfigValue('wordpress/database/table_prefix');
	}
	
	/**
	 * Retrieve an entities table name
	 *
	 * @param string $table
	 * @return string
	 */
	public function getTableName($table)
	{
		return Mage::getSingleton('core/resource')->getTableName($table);
	}
	
	/**
	  * Returns true if it is possible to query the DB
	  *
	  * @param bool $graceful
	  * @return true
	  */
	public function isQueryable()
	{
		if (!$this->_isCached('is_queryable')) {
			if ($adapter = $this->getReadAdapter()) {
				$select = $adapter->select()
					->from($this->getTableName('wordpress/post'), 'ID')
					->limit(1);
				
				try {
					$adapter->fetchOne($select);
					$this->_cache('is_queryable', true);
				}
				catch (Exception $e) {
					$this->_cache('is_queryable', false);
					$this->log($e->getMessage());
				}
			}
			else {
				$this->_cache('is_queryable', false);
			}
		}
		
		return $this->_cached('is_queryable');
	}
	
	/**
	 * Retriev the read adapter
	 *
	 * @return false|Varien_Db_Adapter_Pdo_Mysql
	 */	
	public function getReadAdapter()
	{
//		Debugging
//		return Mage::getSingleton('wordpress/blog')->getDb();

		if ($this->isConnected()) {
			if ($this->isSameDatabase()) {
				return Mage::getSingleton('core/resource')->getConnection('core_read');
			}
		
			return $this->_getWordPressAdapter();
		}
		
		return false;
	}
	
	/**
	 * Retriev the write adapter
	 *
	 * @return false|Varien_Db_Adapter_Pdo_Mysql
	 */
	public function getWriteAdapter()
	{
//		Debugging
//		return Mage::getSingleton('wordpress/blog')->getDb();

		if ($this->isConnected()) {
			if ($this->isSameDatabase()) {
				return Mage::getSingleton('core/resource')->getConnection('core_write');
			}
		
			return $this->_getWordPressAdapter();
		}
		
		return false;
	}
	
	/**
	 * Retrieve the WordPress database adapter
	 *
	 * @return false|Varien_Db_Adapter_Pdo_Mysql
	 */
	protected function _getWordPressAdapter()
	{
		if ($this->isConnected()) {
			return Mage::getSingleton('core/resource')->getConnection('wordpress');
		}
		
		return false;
	}

	/**
	 * Determine whether the DB connection is active
	 *
	 * @return bool|null
	 */
	public function isConnected()
	{
		if (!$this->_isCached('db_connected')) {
			$this->_connect();
		}
		
		return $this->_cached('db_connected');
	}

	/**
	 * Connect to the WordPress database
	 *
	 * @return bool
	 */
	public function connect()
	{
		if (!$this->_isCached('db_connected')) {
			$this->_connect();
		}
		
		return $this->isConnected();
	}
	
	/**
	 * Connect to the database
	 *
	 * @return bool
	 */
	protected function _connect()
	{
		$this->_cache('db_connected', false);
		$this->_beforeConnect();

		if ($this->isSameDatabase()) {
			$this->_cache('db_connected', true);
			$this->_cache('db_connected', $this->isQueryable());

			if ($this->_cached('db_connected')) {
				$this->_afterConnect();
			}
		}
		else if ($configs = $this->_getDatabaseDetails()) {
			try {
				$connection = Mage::getSingleton('core/resource')->createConnection('wordpress', 'pdo_mysql', $configs);
			
				if (!is_object($connection)) {
					throw new Exception('Error connecting to the WordPress database');
				}
				
				$connection->getConnection();

				$this->_cache('db_connected', $connection->isConnected());
				$this->_cache('db_connected', $this->isQueryable());
				
				if ($this->_cached('db_connected')) {
					$this->_afterConnect();
				}
			}
			catch (Exception $e) {
				$this->log($e->getMessage());
				$this->_cache('db_connected', false);
			}
		}

		return $this->_cached('db_connected');
	}
	
	/**
	 * Retrieve an array of the database connection details
	 *
	 * @return array|false
	 */
	protected function _getDatabaseDetails()
	{
		$configs = array('model' => 'mysql4', 'active' => '1', 'host' => '', 'username' => '', 'password' => '', 'dbname' => '', 'charset' => 'utf8');
		
		foreach($configs as $key => $defaultValue) {
			if ($value = $this->getConfigValue('wordpress/database/' . $key)) {
				$configs[$key] = $value;
			}
		}

		foreach(array('username', 'password', 'dbname') as $field) {
			if (isset($configs[$field])) {
				$configs[$field] = Mage::helper('core')->decrypt($configs[$field]);
			}
		}
		
		if (isset($configs['host']) && $configs['host']) {
			return $configs;
		}
		
		return false;
	}
	
	/**
	 * Setup things prior to trying to connec to the database
	 * This includes mapping WordPress table names
	 *
	 * @return Fishpig_Wordpress_Helper_Database
	 */
	protected function _beforeConnect()
	{
		Mage::dispatchEvent('wordpress_database_before_connect', array('helper' => $this));
		
		$entities = (array)Mage::app()->getConfig()->getNode()->wordpress->database->before_connect->tables;

		foreach($entities as $entity => $table) {
			$this->mapTable((string)$table->table, $this->getTablePrefix() . $table->table);
		}
			
		return $this;
	}

	/**
	 * This is called after a connection has been established to the WordPress Database
	 *
	 * @return Fishpig_Wordpress_Helper_Database
	 */
	protected function _afterConnect()
	{
		Mage::dispatchEvent('wordpress_database_after_connect', array('helper' => $this));
		
		$entities = (array)Mage::app()->getConfig()->getNode()->wordpress->database->after_connect->tables;

		foreach($entities as $entity => $table) {
			$this->mapTable((string)$table->table, $this->getTablePrefix() . $table->table);
		}
		
		return $this;
	}
}
