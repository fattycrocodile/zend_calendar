<?php

/**
 *
 * @category   App
 * @package    Zend_View
 * @subpackage Helper
 * @version
 *
 * This class was originally used to extend Zend_Date, it has been reworked to
 * use DateTime, a built in, for speed improvements, however, further cleanup
 * should by considered before futher use.
 */

class Default_View_Helper_CalendarViewHelper extends Zend_View_Helper_Abstract
{
    protected $_locale;
    protected $_now;
    protected $_date;
    protected $_monthNames;
    protected $_dayNames;
    protected $_validDates;
    protected $_numMonthDays;
    protected $_nextMonth;
    protected $_prevMonth;
    protected $_firstDayOfWeek;
    protected $_numWeeks;

    /**
     * @param String $date (MMMM yyyy)
     */
    public function calendarViewHelper($date = null, $locale = "en_US")
    {
        $this->setDate($date, $locale);
    }

    /**
     *
     * @param Zend_Date $date
     */
    protected function initDateParams($date)
    {
        $this->_monthNames = Zend_Locale::getTranslationList('Month', $this->_locale); //locale month list
        $this->_dayNames = Zend_Locale::getTranslationList('Day', $this->_locale); //locale day list
        $this->setMonthsInRange(); //set locale valid dates
        $this->_numMonthDays = cal_days_in_month(CAL_GREGORIAN, $date->format("m"), $date->format("Y")); //num days in locale month
        $this->setNextMonth($date); //set the next month
        $this->setPrevMonth($date); //set the previous month
        $this->setFirstDayOfWeek($date); //first day of the curr month
        $this->_numWeeks = ceil(($this->getFirstDayOfWeek() + $this->getNumMonthDays()) / 7); //num weeks in curr month

    }

    /**
     *
     * @param int $startOffset
     * @param int $endOffset
     */
    public function setMonthsInRange($startOffset = - 1, $endOffset = 12, $userDate = null)
    {
        $this->_validDates = array();
        if (!$userDate) {
            $startDate = clone $this->_now;
        } else {
            $tempDate = new DateTime;
            $startDate = $tempDate->setTimestamp($userDate);
            unset($userDate);
        }
        $startMonth = $startDate->sub(new DateInterval("P" . abs($startOffset) . "M"))->modify('first day of this month');
        $startNum = intval($startMonth->format("m"));
        $this->_validDates[$startMonth->format("M y") ] = $startMonth->getTimestamp();
        for ($i = $startNum; $i <= ($startNum + $endOffset); $i++) {
            $str = $startMonth->modify("+1 month")->format("M y");
            $this->_validDates[$str] = $startMonth->getTimestamp();
        }
        unset($startDate, $startMonth, $startNum);
    }

    /**
     *
     * @param Zend_Date $date
     */
    protected function setNextMonth(DateTime $date)
    {
        $tempDate = clone $date;
        $this->_nextMonth = $tempDate->modify("+1 month");
        unset($tempDate);
    }

    /**
     *
     * @param Zend_Date $date
     */
    protected function setPrevMonth(DateTime $date)
    {
        $tempDate = clone $date;
        $this->_prevMonth = $tempDate->modify("-1 month");
        unset($tempDate);
    }

    protected function setFirstDayOfWeek(DateTime $date)
    {
        $tempDate = clone $date;
        $month = $tempDate->format("F");
        $tempDate->modify("first day of " . $month);
        $this->_firstDayOfWeek = $tempDate->format("w");
        unset($tempDate);
    }

    /**
     *
     * @param String $locale
     */
    public function setLocale($locale)
    {
        if (Zend_Locale::isLocale($locale)) {
            $this->_locale = new Zend_Locale($locale);
        } else { //string
            $this->_locale = new Zend_Locale("en_US"); //default locale

        }
        //update the date params
        $this->initDateParams($this->_date);
    }

