<?php

define('GOOGLE_CLIENT_ID', 'Google_Project_Client_ID'); 
define('GOOGLE_CLIENT_SECRET', 'Google_Project_Client_Secret'); 
define('GOOGLE_OAUTH_SCOPE', 'https://www.googleapis.com/auth/calendar'); 
define('REDIRECT_URI', 'http://localhost:9296/cal/index');

class CalController extends Zend_Controller_Action
{
    function __call($name, $var)
    {
        $this->_redirect('/', array('exit' => 1));
    }
    
    function init()
    {
        require_once  dirname(__FILE__) . '/../api/GoogleCalendarApi.class.php';
        $this->GoogleCalendarApi = new GoogleCalendarApi();

        /* Initialize action controller here */
        // THERE IS TOO MUCH HERE?!
        /* Note the user variable below does NOT mean
           the currently logged in user, it denotes
           the current district */
        // Zend components
        $this->log = Zend_Registry::get('log');
        $this->acl = Zend_Registry::get('acl');
        $this->cache = Zend_Registry::get('cache');
       
        // databases
        $this->users = new Default_Model_DbTable_Users();
        $this->calstart = new Default_Model_DbTable_Calstart();
        $this->blackouts = new Default_Model_DbTable_Blackouts();
        $this->masters = new Default_Model_DbTable_Masters();
        $this->adjustments = new Default_Model_DbTable_Adjustments();
        // Internal components
        $this->auth = Zend_Auth::getInstance();
        if ($this->user = $this->auth->getStorage()->read()) {
            $this->editor = $this->acl->isAllowed($this->user->role, 'cal', 'edit');
        } else {
            $this->editor = FALSE;
        }
        // url variables
        $this->mode = $this->_request->getParam('m');
        $this->calg = $this->_request->getParam('g');
        $this->cals = $this->_request->getParam('s');
        $this->cald = $this->_request->getParam('d');
        $this->calt = $this->_request->getParam('t');
        $this->calv = $this->_request->getParam('v');
        $this->caldate = $this->_request->getParam('cd');
        $this->edit = $this->_request->getParam('edit');
        $this->code = $this->_request->getParam('code');
        header("Access-Control-Allow-Origin: *");
    }

    function preDispatch()
    {
    }

    public function indexAction()
    {
        // since we can allow non users into this page, we need a way to
        // determine their adjustment, startdates, and blackouts
        if (!empty($this->cald)) {
            // look up the district/user id by the posted district
            $this->log->info('District override - ' . $this->cald);
            $id = $this->users->findByUsername($this->cald)->id;
        } elseif (!empty($this->user->id)) {
            $this->log->info('Current user id - ' . $this->user->id);
            $id = $this->user->id;
        } else {
            $this->log->info('No user id specified, failing');
        }
        if (isset($id)) {
            $this->log->info('District ID - ' . $id);
        }
        if (empty($id)) {
            $this->view->error = "Could not find what district to generate calendars for";
        } else {
            // clean up get parameters
            if (!empty($_GET)) $this->clean_url();
            $d = $this->_request->getParam('d');
            if (empty($d)) $this->clean_url();
            $cd = $this->_request->getParam('cd');
            if (empty($cd)) $this->clean_url();
            // set the time
            $this->get_time();
            // we need to have a user, either the currently logged in, or
            // the one defined by URL
            if (empty($this->cals) || empty($this->calg)) {
                //die("in IndexAction EMPTY G OR S");
                $this->clean_url();
            } else {
                $cal_data = $this->getWorkingCalendarDates($this->cals, $this->calg, $this->cald, $id);
            }
            // Determine action
            if ($this->mode == 'ical' || $this->mode == 'google' || $this->mode == 'outlook') {
                // Download iCal
                // disable page render
                $this->log->info('Layout disabled for ical push');
                $this->_helper->layout->disableLayout();
                $this->_helper->viewRenderer->setNoRender(true);
                header("Content-Type: text/calendar; charset=utf-8");
                header("Content-Disposition: download; filename=" . $id . "-" . $this->calg . "-" . $this->cals . ".ics");
                echo $this->geniCal($cal_data, $this->mode);
                return;
            } else {
                // Display Caldisp
                // check if edit privileges enabled
                if ($this->editor && ($this->getRequest()->isPost() || isset($this->edit))) {
                    // note we have to repoll the information since we've
                    // updated and reset our cache.
                    $this->view->editForm = $this->processForm($id);
                    $cal_data = $this->getWorkingCalendarDates($this->cals, $this->calg, $this->cald, $id);
                }
                $this->view->miniCal = $this->miniCal($id);
                $this->view->spanView = $this->spanCal($cal_data, $id);
                $this->view->navBox = $this->navBox();
            }
        }
    }

    private function format_ical_string($s)
    {
        $r = wordwrap(
            preg_replace(
                array('/,/', '/;/', '/[\r\n]/'),
                array('\,', '\;', '\n'),
                $s
            ),
            73,
            "\n",
            TRUE
        );

        // Indent all lines but first:
        $r = preg_replace('/\n/', "\n  ", $r);

        return $r;
    }

