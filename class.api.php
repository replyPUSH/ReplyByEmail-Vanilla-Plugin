<?php if (!defined('APPLICATION')) exit();

/**
 *  @@ ReplyByEmailAPIDomain @@
 *
 *  Links API Worker to the worker collection
 *  and retrieves it. Auto initialising.
 *
 *  Provides a simple way for other workers, or
 *  the plugin file to call it method and access its
 *  properties.
 *
 *  A worker will reference the API work like so:
 *  $this->Plgn->API()
 *
 *  The plugin file can access it like so:
 *  $this->API()
 *
 *  @abstract
 */

abstract class ReplyByEmailAPIDomain extends ReplyByEmailUtilityDomain {

/**
 * The unique identifier to look up Worker
 * @var string $WorkerName
 */

  private $WorkerName = 'API';

  /**
   *  @@ API @@
   *
   *  API Worker Domain address,
   *  links and retrieves
   *
   *  @return void
   */

  public function API(){
    $WorkerName = $this->WorkerName;
    $WorkerClass = $this->GetPluginIndex().$WorkerName;
    return $this->LinkWorker($WorkerName,$WorkerClass);
  }

}

/**
 *  @@ ReplyByEmailAPI @@
 *
 *  The worker used for the internals
 *
 *  Also can be access by other plugin by
 *  hooking ReplyByEmail_Loaded_Handler
 *  and accessing $Sender->Plgn->API();
 *
 */

class ReplyByEmailAPI {
    
   /**
    * A lookup of long and short ActivityType codes
    * short used for ReplyPush API, and long for local
    * 
    * @var array[string]string
    */
    
    protected $ActivityTypes = array(
        'DiscussionComment' => 'dcomnt',
        'Comment' => 'comnt',
        'Discussion' => 'disc',
        'NewDiscussion' => 'ndisc',
        'WallPost' => 'wpost',
        'ActivityComment' => 'acomnt',
        'ConversationMessage' => 'cmsg'
    );
    
    
   /**
    * A cache for context object related to notifications
    * 
    * @var array[int]object
    */
    public static $ContextObjects = array();
    
    
    /**
     * @@ ContextObjectCache @@
     * 
     * Try to revieve context object from
     * cache, args, and finally database,
     * caching the result
     * 
     * @param array[string]mixed $Args
     * @param string $Type
     * @param string $Model
     * @param string $Method
     * @param int $RecordID
     * 
     * @return object
     * 
     */
     
    public function ContextObjectCache($Args, $Type, $Model, $Method, $RecordID){
        $ContextObject = GetValueR("$Type.$RecordID",self::$ContextObjects);
        if($ContextObject)
            return $ContextObject;
        
        $ContextObject = GetValueR($Type,$Args);
        
        if(!$ContextObject){
            $ConextModel = new $Model();
            $ContextObject = $ConextModel->$Method($RecordID);
        }else{
            $ContextObject = (object) $ContextObject;
        }
        
        if(!GetValue($Type,self::$ContextObjects)){
            self::$ContextObjects[$Type] = array();
        }
        self::$ContextObjects[$Type][$RecordID] = $ContextObject;
        
        return $ContextObject;
    }
    
    /**
     * @@ PreCacheContext @@
     * 
     * Stash the various context objects
     * 
     * @param string $Context
     * @param array[string]mixed $Args
     * 
     * @return void
     */
    
    public function PreCacheContext($Context, $Args){
        switch($Context){
            case 'Comment':
                $this->ContextObjectCache($Args, 'Comment', 'CommentModel', 'GetID', $Args['CommentID']);
                //fall through
            case 'Discussion':
                $this->ContextObjectCache($Args, 'Discussion', 'DiscussionModel', 'GetID', $Args['DiscussionID']);
                break;
            case 'Conversation':
                $this->ContextObjectCache($Args, 'Message', 'ConversationMessageModel', 'GetID', $Args['MessageID']);
                break;
        }
    }
    
    
    /**
     * @@ MessageMeta @@
     * 
     * Takes activity args and generates
     * message meta
     * 
     * @param array[string]mixed $Args
     * 
     * @return array[string]mixed
     * 
     */
    