    /**
     *
     * @param Array $arr
     * @return String
     */
    public function getCalendarHeaderHtml($arr = NULL)
    {
        //defaults:
        $showPrevMonthLink = false;
        $showNextMonthLink = false;
        $selectBox = false;
        $selectBoxName = "selectMonth";
        $selectBoxFormName = "selectMonthForm";
        //params:
        if (is_array($arr)) {
            extract($arr);
        }
        //prev/next link in header display
        $pLink = $nLink = "";
        $pLinkClass = "id=\"prevMonth\" style=\"visibility: visible;\"";
        $nLinkClass = "id=\"nextMonth\" style=\"visibility: visible;\"";

        if ($showPrevMonthLink) {
            $t = $this->getPrevMonthAsTimestamp();
            $s = $this->getPrevMonthAsDateString();
            if (!array_key_exists($s, $this->_validDates)) //check if the prev month in list of valid dates
            $pLinkClass = "id=\"prevMonth\" style=\"visibility: hidden;\"";
            $pLink = "<a $pLinkClass href=\"?$selectBoxName=" . urlencode($t) . "\">&lt;&nbsp;$s</a>\n";
        }
        if ($showNextMonthLink) {
            $t = $this->getNextMonthAsTimestamp();
            $s = $this->getNextMonthAsDateString();
            if (!array_key_exists($s, $this->_validDates)) //check if the next month in list of valid dates
            $nLinkClass = "id=\"nextMonth\" style=\"visibility: hidden;\"";
            $nLink = "<a $nLinkClass href=\"?$selectBoxName=" . urlencode($t) . "\">$s&nbsp;&gt;</a>\n";
        }
        //month in header display
        $headDate = $this->getDateAsString();
        if ($selectBox) {
            $headDate = "\n<form name=\"$selectBoxFormName\" method=\"get\">\n";
            $headDate.= $this->getValidDatesSelectBox(array('selectedDateStr' => $this->getDateAsString(),
                'selectBoxName' => $selectBoxName));
            $headDate.= "</form>\n";
        }
        return "<div id=\"calendar_header\">$pLink&nbsp;$headDate&nbsp;$nLink</div>\n";
    }

    /**
     * @return String
     */
    public function getCalendarBodyHtml($arr = NULL)
    {
        //defaults:
        $showToday = false;
        $tableClass = "calendar";
        $showSelect = false;
        //params:
        if (is_array($arr)) {
            extract($arr);
        }

        $html = "<table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" class=\"$tableClass\">\n";
        $html.= "<tr class=\"weekdays\">\n";
        //days of the week display
        foreach ($this->_dayNames as $dayShort => $dayFull) {
            $html.= "<td>$dayShort</td>\n";
        }
        $html.= "</tr>\n";
        //day numbers display
        $today = $this->_now->format("d");
        $nowDate = $this->_now->getTimestamp();
        $focusDate = $this->_date->getTimestamp();
        $focusDay = $this->_date->format("d");
        $focusMonth = $this->_date->format("m");
        $focusYear = $this->_date->format("Y");
        $calDayNum = 1;
        $weekComp = clone $this->_date;
        $weekNumSet = $weekComp->format("W");
        //day numbers display loop
        for ($i = 0; $i < $this->getNumWeeks(); $i++) {
            $html.= "<tr class=\"days\">";
            for ($j = 0; $j < 7; $j++) {
                $cellNum = ($i * 7 + $j);
                $class = "";
                if ($showToday && $nowDate == $focusDate && $today == $calDayNum && $cellNum >= $this->getFirstDayOfWeek()) {
                    $class = "class = \"today\"";
                }
                if ($showSelect && $focusDay == $calDayNum) {
                    $class = "class = \"day_selected\"";
                } else {
                    $weekComp->setDate($focusYear, $focusMonth, $calDayNum);
                    $weekNumAct = $weekComp->format("W");

                    if ($weekNumAct == $weekNumSet) {
                        $class = "class=\"week_selected\"";
                    }
                }
                $html.= "<td $class>";
                if ($cellNum >= $this->getFirstDayOfWeek() && $cellNum < ($this->getNumMonthDays() + $this->getFirstDayOfWeek())) {
                    $date = $weekComp->setDate($focusYear, $focusMonth, $calDayNum)->getTimestamp();
                    // $date = Zend_Locale_Format::toNumber($calDayNum, array('locale' => $this->_locale));
                    $html.= "<a href=\"?cd=$date\">$calDayNum</a>";
                    $calDayNum++;
                }
                $html.= "</td>\n";
            }
            $html.= "</tr>\n";
        }
        unset($weekComp);
        $html.= "</table>\n";
        return $html;
    }

    /**
     * @return String
     */
    public function getCalendarHtml($arr = NULL)
    {
        //defaults:
        $showToday = false;
        $showSelect = false;
        $selectDate = 0;
        $showPrevMonthLink = false;
        $showNextMonthLink = false;
        $tableClass = "calendar";
        $selectBox = false;
        $selectBoxName = "selectMonth";
        $selectBoxFormName = "selectMonthForm";
        //params:
        if (is_array($arr)) {
            extract($arr);
        }

        $html = "<div id=\"calendar_wrapper\">\n";
        $html.= $this->getCalendarHeaderHtml(array('showPrevMonthLink' => $showPrevMonthLink,
            'showNextMonthLink' => $showNextMonthLink,
            'selectBox' => $selectBox,
            'selectBoxName' => $selectBoxName,
            'selectBoxFormName' => $selectBoxFormName)); //returns a div
        $html.= "<div id=\"calendar_body\">\n";
        $html.= $this->getCalendarBodyHtml(array('showToday' => $showToday,
            'showSelect' => $showSelect,
            'selectDate' => $selectDate,
            'tableClass' => $tableClass)); //returns a table
        $html.= "</div>\n</div>\n";
        return $html;
    }

