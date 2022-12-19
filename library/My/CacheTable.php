<?php
class My_CacheTable extends Zend_Db_Table_Abstract
{

    /**
     * @var Zend_Cache
     */
    protected $_cache = null;

    /**
     * @var bool
     */
    public $cache_result = true;

    /**
     * Initialize
     */
    public function init()
    {
        // Get from bootstrap
        $this->_cache = Zend_Registry::get('cache');
        $this->log = Zend_Registry::get('log');
    }

    /**
     * Reset cache
     */
    public function _purgeCache()
    {
        $this->log->info('Cache wiped');
        $this->_cache->clean(Zend_Cache::CLEANING_MODE_ALL);
    }

    /**
     * update
     */
    public function update(array $data, $where)
    {
        parent::update($data, $where);
        $this->_purgeCache();
    }

    /**
     * insert
     */
    public function insert(array $data)
    {
        parent::insert($data);
        $this->_purgeCache();
    }

    /**
     * delete
     */
    public function delete($where)
    {
        parent::delete($where);
        $this->_purgeCache();
    }

    /**
     * Fetch all
     */
    public function fetchAll($where = null, $order = null, $count = null, $offset = null)
    {
        if (is_object($where)) {
            $id = md5($where->__toString());
            
            if ((!($this->_cache->test($id))) || (!$this->cache_result)) {
                $result = parent::fetchAll($where, $order, $count, $offset);
                $this->log->info('Caching Query');
                $this->_cache->save($result);
                
                return $result;
            } else {
                $this->log->info('Result from Cache');
                return $this->_cache->load($id);
            }
        } else {
            $this->log->info('Result Not Cached');
            $result = parent::fetchAll($where, $order, $count, $offset);
            return $result;
        }
    }

    /**
     * Fetch one result
     */
    public function fetchRow($where = null, $order = null, $offset=null)
    {
        if (is_object($where)) {
            $id = md5($where->__toString());
            
            if ((!($this->_cache->test($id))) || (!$this->cache_result)) {
                $result = parent::fetchRow($where, $order);
                $this->log->info('Caching Query');
                $this->_cache->save($result);
                
                return $result;
            } else {
                $this->log->info('Result from Cache');
                return $this->_cache->load($id);
            }
        } else {
            $this->log->info('Result Not Cached');
            $result = parent::fetchRow($where, $order);
            return $result;
        }
    }
}
