<?php
class IndexController extends Zend_Controller_Action
{
    function __call($name, $var)
    {
        $this->_redirect('/', array('exit' => 1));
    }

    public function init()
    {
        /* Initialize action controller here */
        Zend_Dojo::enableView($this->view);
        $this->view->dojo()->disable();
    }

    function preDispatch()
    {
        $auth = Zend_Auth::getInstance();
        if (!$auth->hasIdentity()) {
            $this->_redirect('auth/login');
        }
    }

    function indexAction()
    {
        $this->view->title = "Calendar Creation Tool";
    }
}


