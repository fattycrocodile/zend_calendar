<?php
class Application_Form_Users extends Zend_Form
{
    
    public function init()
    {
        
        $db = Zend_Db_Table::getDefaultAdapter();
        
        $this->setAttrib('id', 'usermod');
        $id = new Zend_Form_Element_Hidden('id');
        $id->addValidator('digits');
        // username
        $name = new Zend_Form_Element_Text('name');
        $name->addValidator('alnum')->addValidator('regex', false, array('/^[a-z_0-9]+/'))->addValidator('stringLength', false, array(0, 20))->setRequired(true)->setLabel('Username:')->addFilters(array('StripTags', 'StringTrim', 'StringToLower'));
        // password
        $password = new Zend_Form_Element_Text('password');
        $password->addValidator('alnum')->addValidator('stringLength', false, array(0, 20))->setRequired(true)->setLabel('Password (case sensitive):')->addFilters(array('StripTags', 'StringTrim'));
        // role
        $select = $db->select()->from('roles', array('id', 'name'));
        $roleOptions = $db->fetchPairs($select);
        $role = new Zend_Form_Element_Select('role');
        $role->setMultiOptions($roleOptions)->setRequired(true)->setLabel('Role:')->addFilters(array('StripTags', 'StringTrim'));
        // district_long
        $district_long = new Zend_Form_Element_Text('district_long');
        $district_long->setRequired(true)->addValidator('stringLength', false, array(0, 64))->setLabel('District Long:')->addFilters(array('StripTags', 'StringTrim'));
        // district_short
        $district_short = new Zend_Form_Element_Text('district_short');
        $district_short->addValidator('alnum')->addValidator('stringLength', false, array(0, 6))->setRequired(true)->setLabel('District Short:')->addFilters(array('StripTags', 'StringTrim', 'StringToUpper'));
        // login
        $login = new Zend_Form_Element_Select('login');
        $login->setMultiOptions(array('enabled' => 'Enabled', 'disabled' => 'Disabled'))->setRequired(true)->setLabel('Enabled:')->addFilters(array('StripTags', 'StringTrim'));
        $this->addElements(array($id, $name, $password, $role, $district_long, $district_short, $login));
        $this->addElement('submit', 'process', array('label' => 'process'));
        $this->addDisplayGroup(array('id', 'name', 'password', 'role', 'district_long', 'district_short', 'login', 'process'), 'user');
        return $this;
    }
    
    public function isValid($data)
    {
        $id = $this->getValue('id');
        if (!empty($id)) {
            $this->getElement('name')->addValidator('Db_NoRecordExists', false, array('table' => 'users', 
                'field' => 'name', 
                'exclude' => array('field' => 'id', 
                'value' => $id)));
            
            $this->getElement('district_long')->addValidator('Db_NoRecordExists', false, array('table' => 'users', 
                'field' => 'district_long', 
                'exclude' => array('field' => 'id', 
                'value' => $id)));
            $this->getElement('district_short')->addValidator('Db_NoRecordExists', false, array('table' => 'users', 
                'field' => 'district_short', 
                'exclude' => array('field' => 'id', 
                'value' => $id)));
        }
        return parent::isValid($data);
    }
}
?>