    private function processForm($user_id)
    {
        $parts = array(
            'controller' => 'cal',
            'action' => 'index',
            'd' => $this->cald, // date
            'g' => $this->calg, // grade
            's' => $this->cals, // subject(s)
            't' => $this->calt, // time
            'v' => $this->calv,
            'cd' => $this->caldate
        );
        $url = $this->view->url($parts, null, true, true);

        $form = new Application_Form_Adjustments(array('action' => $url, 'method' => 'post',));
        if (!empty($this->edit)) {
            $master_values = $this->masters->fetchRow($this->masters->select()->where('id = ?', $this->edit))->toArray();
            $adjustment_row = $this->adjustments->fetchRow($this->adjustments->select()->where('master_id = ?', $this->edit)->where('user_id = ?', $user_id));
            $adjustment_values = is_object($adjustment_row) ? $adjustment_row->toArray() : array();
            $adjustment_values['master_id'] = $master_values['id'];
            $adjustment_values['user_id'] = $user_id;
            $adjustment_values = array_filter($adjustment_values, 'strlen');
            $master_values = array_merge($master_values, $adjustment_values);
        }


        if ($this->getRequest()->isPost()) {
            $formData = $this->_request->getPost();
            if ($form->isValid($formData)) {
                unset($formData['process']);
                // commit the data and send message
                // clear the cache
                // update or insert
                if (!empty($formData['master_id']) && !empty($formData['user_id'])) {
                    $row = $this->adjustments->fetchRow($this->adjustments->select()->where('master_id = ?', $formData['master_id'])->where('user_id = ?', $formData['user_id']));
                } else {
                    $row = "";
                }

                // Apply tidy filtering to the data before inserting.
                $tidy_config = array(
                    'clean' => true,
                    'doctype' => 'omit',
                    'decorate-inferred-ul' => true,
                    'drop-empty-paras' => true,
                    'drop-font-tags' => false,
                    'drop-proprietary-attributes' => true,
                    'enclose-block-text' => true,
                    'join-classes' => true,
                    'join-styles' => true,
                    'show-body-only' => true,
                );

                // Tidy our note...
                $tidy = new tidy;
                $formData['Note'] = $tidy->repairString($formData['Note'], $tidy_config, 'utf8');

                if (empty($row)) {
                    $this->adjustments->insert($formData);
                } else {
                    $where = $this->adjustments->getAdapter()->quoteInto('master_id = ?', $formData['master_id']);
                    $where .= $this->adjustments->getAdapter()->quoteInto('AND user_id = ?', $formData['user_id']);
                    $this->adjustments->update($formData, $where);
                }
                $this->view->success = "<li>Successfully posted change. <a href=\"#" . $formData['master_id'] . "\">Jump to item</a>.</li>";
                $tags = array_merge(array($this->calg), array($this->cals), array($user_id));
                $this->cache->clean(Zend_Cache::CLEANING_MODE_MATCHING_TAG, $tags);
                return;
            } else {
                $form->reset();
                $this->view->error = "<li>There has been an error with your submission</li>";
                $form->populate($formData);
                $form->getDisplayGroup('editor')->setLegend('Editing - ' . $master_values['Standard-Code'] . ' - ' . $master_values['PO Title']);
                return $form;
            }
        }
        $form->populate($master_values);
        $form->getDisplayGroup('editor')->setLegend('Editing - ' . $master_values['Standard-Code'] . ' - ' . $master_values['PO Title']);
        return $form;
    }

