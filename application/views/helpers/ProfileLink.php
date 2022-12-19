<?php
class Zend_View_Helper_ProfileLink extends Zend_View_Helper_Abstract
{
    public $view;
    
    public function setView(Zend_View_Interface $view)
    {
        $this->view = $view;
    }
    
    public function profileLink()
    {
        $auth = Zend_Auth::getInstance();
        if ($auth->hasIdentity()) {
            return 'Welcome, ' . $auth->getIdentity()->name;
        }
        return 'Please Login';
    }
}
