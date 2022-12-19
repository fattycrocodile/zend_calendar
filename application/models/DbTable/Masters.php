<?php
Class Default_Model_DbTable_Masters extends My_CacheTable
//Zend_Db_Table_Abstract

{
    protected $_scheme = 'calgen';
    protected $_name = 'masters';
    protected $_primary = 'id';

    protected $_rowClass = 'My_Db_Row';
    // no dependant tables, no reference maps
    // SELECT CONCAT(grade, subject) as title, grade, subject, MAX(offset) as last_start, SUM(duration) as max_working FROM masters GROUP BY grade, subject
    public function fetchSummary()
    {
        $select = $this->select();
        $select->from($this, array('CONCAT(grade, subject) as title', 'grade', 'subject', 'MAX(offset) as last_start', 'SUM(duration) as max_working'))->group(array('grade', 'subject'));

        return $this->fetchAll($select);
    }
    //SELECT CONCAT(grade, '.', subject) as title, grade, subject FROM masters GROUP BY grade, subject
    public function fetchTitles()
    {
        $select = $this->select();
        $select->from($this, array('CONCAT(grade,".", subject) as title', 'grade', 'subject'))->group(array('grade', 'subject'));

        return $this->fetchAll($select);
    }
     //SELECT subject FROM masters where grade=
    public function fetchTitlesByGrade($grade="KG")
    {
        // $select = $this->select();
        $where = $this->getAdapter()->quoteInto('grade = ?', $grade);
        // $select->from($this)->where('grade = ?',$grade);

        return $this->fetchAll($where);
    }

    // SELECT * FROM masters WHERE grade = \"" . $master_values['grade'] . "\" AND subject = \"" . $master_values['subject'] . "\""
    public function fetchByGradeAndSubject($grade, $subject)
    {
        $where = $this->getAdapter()->quoteInto('grade = ?', $grade);
        $where.= $this->getAdapter()->quoteInto('AND subject= ?', $subject);
        return $this->fetchAll($where);
    }

    /*
       SELECT `m`.*, `a`.`Offset` AS `adj_offset`, `a`.`Duration` AS `adj_duration`, `a`.`Note` AS `adj_note` FROM `masters` AS `m`
	 LEFT JOIN `adjustments` AS `a` ON m.id = a.master_id AND a.user_id='13' WHERE (m.grade IN ('1') AND m.subject IN ('math')) ORDER BY `m`.`id` ASC
     */
    public function fetchByGradeAndSubjectAdjustedByUser($grade, $subject, $user)
    {

        $wgrade = $this->getAdapter()->quoteInto('m.grade IN (?)', $grade);
        $wsubject = $this->getAdapter()->quoteInto('m.subject IN (?)', $subject);
        $wuser = $this->getAdapter()->quoteInto('a.user_id=?', $user);
        $query = $this->select()->setIntegrityCheck(FALSE)
            ->from(array('m' => 'masters'), '*')
            ->joinleft(array('a' => 'adjustments'),
            'm.id = a.master_id AND '.$wuser,
            array('adj_offset' => 'Offset',
            'adj_duration' => 'Duration',
            'adj_note' => 'Note',
            'adj_title' => 'title',
            'show_link' => 'show_link',
            'adj_URL' => 'custom_link'
        )
    )
    ->where($wgrade . " AND " . $wsubject)
    ->order('m.id ASC');
    return $this->fetchAll($query);
    }
}
