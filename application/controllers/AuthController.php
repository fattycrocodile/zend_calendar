<?php
class AuthController extends Zend_Controller_Action
{

    function __call($name, $var)
    {
        $this->_redirect('/', array('exit' => 1));
    }

    function init()
    {
        $this->log = Zend_Registry::get('log');
        $this->db = Zend_Registry::get("db");
    }

    public function indexAction()
    {
        $this->log->info('Auth init');
        $this->_helper->redirector('login');
    }

    public function loginAction()
    {
        // action body
        $this->view->subtitle = "Login";
        $this->view->form = $this->getForm();
    }

    public function logoutAction()
    {
        $auth = Zend_Auth::getInstance();
        $killer = array();
        $auth->getStorage()->write($killer);
        $auth->clearIdentity();
        $this->_helper->redirector('login'); // back to login page

    }

    private function getForm()
    {
        return new Application_Form_Auth(array('action' => '/auth/process',
            'method' => 'post',));
    }

    private function getAuthAdapter(array $params)
    {

        $authAdapter = new Zend_Auth_Adapter_DbTable($this->db, 'users', 'name', 'password', '? AND login="enabled"');
        // Set the input credential values to authenticate against
        $authAdapter->setIdentity($params['username']);
        $authAdapter->setCredential($params['password']);
        return $authAdapter;
    }

    public function processAction()
    {
        $this->log->info('Processing Auth');
        // $this->view->messages = '';
        // action body
        $request = $this->getRequest();
        // Check if we have a POST request
        if (!$request->isPost()) {
            $this->log->info('No post info');
            return $this->_helper->redirector('login');
        }
        // Get our form and validate it
        $form = $this->getForm();
        if (!$form->isValid($request->getPost())) {
            // Invalid entries
            $this->log->info('No valid post data');
            // $this->view->subtitle = "Login Failed";
            $this->view->form = $form;
            return $this->render('login'); // re-render the login form
        }
        // Get our authentication adapter and check credentials
        $this->log->info('Begin auth test');
        $adapter = $this->getAuthAdapter($form->getValues());
        $auth = Zend_Auth::getInstance();
        $result = $auth->authenticate($adapter);
        if (!$result->isValid()) {
            $this->log->info('login failed');
            // Invalid credentials
            $form->setDescription('Invalid credentials provided');
            $auth->clearIdentity();
            $this->view->form = $form;
            return $this->render('login'); // re-render the login form
        }
        // We're authenticated! Redirect to the home page
        Zend_Session::forgetMe();
        $data = $adapter->getResultRowObject(array('id', 'name', 'district_long', 'district_short', 'role'));
        $auth->getStorage()->write($data);
        $this->log->info('login succeeded');
        $this->_redirect('/cal/index/t/week/v/agenda');
    }
}
