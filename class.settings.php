<?php if (!defined('APPLICATION')) exit();

/**
 *  @@ ReplyByEmailSettingsDomain @@
 *
 *  Links Settings Worker to the worker collection
 *  and retrieves it. Auto initialising.
 *
 *  Provides a simple way for other workers, or
 *  the plugin file to call it method and access its
 *  properties.
 *
 *  A worker will reference the Settings work like so:
 *  $this->Plgn->Settings()
 *
 *  The plugin file can access it like so:
 *  $this->Settings()
 *
 *  @abstract
 */

abstract class ReplyByEmailSettingsDomain extends ReplyByEmailAPIDomain {

/**
 * The unique identifier to look up Worker
 * @var string $WorkerName
 */

  private $WorkerName = 'Settings';

  /**
   *  @@ Settings @@
   *
   *  Settings Worker Domain address,
   *  links and retrieves
   *
   *  @return void
   */

  public function Settings(){
    $WorkerName = $this->WorkerName;
    $WorkerClass = $this->GetPluginIndex().$WorkerName;
    return $this->LinkWorker($WorkerName,$WorkerClass);
  }
}

/**
 *  @@ ReplyByEmailSettings @@
 *
 *  The worker used to handle the backend
 *  settings interactions.
 *
 */

class ReplyByEmailSettings {

  /**
   *  @@ ReplyByEmailLink @@
   *
   *  Basic settings menu item and link
   *
   *  @param Gdn_Controller $Sender
   *
   *  @return void
   */

  public function Settings_MenuItems($Sender) {
    $Menu = $Sender->EventArguments['SideMenu'];
    $Menu->AddLink('Site Settings', T('Reply By Email'), 'settings/ReplyByEmail', 'Garden.Settings.Manage');
  }

  /**
   *  @@ ReplyByEmail_Controller @@
   *
   *  Used to load shared resources,
   *  @param Gdn_Controller $Sender
   *
   *  @return void
   */


  public function ReplyByEmail_Controller($Sender){
        $Sender->Permission('Garden.Settings.Manage');
        if(!C('Plugins.ReplyByEmail.NotifyUrl')){
            SaveToConfig('Plugins.ReplyByEmail.NotifyUrl',uniqid());
        }
        
        $Validation = new Gdn_Validation();
        if ($Sender->Form->AuthenticatedPostBack()) {
            $Validation->ApplyRule('Plugins.ReplyByEmail.AccountNo', 'String');
            $Validation->ApplyRule('Plugins.ReplyByEmail.SecretID', 'String');
            $Validation->ApplyRule('Plugins.ReplyByEmail.SecretKey', 'String');
            
            try{
                ReplyPush::validateCredentials(
                    $Sender->Form->GetValue('Plugins.ReplyByEmail.AccountNo'),
                    $Sender->Form->GetValue('Plugins.ReplyByEmail.SecretID'),
                    $Sender->Form->GetValue('Plugins.ReplyByEmail.SecretKey')
                );
            }catch(ReplyPushError $Error){
                $Item = ucfirst($Error->getItem());
                $Msg = $Error->getMessage();
                $Validation->AddValidationResult('Plugins.ReplyByEmail.'.$Item, T('ReplyByEmail.'.$Item.'ValidateError', $Msg));
            }
            
            $Sender->Form->SetFormValue('Plugins.ReplyByEmail.NotifyUrl',str_replace(Url('/replypush/notify/',TRUE),'',$Sender->Form->GetValue('Plugins.ReplyByEmail.NotifyUrl')));

            $Validation->Validate($Sender->Form->FormValues());
            if(count($Validation->Results())>0){
                $Sender->Form->SetFormValue('Plugins.ReplyByEmail.AccountNo', C('Plugins.ReplyByEmail.AccountNo'));
                $Sender->Form->SetFormValue('Plugins.ReplyByEmail.SecretID', C('Plugins.ReplyByEmail.SecretID'));
                $Sender->Form->SetFormValue('Plugins.ReplyByEmail.SecretKey', C('Plugins.ReplyByEmail.SecretKey'));
            }
            $Sender->Form->SetValidationResults($Validation->Results());
        }
        
        $Config = new ConfigurationModule($Sender);
        $Config->Initialize(array(
            'Plugins.ReplyByEmail.AccountNo' => array(
                'Type' => 'string',
                'Control' => 'TextBox',
                'Default' => null, 
                'Description' => 'The Account No found <a href="http://replypush.com/profile">here</a>. Sign up for an account first.',
                'LabelCode' => 'Account No',
                'Options'=>array('maxlength'=>8, 'class'=>'InputBox SmallInput')
            ),
            'Plugins.ReplyByEmail.SecretID' => array(
                'Type' => 'string',
                'Control' => 'TextBox',
                'Default' => null, 
                'Description' => 'The API ID found <a href="http://replypush.com/profile">here</a>.',
                'LabelCode' => 'Secret ID',
                'Options'=>array('maxlength'=>32, 'class'=>'InputBox BigInput')
            ),
            'Plugins.ReplyByEmail.SecretKey' => array(
                'Type' => 'string',
                'Control' => 'TextBox',
                'Default' => null, 
                'Description' => 'The API Key found <a href="http://replypush.com/profile">here</a>.',
                'LabelCode' => 'Secret Key',
                'Options'=>array('maxlength'=>32, 'class'=>'InputBox BigInput')
            ),
            'Plugins.ReplyByEmail.NotifyUrl' => array(
                'Type' => 'string',
                'Control' => 'TextBox',
                'Default' => Url('/replypush/notify/'.C('Plugins.ReplyByEmail.NotifyUrl'),TRUE), 
                'Description' => 'Save this Notify Url <a href="http://replypush.com/profile">here</a>.',
                'LabelCode' => 'Notify Url',
                'Options'=>array('class'=>'InputBox BigInput','readonly'=>'readonly','value'=>Url('/replypush/notify/'.C('Plugins.ReplyByEmail.NotifyUrl'),TRUE))
            ),
            'Plugins.ReplyByEmail.CollateDiscussionSubject' => array(
                'Type' => 'bool',
                'Control' => 'Checkbox',
                'Default' => C('Plugins.ReplyByEmail.CollateDiscussionSubject',TRUE), 
                'Description' => 'Use the same email subject for the different types of discussion/comment notifications, so that the email client will link the emails and keep each discussion in one thread.',
                'LabelCode' => 'Collate Discussion Notifications',
                'Options'=>array('class'=>'InputBox BigInput')
            ),
            'Plugins.ReplyByEmail.CollateMessageSubject' => array(
                'Type' => 'bool',
                'Control' => 'Checkbox',
                'Default' => C('Plugins.ReplyByEmail.CollateMessageSubject',TRUE), 
                'Description' => 'Use the same email subject for each message under a single conversation, so that the email client will link the emails and keep each discussion in one thread.',
                'LabelCode' => 'Collate Conversation Message Notifications',
                'Options'=>array('class'=>'InputBox BigInput')
            )
            
          )
        );

        $Sender->AddSideMenu('settings/replybyemail');
        $Sender->SetData('Title', T('ReplyByEmail.Title','Reply By Email'));
        $Config->RenderAll();
  }

}