    private function navBox()
    {
        // this part is sort of a mess right now, mostly since it's all html.
        // can probably dump it to a view and include it as part of the controller
        // just need a list of subjects and grades available for the user
        $grades = array();
        $subjects =  array();
        $lists = $this->masters->fetchTitles()->toArray();
        foreach ($lists as $selectData) {
            $grades[] = $selectData['grade'];
            // $subjects[] = $selectData['subject'];}
        }
        foreach ((array)$this->calg as $grade) {
            if (is_string($grade)) {
                $sublist = $this->masters->fetchTitlesByGrade($grade)->toArray();
                foreach ($sublist as $selectData) {
                    $subjects[] = $selectData['subject'];
                }
            }
        }
        $lists = array_unique($grades);
        $output = "";
        // Navigation form
        $output .= "<div id=\"calMenu\" class=\"span-6\">\n";
        $output .= "<form name=\"navigator\" method=\"get\" id=\"navigator\">\n";
        // Time selector
        $output .= "<div class=\"span-3\" id=\"timeselect\">\n";
        $output .= "Time Frame\n";

        $output .= "<div class=\"span-3 last\">\n";
        $output .= "<input type=\"radio\" name=\"t\" value=\"week\" onchange=\"submit();\" id=\"week\" " . ($this->_request->getParam('t') == 'week' ? "checked=\"checked\"" : "") . " />\n";
        $output .= "<label for=\"week\">week</label>\n";
        $output .= "</div>\n";

        $output .= "<div class=\"span-3 last\">\n";
        $output .= "<input type=\"radio\" name=\"t\" value=\"month\" onchange=\"submit();\" id=\"month\" " . ($this->_request->getParam('t') == 'month' ? "checked=\"checked\"" : "") . " />\n";
        $output .= "<label for=\"month\">month</label>\n";
        $output .= "</div>\n";

        $output .= "<div class=\"span-3 last\">\n";
        $output .= "<input type=\"radio\" name=\"t\" value=\"year\" onchange=\"submit();\" id=\"year\" " . ($this->_request->getParam('t') == 'year' ? "checked=\"checked\"" : "") . " />\n";
        $output .= "<label for=\"year\">year</label>\n";
        $output .= "</div>\n";

        $output .= "</div>\n";
        // View option
        $output .= "<div id=\"viewopt\" class=\"span-3 last\">\n";
        $output .= "View Type\n";

        $output .= "<div class=\"span-3 last\">\n";
        $output .= "<input type=\"radio\" name=\"v\" value=\"time\" id=\"time\" onchange=\"submit();\" checked=\"checked\" " . ($this->_request->getParam('v') == 'time' ? "checked=\"checked\"" : "") . " />\n";
        $output .= "<label for=\"time\">Timespan</label>\n";
        $output .= "</div>\n";

        $output .= "<div class=\"span-3 last\">\n";
        $output .= "<input type=\"radio\" name=\"v\" value=\"agenda\" id=\"agenda\" onchange=\"submit();\" " . ($this->_request->getParam('v') == 'agenda' ? "checked=\"checked\"" : "") . " />\n";
        $output .= "<label for=\"agenda\">Agenda</label>\n";
        $output .= "</div>\n";

        $output .= "</div>\n";

        $output .= "<hr />\n";
        // Grade selector
        $output .= "<div id=\"gradesubject\" class=\"span-6 last\">\n";
        $output .= "<label for=\"g\" class=\"span-3\">Grade</label> <label for=\"s\" class=\"span-3 last\">Subject</label>\n";

        $output .= "<select multiple=\"multiple\" size=\"10\" name=\"g[]\" onchange=\"submit();\" class=\"span-3\">\n";
        foreach ($lists as $key => $value) {
            $sel = "";
            if ($this->_request->getParam('g') == $value || @in_array($value, $this->_request->getParam('g'))) {
                $sel = "selected";
            }
            $output .= "<option value=\"" . $value . "\" $sel>" . $value . "</option>\n";
        }
        $output .= "</select>\n";
        // Subject Selector
        $lists = array_unique($subjects);
        $output .= "<select multiple=\"multiple\" size=\"10\" name=\"s[]\" onchange=\"submit();\" class=\"span-3 last\">\n";
        foreach ($lists as $key => $value) {
            $sel = "";
            if ($this->_request->getParam('s') == $value || @in_array($value, $this->_request->getParam('s'))) {
                $sel = "selected";
            }
            $output .= "<option value=\"" . $value . "\" $sel>" . $value . "</option>\n";
        }
        $output .= "</select>\n";
        $output .= "</div>\n";
        $output .= "</form>\n";
        $output .= "</div>";
        // subscribe links
        $output .= "<hr />\n";
        $actual_link = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        $googleOauthURL = 'https://accounts.google.com/o/oauth2/auth?scope=' . urlencode(GOOGLE_OAUTH_SCOPE) . '&redirect_uri=' . REDIRECT_URI . '&response_type=code&client_id=' . '664143952635-oipumr3ttgbmguk1cfb1j07h3emk7h6t.apps.googleusercontent.com' . '&access_type=online'; 
        if ((count($this->calg) == 1) && (count($this->cals) == 1)) {
            $output .= "<div id=\"subscribe\" class=\"span-6 last\">";

            // $output .= "<img src=\"/images/rss.png\" width=\"12\" height=\"12\">";
            // $output .= " <a href=\"" . $googleOauthURL . "\">connect with Google Calendar</a><br />";

            $output .= "<img src=\"/images/rss.png\" width=\"12\" height=\"12\">";
            $output .= " <a href=\"" . $this->ical_url() . "\">Subscribe via iCal</a><br />";

            $output .= "<img src=\"/images/rss.png\" width=\"12\" height=\"12\">";
            $output .= " <a href=\"" . $this->ical_url('outlook') . "\">Subscribe via Outlook</a><br />";

            $output .= "<img src=\"/images/rss.png\" width=\"12\" height=\"12\">";
            $output .= " <a href=\"" . $this->ical_url('google') . "\">Subscribe via Google Calendar</a><br />";

            $output .= "</div>";
        }

        return $output;
    }
    // minical generator
    private function miniCal($user)
    {
        $miniCal = new Default_View_Helper_CalendarViewHelper();
        $miniCal->calendarViewHelper($this->caldate);
        $miniCal->setMonthsInRange(0, 12, strtotime($this->calstart->findByUserID($user)->start_date));
        return $miniCal->getCalendarHtml(array(
            'showToday' => TRUE,
            'showPrevMonthLink' => TRUE,
            'showNextMonthLink' => TRUE,
            'tableClass' => "calendar",
            'showSelect' => TRUE,
            'selectDate' => $this->caldate,
            'selectBox' => TRUE,
            'selectBoxName' => "cd",
            'selectBoxFormName' => "selectMonthForm"
        ));
    }
    // view generator
    private function spanCal($cal_data, $id)
    {
        // Our cal data is currently filtered down by P.O. but for this
        // section we need to find items by date. Rather than loop through
        // it several times we'll loop through it once. In the future, this
        // should be reviewed so that it can be in a format usable by both
        // components of this controller.
        // convert $data[grade][subject][PO][date] to $data[grade][subject][date][id]
        if (empty($cal_data)) $cal_data = array();
        foreach ($cal_data as $grade => $gradeData) {
            foreach ($gradeData as $subject => $subjectArray) {
                foreach ($subjectArray as $PO => $POdata) {
                    foreach ($POdata as $itemDate => $dateData) {
                        $new_cal[$grade][$subject][$itemDate][$dateData['id']] = $dateData;
                        $date_conv = strtotime($itemDate);
                        $dates[] = strtotime("monday this week", $date_conv);
                    }
                }
            }
        }
        if (!isset($new_cal)) $new_cal = array();
        // Our date time object for date jumping
        $thisWeek = new DateTime();
        $output = "";
        // timespan view
        if ($this->calv == 'time') {
            // week view
            if ($this->calt == 'week') {
                $thisWeek->setTimestamp($this->caldate);
                $thisWeek->modify("monday this week");
                $output .= "<div id=\"weekview\">";
                $output .= $this->createWeekView($new_cal, $thisWeek, $id);
                $output .= "</div>";
            }
            // month view
            elseif ($this->calt == 'month') {
                $thisWeek->setTimestamp($this->caldate);
                $endMonth = clone $thisWeek;
                $endMonth->modify("+1 month");
                $endMonthNum = $endMonth->format("m");
                $thisWeek->modify("first day of this month");
                $thisWeek->modify("monday this week");
                while ($thisWeek->format("m") != $endMonthNum) {
                    $output .= "<div id=\"weekview\">";
                    $output .= $this->createWeekView($new_cal, $thisWeek, $id);
                    $thisWeek->modify("next monday");
                    $output .= "</div>";
                }
            }
            // year view
            elseif ($this->calt == 'year') {
                $startdate = strtotime($this->calstart->findByUserID($id)->start_date);
                $thisWeek->setTimestamp($startdate);
                $thisWeek->modify("monday this week");
                $week_limit = 1;
                while ($week_limit < 54) {
                    $output .= "<div id=\"weekview\">";
                    if (in_array($thisWeek->getTimestamp(), $dates)) {
                        $output .= $this->createWeekView($new_cal, $thisWeek, $id);
                    }
                    $thisWeek->modify("next monday");
                    $output .= "</div>";
                    $week_limit++;
                }
            }
        }
        // agenda view
        elseif ($this->calv == 'agenda') {
            // week view
            if ($this->calt == 'week') {
                $thisWeek->setTimestamp($this->caldate);
                $thisWeek->modify("monday this week");
                $output .= "<div id=\"weekview\">";
                $output .= $this->createAgendaView($new_cal, $thisWeek, 1);
                $output .= "</div>";
            }
            // month view
            elseif ($this->calt == 'month') {
                $thisWeek->setTimestamp($this->caldate);
                $year = $thisWeek->format("Y");
                $thisWeek->modify("last day of this month");
                $thisWeek->modify("monday this week");
                $end_m = $thisWeek->format("W");
                $thisWeek->setTimestamp($this->caldate);
                $thisWeek->modify("first day of this month");
                $thisWeek->modify("monday this week");
                $start_m = $thisWeek->format("W");
                if ($end_m < $start_m) {
                    $thisWeek->setISODate($year, 53);
                    $end_m = ($thisWeek->format("W") === "53" ? 53 : 52) + 1;
                    // START BARRY HACK
                    if (($end_m == 53) && ($start_m == $end_m)) {
                        $end_m = $start_m + 4;
                    }
                    // END BARRY HACK
                    $thisWeek->setTimestamp($this->caldate);
                    $thisWeek->modify("first day of this month");
                    $thisWeek->modify("monday this week");
                }
                //echo 'Report: '.$start_m.' '.$end_m.'='.($end_m-$start_m+1);
                $output .= "<div id=\"weekview\">";
                $output .= $this->createAgendaView($new_cal, $thisWeek, ($end_m - $start_m) + 1);
                $output .= "</div>";
            }
            // year view
            elseif ($this->calt == 'year') {
                $startdate = strtotime($this->calstart->findByUserID($id)->start_date);
                $thisWeek->setTimestamp($startdate);
                $thisWeek->modify("monday this week");
                $output .= "<div id=\"weekview\">";
                $output .= $this->createAgendaView($new_cal, $thisWeek, 54);
                $output .= "</div>";
            }
        }
        return $output;
    }
    // this function requires that Monday be the current day given for thisWeek
    private function createAgendaView($cal, DateTime $thisWeek, $duration = 2)
    {
        $starttime = $thisWeek->getTimestamp();
        $output = "";
        foreach ($cal as $grade => $subjects) {
            $thisWeek->setTimestamp($starttime);
            $output .= "<div class=\"gradeblock span-18 last\">Grade $grade";
            for ($x = 1; $x <= $duration; ++$x) {
                $output .= "<div class=\"weekblock prepend-1 span-17 last\">" . $thisWeek->format("m/d/y");
                foreach ($subjects as $subject => $callist) {
                    $output .= "<div class=\"subjectblock prepend-1 span-16 last\">$subject";
                    for ($i = 1; $i < 6; ++$i) {
                        $key = $thisWeek->format("Y-m-d");
                        if (!empty($callist[$key])) {
                            foreach ($callist[$key] as $data) {
                                $output .= "<div call=\"itemblock prepend-1 span-15 last\">";
                                $output .= "<div class=\"calinfo poblock span-11\">";
                                $output .= "<h6>[ " . $data['Standard-Code'] . '] ' . $data['PO Title'] . "</h6>";
                                if (!empty($data['URL'])) {
                                    $output .= "<a href=\"" . $data['URL'] . "\" target='_blank'>BT Link</a></br>";
                                }
                                if (!empty($data['Note'])) {
                                    $output .= $this->linked($data['Note']);
                                }
                                if ($this->editor) {
                                    $output .= " <a href=\"?edit=" . $data['id'] . "\">Edit</a>";
                                }
                                if ($this->code) {
                                    $output .= "<a href=\"?add=google\">Add to Google Calendar</a>";
                                }
                                if ($this->code && ($this->_request->getParam('code') != null)) {
                                    $access_token_sess = $_SESSION['google_access_token']; 
                                    if(!empty($access_token_sess)){ 
                                        $access_token = $access_token_sess; 
                                    }else{ 
                                        $data = $this->GoogleCalendarApi->GetAccessToken("664143952635-oipumr3ttgbmguk1cfb1j07h3emk7h6t.apps.googleusercontent.com", REDIRECT_URI, "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", $this->code);
                                        $access_token = $data['access_token']; 
                                        $_SESSION['google_access_token'] = $access_token; 
                                    } 
                                    if(!empty($access_token)){ 
                                        try { 
                                            // Get the user's calendar timezone 
                                            $user_timezone = $this->GoogleCalendarApi->GetUserCalendarTimezone($access_token); 

                                            $calendar_event = array( 
                                                "summary" => $this->format_ical_string($data['Standard-Code'] . " " . $data['PO Title']),
                                                'location' => 'US', 
                                                'description' => $data['grade'] . $data['subject'],
                                            ); 
                                             
                                            $event_datetime = array( 
                                                'event_date' => date('Y/m/d', strtotime($data["cal_end"])), 
                                                'start_time' => date("h:i:sa"), 
                                                'end_time' =>date("h:i:sa", strtotime("+30 minutes"))
                                            );
                                            // Create an event on the primary calendar 
                                            $google_event_id = $this->$GoogleCalendarApi->CreateCalendarEvent($access_token, 'primary', $calendar_event, 0, $event_datetime, $user_timezone); 
                                            
                                            //echo json_encode([ 'event_id' => $event_id ]); 
                                            
                                        } catch(Exception $e) { 
                                            //header('Bad Request', true, 400); 
                                            //echo json_encode(array( 'error' => 1, 'message' => $e->getMessage() )); 
                                            $statusMsg = $e->getMessage(); 
                                        } 
                                    }else{ 
                                        $statusMsg = 'Failed to fetch access token!'; 
                                    } 
                                    $google_calendar_url = "https://www.google.com/calendar/render?cid=webcal://" . $_SERVER['SERVER_NAME'] . $url;
                                    $output .= " <a href=\"?code=" . $google_calendar_url . "\">Add to google calendar</a>";

                                }
                                $output .= "</div>";
                                $output .= "<div class=\"calinfo durationblock span-4 last\">" . $data['Duration'] . " Days</div>";
                                $output .= "</div>";
                            }
                        }
                        $thisWeek->modify("+1 day");
                    }
                    $output .= "</div>";
                    $thisWeek->modify("previous monday");
                }
                $output .= "</div>";
                $thisWeek->modify("next monday");
            }
            $output .= "</div>";
        }
        return $output;
    }
    // this function requires that Monday be the current day given for thisWeek
    private function createWeekView($cal, DateTime $thisWeek, $id)
    {
        // Get our week length and determine the width of the individual divs
        $weeklength = $this->calstart->findByUserID($id)->week_length;

        // Our day width
        $day_width = floor(18 / $weeklength);

        // day labels
        $output = "<div class=\"weekblock span-" . ($weeklength * $day_width) . " last\">";
        for ($i = 1; $i < ($weeklength + 1); ++$i) {
            $output .= "<div id=\"head\" class=\"span-$day_width";
            $output .= ($i == $weeklength) ? " last" : "";
            $output .= "\">" . $thisWeek->format('D (m/d)') . "</div>";
            $thisWeek->modify("+1 day");
        }
        $output .= "</div>";
        $thisWeek->modify("last monday");
        //xdebug_break()
        foreach ($cal as $grade => $subjects) {
            foreach ($subjects as $subject => $callist) {

                for ($i = 1; $i < ($weeklength + 1); ++$i) {
                    // We use floating divs, and the blueprint css layout
                    // our weekview block is currently 18 units wide. We'll
                    // need to come up with a soft way to specify this later
                    // instead of hardcoding the math here
                    $key = $thisWeek->format("Y-m-d");
                    $thisWeekNum = (int)$thisWeek->format("w");
                 
                    if (!empty($callist[$key])) {
                        foreach ($callist[$key] as $data) {
                            $tempEnd = new DateTime($data["cal_end"]);
                            $span = (((int) $tempEnd->format("w")) - $thisWeekNum + 1) * $day_width;
                            $prepend = ($thisWeekNum - 1) * $day_width;
                            $append = $day_width * $weeklength - $span - $prepend;
                            $output .= "<div class=\"itemblock prepend-$prepend span-$span append-$append last\" id=\"" . $data['id'] . "\">";
                            $output .= "<div class=\"calinfo\">";
                            $output .= "<h6>[ " . $data['Standard-Code'] . '] ' . $data['PO Title'] . "</h6>";
                            if (!empty($data['URL'])) {
                                $output .= "<a href=\"" . $data['URL'] . "\" target='_blank'>BT Link</a></br>" . $this->linked($data['Note']);
                            }
                            if ($this->editor) {
                                $output .= " <a href=\"?edit=" . $data['id'] . "\">Edit</a><br/>";
                            }
                            if ($this->code) {
                                $output .= "<a href=\"?add=google\">Add to Google Calendar</a>";
                            }
                            // $output .= " <a href=\"?add=google\">Add to google calendar</a>";
                            
                            if ($this->code) {
                                $access_token_sess = isset($_SESSION['google_access_token']) ? $_SESSION['google_access_token'] : ''; 
                                if(!empty($access_token_sess)){ 
                                    $access_token = $access_token_sess; 
                                }else{ 
                                    $data = $this->GoogleCalendarApi->GetAccessToken("664143952635-oipumr3ttgbmguk1cfb1j07h3emk7h6t.apps.googleusercontent.com", REDIRECT_URI, "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", $this->code);
                                    $access_token = $data['access_token']; 
                                    $_SESSION['google_access_token'] = $access_token; 
                                } 
                                if(!empty($access_token)){ 
                                    try { 
                                        // Get the user's calendar timezone 
                                        $user_timezone = $this->GoogleCalendarApi->GetUserCalendarTimezone($access_token); 
                                        // Create an event on the primary calendar 
                                        $calendar_event = array( 
                                            'description' => $this->format_ical_string($data['Standard-Code'] . ' ' . $data['PO Title']),
                                            'location' => 'US', 
                                            'summary' => 'grade' . $data['grade'] . ': ' . $data['subject'],
                                        );

                                        $event_datetime = array( 
                                            // 'event_date' => date('Y/m/d', strtotime($data["cal_end"])), 
                                            'event_date' => date('Y-m-d'), 
                                            'start_time' => date("h:i:s"), 
                                            'end_time' =>date("h:i:s", strtotime("+30 minutes"))
                                        ); 
                                        
                                        $google_event_id = $this->GoogleCalendarApi->CreateCalendarEvent($access_token, 'primary', $calendar_event, 0, $event_datetime, $user_timezone); 
                                        
                                        //echo json_encode([ 'event_id' => $event_id ]); 
                                        
                                    } catch(Exception $e) { 
                                        //header('Bad Request', true, 400); 
                                        //echo json_encode(array( 'error' => 1, 'message' => $e->getMessage() )); 
                                        $statusMsg = $e->getMessage(); 
                                    } 
                                }else{ 
                                    $statusMsg = 'Failed to fetch access token!'; 
                                } 

                            }
                            $output .= "</div>";
                            $output .= "</div>";
                        }
                    }
                    $thisWeek->modify("+1 day");
                }
                $thisWeek->modify("last monday");
            }
        }
        return $output;
    }
    // Calendar generator
    private function getWorkingCalendarDates($subject, $grade, $user, $id)
    {
        $cache_tags = array_merge((array)$subject, (array)$grade, (array)$user);
        $cache_id = md5(
            implode('|', (array)$subject) . ' - ' .
                implode('|', (array)$grade) . ' - ' .
                implode('|', (array)$user)
        );
        if (($cal_add = $this->cache->load($cache_id)) === false) {
            $this->log->info('Cal data not cached, generating');
            set_time_limit(0);
            $startdate = $this->calstart->findByUserID($id)->start_date;
            $weeklength = $this->calstart->findByUserID($id)->week_length;
            // ++$weeklength;
            $this->log->info('Generating using ' . $startdate);
            $blackout_temp = $this->blackouts->findAllByUserIDAfterDate($id, $startdate)->toArray();
            foreach ($blackout_temp as $blackout_data) {
                $blackout[$blackout_data['blackout_date']] = $blackout_data;
            }
            // load all masters and adjust them based on week length
            // Since 5 is our "standard" week length, we'll perform
            // adjustments if our week length is different.
            // The adjustment will be the duration and offset
            // divided by 5 and multiplied by the week length and
            // rounded up the the nearest whole
            // i,e, $x = ceil(($x/5)*week_length)
            $calendar_master = $this->masters->fetchByGradeAndSubjectAdjustedByUser($grade, $subject, $id)->toArray();
            foreach ($calendar_master as $cal_id => $cal_entry) {
                // print_r($cal_entry);
                // check for adjustments
                if (!is_null($cal_entry['adj_offset'])) $cal_entry['Offset'] = $cal_entry['adj_offset'];
                if (!is_null($cal_entry['adj_duration'])) $cal_entry['Duration'] = $cal_entry['adj_duration'];
                if (!is_null($cal_entry['adj_note'])) $cal_entry['Note'] = $cal_entry['adj_note'];
                if (!is_null($cal_entry['adj_title']) && $cal_entry['adj_title'] != '') $cal_entry['PO Title'] = $cal_entry['adj_title'];
                if (!is_null($cal_entry['adj_URL']) && $cal_entry['adj_URL'] != '') $cal_entry['URL'] = $cal_entry['adj_URL'];
                if ($cal_entry['show_link'] == '1') $cal_entry['URL'] = null;
                if ($weeklength < 5) {
                    $cal_entry['Offset'] = ceil(($cal_entry['Offset'] / 5) * $weeklength);
                    $cal_entry['Duration'] = ceil(($cal_entry['Duration'] / 5) * $weeklength);
                }
                $day_counter = 0;
                $start_date = strtotime($startdate);
                $end_date = $start_date;
                while ($day_counter < ($cal_entry['Offset'])) {
                    $end_day = date("N", $end_date);
                    $prev_sun = date('Y-m-d', $this->previousSunday($end_date));
                    if (@!is_array($blackout[$prev_sun])) {
                        if ($end_day <= $weeklength) {
                            $day_counter++;
                        }
                    }
                    $this->log->info(date('l Y-m-d', $end_date) . " Counter " . $day_counter . " Max " . $cal_entry['Offset']);
                    $end_date = $end_date + 86400;
                }
                $day_counter = 0;
                $dur_count = 0;
                $start_date = $end_date;
                while ($day_counter < $cal_entry['Duration']) {
                    $end_day = date("N", $end_date);
                    $prev_sun = date('Y-m-d', $this->previousSunday($end_date));
                    if (@!is_array($blackout[$prev_sun])) {
                        if ($end_day == 1) {
                            $start_date = $end_date;
                        }
                        if ($end_day <= $weeklength) {
                            $cal_entry['cal_end'] = date('Y-m-d', $end_date);
                            $day_counter++;
                            $cal_add[$cal_entry['grade']][$cal_entry['subject']][$cal_entry['Standard-Code']][date('Y-m-d', $start_date)] = $cal_entry;
                        }
                    }
                    $end_date = $end_date + 86400;
                }
            }
            $this->cache->save($cal_add, $cache_id, $cache_tags);
        }
        return $cal_add;
    }