    public function MessageMeta($Args){
        $RecordID = 0;
        $ContentID = 0;
        $Title = '';
        $Subject = '';
        $Location = '';
        $Content = '';
        $Link = '';
        $HeadlineFormat = '';
        $ActivityType  = GetValueR('Activity.ActivityType', $Args);
        //Strip additional context, like watched categories
        list($ActivityType) = split('\.',$ActivityType);
        $Type = GetValue($ActivityType, $this->ActivityTypes);
        switch($ActivityType){
            case 'DiscussionComment':
            case 'Comment':
                $RecordID = GetValueR('Activity.RecordID',$Args);
                $Comment = $this->ContextObjectCache($Args, 'Comment', 'CommentModel', 'GetID', $RecordID);
                
                $ContentID = $Comment->DiscussionID;
                $Discussion = $this->ContextObjectCache($Args, 'Discussion', 'DiscussionModel', 'GetID', $ContentID);
                
                $HeadlineFormat = T('ReplyByEmail.'.array_search(GetValueR('Activity.HeadlineFormat',$Args),array('HeadlineFormat.Comment'=>T('HeadlineFormat.Comment'),'HeadlineFormat.Mention'=>T('HeadlineFormat.Mention'))),T('ReplyByEmail.HeadlineFormat.Comment'));
                $Title = GetValueR('Activity.Data.Name',$Args);
                // Ensure the different types of discussion notification are under the same subject, so the email are kept in the same thread. 
                if(C('Plugins.ReplyByEmail.CollateDiscussionSubject',TRUE))
                    $Subject = SliceString(FormatString(T('ReplyByEmail.CollateDiscussion.Subject'),array('Site' => Gdn_Format::PlainText(C('Garden.Email.SupportName', C('Garden.Title', ''))), 'DiscussionID' => $Discussion->DiscussionID, 'Title' => Gdn_Format::PlainText($Discussion->Name))),100);
                $Location = GetValueR('Activity.Data.Category',$Args);
                $Content = Gdn_Format::To(SliceString($Comment->Body,C('Plugins.ReplyByEmail.ContentLength',1500)),$Comment->Format);
                $Route = GetValueR('Activity.Route',$Args);
                $Link = Url($Route,TRUE);
                break;
            case 'Discussion':
            case 'NewDiscussion':
                $RecordID = GetValueR('Activity.RecordID',$Args);
                $Discussion = $this->ContextObjectCache($Args, 'Discussion', 'DiscussionModel', 'GetID', $RecordID);
                
                $ContentID = $Discussion->CategoryID;
                $HeadlineFormat = T('ReplyByEmail.'.array_search(GetValueR('Activity.HeadlineFormat',$Args),array('HeadlineFormat.Discussion'=>T('HeadlineFormat.Discussion'),'HeadlineFormat.Mention'=>T('HeadlineFormat.Mention'))),T('ReplyByEmail.HeadlineFormat.Discussion'));
                $Location = GetValueR('Activity.Data.Category',$Args);
                $Title = GetValueR('Activity.Data.Name',$Args);
                // Ensure the different types of discussion notification are under the same subject, so the email are kept in the same thread. 
                if(C('Plugins.ReplyByEmail.CollateDiscussionSubject',TRUE))
                    $Subject = SliceString(FormatString(T('ReplyByEmail.CollateDiscussion.Subject'),array('Site' => Gdn_Format::PlainText(C('Garden.Email.SupportName', C('Garden.Title', ''))), 'DiscussionID' => $Discussion->DiscussionID, 'Title' => Gdn_Format::PlainText($Discussion->Name))),100);
                $Content = Gdn_Format::To(SliceString($Discussion->Body,C('Plugins.ReplyByEmail.ContentLength',1500)),$Discussion->Format);
                $Route = GetValueR('Activity.Route',$Args);
                $Link = Url($Route,TRUE);
                break;
            case 'WallPost':
                
                $Route = GetValueR('Activity.Route',$Args);
                $Link = Url($Route,TRUE);
                $Matches = Null;
                $RecordID = GetValueR('Activity.RecordID',$Args);
                $WallPost = $this->ContextObjectCache($Args, 'Activity', 'ActivityModel', 'GetID', $RecordID);
                $Content = Gdn_Format::To(SliceString(GetValue('Story',$WallPost),C('Plugins.ReplyByEmail.ContentLength',1500)),GetValue('Format',$WallPost));
                break;
            case 'ActivityComment':
                $Comment = GetValue('Comment',$Args);
                $RecordID = $Comment->ActivityCommentID;
                $ContentID = $Comment->ActivityID;
                $Content = Gdn_Format::To(SliceString($Comment->Body,C('Plugins.ReplyByEmail.ContentLength',1500)),$Comment->Format);
                break;
            case 'ConversationMessage':
                $Route = GetValueR('Activity.Route',$Args);
                $Link = Url($Route,TRUE);
                $Matches = NULL;
                preg_match('`^/messages/([0-9]+)#([0-9]+)$`i',$Route,$Matches);
                if($Matches){
                    $Message = $this->ContextObjectCache($Args, 'Message', 'ConversationMessageModel', 'GetID', $Matches[1]);
                    if(C('Plugins.ReplyByEmail.CollateMessageSubject',TRUE)){
                        $FromUserID = GetValueR('Activity.ActivityUserID',$Args);
                        $FromUser = Gdn::UserModel()->GetID($FromUserID);
                        $Subject = SliceString(FormatString(T('ReplyByEmail.CollateMessage.Subject'),array('Site' => Gdn_Format::PlainText(C('Garden.Email.SupportName', C('Garden.Title', ''))), 'UserName' => $FromUser->Name, 'ConversationID' => $Message->ConversationID)),100);
                    }    
                    $Content = Gdn_Format::To(SliceString($Message->Body,C('Plugins.ReplyByEmail.ContentLength',1500)),$Message->Format);
                    $RecordID = $Message->MessageID;
                    $ContentID = $Message->ConversationID;
                }
                break;
        }
        
        $Meta = array(
            'Type' => $Type,
            'ActivityType' => $ActivityType,
            'RecordID'=> $RecordID,
            'ContentID'=> $ContentID,
            'HeadlineFormat' => $HeadlineFormat,
            'Headline' =>  GetValueR('Headline', $Args),
            'Title' => $Title,
            'Subject' => $Subject,
            'Location'=> $Location,
            'Content' => $Content,
            'Route' => $Route,
            'Link' => $Link
        );
        
        return $Meta;
    }
    
