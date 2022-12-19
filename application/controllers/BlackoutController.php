<?php
class BlackoutController extends Zend_Controller_Action
{
    function __call($name, $var)
    {
        // $this->_redirect('/', array('exit' => 1));

    }

    function init()
    {
        /* Initialize action controller here */
        $this->log = Zend_Registry::get('log');
        $this->calstart = new Default_Model_DbTable_Calstart();
        $this->blackouts = new Default_Model_DbTable_Blackouts();
        $this->masters = new Default_Model_DbTable_Masters();
        $this->auth = Zend_Auth::getInstance();
        $this->user = $this->auth->getStorage()->read();
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
        $this->view->title = "Edit Blackout Dates";
        // perform delete if requested
        if ($this->getRequest()->getParam('delete')) {
            $this->deleteBlackout();
        }
        // process the blackout form
        $this->view->blackoutform = $this->blackoutProcess();
        // blackout list
        $this->view->blackoutlist = $this->blackoutTable();
        // Put instructions here.
        $this->view->info = "To use the form below please do the following:" . "<ol>" . "<li>Put in your year start date by clicking in the field and selecting the appropriate date from the calendar that appears." . "<br />Be sure to click the 'Process' button when finished." . "<li>In the Blackout Area, select a blackout date. Note that when processed, it will convert the date to the nearest Sunday.</li>" . "<li>When you have entered all of your blackout dates, click the Generate Calendars link located above the list of blackout dates.</li>" . "<li>Your calendars are now available in the area designated.</li>";
        $this->render('index');
    }

    function blackoutProcess()
    {
        $form = new Application_Form_Blackout(array('action' => '/blackout/edit',
            'method' => 'post',));

        if ($this->getRequest()->isPost()) {
            if ($form->isValid($_POST)) {
                if (isset($_POST['start_sub']) || isset($_POST['week_sub'])) {
                    $data = array('user_id' => $this->user->id,
                        'start_date' => $_POST['start_date'],
                        'week_length' => $_POST['week_length']);
                    // update or insert
                    $cur_data = $this->calstart->findByUserID($this->user->id);
                    $row = is_object($cur_data) ? $cur_data->toArray() : array();
                    if (empty($row)) {
                        $this->calstart->insert($data);
                    } else {
                        $where = $this->calstart->getAdapter()->quoteInto('user_id = ?', $this->user->id);
                        $this->calstart->update($data, $where);
                    }
                    $this->view->success = "<li>Successfully posted year start and week length information.</li>";
                }
                if (isset($_POST['blackout_sub'])) {
                    if (!empty($_POST['blackout_date'])) {
                        $rev_date = date('Y-m-d', $this->previousSunday(strtotime($_POST['blackout_date'])));
                        $data = array('user_id' => $this->user->id,
                            'blackout_date' => $rev_date);
                        $row = $this->blackouts->findByUserIDAndDate($this->user->id, $rev_date)->toArray();
                        if (empty($row)) {
                            $this->blackouts->insert($data);
                            $this->view->success = "<li>Successfully posted blackout date. $rev_date</li>";
                        } else {
                            $this->view->success = "<li>Successfully posted blackout date. $rev_date</li>";
                        }
                        // remove the blackout value and populate the form without it
                        $form->reset();
                        unset($_POST['blackout_date']);
                        $form->populate($_POST);
                    }
                }
            } else {
                $this->view->error.= "<li>All fields require a valid date for processing.</li>";
            }
        } else {
            // get current start date and populate the form
            $cur_data = $this->calstart->findByUserID($this->user->id);
            $row = is_object($cur_data) ? $cur_data->toArray() : array();
            $form->reset();
            $form->populate($row);
        }
        return $form;
    }

    function blackoutTable()
    {
        // get the current start date
        $start = $this->calstart->findByUserID($this->user->id)->start_date;
        // fetch all blackout dates after the current startdate
        $blackout_table = $this->blackouts->findAllByUserIDAfterDate($this->user->id, $start)->toArray();
        $this->log->info('Fetching blackout list');
        $table = "<table id=\"blackout_list\">";
        $table.= "<tr><th>Remove</th><th>Blackout Date</th></tr>";
        foreach ($blackout_table as $v) {
            $table.= "<tr><td><a href=\"/blackout/edit/delete/" . $v['id'] . "\"><img src=\"/images/minus.png\" /> REM</></td><td>" . $v['blackout_date'] . "</td></tr>";
        }
        $table.= "</table>";
        return $table;
    }


    function deleteBlackout()
    {
        $this->log->info("Blackout Removal Posted");
        $remove = explode(".", $this->getRequest()->getParam('delete'));
        $this->blackouts->deleteByUserAndID($this->user->id, $remove[0]);
        $this->view->success.= "<li>Successfully unblocked " . $remove[0] . "</li>";
    }


    function previousSunday($stamp)
    {
        $checkday = date("l", $stamp);
        while ($checkday != "Sunday") {
            $stamp = $stamp - 86400;
            $checkday = date("l", $stamp);
        }
        return $stamp;
    }

    function nextSunday($stamp)
    {
        $checkday = date("l", $stamp);
        while ($checkday != "Sunday") {
            $stamp = $stamp + 86400;
            $checkday = date("l", $stamp);
        }
        return $stamp;
    }
}