    private function geniCal($calendars, $mode = 'ical')
    {
        $district = $this->cald;
        foreach ($calendars as $grade => $subjects) {
            //print_r("============================\nSUBJECTS\n============================\n");
            //print_r($subjects);
            //print_r("============================\nGRADE:".$grade."\n============================\n");
            foreach ($subjects as $subject => $objectives) {
                //print_r("============================\nSUBJECT:".$subject."\n============================\n");
                $calContent = '';
                // iCal header pieces
                $calContent .= "BEGIN:VCALENDAR\n";
                $calContent .= "VERSION:2.0\n";
                $calContent .= "PRODID:-//Up9//CALGEN//EN\n";
                $calContent .= "NAME:$district $subject $grade\n";
                $calContent .= "X-WR-CALNAME:$district $subject $grade\n";
                $calContent .= "DESCRIPTION:$district $subject $grade\n";
                $calContent .= "X-WR-TIMEZONE: " . date_default_timezone_get() . "\n";
                $calContent .= "X-WR-RELCALID:" . $this->uuid() . "\n";
                $calContent .= "CALSCALE:GREGORIAN\n";
                $calContent .= "METHOD:PUBLISH\n";
                foreach ($objectives as $objective => $dates) {
                    foreach ($dates as $date => $entry) {
                        /* we will not be tracking SEQUENCE as part of this project since
                           calendars are generated yearly.
                           Also, we create an event for each day of the duration to allow
                           for wrapping around blackouts and weekends
                        */
                        $date_start_formatted = date('Ymd', strtotime($date));
                        // we have to add a day here since the day end, is not included
                        // in the duration
                        $date_end_formatted = date('Ymd', strtotime($entry['cal_end']) + 86400);
                        // we change the format of URL's based on the subscription
                        $calContent .= "BEGIN:VEVENT\n";
                        $calContent .= "SEQUENCE:1\n";
                        $calContent .= "TRANSP:TRANSPARENT\n";
                        $calContent .= "UID:" . $this->uuid() . "\n";
                        $calContent .= "DTSTART;VALUE=DATE:$date_start_formatted\n";
                        $calContent .= "SUMMARY:" . $this->format_ical_string($entry['Standard-Code'] . " " . $entry['PO Title']) . "\n";
                        if ($mode == 'google') {
                            $calContent .= "DESCRIPTION:" . $entry['URL'] . "</br>" . $this->format_ical_string($entry['Note']) . "\n";
                        } elseif ($mode == 'outlook') {
                            $calContent .= "DESCRIPTION:" . $this->format_ical_string($entry['URL'] . " " . $entry['Note']) . "\n";
                        } else {
                            // default ical setup
                            $calContent .= "URL;VALUE=URI:" . $entry['URL'] . "\n";
                            $calContent .= "DESCRIPTION:" . $this->format_ical_string($entry['Note']) . "\n";
                        }
                        $calContent .= "DTEND;VALUE=DATE:$date_end_formatted\n";
                        $calContent .= "END:VEVENT\n";
                    }
                }
                $calContent .= "END:VCALENDAR\n";
            }
            /*
            print_r("+++++++++++++++++++++++++++++++++++++++++++++++++++++++++\n");
            print_r("+++++++++++++++++++++++++++++++++++++++++++++++++++++++++\n");
            print_r($calContent);
            print_r("+++++++++++++++++++++++++++++++++++++++++++++++++++++++++\n");
            print_r("+++++++++++++++++++++++++++++++++++++++++++++++++++++++++\n");
            //*/
        }
        return $calContent;
    }

