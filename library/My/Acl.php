<?php
class My_Acl extends Zend_Controller_Plugin_Abstract
{

    public function preDispatch(Zend_Controller_Request_Abstract $request)
    {

        $reg = Zend_Registry::getInstance();
        $dispatcher = Zend_Controller_Front::getInstance()->getDispatcher();
        $controllerName = $request->getControllerName();
        $actionName = $request->getActionName();
        if (empty($controllerName)) {
            $controllerName = $dispatcher->getDefaultController();
        }
        $className = $dispatcher->formatControllerName($controllerName);
        $db = $reg->db;
        $log = $reg->log;
        $acl = new Zend_Acl();

        $log->info('Begin Processing - ' . $controllerName . ' ' . $actionName);
        // fetch all existing roles
        // $roles = new Roles();
        $roles = new Default_Model_DbTable_Roles();
        $roleData = $roles->fetchAll($roles->select());
        foreach ($roleData as $role) {
            $acl->addRole(new Zend_Acl_Role($role['name']));
        }
        $log->info('Processing ACL/Redirect');
        // Security is defined as
        /*
           $acl->add(new Zend_Acl_Resource('Controller'));
           $acl->allow('Role', 'Controller', array('Action'));
        */
        // Admin gets everything
        // $acl->deny('anonymous');
        // default (index)
        $acl->add(new Zend_Acl_Resource('index'));
        $acl->allow(array('admin', 'district', 'anonymous'), 'index', array('index'));
        // auth
        $acl->add(new Zend_Acl_Resource('auth'));
        $acl->allow(array('admin', 'district', 'anonymous'), 'auth', array('index', 'login', 'logout', 'process'));
        // blackouts
        $acl->add(new Zend_Acl_Resource('blackout'));
        $acl->allow(array('district', 'admin'), 'blackout', array('index', 'edit'));
        // districts
        $acl->add(new Zend_Acl_Resource('district'));
        $acl->allow('admin', 'district', array('index', 'edit'));
        // masters
        $acl->add(new Zend_Acl_Resource('master'));
        $acl->allow('admin', 'master', array('index', 'edit', 'upload'));
        // calendars
        $acl->add(new Zend_Acl_Resource('cal'));
        $acl->allow(array('admin', 'district'), 'cal', array('index', 'edit'));
        $acl->allow('anonymous', 'cal', array('index'));
        // store our acl's for later use
        $reg->acl = $acl;
        $auth = Zend_Auth::getInstance();
        /* first we'll see if the controller and action exist */
        $log->info('testing ' . $className);
        if ($className) {
            try {
                // if this fails, an exception will be thrown and
                // caught below, indicating that the class can't
                // be loaded.
                Zend_Loader::loadClass($className, $dispatcher->getControllerDirectory());
                $actionName = $request->getActionName();
                if (empty($actionName)) {
                    $actionName = $dispatcher->getDefaultAction();
                }
                $methodName = $dispatcher->formatActionName($actionName);
                $class = new ReflectionClass($className);

                $log->info('Current Path - Controller:' . $controllerName . ' Action:' . $actionName);

                if ($class->hasMethod($methodName)) {
                    $log->info('Using ' . $methodName);
                    // the controller and action exist so now
                    // check ACL
                    // We don't check acl for auth.
                    if ($controllerName != "auth" && $controllerName != "error" && $controllerName != "index") {
                        if ($auth->getIdentity()) {
                            $log->info('Authenticated');
                            $user = $auth->getIdentity()->name;
                            $log->info('We are ' . $user);
                            // var_dump($user);
                            // get roles for the user
                            if (!empty($user)) {
                                $userTable = new Default_Model_DbTable_Users();
                                $userRow = $userTable->findByUsername($user);
                                $userRoles = $userRow->findDependentRowset('Default_Model_DbTable_Roles')->toArray();
                            }
                            $roleName = (!empty($userRoles[0]['name']) ? $userRoles[0]['name'] : "anonymous");
                        } else {
                            // user is unauthenticated
                            $log->info('Unauthenticated');
                            $roleName = "anonymous";
                        }
                        if(@!isset($user)) $user="None";
                        $log->info('This user has ' . $roleName . ' role');
                        $this->update_auth_role($roleName);
                        if (!$acl->isAllowed($roleName, $controllerName, $actionName)) {
                            $log->info('User not allowed User:' . $user . ' Controller:' . $controllerName . ' Action:' . $actionName);
                            // user is authenticated, but not allowed
                            $request->setControllerName("error");
                            $request->setActionName("security");
                            $request->setDispatched(false);
                            return;
                        } else {
                            // user is authenticated, and allowed
                            $log->info('User allowed User:' . $user . ' Controller:' . $controllerName . ' Action:' . $actionName);
                            return;
                        }
                    } else {
                        // We skip checking some controllers
                        $log->info('ACL check skipped (access to everyone)');
                        return;
                    }
                } else {
                    $log->info('No method:' . $methodName . ' for class:' . $className);
                }
            }
            catch(Zend_Exception $e) {
                // Couldn't load the class. No need to act yet,
                // just catch the exception and fall out of the
                // if
                $log->info('Could not load the class - ' . $className);
                $log->info($e->getMessage());
            }
        }
        // we only arrive here if can't find controller or action
        $log->info('Couldn\'t find the controller or the action.');

        $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
        $redirector->gotoSimple('index', 'index');
        /*
           $request->setControllerName( 'index' );
           $request->setActionName( 'index' );
           $request->setDispatched( false );
           return;
        */
    }

    private function update_auth_role($role = "anonymous")
    {
        $auth = Zend_Auth::getInstance();
        if ($auth->hasIdentity()) {
            $current_auth = $auth->getStorage()->read();
            $current_auth->role = $role;
            $auth->getStorage()->write($current_auth);
        }
    }
}
?>
