<?php namespace Allison;

class Recording{

    const SHAREPATH = '/mnt/voicent/recordings/';
    const MSPATH = '/scan/fPImages/Reports/recordings/';

    public $recordings = array();

    protected $year;
    protected $month;
    protected $day;
    protected $phoneNumber;
    protected $targetDir;

    public function __construct($phoneNumber,$year,$month,$day){
        $this->phoneNumber = $phoneNumber;
        $this->_parseDateInputs($year,$month,$day)->_buildDirPath()->_findRecordings();
    }
    protected function _parseDateInputs($year,$month,$day){
        $this->year = date('Y',strtotime($year));
        if(!is_numeric($month)){
            $this->month = date('m',strtotime($month));
        }else{
            $this->month = $month;
        }
        if(is_int($day) && $day <= 9){
            $this->day = '0' . $day;
        }else{
            $this->day = $day;
        }
        return $this;
    }
    protected function _buildDirPath(){
        $this->targetDir = self::SHAREPATH . "y" . $this->year . "/m" . $this->month . "/d" . $this->day;
        return $this;
    }
    protected function _findRecordings(){
        $results = scandir($this->targetDir);
        $pattern = "/" . $this->phoneNumber . "/";
        foreach($results as $result){
            if(preg_match($pattern,$result)){
                $this->recordings[] = $this->targetDir . "/" . $result;
            }
        }
        return $this;
    }
    public static function msBackup($gbl,$phone,$sourceFile){
        $destination = self::MSPATH . $gbl . "_" . $phone . ".mp3";
        if(!copy($sourceFile,$destination)){
            $error = error_get_last();
            throw new \Exception($error['message']);
        }
        return $destination;
    }
}