<?php namespace Allison;

require_once __DIR__ . '/../shipment/shipment.php';
require_once __DIR__ . '/../shipment/notification.php';
require_once __DIR__ . '/../shipment/user.php';
require_once __DIR__ . '/../messenger.php';
require_once __DIR__ . '/../movestar/shipment.php';
require_once __DIR__ . '/../movestar/allisonCall.php';
require_once __DIR__ . '/log.php';
require_once __DIR__ . '/recording.php';


class RecordingDaemon{

    const MSGNAME = 'allison_recording';
    const MSGTYPE = 'allison_email';
    const TARGETMSG = 'allison_clearing';
    const DEVMAIL = 'j.watson@allamericanmoving.com';
    const CC = 'CS-Supervisor@allamericanmoving.com';
    const CUSTSERV = 'Customerservice@allamericanmoving.com';
    const FROM = 'AllisonRecordings@militaryshipment.com';
    const REPLYTO = 'webadmin@allamericanmoving.com';
    const SUBJECT = 'ATTN: Clearing Call Results';
    const BODY = 'A clearing call was initiated for shipment: ';

    protected $questions = array(
        "Press 1 if you would like direct delivery; press 2 if you would like your goods placed into storage: ",
        "Press 1 if your address is correct; press 2 to record: ",
        "Press 1 if your address is correct; press 2 to record again: "
    );

    protected $shipments = array();
    protected $msUpdates;

    public function __construct(){
        $notifications = \Notification::sentToday(self::TARGETMSG);
        foreach($notifications as $notification){
            if(LogReader::callMade($notification->message_to) && $notification->message_code == 1 && !$this->_hasBeenSent($notification->gbl_dps,$notification->message_to)){
                $this->msUpdates = new \MoveStar\AllisonCall();
                $this->msUpdates->gbl_dps = $notification->gbl_dps;
                $this->msUpdates->number_called = $notification->message_to;
                echo "Call was initiated to " . $notification->message_to . "\n";
                $body = self::BODY . " " . $notification->gbl_dps . " (" . $notification->message_to . ")<br>";
                if(LogReader::wasAnsweredByHuman($notification->message_to)){
                    $this->msUpdates->human_response = 1;
                    $body .= "The call was answered by a human and the results can be found below: <br><br>";
                    $response = LogReader::lastCallResults($notification->message_to);
                    if(!$response){
                        $body .= "The human disconnected their call without responding to any of my questions<br>";
                    }else{
                        $body .= $this->_interpretResponses($response);
                    }
                }else{
                    $this->msUpdates->machine_response = 1;
                    $body .= "The call was picked up by an answering service and the member was advised that if they do not contact ";
                    $body .= " you within 2 hours, their House Hold Goods will be placed into storage.";
                }
                $shipment = new \Movestar\Shipment($notification->gbl_dps);
                $this->_createNotification($shipment,$notification->message_to);
                $recording = new Recording($notification->message_to,date('Y'),date('m'),date('d'));
                $subject = $this->_buildSubject($shipment);
                $this->msUpdates->email_subject = $subject;
                if(count($recording->recordings)){
                    $this->msUpdates->corrected_address = 1;
                    echo "Recordings Found:\n";
                    foreach($recording->recordings as $filename){
                        try{
                            $destination = Recording::msBackup($notification->gbl_dps,$notification->message_to,$filename);
                            $this->msUpdates->address_recording_path = $destination;
                            echo $filename . "\n";
                        }catch(\Exception $e){
                            echo $e->getMessage() . "\n";
                        }
                    }
                    $this->msUpdates->email_body = $body;
                    $recipients = array(self::REPLYTO,self::CC);
                    $recipients[] = self::CUSTSERV;
                    //$recipients[] = $this->_getRecipient($notification->gbl_dps);
                    foreach($recipients as $recipient){
                        \Messenger::send($recipient,self::FROM,self::FROM,self::REPLYTO,"","",$subject,$body,$recording->recordings);
                    }
                }else{
                    $this->msUpdates->email_body = $body;
                    $recipients = array(self::REPLYTO,self::CC);
                    $recipients[] = self::CUSTSERV;
                    //$recipients[] = $this->_getRecipient($notification->gbl_dps);
                    foreach($recipients as $recipient){
                        \Messenger::send($recipient,self::FROM,self::FROM,self::REPLYTO,"","",$subject,$body,'');
                    }
                }
                $this->msUpdates->create();
            }
        }
    }
    protected function _getRecipient($gbl_dps){
        $shipment = new \Shipment($gbl_dps);
        if(empty($shipment->mc_tid) || is_null($shipment->mc_tid)){
            $shipment = new \Movestar\Shipment($gbl_dps);
            $user = \User::getUserByFullName($shipment->AssignedTo);
        }else{
            $user = \User::getUserByTid($shipment->mc_tid);
        }
        return $user->email;
    }
    protected function _getMcInitials($gbl_dps){
        $shipment = new \Shipment($gbl_dps);
        $initails = null;
        if(empty($shipment->mc_tid) || is_null($shipment->mc_tid)){
            $user = \User::getUserByFullName($shipment->AssignedTo);
            $initails = substr($user->username,1,(strlen($user->username) - 2));
        }else{
            $initails = substr($shipment->mc_tid,1,(strlen($shipment->mc_tid) - 2));
        }
        return strtoupper($initails);
    }
    protected function _hasBeenSent($gbl_dps,$phoneNumber){
        $notes = \Notification::get("gbl_dps",$gbl_dps);
        if(!count($notes)){
            return false;
        }
        foreach($notes as $note){
            if($note->message_to == $phoneNumber && $note->message_filename == self::MSGNAME){
                return true;
            }
        }
        return false;
    }
    protected function _createNotification($shipmentObj,$recipient){
        $note = new \Notification();
        if(empty($recipient) || is_null($recipient)){
            $note->message_code = 2;
            $note->message_status_code = "Empty Recipient";
        }else{
            $note->message_code = 1;
            $note->message_status_code = "SENT";
        }
        $note->gbl_dps = $shipmentObj->Reference;
        $note->message_filename = self::MSGNAME;
        $note->scac = $shipmentObj->SCAC;
        $note->message_type = self::MSGTYPE;
        $note->message_to = $recipient;
        $note->message_from = "Allison";
        $note->message_sent_by = __FILE__;
        $note->message_sent_date = date('m/d/Y H:i:s');
        $note->registration_id = $shipmentObj->RegistrationID;
        $note->created_by = __FILE__;
        $note->created_date = date('m/d/Y H:i:s');
        $note->status_id = 1;
        $note->create();
        return $this;
    }
    protected function _interpretResponses($responseArray){
        $body = '';
        for($i = 0; $i < count($responseArray); $i++){
            if($i > 2){
                $body .= $this->questions[2] . $responseArray[$i] . "<br>";
            }else{
                if($i == 0 && $responseArray[$i] == 1){
                    $this->msUpdates->direct_delivery = 1;
                }elseif($i == 0 && $responseArray[$i] == 2){
                    $this->msUpdates->storage = 1;
                }
                $body .= $this->questions[$i] . $responseArray[$i] . "<br>";
            }
        }
        return $body;
    }
    protected function _buildSubject($shipmentObj){
        $mcInitials = $this->_getMcInitials($shipmentObj->Reference);
        return self::SUBJECT . ' ' . $shipmentObj->FirstName . ' ' . $shipmentObj->LastName . ' ' . $shipmentObj->Reference . '( MC: ' . $mcInitials . ')';
    }
}