    /**
     * @@ ReferenceKey @@
     * 
     * ReferenceKey for email threading
     * 
     * @param string $Type
     * @param int $RecordID
     * @param int $ContentID
     * @param string $Email
     * 
     * @return string
     */
    
    public function ReferenceKey($Type, $RecordID, $ContentID, $Email){
        
        if(C('Plugins.ReplyByEmail.CollateDiscussionSubject',TRUE)){
            if(in_array($Type, array('comnt', 'dcomnt'))){
                $RecordID = $ContentID;
                $Type = 'disc';
            }
            
            if(in_array($Type, array('disc', 'ndisc'))){
                $Type = 'disc';
            }
        }
        
        return md5($Type.$RecordID.$Email);

    }
    
    
    /**
     * @@ ProcessMail @@
     * 
     * Processes outgoing mail
     * 
     * @param array[string]mixed $Args
     * 
     * @return void
     */
    
    public function ProcessMail($Args){

        $SecretID = C('Plugins.ReplyByEmail.SecretID');
        $SecretKey = C('Plugins.ReplyByEmail.SecretKey');
        $AccountNo = C('Plugins.ReplyByEmail.AccountNo');
        
        //no credentials? Then don't process
        if(!($SecretID && $SecretKey && $AccountNo))
            return;
        
        //get all the component related to the type of notification
        extract($this->MessageMeta($Args));
        
        if($Type){
            if(!$RecordID)
                $RecordID = GetValueR('Activity.RecordID',$Args);
            
            //meta assciated with all notifications
            $UserID = GetValueR('User.UserID',$Args,GetValueR('Activity.NotifyUserID',$Args));
            $User = Gdn::UserModel()->GetID($UserID);
            $FromUserID = GetValueR('Activity.ActivityUserID',$Args);
            $FromUser = Gdn::UserModel()->GetID($FromUserID);
            $Email = GetValueR('User.Email',$Args); 
            
            $TimeStamp = time();
            
            //chose the best available hash method (within reason)
            $HashMethod = C('Plugins.ReplyByEmail.HashMethod',in_array('sha1',hash_algos()) ? 'sha1': 'md5');
            
            //custom 40 byte custom data (AccountNo and HashMethod will be prepended by ReplyPush class to make 56 bytes)
            //comprises of 8 bytes sections, with verifiable data inserted
            $Data = sprintf("%08x%08x%-8s%08x%08x", $FromUserID, $RecordID, $Type, $ContentID, $TimeStamp);
            
            //use API class to create reference 
            $ReplyPush = new ReplyPush($AccountNo, $SecretID, $SecretKey, $User->Email, $Data, $HashMethod);
            
            $Email = GetValueR('Email',$Args); 
            $MessageID = $Email->PhpMailer->MessageID = $ReplyPush->reference();
            
            if($Email->PhpMailer->From==$User->Email){
                $Email->PhpMailer->From = C('Plugins.ReplyByEmail.ReplyPushEmail','post@replypush.com');
            }
            
            //if collating subjects
            if($Subject){
                $Email->Subject($Subject);
            }
            
            $ReplyPushModel = new ReplyPushModel();
            
            //get special reference key for threading
            $RefHash = $this->ReferenceKey($Type, $RecordID, $ContentID, $User->Email);
            
            //get historic Reference for threading
            $Ref = $ReplyPushModel->GetRef($RefHash);
            
            //add headers if historic refernces
            if($Ref){
                $Email->PhpMailer->AddCustomHeader("References: {$Ref}");
                $Email->PhpMailer->AddCustomHeader("In-Reply-To: {$Ref}");
            }
            
            //save current MessageID as Ref
            $ReplyPushModel->SaveRef($RefHash, $MessageID);
                
            
            $Email->PhpMailer->AddReplyTo(
                C('Plugins.ReplyByEmail.ReplyPushEmail','post@replypush.com'), 
                FormatString(
                    T('ReplyByEmail.ReplyToFormat','{Name,text} [at] {Forum,text}'),
                    array('Name'=> GetValueR('Name',$FromUser,'Somebody'),'Forum'=> Gdn::Request()->Host())
                )
            );
            
            
            
            if(C('Plugins.ReplyByEmail.LocaleOverride', TRUE)){
                $Vars = array(
                    'Name' => $FromUser->Name,
                    'Title' => $Title,
                    'Location' => $Location,
                    'Content' => $Content,
                    'Link' => $Link
                );
                $Vars['Headline'] = $HeadlineFormat ? FormatString($HeadlineFormat, $Vars): GetValue('Headline',$Notification);
                $Email->PhpMailer->IsHtml();
                $Email->PhpMailer->Body = FormatString(T('ReplyByEmail.Msg.'.$ActivityType), $Vars);
                $Email->PhpMailer->AltBody = FormatString(T('ReplyByEmail.MsgAlt.'.$ActivityType), $Vars);
            }
            
            $Sig = FormatString(T('ReplyByEmail.SigText'),array('Tag'=>'\n***** reply service *****\n\n', 'SigID' => mt_rand()));
            $SigHtml = FormatString(T('ReplyByEmail.SigHtml'),array('Tag'=>'<a href="http://replypush.com#rp-sig"></a>', 'SigID' => mt_rand()));
            
            if($Email->PhpMailer->ContentType == 'text/html'){
                $Email->PhpMailer->Body = '<a href="http://replypush.com#rp-message"></a>'.$Email->PhpMailer->Body.$SigHtml;
                $Email->PhpMailer->AltBody .= $Sig;
            }else{
                $Email->PhpMailer->Body = '***** '.Gdn_Format::Text(Gdn::Request()->Host())." message *****\n\n".$Email->PhpMailer->Body.$Sig;
            }
        }
    }
    
