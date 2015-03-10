<?php if (!defined('APPLICATION')) exit();

$Definition['ReplyByEmail.SigText']  = "{Tag}\n\n\n***** reply service *****\n\nYou can reply by the link provided, or reply directly to this email.\n\nPlease put your message directly ABOVE the quoted message.\n\nPlease ensure privacy of others.\n\nThank You.\n\n{SigID,text}";
$Definition['ReplyByEmail.SigHtml'] = '{Tag}<br><br><b>***** reply service *****</b><br><br><p><b>You can reply by the link provided, or reply directly to this email.</p><br><p><u>Please put your message directly ABOVE the quoted message.</u></p><p>Please ensure privacy of others.</p><br><p>Thank You.</b></p><br><p><small>[{SigID,text}]</small></p>';

$Definition['ReplyByEmail.Msg.TxtNotSupported'] = 'Only HTML email messages are supported, please use an HTML enabled email client, to view these messages.';

$Definition['ReplyByEmail.Msg.Comment'] = '{Headline}

<blockquote>{Content}</blockquote>

<p>You can check it out <a href="{Link}">here</a></p>
<br>
<p>Have a great day!</p>';

$Definition['ReplyByEmail.MsgAlt.Comment'] = '{Headline}

{Content}

You can check it out here:
{Link}

Have a great day!';

$Definition['ReplyByEmail.Msg.DiscussionComment'] = $Definition['ReplyByEmail.Msg.Comment'];
$Definition['ReplyByEmail.Msg.NewDiscussion'] = $Definition['ReplyByEmail.Msg.Comment'];
$Definition['ReplyByEmail.Msg.Discussion'] = $Definition['ReplyByEmail.Msg.Comment'];

$Definition['ReplyByEmail.Msg.WallPost'] = '<p>{Name,text} posted on your wall:</p>

<blockquote>{Content}</blockquote>

<p>You can check it out <a href="{Link}">here</a></p>
<br>
<p>Have a great day!</p>';


$Definition['ReplyByEmail.MsgAlt.WallPost'] = '{Name,text} posted on your wall:

{Content}

You can check it out here:
{Link}

Have a great day!';

$Definition['ReplyByEmail.Msg.ConversationMessage'] = '<p>{Name,text} sent you a message:</p>

<blockquote>{Content}</blockquote>

<p>You can check it out <a href="{Link}">here</a></p>
<br>
<p>Have a great day!</p>';

$Definition['ReplyByEmail.MsgAlt.ConversationMessage'] = '{Name,text} sent you a message:

{Content}

You can check it out here:
{Link}

Have a great day!';

$Definition['ReplyByEmail.ReplyErrorBody'] = 'Hello {Name},

Your reply by email response could not be processed, because of the following error(s):

{ErrorMessage}

Don\'t reply directly to this email. Instead you can try another reply to the original notification.

Thank You

[{SigID,text}]';

$Definition['ReplyByEmail.IncomingError.NoEOM'] = 'Could not find /eom so can\'t send message!  Make sure to end your reply with /eom (on new line) to use this service.';

$Definition['ReplyByEmail.HeadlineFormat.Comment'] = '<p>{Name,text} commented on &lsquo;{Title,text}&rsquo; in {Location,text}:</p>';
$Definition['ReplyByEmail.HeadlineFormat.Mention'] = '<p>{Name,text} mentioned you on &lsquo;{Title,text}&rsquo; in {Location,text}:</p>';
$Definition['ReplyByEmail.HeadlineFormat.Discussion'] = '<p>{Name,text} started a new discussion, &lsquo;{Title,text}&rsquo; in {Location,text}:</p>';
$Definition['ReplyByEmail.CollateDiscussion.Subject'] = '[{Site,text}] {Title,text} #{DiscussionID,integer}';
$Definition['ReplyByEmail.CollateMessage.Subject'] = '[{Site,text}] {UserName,text} send you a message #{ConversationID,integer}';

//do not edit bellow (used for reference)
$Definition['HeadlineFormat.Comment'] = '{ActivityUserID,user} commented on <a href="{Url,html}">{Data.Name,text}</a>';
$Definition['HeadlineFormat.DiscussionComment'] = $Definition['HeadlineFormat.Comment'];
$Definition['HeadlineFormat.Discussion'] = '{ActivityUserID,user} started a new discussion: <a href="{Url,html}">{Data.Name,text}</a>';
$Definition['HeadlineFormat.NewDiscussion'] = $Definition['HeadlineFormat.Discussion'];
$Definition['HeadlineFormat.Mention'] = '{ActivityUserID,user} mentioned you in <a href="{Url,html}">{Data.Name,text}</a>';
$Definition['HeadlineFormat.NotifyWallPost'] =  '{ActivityUserID,User} posted on your <a href="{Url,url}">wall</a>.';
$Definition['HeadlineFormat.ConversationMessage'] = '{ActivityUserID,User} sent you a <a href="{Url,html}">message</a>';
