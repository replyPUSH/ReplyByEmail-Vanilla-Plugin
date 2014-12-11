<?php if (!defined('APPLICATION')) exit();

class ReplyPushModel extends Gdn_Model {
    
    public static $Ref = array();
    
    public function  __construct($Name = ''){
        parent::__construct('ReplyPushRef');
    }
    
    public function LogTransaction($Notification){
        try{
            $this->SQL->Database->BeginTransaction();
            $this->SQL->Insert('ReplyPushLog', 
                array(
                'MessageID'=>GetValue('msg_id',$Notification),
                'Message'=>Gdn_Format::Serialize($Notification)));
                
            $this->SQL->Database->CommitTransaction();
        }catch(Exception $Ex) {
            $this->SQL->Database->RollbackTransaction();
            throw $Ex;
        }
    }
    
    public function GetTransaction($MessageID){
        return $this->SQL->Select('l.MessageID')
            ->From('ReplyPushLog l')
            ->Where('l.MessageID',($MessageID))
            ->Get()
            ->FirstRow();
    }
    
    public function GetRef($RefHash){
        if(GetValue($RefHash,self::$Ref))
            return self::$Ref[$RefHash];
            
        $Ref = $this->SQL->Select('r.Ref')
            ->From('ReplyPushRef r')
            ->Where('r.RefHash',$RefHash)
            ->Get()
            ->FirstRow();
        if(!$Ref)
            return '';
        return $Ref->Ref;
    }
    
    public function SaveRef($RefHash, $Ref){
        if(!$RefHash || !$Ref)
            return;
        if($this->GetRef($RefHash)){
            $this->SQL->Update('ReplyPushRef',
                array(
                    'Ref' => $Ref
                ),
                array(
                    'RefHash' => $RefHash
                )
            )
            ->Put();
            
            self::$Ref[$RefHash] = $Ref;
        }else{
            $this->SQL->Insert('ReplyPushRef', 
                array(
                    'RefHash' => $RefHash,
                    'Ref' => $Ref
                )
            );
        }
    }
}
