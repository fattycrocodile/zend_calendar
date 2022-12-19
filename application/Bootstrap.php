<?php

error_reporting(E_ALL);
ini_set('display_errors', TRUE);
ini_set('display_startup_errors', TRUE);

class Bootstrap extends Zend_Application_Bootstrap_Bootstrap
{

    protected function _initAutoload()
    {
        $autoloader = new Zend_Application_Module_Autoloader(array(
            'namespace' => 'Default',
            'basePath'  => dirname(__FILE__),
        ));
        return $autoloader;
    }

    protected function _initDB()
    {
      $resource = $this->getPluginResource('db');
      $db = $resource->getDbAdapter();
      Zend_Db_Table_Abstract::setDefaultAdapter ( $db );
      $db->setFetchMode(Zend_Db::FETCH_ASSOC);
      if (APPLICATION_ENV == 'development') {
          $profiler = $db->getProfiler();
          $profiler->setEnabled(true);
          $profiler->setFilterQueryType(Zend_Db_Profiler::SELECT |
                              Zend_Db_Profiler::INSERT |
                              Zend_Db_Profiler::UPDATE |
                              Zend_Db_Profiler::DELETE |
                              Zend_Db_Profiler::CONNECT |
                              Zend_Db_Profiler::QUERY |
                              Zend_Db_Profiler::TRANSACTION);
      }
      Zend_Registry::set('db', $db);
    }

    protected function _initVars()
    {
        $config = $this->getOptions();
        // File storage for uploads
        Zend_Registry::set('upload_dir', $config['folder']['upload']);
    }

    protected function _initLog()
    {
        $writer = new Zend_Log_Writer_Null();
        if (APPLICATION_ENV == 'development') {
            // logging enabled during dev
            $writer = new Zend_Log_Writer_Stream(APPLICATION_PATH . '/../working/logs/calgen.log');
        }
        $log = new Zend_Log($writer);
        Zend_Registry::set('log', $log);
    }

    protected function _initRoutes()
    {
        $this->bootstrap('frontController');
        $frontController = Zend_Controller_Front::getInstance();
        $restRoute = new Zend_Rest_Route($frontController, array(), array(
    'default' => array('data')));

        $frontController->getRouter()->addRoute('data', $restRoute);
    }

    public function _initModuleLoaders()
    {
        $this->bootstrap('Frontcontroller');

        $fc = $this->getResource('Frontcontroller');
        $modules = $fc->getControllerDirectory();

        foreach ($modules AS $module => $dir) {
            $moduleName = strtolower($module);
            $moduleName = str_replace(array('-', '.'), ' ', $moduleName);
            $moduleName = ucwords($moduleName);
            $moduleName = str_replace(' ', '', $moduleName);

            $loader = new Zend_Application_Module_Autoloader(array(
                'namespace' => $moduleName,
                'basePath' => realpath($dir . "/../"),
            ));
        }
    }

    protected function _initCss() {
        if (APPLICATION_ENV == "production") {
            return;
        }
        require_once APPLICATION_PATH."/../library/lessphp/lessc.inc.php";
        $sLess = APPLICATION_PATH."/../public/styles/less/screen.less";
        $sCss  = APPLICATION_PATH."/../public/styles/css/screen.css";
        $oLessc = new lessc($sLess);
        // file_put_contents($sCss, $oLessc->parse());
    }

    protected function _initView()
    {
        // Zend Items
        $view = new Zend_View();
        $view->addHelperPath("My/View/Helper", "My_View_Helper");
        $view->addHelperPath('My/Dojo/View/Helper/', 'My_Dojo_View_Helper');
        $view->addHelperPath('Zend/Dojo/View/Helper/', 'Zend_Dojo_View_Helper');
        $view->addHelperPath('SZend/Dojo/View/Helper/', 'SZend_Dojo_View_Helper');

        // Basic Header Items
        $view->doctype( 'HTML5' );
        $view->headTitle()->setSeparator(' - ');
        $view->title = "Calgen";
        $view->headTitle('Curriculum Calendar Generator');
        $view->headMeta()->appendHttpEquiv('Content-Type', 'text/html;charset=utf-8');
        $view->headLink()->headLink(array(
                'rel' => 'favicon',
                'type' => 'image/ico',
                'href' => $view->baseUrl('favicon.ico')
            ));

        // CSS Sheets
        $view->headLink()->appendStylesheet('/styles/css/print.css', 'print');
        $view->headLink()->appendStylesheet('/styles/css/screen.css?t=new', 'screen,projection');
        $view->headLink()->appendStylesheet('/styles/css/ie.css', 'screen', 'IE');
        // DOJO Setup
        Zend_Dojo_View_Helper_Dojo::setUseProgrammatic();
        $view->dojo()
            ->setCdnDojoPath(Zend_Dojo::CDN_DOJO_PATH_GOOGLE)
            ->setCdnVersion('1.6')
            ->addStylesheetModule('dijit.themes.nihilo')
            ->disable();
        // Rended our view!
        $viewRenderer = Zend_Controller_Action_HelperBroker::getStaticHelper(
            'ViewRenderer'
        );
        $viewRenderer->setView($view);
        return $view;
    }

    protected function _initNavigation()
    {
        $this->bootstrap('layout');
        $layout = $this->getResource('layout');
        $view = $layout->getView();
        $config = new Zend_Config_Xml(APPLICATION_PATH.'/configs/navigation.xml','nav');
        $navigation = new Zend_Navigation($config);
        $view->navigation($navigation);
    }

    protected function _initCache()
    {
        $manager = $this->bootstrap('cachemanager');
        $cachemanager = $this->getResource('cachemanager');
        $cache = $cachemanager->getCache('file');
        Zend_Db_Table_Abstract::setDefaultMetadataCache($cache);
        Zend_Registry::set('cache', $cache);
        Zend_Date::setOptions(array('cache' => $cache));
    }



    protected function _sendResponse (Zend_Controller_Response_Http $response)
    {
        $response->setHeader('Content-Type', 'text/html; charset=UtF-8');
        $response->setHeader( 'Accept-encoding', 'gzip,deflate');
        $response->sendResponse();
    }

}
