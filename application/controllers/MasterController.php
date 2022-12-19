<?php
class MasterController extends Zend_Controller_Action
{
    function __call($name, $var)
    {
        $this->_redirect('/', array('exit' => 1));
    }

    function init()
    {
        /* Initialize action controller here */
        $this->log = Zend_Registry::get('log');
        $this->masters = new Default_Model_DbTable_Masters();
        $auth = Zend_Auth::getInstance();
        $this->user = $auth->getStorage()->read();
        $this->cache = Zend_Registry::get('cache');
    }

    function preDispatch()
    {
    }

    public function indexAction()
    {
        $this->_helper->redirector('edit');
    }

    public function editAction()
    {
        $this->view->title = "Edit Master Calendars";

        //$this->view->headLink()->appendStylesheet('/styles/css/fileuploader.css', 'screen,projection');

        if ($this->getRequest()->getParam('delete')) {
            $this->deleteCalendar();
        }
        if ($this->getRequest()->getParam('clear')) {
            $this->cache->clean(Zend_Cache::CLEANING_MODE_ALL);
        }
        $rows = $this->masters->fetchTitles()->toArray();
        $table = "<table>\n<tr><th>Rem</th><th>Grade</th><th>Subject</th></tr>\n";
        foreach ($rows as $row) {
            $table.= "<tr><td><a href=\"/master/edit/delete/" . $row['grade'] . "." . $row['subject'] . "\"><img src=\"/images/minus.png\" /> REM</a></td><td>" . $row['grade'] . "</td><td>" . $row['subject'] . "</td></tr>\n";
        }
        $table.= "</table>";
        $this->view->list = $table;

        $this->render('index');
    }

    public function uploadAction()
    {
        $this->log->info('Upload triggered');
        $this->log->info($this->_request->getRequestUri());
        $this->_helper->layout->disableLayout();

        $uploadDir = Zend_Registry::get('upload_dir');

        // list of valid extensions, ex. array("jpeg", "xml", "bmp")
        $allowedExtensions = array("csv");
        // max file size in bytes
        $sizeLimit = 10 * 1024 * 1024;

        $uploader = new My_qqFileUploader($allowedExtensions, $sizeLimit);
        $uploader->setLogger($this->log);
        $uploader->setMasters($this->masters);
        $result = $uploader->handleUpload($uploadDir);
        // to pass data through iframe you will need to encode all html tags
		$this->log->info($result);
        $this->view->response = htmlspecialchars(json_encode($result), ENT_NOQUOTES);
    }

    private function deleteCalendar()
    {
        $this->log->info("Calendar Removal Posted");
        $remove = explode(".", $this->getRequest()->getParam('delete'));
        $this->masters->delete(array('grade = ?' => $remove[0], 'subject = ?' => $remove[1]));
        $this->view->success.= "<li>Successfully deleted " . $remove[1] . " " . $remove[0] . "</li>";
    }

}