    /**
     * @@ ProcessMail @@
     * 
     * Processes, authenticates and verifies
     * POST notification from replypus.com
     * 
     * @return void
     */
    
    public function ProcessIncomingNotification(){
        $Notification = $_POST;
        
        if(!empty($Notification)){
            //no message id no message
            if(!GetValue('msg_id',$Notification)){
                $this->Denied();
            }
            $ReplyPushModel = new ReplyPushModel();
            
            //check for duplicate message id
            if($ReplyPushModel->GetTransaction(GetValue('msg_id',$Notification))){
                return;//ignore
            }

            $SecretID = C('Plugins.ReplyByEmail.SecretID');
            $SecretKey = C('Plugins.ReplyByEmail.SecretKey');
            $AccountNo = C('Plugins.ReplyByEmail.AccountNo');
            
            //the user the notification reply come from
            $User = Gdn::UserModel()->GetByEmail(GetValue('from',$Notification));
            if(!$User)
                $this->Denied();
            
            //use API class to check reference
            $ReplyPush = new ReplyPush($AccountNo, $SecretID, $SecretKey, $User->Email, $Notification['in_reply_to']);


            if($ReplyPush->hashCheck()){
                //split 56 bytes into 8 byte components and process
                $MessageData = str_split($ReplyPush->referenceData,8);
                $FromUserID = hexdec($MessageData[2]);
                $RecordID = hexdec($MessageData[3]);
                $Type = trim($MessageData[4]);
                $ContentID = trim($MessageData[5]);
                
                //get special reference key for threading
                $RefHash = $this->ReferenceKey($Type, $RecordID, $ContentID, $User->Email);
                
                //get historic Reference for threading
                $Ref = $ReplyPushModel->GetRef($RefHash);
                
                //save current MessageID as Ref
                $ReplyPushModel->SaveRef($RefHash, GetValue('from_msg_id',$Notification));
                
                //handle error notifications without inserting anything.
                if(GetValue('error',$Notification)){
                    $this->ProcessIncomingError(GetValue('error',$Notification), $User, GetValue('subject',$Notification), $Ref);
                    return;
                }
                
                //You don't know this yet, you will work it out
                $ContentID = 0;
                
                //start session as from user
                Gdn::Session()->Start($User->UserID);
                $Model = NULL;
                
                $ActivityType = array_search($Type, $this->ActivityTypes);
                
                switch($ActivityType){
                    case 'NewComment':
                    case 'DiscussionComment':
                    case 'Comment':
                        $CommentModel = new CommentModel();
                        $Comment = $CommentModel->GetID($RecordID);
                        if($Comment->InsertUserID!=$FromUserID){
                            $this->Denied();
                        }else{
                            $Fields = array('DiscussionID'=>$Comment->DiscussionID,'Body'=>$this->FormatContent($Notification));
                            $CommentModel->Save($Fields);
                        }
                        $ContentID = $Comment->DiscussionID;
                        $Model = $CommentModel;
                        break;
                    case 'Discussion':
                    case 'NewDiscussion':
                        $DiscussionModel = new DiscussionModel();
                        $Discussion = $DiscussionModel->GetID($RecordID);
                        if($Discussion && $Discussion->InsertUserID!=$FromUserID){
                            $this->Denied();
                        }else{
                            $CommentModel = new CommentModel();
                            $Fields = array('DiscussionID'=>$Discussion->DiscussionID,'Body'=>$this->FormatContent($Notification));
                            $CommentModel->Save($Fields);
                        }
                        $Model = $DiscussionModel;
                        break;
                    case 'WallPost':
                        $ActivityModel = new ActivityModel();
                        $WallPost = $ActivityModel->GetID($RecordID);
                        if(GetValue('InsertUserID',$WallPost)!=$FromUserID){
                            $this->Denied();
                        }else{
                            $Activity = array(
                                'ActivityID' => $RecordID,
                                'Body' => $this->FormatContent($Notification),
                                'Format' => 'Text'
                            );
                            
                            $ActivityModel->Comment($Activity);
                            //currently no notification for ActivityComment so create one
                        }
                        $Model = $ActivityModel;
                        break;
                    case 'ConversationMessage':
                        $ConversationModel = new ConversationModel();
                        $Conversation = $ConversationModel->GetID($RecordID);
                        if($Conversation->InsertUserID!=$FromUserID){
                            $this->Denied();
                        }else{
                            $ConversationMessageModel = new ConversationMessageModel();
                            $Message = array('ConversationID'=>$RecordID,'Body'=>$this->FormatContent($Notification));
                            $ConversationMessageModel->Save($Message);
                            $ContentID = $RecordID;
                        }
                        $Model = $ConversationModel;
                        break;
                }
                
                if(!$Model)
                    $this->Denied();
                
                //if there was errors inserting then reply email them back 
                if(count($Model->Validation->Results())){
                    $Subject = GetValue('subject',$Notification);
                    $this->SendReplyError($User->Email, $User->Name, $Model->Validation->ResultsText(), $Subject);
                }
                
            }else{
                $this->Denied();
            }
            //don't save actual message
            unset($Notification['content']);
            
            $ReplyPushModel->LogTransaction($Notification);
        }
    }
    