    /**
     * @return String
     */
    public function getValidDatesSelectBox($arr = NULL)
    {
        //defaults:
        $selectedDateStr = false;
        $selectBoxName = "";
        //params:
        if (is_array($arr)) {
            extract($arr);
        }

        $html = "<select name=\"$selectBoxName\" onchange=\"submit();\">\n";
        foreach ($this->_validDates as $option => $value) {
            $sel = "";
            if ($selectedDateStr && $selectedDateStr == $option) {
                $sel = "selected";
            }
            $html.= "<option value=\"$value\" $sel>$option</option>\n";
        }
        $html.= "</select>\n";
        return $html;
    }

    /**
     * @return Array
     */
    public function getValidDates()
    {
        return $this->_validDates;
    }

    /**
     * @return Array
     */
    public function getMonthNames()
    {
        return $this->_monthNames;
    }

    /**
     * @return Array
     */
    public function getDayNames()
    {
        return $this->_dayNames;
    }

    /**
     * @return Zend_Locale
     */
    public function getLocale()
    {
        return $this->_locale;
    }

    /**
     * @return String
     */
    public function getLocaleAsString()
    {
        return $this->_locale->toString();
    }

    /**
     * @return int
     */
    public function getFirstDayOfWeek()
    {
        return $this->_firstDayOfWeek;
    }

    /**
     * @return String
     */
    public function getDateAsString()
    {
        return $this->_date->format("M y");
    }

    /**
     *
     * @param String $date
     * @param String $locale
     */
    public function setDate($date = null, $locale = "en_US")
    {
        //locale
        if (Zend_Locale::isLocale($locale)) {
            $this->_now = new DateTime();
            $this->_locale = new Zend_Locale($locale);
        } else {
            $this->_now = new DateTime();
            $this->_locale = new Zend_Locale("en_US"); //default locale

        }
        //date
        try {
            $this->_date = new DateTime();
            $this->_date->setTimestamp($date);
        }
        catch(Zend_Date_Exception $e) {
            $this->_date = new DateTime(null);
        }
        //date params
        $this->initDateParams($this->_date);
    }

    /**
     * @return Zend_Date
     */
    public function getDate()
    {
        return $this->_date;
    }

    /**
     * @return int
     */
    public function getNumMonthDays()
    {
        return $this->_numMonthDays;
    }

    /**
     * @return String
     */
    public function getMonthName()
    {
        return $this->_date->format("F");
    }

    /**
     * @return String
     */
    public function getMonthShortName()
    {
        return $this->_date->format("M");
    }

    /**
     * @return int
     */
    public function getMonthNum()
    {
        return $this->_date->format("n");
    }

    /**
     * @return int
     */
    public function getYear()
    {
        return $this->_date->format("y");
    }

    /**
     * @return String
     */
    public function getNextMonthName()
    {
        return $this->_nextMonth->format("F");
    }

    /**
     * @return int
     */
    public function getNextMonthNum()
    {
        return $this->_nextMonth->format("n");
    }

    /**
     * @return int
     */
    public function getNextMonthYear()
    {
        return $this->_nextMonth->format("y");
    }

    /**
     * @return String "MMMM yyyy"
     */
    public function getNextMonthAsDateString()
    {
        return $this->_nextMonth->format("M y");
    }

    /**
     * @return int
     */
    public function getNextMonthAsTimestamp()
    {
        return $this->_nextMonth->getTimestamp();
    }

    /**
     * @return String
     */
    public function getPrevMonthName()
    {
        return $this->_prevMonth->format("F");
    }

    /**
     * @return int
     */
    public function getPrevMonthNum()
    {
        return $this->_prevMonth->format("n");
    }

    /**
     * @return int
     */
    public function getPrevMonthYear()
    {
        return $this->_prevMonth->format("y");
    }

    /**
     * @return String
     */
    public function getPrevMonthAsDateString()
    {
        return $this->_prevMonth->format("M y");
    }

    /**
     * @return int
     */
    public function getPrevMonthAsTimestamp()
    {
        return $this->_prevMonth->getTimestamp();
    }

    /**
     * @return int
     */
    public function getNumWeeks()
    {
        return $this->_numWeeks;
    }
}
?>