    private function uuid()
    {
        $pr_bits = false;
        if (is_a($this, 'uuid')) {
            if (is_resource($this->urand)) {
                $pr_bits .= @fread($this->urand, 16);
            }
        }
        if (!$pr_bits) {
            // If /dev/urandom isn't available (eg: in non-unix systems), use mt_rand().
            $pr_bits = "";
            for ($cnt = 0; $cnt < 16; $cnt++) {
                $pr_bits .= chr(mt_rand(0, 255));
            }
        }
        $time_low = bin2hex(substr($pr_bits, 0, 4));
        $time_mid = bin2hex(substr($pr_bits, 4, 2));
        $time_hi_and_version = bin2hex(substr($pr_bits, 6, 2));
        $clock_seq_hi_and_reserved = bin2hex(substr($pr_bits, 8, 2));
        $node = bin2hex(substr($pr_bits, 10, 6));

        /**
         * Set the four most significant bits (bits 12 through 15) of the
         * time_hi_and_version field to the 4-bit version number from
         * Section 4.1.3.
         * @see http://tools.ietf.org/html/rfc4122#section-4.1.3
         */
        $time_hi_and_version = hexdec($time_hi_and_version);
        $time_hi_and_version = $time_hi_and_version >> 4;
        $time_hi_and_version = $time_hi_and_version | 0x4000;

        /**
         * Set the two most significant bits (bits 6 and 7) of the
         * clock_seq_hi_and_reserved to zero and one, respectively.
         */
        $clock_seq_hi_and_reserved = hexdec($clock_seq_hi_and_reserved);
        $clock_seq_hi_and_reserved = $clock_seq_hi_and_reserved >> 2;
        $clock_seq_hi_and_reserved = $clock_seq_hi_and_reserved | 0x8000;
        return sprintf('%08s-%04s-%04x-%04x-%012s', $time_low, $time_mid, $time_hi_and_version, $clock_seq_hi_and_reserved, $node);
    }

