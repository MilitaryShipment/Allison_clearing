<?php namespace Allison;

require_once __DIR__ . '/../movestar/shipment.php';
require_once __DIR__ . '/../shipment/notification.php';
require_once __DIR__ . '/../shipment/user.php';
require_once __DIR__ . '/../thirdParty/voicent_aamg.php';


class ClearingDaemon{

    const SUITE = 'movestar';
    const DRIVER = 'mssql';
    const DB = '[edc-movestar]';

    const SHIPMENTS = 'tbl_shipment';
    const NOTES = 'ctl_notifications';

    const PRIMARYKEY = 'reference';
    const MSGNAME = 'allison_clearing';
    const MSGTYPE = 'allison_call';

    const NOTNUMBER = "/[^0-9]/";
    const LEADPAREN = "/^.*?\s/";

    const SANDPHONE = '9012646875';

    protected $shipments = array();

    public function __construct(){
        $voicent = new \Voicent();
        $this->_getShipments();
        foreach($this->shipments as $shipment){
            if(empty($shipment->AssignedTo) || is_null($shipment->AssignedTo)){
                $exceptionStr = "Unable to make clearing call for shipment " . $shipment->Reference . " because MoveStar's AssignedTo field is empty";
                throw new \Exception($exceptionStr);
            }
            $coordinator = \User::getUserByFullName($shipment->AssignedTo);
            if(empty($coordinator->rc_direct_phone) || is_null($coordinator->rc_direct_phone)){
                $exceptionStr = "Unable to make clearing call for shipment " . $shipment->Reference . " because " . $shipment->AssignedTo . "'s RingCentral Phone number is empty";
                throw new \Exception($exceptionStr);
            }
            $primaryPhone = $this->_cleanPhoneNumber($shipment->PrimaryPhone);
            $secondaryPhone = $this->_cleanPhoneNumber($shipment->SecondaryPhone);
            if(!$this->_hasBeenSent($shipment->Reference,$primaryPhone)){
                echo "Initiating call to: " . $primaryPhone . "\n";
                $voicent->clearing_call($primaryPhone,$this->_cleanScac($shipment->Carrier),$this->_buildAddressString($shipment),$shipment->DestinationCity,$this->_getFullState($shipment->DestinationState),$shipment->DestinationZip,$shipment->AssignedTo,"");
                $this->_createNotification($shipment,$primaryPhone);
            }
            if(!$this->_hasBeenSent($shipment->Reference,$secondaryPhone)){
                echo "Initiating call to: " . $secondaryPhone . "\n";
                if(!empty($secondaryPhone) && !is_null($secondaryPhone)){
                    $voicent->clearing_call($secondaryPhone,$this->_cleanScac($shipment->Carrier),$this->_buildAddressString($shipment),$shipment->DestinationCity,$this->_getFullState($shipment->DestinationState),$shipment->DestinationZip,$shipment->AssignedTo,"");
                }
                $this->_createNotification($shipment,$secondaryPhone);
            }
        }
    }

    protected function _getShipments(){
        $gbls = array();
        $results = $GLOBALS['db']
            ->suite(self::SUITE)
            ->driver(self::DRIVER)
            ->database(self::DB)
            ->table("tbl_shipment_clearing_queue scq")
            ->select("s.Reference")
            ->leftJoin("tbl_shipment s","scq.ShipmentMoveStarId","=","s.MoveStarID")
            ->where("scq.CAPSOn","IS NOT","NULL")
            ->andWhere("cast(CAPSOn as date)","=","cast(GETDATE() as date)")
            ->andWhere("scq.SendActionsOnly","=","0")
            ->get();
        while($row = sqlsrv_fetch_array($results,SQLSRV_FETCH_ASSOC)){
            $gbls[] = $row[self::PRIMARYKEY];
        }
        foreach($gbls as $gbl){
            $this->shipments[] = new \Movestar\Shipment($gbl);
        }
        return $this;
    }
    protected function _hasBeenSent($gbl_dps,$phoneNumber){
        $notes = \Notification::get("gbl_dps",$gbl_dps);
        if(!count($notes)){
            return false;
        }
        foreach($notes as $note){
            if($note->message_to == $phoneNumber && $note->message_type == self::MSGTYPE){
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
    protected function _cleanPhoneNumber($phoneNumber){
        return preg_replace(self::NOTNUMBER,"",$phoneNumber);
    }
    protected function _cleanScac($scac){
        return preg_replace(self::LEADPAREN,"",$scac);
    }
    protected function _getFullState($abbreviation){
        $stateName = null;
        $results = $GLOBALS['db']
            ->suite("mssql")
            ->driver("mssql")
            ->database("Sandbox")
            ->table("ctl_zip3")
            ->select("state_name")
            ->where("state","=",$abbreviation)
            ->get();
        while($row = sqlsrv_fetch_array($results,SQLSRV_FETCH_ASSOC)){
            $stateName = $row['state_name'];
        }
        return $stateName;
    }
    protected function _buildAddressString($shipment){
        if(empty($shipment->DestinationAddress2) || is_null($shipment->DestinationAddress2)){
            return $shipment->DestinationAddress1;
        }
        return $shipment->DestinationAddress1 . " " . $shipment->DestinationAddress2;
    }
}
