<?php
class My_Db_Row extends Zend_Db_Table_Row_Abstract
{

    /**
     * Upon insert/update, self::_refresh() is automatically called, generating an error if refresh returns no data.
     *
     */
    public function _refresh()
    {
        try {
            parent::_refresh();
        }
        catch(Exception $e) {
            // just ignore!
            // this is pretty lame, but we've been doing good at keeping track of our id's
            // this is generating problems when zend tries to create it for us
            // since zend does not have a disable feature, we simply catch and drop the error
            
        }
    }
}
