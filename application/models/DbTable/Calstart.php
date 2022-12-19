<?php
Class Default_Model_DbTable_Calstart extends My_CacheTable
//Zend_Db_Table_Abstract

{
    protected $_scheme = 'calgen';
    protected $_name = 'calstart';
    protected $_primary = 'id';
    
    protected $_rowClass = 'My_Db_Row';
    
    protected $_referenceMap = array('Users' => array('columns' => 'user_id', 
        'refTableClass' => 'Default_Model_DbTable_Users', 
        'refColumns' => 'id'),);
    
    public function findByUserID($user)
    {
        $where = $this->getAdapter()->quoteInto('user_id = ?', $user);
        return $this->fetchRow($where);
    }
}
