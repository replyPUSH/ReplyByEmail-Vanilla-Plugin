<?php if (!defined('APPLICATION')) exit();

/**
 *  @@ ReplyByEmailNotifyDomain @@
 *
 *  Links Notify Worker to the worker collection
 *  and retrieves it. Auto initialising.
 *
 *  Provides a simple way for other workers, or
 *  the plugin file to call it method and access its
 *  properties.
 *
 *  A worker will reference the Notify work like so:
 *  $this->Plgn->Notify()
 *
 *  The plugin file can access it like so:
 *  $this->Notify()
 *
 *  @abstract
 */

abstract class ReplyByEmailNotifyDomain extends ReplyByEmailSettingsDomain {

/**
 * The unique identifier to look up Worker
 * @var string $WorkerName
 */

  private $WorkerName = 'Notify';

  /**
   *  @@ Notify @@
   *
   *  Notify Worker Domain address,
   *  links and retrieves
   *
   *  @return void
   */

  public function Notify(){
    $WorkerName = $this->WorkerName;
    $WorkerClass = $this->GetPluginIndex().$WorkerName;
    return $this->LinkWorker($WorkerName,$WorkerClass);
  }

}

/**
 *  @@ ReplyByEmailNotify @@
 *
 *  The worker used to handle the main
 *  interactions.
 *
 */

class ReplyByEmailNotify {
        
    //where replypush.com nofications are sent to for processing
    public function ReplyPush_Controller($Sender, $Args){        
        if(GetValue('0',$Args)==C('Plugins.ReplyByEmail.NotifyUrl')){
            $this->Plgn->API()->ProcessIncomingNotification();
        }else{
            $this->Plgn->API()->Denied();
        }
    }
}
