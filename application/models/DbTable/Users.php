<?php
Class Default_Model_DbTable_Users extends My_CacheTable
{
    protected $_scheme = 'calgen';
    protected $_name = 'users';
    protected $_primary = 'id';
    
    protected $_rowClass = 'My_Db_Row';
    
    protected $_dependentTables = array('Default_Model_DbTable_Roles', 
        'Default_Model_DbTable_Blackouts', 
        'Default_Model_DbTable_Calstart');
    
    public function findByUsername($user)
    {
        $where = $this->getAdapter()->quoteInto('name = ?', $user);
        return $this->fetchRow($where, 'id');
    }
    
    public function findUserById($id)
    {
        $where = $this->getAdapter()->quoteInto('id = ?', $id);
        return $this->fetchRow($where);
    }
}


