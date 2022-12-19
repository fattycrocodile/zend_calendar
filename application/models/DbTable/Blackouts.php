<?php
Class Default_Model_DbTable_Blackouts extends My_CacheTable
//Zend_Db_Table_Abstract

{
    protected $_scheme = 'calgen';
    protected $_name = 'blackout';
    protected $_primary = 'id';

    protected $_rowClass = 'My_Db_Row';

    protected $_referenceMap = array('Users' => array('columns' => 'user_id',
        'refTableClass' => 'Default_Model_DbTable_Users',
        'refColumns' => 'id'),);

    public function findByUserID($user)
    {
        $where = $this->getAdapter()->quoteInto('user_id = ?', $user);
        return $this->fetchAll($where);
    }

    public function findByUserIDAndDate($user, $date)
    {
        $where = $this->getAdapter()->quoteInto('user_id = ?', $user);
        $where.= $this->getAdapter()->quoteInto('AND blackout_date = ?', $date);
        return $this->fetchAll($where);
    }

    public function findAllByUserIDAfterDate($user, $date)
    {
        $where = $this->getAdapter()->quoteInto('user_id = ?', $user);
        $where.= $this->getAdapter()->quoteInto('AND blackout_date >= ?', $date);
        return $this->fetchAll($where, 'blackout_date ASC');
    }

    public function deleteByUserIDAndDate($user, $date)
    {
        $where = $this->getAdapter()->quoteInto('user_id = ?', $user);
        $where.= $this->getAdapter()->quoteInto('AND blackout_date = ?', $date);
        return $this->delete($where);
    }

    public function deleteByUserAndID($user, $id)
    {
        $where = $this->getAdapter()->quoteInto('user_id = ?', $user);
        $where.= $this->getAdapter()->quoteInto('AND id = ?', $id);
        return $this->delete($where);
    }
}
