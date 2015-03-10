<?php if (!defined('APPLICATION')) exit();

// Define the plugin:
$PluginInfo['ReplyByEmail'] = array(
   'Name' => 'ReplyByEmail',
   'Description' => 'Allows users to reply by email to discussions, wall post, and messages using the replypush.com service',
   'Version' => '0.1.15b',
   'Author' => "Paul Thomas",
   'SettingsUrl' => 'settings/replybyemail',
   'RequiredApplications' => array('Vanilla' => '2.1'),
   'MobileFriendly' => TRUE,
   'AuthorEmail' => 'dt01pqt_pt@yahoo.com',
   'AuthorUrl' => 'http://vanillaforums.org/profile/x00',
   'HasLocale' => TRUE

);

/**
 *  @@ ReplyByEmailLoad function @@
 *
 *  A callback for spl_autoload_register
 *
 *  Will load class.[name].php for ReplyByEmail[Name]
 *  or ReplyByEmail[Name]Domain
 *
 *  @param string $Class class name to be matched
 *
 *  @return void
 */

function ReplyByEmailLoad($Class){
  $Match = array();
  if(preg_match('`^ReplyByEmail(.*)`',$Class,$Match)){
    $File = strtolower(preg_replace('`Domain$`','',$Match[1]));
    include_once(PATH_PLUGINS.DS.'ReplyByEmail'.DS.'class.'.$File.'.php');
  }
}

// auto load worker/domain classes.
spl_autoload_register('ReplyByEmailLoad');

// Initialise loader to be use by various libraries an architecture
ReplyByEmailUtility::InitLoad();

// auto load replypush api class
ReplyByEmailUtility::RegisterLoadMap('`^(ReplyPush)$`','library','class.{$Matches[0]}.php');


/**
 *  @@ ReplyByEmail @@
 *
 *  The plugin class which is referenced by
 *  Garden's pluggable interface.
 *
 *  The plugin hook uses workers, which often
 *  collaborate together on the tasks in hand.
 */

//<<<< must be flush no indentation !!!!
class ReplyByEmail extends ReplyByEmailNotifyDomain{
    
    public function Base_GetAppSettingsMenuItems_Handler($Sender){
        $this->Settings()->Settings_MenuItems($Sender);
    }
    
    public function SettingsController_ReplyByEmail_Create($Sender){
        $this->Settings()->ReplyByEmail_Controller($Sender);
    }
    
    /* pre-cache contexts */
    public function DiscussionModel_BeforeNotification_Handler($Sender, $Args){
        $this->API()->PreCacheContext('Discussion',$Args);
    }
    
    public function CommentModel_BeforeNotification_Handler($Sender, $Args){
        $this->API()->PreCacheContext('Comment',$Args);
    }
    
    public function ConversationModel_AfterAdd_Handler($Sender, $Args){
        $this->API()->PreCacheContext('Conversation',$Args);
    }
    /* end pre-cache contexts */
    
    public function ActivityModel_BeforeSendNotification_Handler($Sender, $Args){
        //process before sending the emails out
        $this->API()->ProcessMail($Args);
    }
    
    public function VanillaController_ReplyPush_Create($Sender, $Args){
        //incoming replypush.com notifications
        $this->Notify()->ReplyPush_Controller($Sender, $Args);
    }
    
    public function Base_BeforeLoadRoutes_Handler($Sender, &$Args){
        $this->Utility()->DynamicRoute($Args['Routes'],'^replypush/notify(/'.C('Plugins.ReplyByEmail.NotifyUrl').'/?)$','vanilla/replypush$1','Internal', TRUE);
    }
    
    public function Base_BeforeBlockDetect_Handler($Sender,&$Args){
        $Args['BlockExceptions']['/replypush\/notify(\/'.C('Plugins.ReplyByEmail.NotifyUrl').'\/?)$/i']=Gdn_Dispatcher::BLOCK_NEVER;
    }
    
    public function Base_BeforeDispatch_Handler($Sender){
        $this->Utility()->HotLoad();
    }

    public function Setup() {
        $this->Utility()->HotLoad(TRUE);
    }

    public function PluginSetup(){
        Gdn::Structure()
            ->Table('ReplyPushLog')
            ->Column('MessageID','varchar(36)',FALSE,'primary')
            ->Column('Message','text')
            ->Set();
            
        Gdn::Structure()
            ->Table('ReplyPushRef')
            ->Column('RefHash', 'varchar(32)',FALSE,'primary')
            ->Column('Ref','text')
            ->Set();
        Gdn_LibraryMap::ClearCache();
    }
}