    private function getWorkingDays($startDate, $endDate)
    {
        //The total number of days between the two dates.
        $days = ($endDate - $startDate) / 86400 + 1;
        $no_full_weeks = floor($days / 7);
        $no_remaining_days = fmod($days, 7);
        //It will return 1 if it's Monday,.. ,7 for Sunday
        $the_first_day_of_week = date("N", $startDate);
        $the_last_day_of_week = date("N", $endDate);
        //In the first case the whole interval is within a week
        // in the second case the interval falls in two weeks.
        if ($the_first_day_of_week <= $the_last_day_of_week) {
            if ($the_first_day_of_week <= 6 && 6 <= $the_last_day_of_week) $no_remaining_days--;
            if ($the_first_day_of_week <= 7 && 7 <= $the_last_day_of_week) $no_remaining_days--;
        } else {
            if ($the_first_day_of_week <= 6) {
                //In the case when the interval falls in two weeks, there will be a Sunday for sure
                $no_remaining_days--;
            }
        }
        // fix for remainder of days
        $workingDays = $no_full_weeks * 5;
        if ($no_remaining_days > 0) {
            $workingDays += $no_remaining_days;
        }
        return $workingDays;
    }

    private function previousSunday($stamp)
    {
        $checkday = date("l", $stamp);
        while ($checkday != "Sunday") {
            $stamp = $stamp - 86400;
            $checkday = date("l", $stamp);
        }
        return $stamp;
    }