    /**
     * @@ FormatContent @@
     * 
     * Pre- formats html and text content
     * 
     * @param array[string]mixed $Notification
     * 
     * @return string
     */
    
    public function FormatContent($Notification){
        $Html = GetValueR('content.text/html',$Notification);
        if($Html){
            return trim(
                str_replace(
                    "\n",
                    '',
                    $Html
                )
            );
        }else{
            return trim(GetValueR('content.text/plain',$Notification));
        }
    }
    
    /**
     * @@ ProcessIncomingError @@
     * 
     * Get long error message and send error email
     * 
     * @param string $Error the short hand error message
     * @param object $User 
     * @param string $Subject
     * 
     * @return void
     */
    
    public function ProcessIncomingError($Error, $User, $Subject, $Ref=''){
        //LogMessage(__FILE__,__LINE__,__CLASS__,__METHOD__,var_export(array($Error, $User, $Subject),TRUE));
        $ErrorMsg = T('ReplyByEmail.IncomingError.'.$Error);
        if($ErrorMsg)
            $this->SendReplyError($User->Email, $User->Name, $ErrorMsg, $Subject);
    }
    
    /**
     * @@ SendReplyError @@
     * 
     * Send error email
     * 
     * @param string $Email
     * @param string $Name
     * @param string $ErrorMessage
     * @param string $Subject
     * 
     * @return void
     */
    
