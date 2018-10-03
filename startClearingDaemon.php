<?php namespace Allison;

require_once __DIR__ . '/clearingDaemon.php';
require_once __DIR__ . '/recordingDaemon.php';


while(true){
    try{
        echo date('m/d/Y H:i:s') . " Trying to make clearing calls...\n";
        $d = new ClearingDaemon();
    }catch(\Exception $e){
        echo $e->getMessage() . "\n";
    }
    sleep(300);
    try{
        echo date('m/d/Y H:i:s') . " Checking for recordings...\n";
        $r = new RecordingDaemon();
    }catch(\Exception $e){
        echo $e->getMessage() . "\n";
    }
}