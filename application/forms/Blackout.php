<?php
class Application_Form_Blackout extends Zend_Dojo_Form
{
    public function init()
    {
        $this->setAttrib('id', 'blackout_form');
        $this->addElement('select', 'week_length',
            array(
                'label' => 'Week Length (in days)',
                'multiOptions' => array(
                        '4'=>'Four',
                        '5'=>'Five'
                ),
                'required' => true,
                'invalidMessage' => 'An invalid number of days has been specified',
                'default' => '5'
            )
        );
        $this->addElement('DateTextBox', 'start_date', array('label' => 'Calendar Start', 'required' => true, 'setStrict' => true, 'invalidMessage' => 'Invalid date specified.', 'formatLength' => 'short',));
        $this->addElement('DateTextBox', 'blackout_date', array('label' => 'Select Blackout Date', 'required' => false, 'setStrict' => true, 'invalidMessage' => 'Invalid date specified.', 'formatLength' => 'short',));
        $this->addElement('submit', 'week_sub', array('label' => 'process'));
        $this->addElement('submit', 'start_sub', array('label' => 'process'));
        $this->addElement('submit', 'blackout_sub', array('label' => 'process'));
        $this->addDisplayGroup(array('week_length', 'week_sub'), 'week_fields', array('legend' => 'Week Length'));
        $this->addDisplayGroup(array('start_date', 'start_sub'), 'start_fields', array('legend' => 'Start Date'));
        $this->addDisplayGroup(array('blackout_date', 'blackout_sub'), 'blackout_fields', array('legend' => 'Blackout Entry'));
        return $this;
    }
}
?>
