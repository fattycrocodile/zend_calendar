<?php
class Application_Form_Adjustments extends Zend_Dojo_Form
{
    public function init()
    {

        $db = Zend_Db_Table::getDefaultAdapter();

        $this->setAttrib('id', 'adjustments');
        // master_id
        $master_id = new Zend_Form_Element_Hidden('master_id');
        $master_id->addValidator('digits');
        // user_id
        $user_id = new Zend_Form_Element_Hidden('user_id');
        $user_id->addValidator('digits');     
        // title
        $title= new Zend_Form_Element_Text('title');
        $title->setLabel('Override title:');
        $title->setAttribs(array('style' => 'width:100%; padding:5px;'));
        // BT Link
        $custom_link= new Zend_Form_Element_Text('custom_link');
        $custom_link->setLabel('Override link URL:');
        $custom_link->setAttribs(array('style' => 'width:100%; padding:5px;'));
        // BT Link visibility
        $show_link= new Zend_Form_Element_Radio('show_link');
        $show_link->setLabel('BT link visibility:')->addMultiOptions(array('1' => 'Hidden', '0' => 'Visible'))->setSeparator(' | ');
        // offset
        $offset = new Zend_Form_Element_Text('Offset');
        $offset->addValidator('digits')->setRequired(true)->setLabel('Offset (Days from start of year):');
        $offset->setAttribs(array('style' => 'padding:5px;'));
        // duration
        $duration = new Zend_Form_Element_Text('Duration');
        $duration->addValidator('digits')->setRequired(true)->setLabel('Duration (Number of days required):')->addFilters(array('StripTags', 'StringTrim'));
        $duration->setAttribs(array('style' => 'padding:5px;'));
        // note
        $note = new Zend_Dojo_Form_Element_Editor('Note', array('degrade' => true,
            'required' => false,
            'label' => 'Note:'));
        $this->setLegend('Test');
        $this->addElements(array($master_id, $user_id, $title, $custom_link, $show_link, $offset, $duration, $note));
        $this->addElement('submit', 'process', array('label' => 'process'));
        $this->addDisplayGroup(array('name', 'master_id', 'user_id', 'title', 'custom_link' , 'show_link', 'Offset', 'Duration', 'Note', 'process'), 'editor');
        return $this;
    }
}
?>