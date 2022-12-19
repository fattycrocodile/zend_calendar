<?php
Class Default_Model_DbTable_Roles extends My_CacheTable
//Zend_Db_Table_Abstract

{
    protected $_scheme = 'calgen';
    protected $_name = 'roles';
    protected $_primary = 'id';
    
    protected $_rowClass = 'My_Db_Row';
    
    protected $_referenceMap = array('Users' => array('columns' => 'id', 
        'refTableClass' => 'Default_Model_DbTable_Users', 
        'refColumns' => 'role'),);
}
