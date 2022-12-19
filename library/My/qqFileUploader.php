<?php
class My_qqFileUploader {
    private $allowedExtensions = array();
    private $sizeLimit = 10485760;
    private $file;

    function __construct(array $allowedExtensions = array(), $sizeLimit = 10485760){
        $allowedExtensions = array_map("strtolower", $allowedExtensions);

        $this->allowedExtensions = $allowedExtensions;
        $this->sizeLimit = $sizeLimit;

        $this->checkServerSettings();

        if (isset($_GET['qqfile'])) {
            $this->file = new My_qqUploadedFileXhr();
        } elseif (isset($_FILES['qqfile'])) {
            $this->file = new My_qqUploadedFileForm();
        } else {
            $this->file = false;
        }
    }

    private function checkServerSettings(){
        $postSize = $this->toBytes(ini_get('post_max_size'));
        $uploadSize = $this->toBytes(ini_get('upload_max_filesize'));

        if ($postSize < $this->sizeLimit || $uploadSize < $this->sizeLimit){
            $size = max(1, $this->sizeLimit / 1024 / 1024) . 'M';
            $this->log->info("error: increase post_max_size and upload_max_filesize to $size'");
        }
    }

    private function toBytes($str){
        $val = trim($str);
        $last = strtolower($str[strlen($str)-1]);
        switch($last) {
            case 'g': $val *= 1024;
            case 'm': $val *= 1024;
            case 'k': $val *= 1024;
        }
        return $val;
    }

    /**
     * Returns array('success'=>true) or array('error'=>'error message')
     */
    function handleUpload($uploadDirectory, $replaceOldFile = FALSE){
        if (!is_writable($uploadDirectory)){
            return array('error' => "Server error. Upload directory isn't writable.");
        }

        if (!$this->file){
            return array('error' => 'No files were uploaded.');
        }

        $size = $this->file->getSize();

        if ($size == 0) {
            return array('error' => 'File is empty');
        }

        if ($size > $this->sizeLimit) {
            return array('error' => 'File is too large');
        }

        $pathinfo = pathinfo($this->file->getName());
        $filename = $pathinfo['filename'];
        //$filename = md5(uniqid());
        $ext = $pathinfo['extension'];

        if($this->allowedExtensions && !in_array(strtolower($ext), $this->allowedExtensions)){
            $these = implode(', ', $this->allowedExtensions);
            return array('error' => 'File has an invalid extension, it should be one of '. $these . '.');
        }

        if(!$replaceOldFile){
            /// don't overwrite previous files that were uploaded
            while (file_exists($uploadDirectory . $filename . '.' . $ext)) {
                $filename .= rand(10, 99);
            }
        }

        if ($this->file->save($uploadDirectory . $filename . '.' . $ext)){
			$this->fileProcess($uploadDirectory . $filename . '.' . $ext);
            return array('success'=>true);
        } else {
            return array('error'=> 'Could not save uploaded file.' .
                'The upload was cancelled, or server error encountered');
        }

    }

    public function setLogger($log) {
        $this->log = $log;
    }

    public function setMasters($master) {
        $this->masters = $master;
    }

	private function fileProcess($location) {
        $this->log->info("File location: " . $location);
        $this->log->info("File name: " . basename($location));
        list($subject, $grade, $extension) = explode('.',basename($location));
        $this->log->info('Subject: '.$subject.' Grade: '.$grade.' Extension: '.$extension);
        $csv = new My_CSV_Reader();
        $csv->setLogger($this->log);
        $processed = $csv->parseFile($location);
        $this->log->info('Info converted to array: ' . (!empty($processed)));
        $this->masters->delete( array( 'grade = ?' => $grade, 'subject = ?' => $subject));

        foreach($processed as $row) {
            if(is_array($row)) {
                $data['Standard-Code'] = $row['Standard-Code'];
                $data['Offset'] = $row['Offset'];
                $data['Duration'] = $row['Duration'];
                $data['PO Title'] = $row['PO Title'];
                $data['URL'] = $row['URL'];
                $data['Note'] = $row['Note'];
                if(!$this->array_empty($data)) {
                    $data['grade'] = $grade;
                    $data['subject'] = $subject;
                    $this->masters->insert($data);
                }
                unset($data);
            }
        }
        unlink($location);
    }

    private function array_empty($mixed) {
        if (is_array($mixed)) {
            foreach ($mixed as $value) {
                if (!$this->array_empty($value)) {
                    return false;
                }
            }
        }
        elseif (!empty($mixed)) {
            return false;
        }
        return true;
    }

}

