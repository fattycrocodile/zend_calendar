<?php
class DistrictController extends Zend_Controller_Action
{
    function __call($name, $var)
    {
        $this->_redirect('/', array('exit' => 1));
    }

    function init()
    {
        /* Initialize action controller here */
        // Zend_Dojo::enableView($this->view);
        $this->log = Zend_Registry::get('log');
        $this->db = Zend_Registry::get('db');
        $this->users = new Default_Model_DbTable_Users();
        $this->auth = Zend_Auth::getInstance();
        $this->user = $this->auth->getStorage()->read();
        $this->view->dojo()->disable();
    }

    function preDispatch()
    {
    }

    function indexAction()
    {
        $this->_helper->redirector('edit');
    }

    function editAction()
    {
        $this->view->title = "Edit District Accounts";
        // NEW LOGIN
        $this->view->userform = new Application_Form_Users(array('action' => '/district/edit',
            'method' => 'post',));
        // if post, process user add/edit
        if ($this->getRequest()->isPost()) {
            $this->processUser();
        }
        // if get, process user delete
        elseif ($this->getRequest()->getParam('delete')) {
            $this->deleteUser($this->getRequest()->getParam('delete'));
        }
        // if get, process user edit
        elseif ($this->getRequest()->getParam('edit')) {
            $this->editUser($this->getRequest()->getParam('edit'));
        }


        $this->userTable();
        $this->render('index');
    }

    function processUser()
    {

        $form = new Application_Form_Users(array('action' => '/district/edit',
            'method' => 'post',));
        if ($form->isValid($_POST)) {
            $post = $form->getValues();
            $this->log->info($post);
            // unset($_POST['process']);
            // if ID is set, update
            if (!empty($post['id'])) {
                $where = $this->users->getAdapter()->quoteInto('id = ?', $post['id']);
                $this->users->update($post, $where);
            } else {
                // else insert
                $this->users->insert($post);
            }
            $this->view->success.= "<li>User successfully added/updated</li>";
        } else {
            $this->view->error.= "<li>There was an error with your submission, see below.</li>";
            $this->view->userform = $form;
        }
    }

    function deleteUser($id)
    {
        $this->users->delete('id = "' . $id . '"');
        $this->view->success.= "<li>User successfully deleted.</li>";
    }

    function editUser($id)
    {
        $this->log->info('Editing user ' . $id);
        $result = $this->users->findUserById($id)->toArray();
        $form = new Application_Form_Users(array('action' => '/district/edit',
            'method' => 'post',));

        $form->setDefaults($result);
        $values = $form->getValues();
        $this->view->userform = $form;
    }

    function userTable()
    {
        $rows = $this->users->fetchAll($this->users->select()->order('district_long ASC'))->toArray();
        $table = "<a href=\"" . $this->view->url(array('controller' => 'district', 'action' => 'edit'), 'default', true) . "/add/1\"><img src=\"/images/plus.png\" /> Add a user</a>";
        $table = "<table>\n<tr><th>Rem</th><th>Edit</th><th>Name</th><th>Role</th><th>District Long</th><th>District Short</th><th>Login</th></tr>\n";
        foreach ($rows as $row) {

            $table.= "<tr>" . "<td><a href=\"/district/edit/delete/" . $row['id'] . "\"><img src=\"/images/minus.png\" /> REM</a></td>" . "<td><a href=\"/district/edit/edit/" . $row['id'] . "\"><img src=\"/images/pencil.png\" />EDIT</a></td>" . "<td>" . $row['name'] . "</td>" . "<td>" . $row['role'] . "</td>" . "<td>" . $row['district_long'] . "</td>" . "<td>" . $row['district_short'] . "</td>" . "<td>" . $row['login'] . "</td>" . "</tr>\n";
        }
        $table.= "</table>";
        $this->view->list = $table;
    }
}
