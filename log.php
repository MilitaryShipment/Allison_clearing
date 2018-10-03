<?php namespace Allison;

//16:01:58.199 T-01700: Running Inside VMWare -- 5/18/2018

class LogReader{

    const LOGPATH = '/mnt/voicent/logs/line1_output.log';
    const PATTERNBASE = "Making\sCall\s...\s1";
    const RESPATTERN = "/DTMF\s=\s([0-9])/";
    const EOCPATTERN = "/Setting\scall\sstate\s=\s0/";
    const CONPATTERN = "/Call\s\([0-9]{1,10}\)\sconnected/";
    const MACHINEPATTERN = "/Call\smachine\sanswered\sdetected/";
    const HUMANPATTERN = "/Call\shuman\sanswered\sdetected/";

    protected $log;

    public function __construct()
    {
        $this->log = file(self::LOGPATH);
    }
    public static function callMade($phoneNumber){
        $pattern = "/" . self::PATTERNBASE . $phoneNumber . "/";
        $log = file(self::LOGPATH);
        foreach($log as $line){
            if(preg_match($pattern,$line)){
                return true;
            }
        }
        return false;
    }
    public static function lastCallResults($phoneNumber){
        $callPattern = "/" . self::PATTERNBASE . $phoneNumber . "/";
        $startLine = 0;
        $endLine = 0;
        $flag = 0;
        $results = array();
        $log = file(self::LOGPATH);
        if(!self::callMade($phoneNumber)){
            throw new \Exception('Unable to gather results. No Call made');
        }
        $i = count($log);
        while($i--){
            if(preg_match($callPattern,$log[$i])){
                $startLine = $i;
                $flag = $i;
            }
            if(preg_match(self::EOCPATTERN,$log[$i]) && $flag != $i){
                $endLine = $i;
            }
            if($startLine > 0 && $endLine > 0){
                break;
            }
        }
        for($i = $startLine;$i < $endLine; $i++){
            if(preg_match(self::RESPATTERN,$log[$i],$matches)){
                $results[] = $matches[1];
            }
        }
        if(count($results)){
            return $results;
        }
        return false;
    }
    public static function wasAnsweredByHuman($phoneNumber){
        $startLine = 0;
        $pattern = "/" . self::PATTERNBASE . $phoneNumber . "/";
        $log = file(self::LOGPATH);
        for($i = 0; $i < count($log); $i++){
            if(preg_match($pattern,$log[$i])){
                $startLine = $i;
                break;
            }
        }
        if($startLine == 0){
            throw new \Exception('No Start Line. Cannot verify that call was made');
        }
        for($i = $startLine + 1; $i < count($log);$i++){
            if(preg_match("/" . self::PATTERNBASE . "/",$log[$i])){
                //todo you have gone to far and are accessing a new call
                break;
            }
            if(preg_match(self::HUMANPATTERN,$log[$i])){
                return true;
            }
        }
        return false;
    }
    public static function isConnected(){
        $log = file(self::LOGPATH);
        $i = count($log);
        while($i--){}
    }
}