    private function nextSunday($stamp)
    {
        $checkday = date("l", $stamp);
        while ($checkday != "Sunday") {
            $stamp = $stamp + 86400;
            $checkday = date("l", $stamp);
        }
        return $stamp;
    }

    private function getColor($string)
    {
        // this is not fully functional :/ TO be the basis for
        // coloring differing subjects/etc in timespan views
        $base = 200;
        $checksum = md5($subject . $grade);
        $c = array(
            "R" => hexdec(substr($checksum, 0, 2)),
            "G" => hexdec(substr($checksum, 2, 2)),
            "B" => hexdec(substr($checksum, 4, 2))
        );
        $c['R'] = $c['R'] < $base ? $base : $c['R'];
        $c['G'] = $c['G'] < $base ? $base : $c['G'];
        $c['B'] = $c['B'] < $base ? $base : $c['B'];
        return $c;
        echo "<div style='background-color:rgb($c[R],$c[G],$c[B]); float:left;'>$checksum	</div>";
    }

    private function clean_url()
    {
        // return;
        $test = $this->_request->getParams();
        // Need to turn s and g into arrays for consistency
        $gsdata = $this->masters->fetchTitles()->toArray();
        $raw = array_merge(array(
            'controller' => 'cal',
            'action' => 'index',
            'd' => $this->_request->getParam('d'),
            'g' => $this->_request->getParam('g'),
            's' => $this->_request->getParam('s'),
            'm' => $this->_request->getParam('m'),
            't' => $this->_request->getParam('t'),
            'v' => $this->_request->getParam('v'),
            'cd' => $this->_request->getParam('cd')
        ), $_GET);

        // so many pieces are required to get the right information here...
        $test_time = empty($raw['d']) ? $this->user->name : $raw['d'];
        $test_time = $this->users->findByUsername($test_time)->id;
        $test_time = strtotime($this->calstart->findByUserID($test_time)->start_date);
        $test_time = $test_time < time() ? time() : $test_time;
        $this->log->info('time test:' . $test_time);

        $parts = array(
            'controller' => 'cal',
            'action' => 'index',
            'd' => empty($raw['d']) ? $this->user->name : $raw['d'],
            'g' => empty($raw['g']) ? '1' : $raw['g'],
            's' => empty($raw['s']) ? 'Math' : $raw['s'],
            //'g' => empty($raw['g']) ? $gsdata[0]['grade'] : $raw['g'],
            //'s' => empty($raw['s']) ? $gsdata[0]['subject'] : $raw['s'],
            't' => empty($raw['t']) ? 'week' : $raw['t'],
            'v' => empty($raw['v']) ? 'time' : $raw['v'],
            'm' => $raw['m'],
            'cd' => empty($raw['cd']) ? $test_time : $raw['cd']
        );

        if (!empty($raw['edit'])) $parts['edit'] = $raw['edit'];
        if (!empty($raw['code'])) $parts['code'] = urlencode($raw['code']);
        $url = $this->view->url($parts);
        $this->_redirect($url);
    }