    public function SendReplyError($ToEmail, $Name, $ErrorMessage, $Subject, $Ref=''){
        $Email = new Gdn_Email();
   
        $Email->Subject($Subject ? $Subject : T('ReplyByEmail.ReplyErrorSubject','Errors in your reply by email responce.'));

        $Email->To($ToEmail);
    
        $Email->Message(FormatString(T('ReplyByEmail.ReplyErrorBody'), array('Name'=> $Name, 'ErrorMessage' => $ErrorMessage, 'SigID' => mt_rand())));
        
        if($Email->PhpMailer->From==$ToEmail){
            $Email->PhpMailer->From = C('Plugins.ReplyByEmail.ReplyPushEmail','post@replypush.com');
        }
        
        //add headers if historic refernces
        if($Ref){
            $Email->PhpMailer->AddCustomHeader("References: {$Ref}");
            $Email->PhpMailer->AddCustomHeader("In-Reply-To: {$Ref}");
        }
    
        $Email->PhpMailer->AddReplyTo(
            C('Plugins.ReplyByEmail.ReplyPushEmail','post@replypush.com'), 
            FormatString(
                T('ReplyByEmail.ReplyToFormat','{Name,text} [at] {Forum,text}'),
                array('Name'=> 'noreply','Forum'=> Gdn::Request()->Host())
            )
        );
        
        $Email->Send();
    }
    
    /**
     * @@ Denied @@
     * 
     * Deny invalid notifications
     * 
     * @return void
     */

    public function Denied(){
        header("HTTP/1.0 403 Denied");
        exit();
    }

}
