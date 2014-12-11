<?php if (!defined('APPLICATION')) exit();

// Define the plugin:
$PluginInfo['ReplyByEmail'] = array(
   'Name' => 'ReplyByEmail',
   'Description' => 'Allows users to reply by email to discussions, wall post, and messages using the replypush.com service',
   'Version' => '0.1.9b',
   'Author' => "Paul Thomas",
   'SettingsUrl' => 'settings/replybyemail',
   'RequiredApplications' => array('Vanilla' => '2.0.18'),
   'MobileFriendly' => TRUE,
   'AuthorEmail' => 'dt01pqt_pt@yahoo.com',
   'AuthorUrl' => 'http://vanillaforums.org/profile/x00',
   'HasLocale' => TRUE

);

class ReplyByEmail extends Gdn_Plugin {
    
    public function SettingsController_ReplyByEmail_Create($Sender){
        $Sender->Permission('Garden.Settings.Manage');
        if(!C('Plugins.ReplyByEmail.NotifyUrl')){
            SaveToConfig('Plugins.ReplyByEmail.NotifyUrl',uniqid());
        }
        
        $Validation = new Gdn_Validation();
        if ($Sender->Form->AuthenticatedPostBack()) {
            $Validation->ApplyRule('Plugins.ReplyByEmail.AccountNo', 'String');
            $Validation->ApplyRule('Plugins.ReplyByEmail.SecretID', 'String');
            $Validation->ApplyRule('Plugins.ReplyByEmail.SecretKey', 'String');
            $Punct =  preg_quote('!"#$%&\'()*+,-./:;<=>?@[\]^_`{|}~','`');
            if(!preg_match('`^[a-f0-9]{8}$`i',$Sender->Form->GetValue('Plugins.ReplyByEmail.AccountNo'))){
                $Validation->AddValidationResult('Plugins.ReplyByEmail.AccountNo', 'Account No should be 8 charater long hexadecimal.');
            }
            if(!preg_match('`^[a-z0-9'.$Punct.']{32}$`i',$Sender->Form->GetValue('Plugins.ReplyByEmail.SecretID'))){
                $Validation->AddValidationResult('Plugins.ReplyByEmail.SecretID', 'Secret ID should be 32 characters long with alphanumeric and punctuation characters');
            }
            if(!preg_match('`^[a-z0-9'.$Punct.']{32}$`i',$Sender->Form->GetValue('Plugins.ReplyByEmail.SecretKey'))){
                $Validation->AddValidationResult('Plugins.ReplyByEmail.SecretKey', 'Secret Key should be 32 characters long with alphanumeric and punctuation characters');
            }
            
            $Sender->Form->SetFormValue('Plugins.ReplyByEmail.NotifyUrl',str_replace(Url('/replypush/notify/',TRUE),'',$Sender->Form->GetValue('Plugins.ReplyByEmail.NotifyUrl')));

            $Validation->Validate();
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
            )
            ,            
            'Plugins.ReplyByEmail.NotifyUrl' => array(
                'Type' => 'string',
                'Control' => 'TextBox',
                'Default' => Url('/replypush/notify/'.C('Plugins.ReplyByEmail.NotifyUrl'),TRUE), 
                'Description' => 'Save this Notify Url <a href="http://replypush.com/profile">here</a>.',
                'LabelCode' => 'Notify Url',
                'Options'=>array('class'=>'InputBox BigInput','readonly'=>'readonly','value'=>Url('/replypush/notify/'.C('Plugins.ReplyByEmail.NotifyUrl'),TRUE))
            )
          )
        );

        $Sender->AddSideMenu('settings/replybyemail');
        $Sender->SetData('Title', T('ReplyByEmail.Title','Reply By Email'));
        $Config->RenderAll();
    }
    
    public function ActivityModel_BeforeSendNotification_Handler($Sender, $Args){
        error_reporting(-1);
        $SecretID = C('Plugins.ReplyByEmail.SecretID');
        $SecretKey = C('Plugins.ReplyByEmail.SecretKey');
        $AccountNo = C('Plugins.ReplyByEmail.AccountNo');
        
        if(!($SecretID && $SecretKey && $AccountNo))
            return;
            
        $ActivityType = GetValueR('Activity.ActivityType',$Args);
        $RecordID = 0;
        $ContentID = 0;
        $Title = '';
        $Location = '';
        $Content = '';
        $Link = '';
        switch($ActivityType){
            case 'DiscussionComment':
            case 'Comment':
                $ActivityType = 'Comment';
                $Type = 'comnt';
                $CommentModel = new CommentModel();
                $RecordID = GetValueR('Activity.RecordID',$Args);
                $Comment = $CommentModel->GetID($RecordID);
                $ContentID = $Comment->DiscussionID;
                $Title = GetValueR('Activity.Data.Name',$Args);
                $Location = GetValueR('Activity.Data.Category',$Args);
                $Content = Gdn_Format::To(SliceString($Comment->Body,1500),$Comment->Format);
                $Route = GetValueR('Activity.Route',$Args);
                $Link = Url($Route,TRUE);
                break;
            case 'WallPost':
                $Type = 'wpost';
                $Route = GetValueR('Activity.Route',$Args);
                $Link = Url($Route,TRUE);
                $Matches = Null;
                $ActivityModel = new ActivityModel();
                $RecordID = GetValueR('Activity.RecordID',$Args);
                $WallPost = $ActivityModel->GetID($RecordID);
                $Content = Gdn_Format::To(SliceString(GetValue('Story',$WallPost),1500),GetValue('Format',$WallPost));
                break;
            case 'ActivityComment':
                $Type = 'acomnt';
                $Comment = GetValue('Comment',$Args);
                $RecordID = $Comment->ActivityCommentID;
                $ContentID = $Comment->ActivityID;
                $Content = Gdn_Format::To(SliceString($Comment->Body,1500),$Comment->Format);
                break;
            case 'ConversationMessage':
                $Type = 'cmsg';
                $Route = GetValueR('Activity.Route',$Args);
                $Link = Url($Route,TRUE);
                $Matches = NULL;
                preg_match('`^/messages/([0-9]+)#([0-9]+)$`i',$Route,$Matches);
                if($Matches){
                    $ConversationMessageModel = new ConversationMessageModel();
                    $Message = $ConversationMessageModel->GetID($Matches[1]);
                    //LogMessage(__FILE__,__LINE__,__CLASS__,__METHOD__,var_export($Message,TRUE));
                    $Content = Gdn_Format::To(SliceString($Message->Body,1500),$Message->Format);
                    $RecordID = $Message->MessageID;
                    $ContentID = $Message->ConversationID;
                }
                break;
        }
        
        if($Type){
            if(!$RecordID)
                $RecordID = GetValueR('Activity.RecordID',$Args);
            $UserID = GetValueR('User.UserID',$Args,GetValueR('Activity.NotifyUserID',$Args));
            $User = Gdn::UserModel()->GetID($UserID);
            $FromUserID = GetValueR('Activity.ActivityUserID',$Args);
            $FromUser = Gdn::UserModel()->GetID($FromUserID);
            $Email = GetValueR('User.Email',$Args); 
            
            $TimeStamp = time();
            $RandomSalt = mt_rand();
            $HashMethod = C('Plugins.ReplyByEmail.HashMethod',in_array('sha1',hash_algos()) ? 'sha1': 'md5');
            
            $Data =   sprintf("%-8s%-8s%08x%08x%-8s%08x%08x", $AccountNo, $HashMethod, $FromUserID, $RecordID, $Type, $TimeStamp, $RandomSalt);
            LogMessage(__FILE__,__LINE__,__CLASS__,__METHOD__,var_export(array($UserID,$FromUserID,$Email),TRUE));

            $SecuredData = "$SecretID{$User->Email}".$Data;

            $KeyHashHash = hash_hmac($HashMethod, $SecuredData, $SecretKey, TRUE);

            $Identifier = base64_encode($Data.$KeyHashHash);
            
            if($ContentID){
                $References = Gdn::SQL()->Select('r.Refs')->From('ReplyPushRefs r')->Where(array('r.UserID'=>$UserID,'r.ContentID'=>$ContentID,'r.Type'=>$Type))->Get()->FirstRow();
                $New = !$References;
                $Loc = FALSE;
                $ReferencesNext = '<'.$Identifier.'@replypush.com>'.($New ? '': ' '.$References->Refs);
                
                if($New){
                    Gdn::SQL()->Insert('ReplyPushRefs', array('UserID'=>$UserID,'ContentID'=>$ContentID,'Type'=>$Type, 'Refs' => $ReferencesNext));
                    
                }else{
                    
                    Gdn::SQL()->Put('ReplyPushRefs', array('Refs' => $ReferencesNext), array('UserID'=>$UserID,'ContentID'=>$ContentID,'Type'=>$Type));
                }
                
            }
            
            $Email = GetValueR('Email',$Args); 
            $Email->PhpMailer->MessageID = '<'.$Identifier.'@replypush.com>';
            if($References){
                $Email->PhpMailer->AddCustomHeader('References: '.$References->Refs);
            }
            
            if($Email->PhpMailer->From==$User->Email){
                $Email->PhpMailer->From = 'post@replypush.com';
            }
            
            $Email->PhpMailer->AddReplyTo(
                'post@replypush.com', 
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
                $Email->PhpMailer->IsHtml();
                $Email->PhpMailer->Body = FormatString(T('ReplyByEmail.Msg.'.$ActivityType), $Vars);
                $Email->PhpMailer->AltBody = T('ReplyByEmail.Msg.TxtNotSupported');
            }
            
            $Sig = FormatString(T('ReplyByEmail.SigText'),array('Tag'=>'\n***** reply service *****\n\n'));
            $SigHtml = FormatString(T('ReplyByEmail.SigHtml',array('Tag'=>'<a href="http://replypush.com#rp-sig"></a>')));
            
            if(!empty($Email->PhpMailer->AltBody) && !C('Plugins.ReplyByEmail.LocaleOverride', TRUE))
                $Email->PhpMailer->AltBody .= $Sig;
            
            if($Email->PhpMailer->ContentType == 'text/html'){
                $Email->PhpMailer->Body = '<a href="http://replypush.com#rp-message"></a>'.$Email->PhpMailer->Body.$SigHtml;
            }else{
                $Email->PhpMailer->Body = '***** '.Gdn_Format::Text(Gdn::Request()->Host())." message *****\n\n".$Email->PhpMailer->Body.$Sig;
            }
        }
    }
    
    public function VanillaController_ReplyPush_Create($Sender, $Args){
        $Notification = $_POST;
        if(!empty($Notification)){
            //no message id no message
            if(!GetValue('msg_id',$Notification)){
                $this->Denied();
            }
            //check for duplicate message id
            if(Gdn::SQL()->Select('l.MessageID')->From('ReplyPushLog l')->Where('l.MessageID',GetValue('msg_id',$Notification))->Get()->FirstRow()){
                return;//ignore
            }
            $Ref = base64_decode(preg_replace('`@replypush\.com`','',$Notification['in_reply_to']));
            $RefData = substr($Ref,0,56);
            $RefHash = substr($Ref,56);
            $HashMethod = trim(substr($Ref,8,8));
            
            $SecretID = C('Plugins.ReplyByEmail.SecretID');
            $SecretKey = C('Plugins.ReplyByEmail.SecretKey');
            $AccountNo = C('Plugins.ReplyByEmail.AccountNo');
            
            $User = Gdn::UserModel()->GetByEmail(GetValue('from',$Notification));
            $SecuredData = "$SecretID{$User->Email}".$RefData;
            
            if($User && $HashMethod && $this->HashCmp(hash_hmac($HashMethod, $SecuredData, $SecretKey, TRUE),$RefHash)){
                $MessageData = str_split($RefData,8);
                $FromUserID = hexdec($MessageData[2]);
                $RecordID = hexdec($MessageData[3]);
                $Type = trim($MessageData[4]);
                $ContentID = 0;
                
                Gdn::Session()->Start($User->UserID);
                switch($Type){
                    case 'comnt':
                        $CommentModel = new CommentModel();
                        $Comment = $CommentModel->GetID($RecordID);
                        
                        if($Comment->InsertUserID!=$FromUserID){
                            $this->Denied();
                        }else{
                            $Fields = array('DiscussionID'=>$Comment->DiscussionID,'Body'=>$this->StripRP(GetValueR('content.text/html',$Notification,GetValueR('content.text/html',$Notification))));
                            $CommentModel->Save($Fields);
                        }
                        $ContentID = $Comment->DiscussionID;
                        break;
                    case 'wpost':
                        $ActivityModel = new ActivityModel();
                        $WallPost = $ActivityModel->GetID($RecordID);
                        //LogMessage(__FILE__,__LINE__,__CLASS__,__METHOD__,var_export(array($WallPost,$FromUserID),TRUE));
                        if(GetValue('InsertUserID',$WallPost)!=$FromUserID){
                            $this->Denied();
                        }else{
                            LogMessage(__FILE__,__LINE__,__CLASS__,__METHOD__,var_export(array('hi'),TRUE));
                            $Activity = array(
                                'ActivityID' => $RecordID,
                                'Body' => $this->StripRP(GetValueR('content.text/html',$Notification,GetValueR('content.text/html',$Notification))),
                                'Format' => 'Text'
                            );
                            
                            $ActivityModel->Comment($Activity);
                        }
                        break;
                    case 'acomnt':
                    
                        break;
                    case 'cmsg':
                        $ConversationModel = new ConversationModel();
                        $Conversation = $ConversationModel->GetID($RecordID);
                        if($Conversation->InsertUserID!=$FromUserID){
                            $this->Denied();
                        }else{
                            $ConversationMessageModel = new ConversationMessageModel();
                            $Message = array('ConversationID'=>$RecordID,'Body'=>$this->StripRP(GetValueR('content.text/html',$Notification,GetValueR('content.text/html',$Notification))));
                            $ConversationMessageModel->Save($Message);
                            $ContentID = $RecordID;
                        }
                        break;
                }
                
                if($ContentID){
                    $References = Gdn::SQL()->Select('r.Refs')->From('ReplyPushRefs r')->Where(array('r.UserID'=>$User->UserID,'r.ContentID'=>$ContentID,'r.Type'=>$Type))->Get()->FirstRow();
                    if($References){
                        $MessageID = GetValue('from_msg_id', $Notification);
                        //ensure message id not duplicated. 
                        if(strpos($References->Refs, $MessageID)===FALSE){
                            $ReferencesNext = $MessageID.' '.$References->Refs;
                            Gdn::SQL()->Put('ReplyPushRefs', array('Refs' => $ReferencesNext), array('UserID'=>$User->UserID,'ContentID'=>$ContentID,'Type'=>$Type));
                        }
                    }
                }
            }else{
                $this->Denied();
            }
            unset($Notification['content']);
            try{
                Gdn::SQL()->Database->BeginTransaction();
                Gdn::SQL()->Insert('ReplyPushLog', array('MessageID'=>GetValue('msg_id',$Notification), 'Message'=>Gdn_Format::Serialize($Notification)));
                Gdn::SQL()->Database->CommitTransaction();
            }
            catch(Exception $Ex) {
                Gdn::SQL()->Database->RollbackTransaction();
                throw $Ex;
            }
        }
    }
    
    public function Denied(){
        header("HTTP/1.0 403 Denied");
        exit();
    }
    
    public function HashCmp($A, $B){
        if (strlen($A) != strlen($B))
            return FALSE;
        $Result = 0;
        foreach(array_combine(str_split($A), str_split($B)) as $X => $Y){
            $Result |= ord($X) ^ ord($Y);
        }
        return $Result == 0;
    }
    
    public function StripRP($Msg){
        error_reporting(E_ALL);
        /** general spacers for time and date */
        $spacers = "[\\s,/\\.\\-]";

        /** matches times */
        $timePattern = "(?:[0-2])?[0-9]:[0-5][0-9](?::[0-5][0-9])?(?:(?:\\s)?[AP]M)?";

        /** matches day of the week */
        $dayPattern = "(?:(?:Mon(?:day)?)|(?:Tue(?:sday)?)|(?:Wed(?:nesday)?)|(?:Thu(?:rsday)?)|(?:Fri(?:day)?)|(?:Sat(?:urday)?)|(?:Sun(?:day)?))";

        /** matches day of the month (number and st, nd, rd, th) */
        $dayOfMonthPattern = "[0-3]?[0-9]" . $spacers . "*(?:(?:th)|(?:st)|(?:nd)|(?:rd))?";

        /** matches months (numeric and text) */
        $monthPattern = "(?:(?:Jan(?:uary)?)|(?:Feb(?:uary)?)|(?:Mar(?:ch)?)|(?:Apr(?:il)?)|(?:May)|(?:Jun(?:e)?)|(?:Jul(?:y)?)" .
            "|(?:Aug(?:ust)?)|(?:Sep(?:tember)?)|(?:Oct(?:ober)?)|(?:Nov(?:ember)?)|(?:Dec(?:ember)?)|(?:[0-1]?[0-9]))";

        /** matches years (only 1000's and 2000's, because we are matching emails) */
        $yearPattern = "(?:[1-2]?[0-9])[0-9][0-9]";

        /** matches a full date */
        $datePattern = "(?:" . $dayPattern . $spacers . "+)?(?:(?:" . $dayOfMonthPattern . $spacers . "+" . $monthPattern . ")|" .
            "(?:" . $monthPattern . $spacers . "+" . $dayOfMonthPattern . "))" .
            $spacers . "+" . $yearPattern;

        /** matches a date and time combo (in either order) */
        $dateTimePattern = "(?:" . $datePattern . "[\\s,]*(?:(?:at)|(?:@))?\\s*" . $timePattern . ")|" .
            "(?:" . $timePattern . "[\\s,]*(?:on)?\\s*". $datePattern . ")";

        /** matches a leading line such as
        * ----Original Message----
        * or simply
        * ------------------------
        */
        $leadInLine = "-+\\s*(?:Original(?:\\sMessage)?)?\\s*-+\\n";

        /** matches a header line indicating the date */
        $dateLine = "(?:(?:date)|(?:sent)|(?:time)):\\s*". $dateTimePattern . ".*\\n";

        /** matches a subject or address line */
        $subjectOrAddressLine = "((?:from)|(?:subject)|(?:b?cc)|(?:to))|:.*\\n";

        /** matches gmail style quoted text beginning, i.e.
        * On Mon Jun 7, 2010 at 8:50 PM, Simon wrote:
        */
        $gmailQuotedTextBeginning = "(On\\s+" . $dateTimePattern . ".*wrote:\\n)";


        /** matches the start of a quoted section of an email */
        $Regx = "~(?i)(?:(?:" . $leadInLine . ")?" .
            "(?:(?:" . $subjectOrAddressLine . ")|(?:" . $dateLine . ")){2,6})|(?:" .
            $gmailQuotedTextBeginning . ")~";
            
        if (preg_match_all($Regx, $Msg, $matches)) {
            foreach ($matches[0] as $k => $header) {
                $startPos = strpos($Msg, $header);
                $lookAhead = $k+1;
                $afterPos = array_key_exists($lookAhead, $matches[0]) ?
                    strpos($Msg, $matches[0][$lookAhead]) : strlen($Msg);

                $Msg = substr($Msg, 0, $startPos) . " " . substr($Msg, $afterPos);
            }
        }
            
        $Msg = preg_replace(array(
                '`<![^>]*>`',
                '`</?html[^>]*>`i',
                '`<head[^>]*>.*?</?head[^>]*>`is',
                '`</?body[^>]*>`i',
                '`\*\*\*\*\*\s*[^\s]*\s*message\s*\*\*\*\*\*.*$`isx',
                '`<a\s*(?=((.*?)href=["\']http://replypush.com#rp-message["\']))[^>]+></a>.*$`i',
                '`\*\*\*\*\*\s*reply\s*service\s*\*\*\*\*\*.*$`isx',
                '`<a\s*(?=((.*?)href=["\']http://replypush.com#rp-sig["\']))[^>]+></a>.*$`i',
                '`<blockquotes[^>]*>.*?</?blockquotes[^>]*>`is',
                '`(\n|</?br>)\s*sent\s+from\s+my\s+(iphone|ipad).*`i',
                '`(\n|</?br>)\s*[_]{4,}\n.*`',
                '`(\n|</?br>)\s*[-]{4,}\n.*`',
                '`(?:[a-z0-9!#$%&\'*+/=?^_\`{|}~-]+(?:\.[a-z0-9!#$%&\'*+/=?^_\`{|}~-]+)*|"(?:[\x01-\x08\x0b\x0c\x0e-\x1f\x21\x23-\x5b\x5d-\x7f]|\\[\x01-\x09\x0b\x0c\x0e-\x7f])*")@(?:(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?|\[(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?|[a-z0-9-]*[a-z0-9]:(?:[\x01-\x08\x0b\x0c\x0e-\x1f\x21-\x5a\x53-\x7f]|\\[\x01-\x09\x0b\x0c\x0e-\x7f])+)\])`i',
                '`^(\s|\n|</?br>)+`i',
                '`(\s|\n|</?br>|<[a-z]+( [^>]*)?>)+$`i'
            ),
            '',
            $Msg
        );
    
        return $Msg;
    }
    
    public function Base_BeforeLoadRoutes_Handler($Sender, &$Args){
        $this->DynamicRoute($Args['Routes'],'^replypush/notify(/'.C('Plugins.ReplyByEmail.NotifyUrl').'/?)$','vanilla/replypush$2','Internal', TRUE);
    }
    
    public function Base_BeforeBlockDetect_Handler($Sender,&$Args){
        $Args['BlockExceptions']['/replypush\/notify(\/'.C('Plugins.ReplyByEmail.NotifyUrl').'\/?)$/i']=Gdn_Dispatcher::BLOCK_NEVER;
    }
    
    public function DynamicRoute(&$Routes, $Route, $Destination, $Type = 'Internal', $Oneway = FALSE){
        $Key = str_replace('_','/',base64_encode($Route));
        $Routes[$Key] = array($Destination, $Type);
        if($Oneway && $Type == 'Internal'){
            if(strpos(strtolower($Destination), strtolower(Gdn::Request()->Path()))===0){
                Gdn::Dispatcher()->Dispatch('Default404');
            }
        }
    }
    
    public function Base_BeforeDispatch_Handler($Sender){
        if(C('Plugins.MyProfile.Version')!=$this->PluginInfo['Version'])
            $this->Structure();
    }

    public function Setup() {
        $this->Structure();
    }
    
    public function Structure(){
        Gdn::Structure()
            ->Table('ReplyPushLog')
            ->Column('MessageID','varchar(36)',FALSE,'primary')
            ->Column('Message','text')
            ->Set();
            
        Gdn::Structure()
            ->Table('ReplyPushRefs')
            ->Column('UserID', 'int(11)',FALSE,array('key'))
            ->Column('ContentID','int(11)',FALSE,'key')
            ->Column('Type','varchar(8)',FALSE,'key')
            ->Column('Refs','text')
            ->Set();
        Gdn_LibraryMap::ClearCache();    
        SaveToConfig('Plugins.MyProfile.Version', $this->PluginInfo['Version']);
    }
}