    private function ical_url($m = 'ical')
    {
        if ($m == 'google') {
            $parts = array(
                'controller' => 'cal',
                'action' => 'index',
                'd' => $this->cald, // date
                'g' => $this->calg, // grade
                's' => $this->cals, // subject(s)
                'm' => $m
            ); // mode (ical/google)
            $url = $this->view->url($parts, null, true, true);
        } elseif ($m == 'outlook') {
            $parts = array(
                'controller' => 'cal',
                'action' => 'index',
                'd' => $this->cald, // date
                'g' => $this->calg, // grade
                's' => $this->cals, // subject(s)
                'm' => 'outlook'
            ); // mode (ical/google)
            $url = $this->view->url($parts, null, true, true);
            return "webcal://" . $_SERVER['SERVER_NAME'] . $url;
        } else {
            $parts = array(
                'controller' => 'cal',
                'action' => 'index',
                'd' => $this->cald, // date
                'g' => $this->calg, // grade
                's' => $this->cals, // subject(s)
                'm' => 'ical'
            ); // mode (ical/google)
            $url = $this->view->url($parts, null, true, true);
            return "webcal://" . $_SERVER['SERVER_NAME'] . $url;
        }
    }


    private function get_time()
    {
        if (empty($this->caldate)) {
            $this->caldate = time("now");
        }
    }

    private function linked($txt)
    {
        return preg_replace('@(https?://([-\w\.]+[-\w])+(:\d+)?(/([\w/_\.#-]*(\?\S+)?[^\.\s])?)?)@', '<a href="$1" target="_blank">$1</a>', $txt);
    }
}
