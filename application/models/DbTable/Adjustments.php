<?php
Class Default_Model_DbTable_Adjustments extends My_CacheTable
//Zend_Db_Table_Abstract

{
    protected $_scheme = 'calgen';
    protected $_name = 'adjustments';
    protected $_primary = array('master_id', 'user_id');
    
    protected $_rowClass = 'My_Db_Row';
    
    protected $_referenceMap = array('Users' => array('columns' => 'user_id', 
        'refTableClass' => 'Default_Model_DbTable_Users', 
        'refColumns' => 'id'), 
        'Masters' => array('columns' => 'master_id', 
        'refTableClass' => 'Defaults_Model_DbTable_Master', 
        'refColumns' => 'id'));
    
    public function findByID($master_id, $user_id)
    {
        $where = $this->getAdapter()->quoteInto('id = ?', $id);
        return $this->fetchiRow($where);
    }
}
