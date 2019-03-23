#!/usr/bin/php -q
<?php

$debug=false; // Switch to true to output last email to test.txt

if($debug) {
	$myFile = "test.txt";
	$fh = fopen($myFile, 'w');
	fwrite($fh, "DEBUGGER -> Initiating...".date("Y-m-d, H:i:s")."\n\n");
}

if (isset($_SERVER['REMOTE_ADDR'])) {
	$visit=true; // Can't kill here; what if it's HTTP Post?
	foreach($_POST as $k=>$v)
		if(is_numeric($k)) unset($_POST[$k]);  // Don't get us killed by SSI on HTTP POST streams
}
else $visit=false;

$_SERVER['is_cli'] = ''; // Prevent SSI from displaying error due to REMOTE_ADDR being set (for HTTP POST)

$_SERVER['SERVER_SOFTWARE'] = '';
$_SERVER['SERVER_NAME'] = '';

if($debug) {
	fwrite($fh, "DEBUGGER -> Loading SSI...\n\n");
}

include_once(dirname(__FILE__) . '/SSI.php');
error_reporting(E_ALL);
loadLanguage('Errors', $language, false);
loadLanguage('index', $language, false);
loadLanguage('Post', $language, false);

// SETTINGS

// When a member emails the forum with a subject/topic that does not exist, a new topic will be created.
// Set this to the ID number of the board you want these topics created in.
$mailinglist_board=($modSettings['mailinglist_board']? $modSettings['mailinglist_board']:1);

// REQUIRES THE MAILING LIST MOD
// Automatically announce and email out new topics posted to the board via email.
// IMPORTANT: This will email EVERYONE in the board chosen above.  Make sure the group is small or you have a powerful server.
// This won't automatically announce topics posted directly to the board (the normal way).
$mailinglist_autoannounce=$modSettings['mailinglist_autoannounce'];

// Prefix to apply to new topic subjects, example "[Forum Board] Subject"
$mailinglist_prefix=$modSettings['mailinglist_prefix'];

if($debug) {
	fwrite($fh, "DEBUGGER -> Beginning email parse...");
}


if(isset($modSettings['mailinglist_postvar'])&&$modSettings['mailinglist_postvar']) {

	function fixFormData(&$dat) {
		if(is_array($dat)) {
			reset($dat);
			while(list($key, $val) = each($dat)) {
				if(is_array($dat[$key]))
					fixFormData($dat[$key], $fixSlashes, $fixEntities);
				else
					$dat[$key]=stripslashes($val);
			}
		}
		else {
			$dat = stripslashes($dat);
		}
	}
	
	// SMF adds its own slashes; like magic quotes
	fixFormData($_POST);
	if($debug) fwrite($fh, "DEBUGGER -> Nullifying slashes added by SMF...\n\n");
	
	// POST stream
	$data=file('php://input');
	
	$str="";
	foreach($_POST as $k=>$v)
			$str.="\n$k =>$v";
		
	$str.="\n\nAttempting to Parse Post Stream:\n";
	
	// Support for cloudmailin, which names its POST variables differently
	if(isset($_POST['message'])) {
    $data=explode("\n", $_POST['message']);
    unset($_POST);
    $str="";
  }
	elseif(!isset($data['body'])&&count($data)==1)
		parse_str($data[0], $data);
	
	foreach($data as $k=>$v)
		$str.="\ndata[$k] =>$v";
		
	if($debug) fwrite($fh, "\nAnalyzing HTTP POST Data\n\nOutput:\n\nVariables:\n".$str."\n\nRaw POST Stream:\n".implode("\n", $data));
	
	// Or Preparsed mail - must rebuild raw email; based on mailhooks.com 
	if(!isset($data['body'])&&isset($_POST)&&isset($_POST['body'])&&isset($_POST['from'])&&isset($_POST['subject'])) {
		
		if($debug) fwrite($fh, "\n\nUSING POST VARIABLES... ");
		
		// Incase not everyone labels their POST data like mailhooks.com
		if(strpos($_POST['body'], '</div>')!==false&&!isset($_POST['body_html'])) {
			$_POST['body_html']=$_POST['body'];
			$_POST['body']=strip_tags($_POST['body']);
		}
		
		$data=array(
"Subject: ".$_POST['subject'],
"From: ".$_POST['from'],
"Content-Type: multipart/alternative; boundary=00248c0eecdc05e53a049983e219",
"",
"--00248c0eecdc05e53a049983e219
Content-Type: text/plain; charset=ISO-8859-1
".$_POST['body']);
		if(isset($_POST['body_html'])) {
			$data[]="
--00248c0eecdc05e53a049983e219
Content-Type: text/html; charset=ISO-8859-1
".$_POST['body_html'];

		}
		
		$data[]="
--00248c0eecdc05e53a049983e219--";

	}
	elseif($data) {
		if($debug) fwrite($fh, "\nUSING POST STREAM... \n");
	}
	
	if(!$data) {
		if($debug) fwrite($fh, "\nERROR: EMPTY POST... \n");
		die;
	}
	
	// For some reason, the MySQL database quits at this point when reading HTTP POST
	// Connect to the MySQL database if not already connected, using SSI code
	//loadDatabase();
	// Not needed in SMF2, actually inhibits new queries
	
	
}
else { // Otherwise just email piping - the best way
	
	if($visit) {
		if($debug) fwrite($fh, "\nError: Browser detected.  HTTP not enabled; only email piping allowed!\n\n");
		die; // Only for use by piping
	}
	
	$data = file('php://stdin');
	
	if($debug) fwrite($fh, "\nDEBUGGER -> Reading standard input (email piping)...\n\n");
	
}

$attachIDs=array();
$headers = array();
$header_data = '';
for ($i = 0, $n = count($data); $i < $n; $i++)
{
	if (preg_match('~^(From|To|Cc|Bcc|Subject|Content-Type|In-Reply-To|Message-ID|X-Mailer|Content-Transfer-Encoding): (.*)$~i', $data[$i], $match) != 0)
		$headers[strtolower($match[1])] = trim($match[2]);

	if (preg_match('~^References: (.*)$~i', $data[$i], $match) != 0)
		$headers['references'][] = trim($match[1]);

	$header_data .= $data[$i];

	if (trim($data[$i]) == '')
		break;
}

// Fix headers special chars; SMF topics always encode them; SMF also doesn't escape things before queries
if(!empty($headers['subject'])) {

	if(function_exists('iconv_mime_decode')) { //PHP5 only
		$subj=iconv_mime_decode($smcFunc['htmlspecialchars']($headers['subject']));
		$headers['subject']=escapestring__recursive(utf8_encode($subj));
	}
	elseif(strpos($headers['subject'], '=?UTF-8?')!==false) { //Leaves underscores instead of spaces for ISO encoding
		$subj=mb_decode_mimeheader($smcFunc['htmlspecialchars']($headers['subject']));
		$headers['subject']=escapestring__recursive(utf8_encode($subj));
	}
	else { //Cannot decode UTF8
		$subj=imap_mime_header_decode($smcFunc['htmlspecialchars']($headers['subject']));
		$headers['subject']=escapestring__recursive(utf8_encode($subj[0]->text));
	}
		
}
else $headers['subject']='';


// Get the users email.
$headers['from'] = (!empty($headers['from'])) ? $headers['from'] : '';
preg_match('~(([^\<]*?)\s*\<)*([0-9A-Za-z=_+\-/][0-9A-Za-z=_\'+\-/\.]*@[\w\-]+(\.[\w\-]+)*(\.[\w]{2,6}))(\s*\((.*)\))*~i', $headers['from'], $match);
$email = (!empty($match[3])) ? $match[3] : '';
$name = $email;
if(!empty($match[2])) $name=$match[2];
elseif(!empty($match[7])) $name=$match[7];

if($name) {

	if(function_exists('iconv_mime_decode')) { //PHP5 only
		$s=iconv_mime_decode($smcFunc['htmlspecialchars']($name));
		$name=escapestring__recursive(utf8_encode($s));
	}
	elseif(strpos($headers['subject'], '=?UTF-8?')!==false) { //Leaves underscores instead of spaces for ISO encoding
		$s=mb_decode_mimeheader($smcFunc['htmlspecialchars']($name));
		$name=escapestring__recursive(utf8_encode($s));
	}
	else { //Cannot decode UTF8
		$s=imap_mime_header_decode($smcFunc['htmlspecialchars']($name));
		$name=escapestring__recursive(utf8_encode($s[0]->text));
	}
		
}

if($headers['subject']=='') {
	emailError($txt['no_subject']);
}

// Get the message body...
$text = ''; $skippingHeaders=0;
for (; $i < $n; $i++) {
  // Remove spam detection flags and content preview
  if($skippingHeaders!=1) {
    if($skippingHeaders==0&&strpos($data[$i], 'X-Spam-Flag:')!==false) { // Spam headers detected
      $skippingHeaders=1;
      $text=''; // Remove all clutter previously
      continue;
    }
    else $text .= $data[$i];
  }
  elseif(trim($data[$i])=='') $skippingHeaders=2; // End of spam headers
}
	
// Some headers in message body?
if(preg_match('~X-Mailer: (.*)~i', $text, $match) != 0)
	$headers['x-mailer']=$match[1];
	
// What headers do we have now?
if($debug) {
	foreach($headers as $k=>$v) 
		fwrite($fh, "Header[$k] = $v\n");
}

if($debug) fwrite($fh, "\nSending to Board: $mailinglist_board, with prefix `$mailinglist_prefix`\n");

if(isset($headers['cc'])) $headers['to'].=','.$headers['cc'];
if(isset($headers['bcc'])) $headers['to'].=','.$headers['bcc'];
preg_match_all('~([0-9A-Za-z=_+\-/][0-9A-Za-z=_\'+\-/\.]*@[\w\-]+(\.[\w\-]+)*(\.[\w]{2,6}))~i', $headers['to'], $m, PREG_SET_ORDER);
$to=array();
foreach($m as $match)
	if(!empty($match[1])) $to[]=$match[1];

// Route this to the correct board depending on email sent to
// Defaults to the webmaster_email board if none found
if(!in_array($webmaster_email, $to)) {
	if($debug) fwrite($fh, "Non-Default Address Used.  Checking ".$headers['to']."\n");
	$i=1; $headers['to']=$webmaster_email;
	while(isset($modSettings['mailinglist_board'.$i])&&!in_array($headers['to'], $to)) {
		if($debug) fwrite($fh, "Comparing `".$modSettings['mailinglist_email'.$i]."` to `".implode(",", $to)."`\n");
		if(in_array($modSettings['mailinglist_email'.$i], $to)) {
			$webmaster_email=$modSettings['mailinglist_email'.$i]; // Send from new email address
			$headers['to']=$modSettings['mailinglist_email'.$i];
			$mailinglist_board=$modSettings['mailinglist_board'.$i];
			if(isset($modSettings['mailinglist_prefix'.$i]))
				$mailinglist_prefix=$modSettings['mailinglist_prefix'.$i];
			if($debug) fwrite($fh, "Address Detected: ".$headers['to']." -> New Board: $mailinglist_board, with prefix `$mailinglist_prefix`\n");
			break;
		}
		if($debug) fwrite($fh, "Not `".$modSettings['mailinglist_email'.$i]."`.\n");
		$i++;
	}
}
else $headers['to']=$webmaster_email;
	

/* See if topic exists */

// Isolate topic subject; remove re prefix
$tsubject=preg_replace("`(^re: )`is", "", $headers['subject']);

$request=$smcFunc['db_query']('', "select m.id_topic, t.id_member_started, b.member_groups, m.poster_email, m.subject
	from {db_prefix}messages as m CROSS JOIN {db_prefix}topics as t CROSS JOIN {db_prefix}boards AS b CROSS JOIN {db_prefix}settings as s
	WHERE m.id_topic=t.id_topic
	AND (m.subject='".($tsubject)."' or m.subject like 'fw: ".($tsubject)."' or m.subject like 'fwd: ".($tsubject)."' or m.subject like '$mailinglist_prefix $tsubject')
	AND b.id_board = t.id_board
	AND m.id_msg = t.id_first_msg
	AND s.variable='recycle_board'
	AND s.value!=b.id_board
	ORDER BY m.id_msg DESC
	LIMIT 1");
if(preg_match('`(^re: )`is', $headers['subject'], $match)!=0 // Must be a reply
	&&$smcFunc['db_num_rows']($request)>0) {
		$fetch=$smcFunc['db_fetch_assoc']($request);
		$tsubject=$fetch['subject'];
}
else  {
$request=$smcFunc['db_query']('', "select b.member_groups
	from {db_prefix}boards AS b CROSS JOIN {db_prefix}settings as s
	WHERE b.id_board = $mailinglist_board
	AND s.variable='recycle_board'
	AND s.value!=b.id_board
	LIMIT 1");
	$fetch=$smcFunc['db_fetch_assoc']($request);
	$fetch["id_topic"]=0; // Create new topic
	$fetch["id_member_started"]=-1;
	$fetch["poster_email"]="";
	if($mailinglist_prefix) $tsubject=$mailinglist_prefix." ".$tsubject;
}
$smcFunc['db_free_result']($request);

/* Check for email now, avoid looking at message if it's not a user. */
if($debug) fwrite($fh, "Isolated Email From = $email, Name = $name\n");

// Find User via email
$request = $smcFunc['db_query']('', "
	SELECT *
	FROM {db_prefix}members
	WHERE email_address = '".escapestring__recursive($email)."'
		AND is_activated = 1
	LIMIT 1");

$bounceback=false;
if ($smcFunc['db_num_rows']($request) == 0) {
	//if($modSettings['mailinglist_allowguests']&&$modSettings['mailinglist_ticketsys']&&($fetch['id_topic']==0||$fetch['poster_email']==$email)) {
	// Allow non-members to reply in general, even to threads they didn't start
	if($modSettings['mailinglist_allowguests']||($modSettings['mailinglist_ticketsys']&&($fetch['id_topic']==0||$fetch['poster_email']==$email))) {
		$user_settings=array('real_name'=>$name, 'email_address'=>$email, 'id_member'=>0, 'id_group'=>-1, 'additional_groups'=>'-1', 'id_post_group'=>-1, 'member_ip'=>$user_info['ip']);
		if($debug) fwrite($fh, "DEBUGGER -> Non-Member: ".$user_settings['real_name']."\n\n");
		if($fetch['id_topic']==0&&(strpos($tsubject, ":")!==false||strpos($tsubject, "fail")!==false||strpos($tsubject, "delivery")!==false)) {
			$bounceback=true;
			if($debug) fwrite($fh, "DEBUGGER -> Potential delivery fail bounceback, not sending notifications...\n\n");
		}
	}
	else {
		$smcFunc['db_free_result']($request);
		emailError($txt['error_bad_email']." ".$txt['invalid_username'], 
		// Don't want infinite bounceback
		($fetch['id_topic']==0&&(strpos($tsubject, ":")!==false||strpos($tsubject, "fail")!==false||strpos($tsubject, "delivery")!==false)? 
			false:true));
	}
}
else {
	$user_settings = $smcFunc['db_fetch_assoc']($request);
	if($debug) fwrite($fh, "DEBUGGER -> User Found: ".$user_settings['real_name']."\n\n");
}

$id_member = $user_settings['id_member'];
$smcFunc['db_free_result']($request);

$cur_language = empty($user_settings['lngfile']) || empty($modSettings['userLanguage']) ? $language : $user_settings['lngfile'];
loadLanguage('Errors', $cur_language, false);
loadLanguage('Post', $cur_language, false);

// Check what groups they are in.
if (empty($user_settings['additional_groups']))
	$user_info['groups'] = array($user_settings['id_group'], $user_settings['id_post_group']);
else
	$user_info['groups'] = array_merge(
		array($user_settings['id_group'], $user_settings['id_post_group']),
		explode(',', $user_settings['additional_groups'])
	);

	

//////// DECODE/ISOLATE MESSAGE and REMOVE HEADERS //////////////
$encoding='quoted-printable'; $html=false; $charset=false;

if($bounceback) {
	$text=explode("--", $text);
	$text=$text[0];
}
else {
// Get boundaries
if(isset($headers['content-type'])&&preg_match('~alternative;[\s]*boundary=["]*([^"\s]*)["]*~i', $headers['content-type'], $match)!=0)
	$boundary=$match[1];
elseif(preg_match('~Content-Type\: multipart\/alternative;[\s]*boundary=["]*([^"\s]*)["]*~i', $header_data, $match)!=0)
	$boundary=$match[1];
elseif(preg_match('~Content-Type\: multipart\/alternative;[\s]*boundary=["]*([^"\s]*)["]*~i', $text, $match)!=0)
	$boundary=$match[1];
else $boundary=false;


// Get attachments boundaries
if(isset($headers['content-type'])&&preg_match('~mixed;[\s]*boundary=["]*([^"\s]*)["]*~i', $headers['content-type'], $match)!=0)
	$attboundary=$match[1];
elseif(preg_match('~Content-Type\: multipart\/mixed;[\s]*boundary=["]*([^"\s]*)["]*~i', $header_data, $match)!=0)
	$attboundary=$match[1];
elseif(preg_match('~Content-Type\: multipart\/mixed;[\s]*boundary=["]*([^"\s]*)["]*~i', $text, $match)!=0)
	$attboundary=$match[1];
else $attboundary=false;

// Debug
if($debug)
	fwrite($fh, "DEBUGGER -> Message Boundary: $boundary \nDEBUGGER -> Attachments Boundary: $attboundary\n\n");

// Separate attachments out of email if possible
if($attboundary!==false&&count($match=explode('--'.$attboundary, $text))>1) {
	require_once($sourcedir . '/Subs-Post.php');
	
	// Borrowed from Subs-Post, modified to use file stored in string text.
		function makeAttach(&$attachmentOptions)
		{
			global $db_prefix, $modSettings, $sourcedir, $txt, $smcFunc;
			
			// We need to know where this thing is going.
			if (!empty($modSettings['currentAttachmentUploadDir']))
			{
				if (!is_array($modSettings['attachmentUploadDir']))
					$modSettings['attachmentUploadDir'] = unserialize($modSettings['attachmentUploadDir']);
		
				// Just use the current path for temp files.
				$attach_dir = $modSettings['attachmentUploadDir'][$modSettings['currentAttachmentUploadDir']];
				$id_folder = $modSettings['currentAttachmentUploadDir'];
			}
			else
			{
				$attach_dir = $modSettings['attachmentUploadDir'];
				$id_folder = 1;
			}
		
			$attachmentOptions['errors'] = array();
			if (!isset($attachmentOptions['post']))
				$attachmentOptions['post'] = 0;
		
			// Get the hash if no hash has been given yet.
			if (empty($attachmentOptions['file_hash']))
				$attachmentOptions['file_hash'] = getAttachmentFilename($attachmentOptions['name'], false, null, true);
		
			// Is the file too big?
			if (!empty($modSettings['attachmentSizeLimit']) && $attachmentOptions['size'] > $modSettings['attachmentSizeLimit'] * 1024)
				$attachmentOptions['errors'][] = 'too_large';
		
			if (!empty($modSettings['attachmentCheckExtensions']))
			{
				$allowed = explode(',', strtolower($modSettings['attachmentExtensions']));
				foreach ($allowed as $k => $dummy)
					$allowed[$k] = trim($dummy);
		
				if (!in_array(strtolower(substr(strrchr($attachmentOptions['name'], '.'), 1)), $allowed))
					$attachmentOptions['errors'][] = 'bad_extension';
			}
		
			if (!empty($modSettings['attachmentDirSizeLimit']))
			{
				// Make sure the directory isn't full.
				$dirSize = 0;
				$dir = @opendir($attach_dir) or emailError($txt['cant_access_upload_path']);
				while ($file = readdir($dir))
				{
					if ($file == '.' || $file == '..')
						continue;
		
					if (preg_match('~^post_tmp_\d+_\d+$~', $file) != 0)
					{
						// Temp file is more than 5 hours old!
						if (filemtime($attach_dir . '/' . $file) < time() - 18000)
							@unlink($attach_dir . '/' . $file);
						continue;
					}
		
					$dirSize += filesize($attach_dir . '/' . $file);
				}
				closedir($dir);
		
				// Too big!  Maybe you could zip it or something...
				if ($attachmentOptions['size'] + $dirSize > $modSettings['attachmentDirSizeLimit'] * 1024)
					$attachmentOptions['errors'][] = 'directory_full';
			}
		
			// Check if the file already exists.... (for those who do not encrypt their filenames...)
			if (empty($modSettings['attachmentEncryptFilenames']))
			{
				// Make sure they aren't trying to upload a nasty file.
				$disabledFiles = array('con', 'com1', 'com2', 'com3', 'com4', 'prn', 'aux', 'lpt1', '.htaccess', 'index.php');
				if (in_array(strtolower(basename($attachmentOptions['name'])), $disabledFiles))
					$attachmentOptions['errors'][] = 'bad_filename';
		
				// Check if there's another file with that name...
				$request = $smcFunc['db_query']('', '
					SELECT id_attach
					FROM {db_prefix}attachments
					WHERE filename = {string:filename}
					LIMIT 1',
					array(
						'filename' => strtolower($attachmentOptions['name']),
					)
				);
				if ($smcFunc['db_num_rows']($request) > 0)
					$attachmentOptions['errors'][] = 'taken_filename';
				$smcFunc['db_free_result']($request);
			}
		
			if (!empty($attachmentOptions['errors']))
				return false;
		
			if (!is_writable($attach_dir))
				emailError($txt['attachments_no_write']);
				
			// Assuming no-one set the extension let's take a look at it.
			if (empty($attachmentOptions['fileext']))
			{
				$attachmentOptions['fileext'] = strtolower(strrpos($attachmentOptions['name'], '.') !== false ? substr($attachmentOptions['name'], strrpos($attachmentOptions['name'], '.') + 1) : '');
				if (strlen($attachmentOptions['fileext']) > 8 || '.' . $attachmentOptions['fileext'] == $attachmentOptions['name'])
					$attachmentOptions['fileext'] = '';
			}
			
			$smcFunc['db_insert']('',
				'{db_prefix}attachments',
				array(
					'id_folder' => 'int', 'id_msg' => 'int', 'filename' => 'string-255', 'file_hash' => 'string-40', 'fileext' => 'string-8',
					'size' => 'int', 'width' => 'int', 'height' => 'int',
					'mime_type' => 'string-20', 'approved' => 'int',
				),
				array(
					$id_folder, (int) $attachmentOptions['post'], $attachmentOptions['name'], $attachmentOptions['file_hash'], $attachmentOptions['fileext'],
					(int) $attachmentOptions['size'], (empty($attachmentOptions['width']) ? 0 : (int) $attachmentOptions['width']), (empty($attachmentOptions['height']) ? '0' : (int) $attachmentOptions['height']),
					(!empty($attachmentOptions['mime_type']) ? $attachmentOptions['mime_type'] : ''), (int) $attachmentOptions['approved'],
				),
				array('id_attach')
			);
			$attachmentOptions['id'] = $smcFunc['db_insert_id']('{db_prefix}attachments', 'id_attach');
		
			if (empty($attachmentOptions['id']))
				return false;
		
			$attachmentOptions['destination'] = getAttachmentFilename(basename($attachmentOptions['name']), $attachmentOptions['id'], $id_folder, false, $attachmentOptions['file_hash']);
			
			$attachWrite = fopen($attachmentOptions['destination'], 'w');
			fwrite($attachWrite, $attachmentOptions['contents']);
			fclose($attachWrite);
		
			return true;
		}
	
	foreach($match as $k=>$v)
		if($k==0) continue;
		elseif($k==1) {
			$text=$v;
			if($debug) {
				fwrite($fh, "DEBUGGER -> Message Body:\n".$text);
				fwrite($fh, "DEBUGGER -> Attachments Saved:\n");
			}
		}
		elseif(strpos($v, 'Content-Disposition: attachment')!==false) {
      if($debug) fwrite($fh, "DEBUGGER -> Attachment Preview:\n".substr($v, 0, 600)."...\n");
      
			// Separate Headers
			if(count($mm=preg_split('~(\r\n\r\n|\r\r|\n\n)~', $v, 2))>1)
				$v=$mm[1];
			else {
				if($debug) fwrite($fh, "DEBUGGER -> Attach Error: No Header\n");
				continue;
			}
			
			// Get filename
			if(preg_match('~filename=["]*([^"\n\r]*)["]*~i', $mm[0], $m)!=0)
				$filename=$m[1];
			else {
				if($debug) fwrite($fh, "DEBUGGER -> Attach Error: No Filename\n");
				continue;
			}
			
			// Decode
			if(stripos($mm[0], 'Content-Transfer-Encoding: quoted-printable')!==false) {
				$v=str_replace("=A0", " ", $v); // quoted_printable_decode stops when it hits =A0
				$v=str_replace("=C2", " ", $v); // gmail only, excess linebreaks
				$v=str_replace("=20", " ", $v); // gmail only, excess linebreaks
				$v=str_replace("=92", "'", $v); // gmail only, fancy quotes that crash the script
				$v=quoted_printable_decode($v);
			}
			elseif(stripos($mm[0], 'Content-Transfer-Encoding: base64')!==false) {
				$v=base64_decode($v);
			}
			
			$attachmentOptions = array(
				 'poster' => $id_member,
				 'name' => $filename,
				 'contents' => $v,
				 'size' => strlen($v),
				 'approved' => 1,
			 );
	
			if(makeAttach($attachmentOptions))
			 {
				 $attachIDs[] = $attachmentOptions['id'];
			 }
			else
			{
				if (in_array('too_large', $attachmentOptions['errors']))
					emailError(str_replace('%1$d', $modSettings['attachmentSizeLimit'], $txt['file_too_big']));
				if (in_array('bad_extension', $attachmentOptions['errors']))
					emailError($attachmentOptions['name'] . ".\n" . $txt['cant_upload_type'] . ' ' . $modSettings['attachmentExtensions'] . '.');
				if (in_array('directory_full', $attachmentOptions['errors']))
					emailError($txt['ran_out_of_space']);
				if (in_array('bad_filename', $attachmentOptions['errors']))
					emailError(basename($attachmentOptions['name']) . ".\n" . $txt['restricted_filename'] . '.');
				if (in_array('taken_filename', $attachmentOptions['errors']))
					emailError($txt['filename_exists']);
			}
			
			if($debug) fwrite($fh, "\n$filename (".$attachmentOptions['id'].")\n");
		}
			
}
elseif($debug) fwrite($fh, "DEBUGGER -> Message Body:\n".$text);

// Prefer HTML?
$contType=0;
if($modSettings['mailinglist_htmlemails']&&preg_match('~(Content-Type\: text\/html)(;\s*charset=[\"]*([^\"\s]*)[\"]*)*~i', $text, $match)!=0) {
	$contType=strpos($text, $match[1].$match[2])+strlen($match[1].$match[2]);
	
	$html=true;
	if(isset($match[3])) $charset=$match[3];
	
	if($debug) fwrite($fh, "\n\nDEBUGGER -> CONTENT-TYPE HTML and CHARSET $charset \n\n");
}
// Prefer clean over html
elseif(preg_match('~(Content-Type\: text\/plain)(;\s*charset=[\"]*([^\"\s]*)[\"]*)*~i', $text, $match)!=0) {
	$contType=strpos($text, $match[1].$match[2])+strlen($match[1].$match[2]);
	if(isset($match[3])) $charset=$match[3];
	
	if($debug) fwrite($fh, "\n\nDEBUGGER -> CONTENT-TYPE PLAINTEXT and CHARSET $charset \n\n");
}
// Otherwise deal with whatever (usually bad HTML)
elseif(preg_match('~(Content-Type\: [^;\s]*)(;\s*charset=[\"]*([^\"\s]*)[\"]*)*~i', $text, $match)!=0) {
	$contType=strpos($text, $match[1].$match[2])+strlen($match[1].$match[2]);
	
	$html=true;
	if(isset($match[3])) $charset=$match[3];
	
	if($debug) fwrite($fh, "\n\nDEBUGGER -> CONTENT-TYPE UNKNOWN: ".$match[2]." \n\n");
}
elseif(isset($headers['content-type'])) {
	if(preg_match('~(text\/plain)(;\s*charset=[\"]*([^\"\s]*)[\"]*)*~i', $headers['content-type'], $match)!=0) {
		if(isset($match[3])) $charset=$match[3];
	}
	elseif(preg_match('~(text\/html)(;\s*charset=[\"]*([^\"\s]*)[\"]*)*~i', $headers['content-type'], $match)!=0) {
		$html=true;
		if(isset($match[3])) $charset=$match[3];
	}
	
	if($debug) fwrite($fh, "\n\nDEBUGGER -> CONTENT-TYPE NOT MULTIPART, USING HEADER: ".$headers['content-type']."\n CONTENT-TYPE ".($html? "HTML":"PLAINTEXT")." and CHARSET $charset\n\n");
}

// Save encoding details the few lines before the Content-Type incase
$oldText=substr($text, 0, $contType);
if($boundary!==false&&strpos($oldText, '--'.$boundary)!==false)
	$oldText=substr($oldText, strrpos($oldText, '--'.$boundary));

// Now crop off the top where Content-Type is
$text=substr($text, $contType);

// More elegant - detect headers by first empty line
// Updated to detect first line w/o colon, as some email clients don't separate this way
$original = $oldText.$text;
if(count($mm=preg_split('~(\r\n|\r|\n)~', $original))>1) {
  if($debug) fwrite($fh, "\n\nDEBUGGER -> Checking that line break exists between headers and content.\n\n");
  $foundHeaders=false;
  for($i=0; $i<count($mm); $i++) {
    if(!$foundHeaders)
    {
      if(strpos($mm[$i], ":")!==false) $foundHeaders=true;
    }
    else {
      if($mm[$i]=="") {
        if($debug) fwrite($fh, "Email format is fine, linebreak exists between headers and content.\n\n");
        break; // We're fine
      }
      if(strpos($mm[$i], ":")===false) {
        if($debug) fwrite($fh, "Fixing email format.  Adding linebreak between headers and content.\n\n");
        $mm[$i] = "\r\n".$mm[$i];
        $original = implode("\r\n", $mm);
        break;
      }
    }
  }
}
if($contType>0&&count($mm=preg_split('~(\r\n\r\n|\r\r|\n\n)~', $original, 2))>1) {
  $text=$mm[1];
  $oldText=$mm[0];
  unset($mm);
}
unset($original);
if($debug) fwrite($fh, "\n\nDEBUGGER -> HEADERS PULLED OUT FOR CURRENT MESSAGE FORMAT: $oldText\n\n");

// End of messages with boundary defined (gmail)
if($boundary!==false&&($temp=strpos($text, '--'.$boundary))!==false)
	$text=substr($text, 0, $temp);
// End of message generic
elseif(count($match=preg_split('~(--[0-9\-\=]{6}[0-9\-\=]+:*[0-9\-]+)~i', $text))>1)
	$text=$match[0];
// Generic gmail
elseif(count($match=preg_split('~(--[-]*=_[A-Z0-9_]+\.[A-Z0-9]+[-]*)~i', $text))>1)
	$text=$match[0];
// End of base64 messages
elseif(count($match=preg_split('~(--[-]*part[A-Z0-9_]+-boundary-[0-9]+-[0-9]+[-]*)~i', $text))>1)
	$text=$match[0];
	
if($debug) fwrite($fh, "\n\nDEBUGGER -> SELECTING EMAIL FORMAT: ".($html? "HTML":"PLAINTEXT")." \n\n".$text);

// Now crop off the headers from the top if any left

// Content Transfer Types (default quoted-printable, comcast uses 7bit)
if(preg_match('~Content-Transfer-Encoding: (.*)~i', $text, $match) != 0) {
	$encoding=trim($match[1]);
	$text=substr($text, strpos($text, 'Content-Transfer-Encoding: '.$encoding)+strlen('Content-Transfer-Encoding: '.$encoding));
	
	if($debug) fwrite($fh, "\n\nDEBUGGER -> ENCODING DETECTED IN TEXT: $encoding \n\n");
}
elseif(preg_match_all('~Content-Transfer-Encoding: (.*)~i', $oldText, $match, PREG_SET_ORDER) != 0) {
	$encoding=$match[count($match)-1][1];
	
	if($debug) fwrite($fh, "\n\nDEBUGGER -> ENCODING DETECTED IN LINES BEFORE CONTENT-TYPE: $encoding \n\n");
}
elseif(isset($headers['content-transfer-encoding'])) {
	$encoding=$headers['content-transfer-encoding'];
	
	if($debug) fwrite($fh, "\n\nDEBUGGER -> ENCODING DETECTED IN HEADER: $encoding \n\n");
}
else $encoding="none";

// Decode isolated message
if($encoding=='quoted-printable') {
	$text=str_replace("=A0", " ", $text); // quoted_printable_decode stops when it hits =A0
	$text=str_replace("=C2", " ", $text); // gmail only, excess linebreaks
	$text=str_replace("=20", " ", $text); // gmail only, excess linebreaks
	$text=str_replace("=92", "'", $text); // gmail only, fancy quotes that crash the script
	$text=quoted_printable_decode($text);
}
elseif($encoding=='base64') {
	$text=base64_decode($text);
}
else if(strpos($text, '&#39;')!==false || strpos($text, '&#039;')!==false || strpos($text, '&gt;')!==false || strpos($text, '&lt;')!==false || strpos($text, '&amp;')!==false || strpos($text, '&quot;')!==false) {
	if($debug) fwrite($fh, "\n\nDEBUGGER -> HTML Entities detected. Decoding... \n\n".$text);
	$text = html_entity_decode($text, ENT_QUOTES);
}

if($debug) fwrite($fh, "\n\nDEBUGGER -> DECODING EMAIL: $encoding \n\n".$text);

//////// EXTRACT/FIX REPLY ////////////

// Don't extract if forwarded message
$fw=false;
if(preg_match("`(^fwd?: )`is", $headers['subject'], $match)!=0) $fw=true;
elseif($fetch['id_topic']==0) $fw=true; // Don't need to extract if new topic (no replies)

// Fix all html emails, even if not stated as HTML
if(!$html&&strpos($text, '</div>')!==false) {
	$html=true;
	
	if($debug) fwrite($fh, "\n\nDEBUGGER -> ERROR: HTML DETECTED IN NON-HTML EMAIL \n\n".$text);
}
	
if($html) {

	// Extract HTML body
	$split_text = preg_split('~(\<body)([^\>]*)\>~i', $text); // Opening tag
	if (count($split_text) > 1)
       $text = $split_text[1];
       
    $split_text = preg_split('~(\</body)([^\>]*)\>~i', $text); // Closing tag
	if (count($split_text) > 1)
       $text = $split_text[0];
	
	if($debug) fwrite($fh, "\n\nDEBUGGER -> HTML BODY EXTRACTED: \n\n".$text);
}

if(!$fw) { // Extract reply only if not forwarded message

	// Gmail quoted replies
	if($html&&($temp=strpos($text, '<div class="gmail_quote">'))!==false) {
		$text=substr($text, 0, $temp);
		if($debug) fwrite($fh, "\n\nDEBUGGER -> EXTRACTION VIA GMAIL QUOTE TAG \n");
	}
	// Reply divider for HTML in general
	if($html&&count($match=preg_split('~(\<hr[^\>]*\>)~i', $text))>1) {
		$text=$match[0];
		if($debug) fwrite($fh, "\n\nDEBUGGER -> EXTRACTION VIA HR TAG \n");
	}
	// Common divider
	if(count($match=preg_split('~[-]{2,5}\s?[Oo]riginal [Mm]essage\s?[-]{2,5}~', $text))>1) {
		$text=$match[0];
		if($debug) fwrite($fh, "\n\nDEBUGGER -> EXTRACTION VIA --Original Message-- \n");
	}
	
	// Reply Divider for Yahoo Email
	if(count($match=preg_split('~((______)_*\s*From)~i', $text))>1) {
		$text=$match[0];
		if($debug) fwrite($fh, "\n\nDEBUGGER -> EXTRACTION VIA ________ Divider \n");
	}
	
	// Gmail
	if(count($match=preg_split('~(([\r\n -]|<br />|<br>{1})On\s+.*,\s*.*\s*.*wrote:)~i', $text))>1) {
		$text=$match[0];
		if($debug) fwrite($fh, "\n\nDEBUGGER -> EXTRACTION VIA Gmail 'On... wrote' \n");
	}
	
	// For Hotmail
	if(count($match=preg_split('~(([\r\n]*)To:.*\s*Subject:.*\s*From:.*\s*Date:.*)~', $text))>1) {
		$text=$match[0];
		if($debug) fwrite($fh, "\n\nDEBUGGER -> EXTRACTION VIA To.. Subject.. From.. Date \n");
	}
	if(count($match=preg_split('~(([\r\n]*)Subject:.*\s*To:.*\s*From:.*\s*Date:.*)~', $text))>1) {
		$text=$match[0];
		if($debug) fwrite($fh, "\n\nDEBUGGER -> EXTRACTION VIA Subject.. To.. From.. Date \n");
	}
	
	// Other email clients: From...Subject block
	if(count($match=preg_split('~(([\r\n]*)From:.*\s*.*\s*.*\s*Subject:.*)~', $text))>1) {
		$text=$match[0];
		if($debug) fwrite($fh, "\n\nDEBUGGER -> EXTRACTION VIA From... Subject \n");
	}
	
	if($html&&($temp=strpos($text, '<blockquote'))!==false) { // Roundcube and a few others
		$text=substr($text, 0, $temp);
		if($debug) fwrite($fh, "\n\nDEBUGGER -> EXTRACTION VIA BLOCKQUOTE TAG \n");
	}
	if($html&&($temp=strpos($text, '<div class="quoteheader">'))!==false) { //Some clients
		$text=substr($text, 0, $temp);
		if($debug) fwrite($fh, "\n\nDEBUGGER -> EXTRACTION VIA QUOTEHEADER TAG \n");
	}
	
	// Remove all lines with > in front
	if(count($match=preg_split('~(([\r\n]+)(\>)+ .*)~i', $text))>1) {
		$text=$match[0];
		if($debug) fwrite($fh, "\n\nDEBUGGER -> EXTRACTION VIA > \n");
	}
	
	// Gmail
	// Have to check twice for some reason, first one sometimes does not trigger
	if(count($match=preg_split('~(([\r\n]*[- ]*)On\s+.*,\s*.*\s*.*wrote:)~i', $text))>1) {
		$text=$match[0];
		if($debug) fwrite($fh, "\n\nDEBUGGER -> EXTRACTION VIA Gmail 'On... wrote' \n");
	}
	
	// Last resort
	if(count($match=preg_split('~(([\r\n]*)From:.*\s*Subject:.*)~', $text))>1) {
		$text=$match[0];
		if($debug) fwrite($fh, "\n\nDEBUGGER -> EXTRACTION VIA FROM...SUBJECT \n");
	}
	
	// Remove HTML border
	if($html)
		$text=preg_replace("~(\<[^\>]*)style=[\'\"]{1}[^\'\"]*border[^\'\";]*;[^\'\"]*[\'\"]{1}([^\>]*\>)~is", "", $text);
	
	if($debug) fwrite($fh, "\n\nDEBUGGER -> REPLY EXTRACTED: \n\n".$text);
	
}

 //Second check should not be required... but incase
if($boundary!==false&&($temp=strpos($text, '--'.$boundary))!==false)
	$text=substr($text, 0, $temp);

if(preg_match('~(Content-Type\: .*)~i', $text, $match)!=0)
	$text=substr($text, strpos($text, $match[1])+strlen($match[1]));
	
	
// Remove Outlook double-spacing
if((isset($headers['x-mailer'])&&strpos($headers['x-mailer'], 'Microsoft Office Outlook')!==false)) {
	if(!$html) {
		$text=preg_replace("~([ ]*)(\r\n|\n|\r)(\r\n|\n|\r)([ ]*)~is", "\r\n", $text);
	
		if($debug) fwrite($fh, "\n\nDEBUGGER -> OUTLOOK EMAIL DETECTED, REMOVING DOUBLESPACING: \n\n".$text);
	}
	else {
		$text=preg_replace("~<p class=MsoNormal>~is", "<p style=\"margin:0px;\">", $text);
	
		if($debug) fwrite($fh, "\n\nDEBUGGER -> OUTLOOK EMAIL DETECTED, REMOVING DOUBLESPACING: <p> tag margins".$text);
	}
}

// AOL double spaces
if(strpos($email, "@aol.com")!==false) $text=preg_replace("`(\s)&nbsp;(\s)`is", "", $text);

if($html) {
	
	$text=preg_replace("`[\r\n]*`is", "", $text); 
	$text=preg_replace("`\<br(( )[^\>]*)*\>`is", '<br />', $text);
	$text=preg_replace("`\<img([^\>]*)\>`is", '<img\\1 />', $text);

	if($modSettings['mailinglist_htmlemails']) {
		
		// @param string $html * @return string * @author Milian Wolff <mail@milianw.de>
		function closetags($html) {
			//put all opened tags into an array	
			
			preg_match_all('#<([a-z]+)(?: .*)?(?<![/|/ ])>#iU', $html, $result);  
			$openedtags = $result[1];	
			
			//put all closed tags into an array	
			preg_match_all('#</([a-z]+)>#iU', $html, $result);	
			$closedtags = $result[1];  
			$len_opened = count($openedtags); 
			
			// all tags are closed  
			if (count($closedtags) != $len_opened) {	
				$openedtags = array_reverse($openedtags);  
				// close tags  
				for ($i=0; $i < $len_opened; $i++) {
					if (!in_array($openedtags[$i], $closedtags)){
						   $html .= '</'.$openedtags[$i].'>';	 
					} 
					else {
						unset($closedtags[array_search($openedtags[$i], $closedtags)]);	  
						}	 
					}
			}
			
			// Don't close auto-closed tags
			$html = strtr($html, array('</br>' => "", '</hr>' => "", "</img>"=>""));
			
			// Remove tags with no content
			while(preg_match('#<([a-z:]+)(?: [^>]*)?(?<![/|/ ])></([a-z:]+)>#iU', $html, $matches)!==0)
				$html = preg_replace('#<([a-z:]+)(?: [^>]*)?(?<![/|/ ])></([a-z:]+)>#iU', '', $html);
			
			// Trailing linebreaks
			$html=preg_replace("`([ ]*\<br \/\>[ ]*)+((</[a-z:]+>)*)$`is", "\\2", $html);
			
			// Fix <...> </...> tags with only spaces
			$html = preg_replace('`((<([a-z:]+)(?: [^>]*)?(?<![/|/ ])>)+) ((</[a-z:]+>)+)`is', '\\1&nbsp;\\5', $html);
			
			// Remove space/break tags at end of post
			$html = preg_replace('#((<([a-z:]+)(?: [^>]*)?(?<![/|/ ])>)+)(&nbsp;)*((</[a-z:]+>)+)$#iU', '', $html);
			
			// Remove trailing open tags
			$html = preg_replace('#(<([a-z:]+)(?: [^>]*)?(/|/ )?>)+$#i', '', $html);
			
			return $html;
		}
		$text=closetags(closetags($text));
	}
	else {
		$text = strtr($text, array('<br />' => "<br />\r\n", '</div>' => "</div>\r\n", '</p>'=>"</p>\r\n", '</li>' => "</li>\r\n"));
		
		//Convert to plaintext
		$text=strip_tags($text);
	}
}
} // End of Else for $bounceback

////////////////////////////// END OF REPLY EXTRACTION /////////////////////////////////////////

$text=trim($text);

if(!$modSettings['mailinglist_htmlemails']&&!$bounceback) $text=nl2br(htmlspecialchars($text, ENT_QUOTES));

$text=escapestring__recursive($text);

if($debug) fwrite($fh, "\n\nDEBUGGER -> Converted to post: ".($html? "HTML":"PLAINTEXT")." \n\n".$text);

////////////////////////////// END OF POST CONVERSION /////////////////////////////////////////

// Prepare to insert post/topic
$messageID=$fetch['id_topic'];

// Work out our board query string.
if (in_array(1, $user_info['groups'])||$modSettings['mailinglist_allowguests']||($modSettings['mailinglist_ticketsys']&&($messageID==0||$id_member==$fetch['id_member_started'])))
	$user_info['query_see_board'] = '1';
// If not an admin, the string is what they can see...
else
	$user_info['query_see_board'] = '(FIND_IN_SET(' . implode(', b.member_groups) OR FIND_IN_SET(', $user_info['groups']) . ', b.member_groups))';

// Load up the core topic details, make sure it's all legit etc...

if($messageID>0)
	$request = $smcFunc['db_query']('', "
		SELECT t.id_topic, t.id_board, t.locked, t.id_member_started, m.subject, b.count_posts
		FROM {db_prefix}topics AS t CROSS JOIN {db_prefix}boards AS b CROSS JOIN {db_prefix}messages AS m CROSS JOIN {db_prefix}settings as s
		WHERE t.id_topic = $messageID
			AND b.id_board = t.id_board
			AND m.id_msg = t.id_first_msg
			AND s.variable='recycle_board'
			AND s.value!=b.id_board
			AND ".$user_info['query_see_board']."
			ORDER BY m.id_msg DESC
			LIMIT 1");
else // New topic
	$request = $smcFunc['db_query']('', "
		SELECT b.count_posts
		FROM {db_prefix}boards AS b
		WHERE b.id_board = $mailinglist_board
			AND ".$user_info['query_see_board']."
		LIMIT 1");

if ($smcFunc['db_num_rows']($request) == 0 && !$modSettings['mailinglist_allowguests'])
	emailError($txt['cannot_post_reply_any']);
$topic_info = $smcFunc['db_fetch_assoc']($request);

// If new topic, add details
if($messageID==0) {
	$topic_info["id_topic"]=0;
	$topic_info["id_board"]=$mailinglist_board;
	$topic_info["subject"]=$tsubject;
	$topic_info["locked"]=0;
	$topic_info["id_member_started"]=$id_member;
}

$smcFunc['db_free_result']($request);

// Right, we have the topic info, and the permissions - do they have a right to reply?
if(!in_array(1, $user_info['groups'])) { // Admin permissions not accounted for in allowedTo

  if(!$modSettings['mailinglist_allowguests']) {
    if ($topic_info['locked'] && !allowedTo('moderate_forum'))
      emailError($txt['topic_locked']);
    elseif ($topic_info['id_member_started'] == $id_member && !allowedTo('post_reply_own')&&!$modSettings['mailinglist_ticketsys'])
      emailError($txt['cannot_post_reply_own']);
    elseif (!allowedTo('post_reply_any')&&!$modSettings['mailinglist_ticketsys'])
      emailError($txt['cannot_post_reply_any']);
  }
	
	// Check ban triggers
	  $bcheck=$smcFunc['db_query']('', "
		 SELECT id_member
		 FROM {db_prefix}ban_items
		 WHERE '" . escapestring__recursive($email) . "' LIKE email_address OR (id_member=" . escapestring__recursive($id_member) . " AND id_member!=0)
		 LIMIT 1");
	  if($smcFunc['db_num_rows']($bcheck)>0) emailError($txt['cannot_post_reply_any']);
	  $smcFunc['db_free_result']($bcheck);
}

if($debug) fwrite($fh, "\n\nDEBUGGER -> Passed user permissions check. Posting... ".$topic_info['subject']);

// If we get to this point - we must be doing pretty damn well. Let's make the post!
require_once($sourcedir . '/Subs-Post.php');

// Setup the variables.
$msgOptions = array(
	'id' => 0,
	'subject' => ($topic_info['id_topic']==0||strpos($topic_info['subject'], 'Re:') === 0 ? $topic_info['subject'] : 'Re: ' . escapestring__recursive($topic_info['subject'])), // SMF doesn't escape things, must do it here
	'body' =>  $text, // We already escaped text earlier so we're fine
	'smileys_enabled'	=>	1, // I want smilies!!
	'attachments' => $attachIDs
);
$topicOptions = array(
	'id' => $topic_info['id_topic'],
	'board' => $topic_info['id_board'],
	'mark_as_read' => false,
);
$posterOptions = array(
	'id' => $id_member,
	'name' => $user_settings['real_name'],
	'email' => $user_settings['email_address'],
	'update_post_count' => 1, 
	'ip' => $user_settings['member_ip'],
);

$user_info['name']=$posterOptions['name'];

// Attempt to make the post.
createPost($msgOptions, $topicOptions, $posterOptions);

for($i=0; $i<3; $i++) {
  if($debug) fwrite($fh, "\n\nDEBUGGER -> Posted.  Checking format...");

  // Check if post made it through; if not, encode to UTF8 and try again
  if($topic_info['id_topic']==0) {
    $request=$smcFunc['db_query']('', "select m.id_msg, m.body
      from {db_prefix}messages as m CROSS JOIN {db_prefix}topics as t CROSS JOIN {db_prefix}boards AS b CROSS JOIN {db_prefix}settings as s
      WHERE m.id_topic=t.id_topic
      AND m.subject='".($tsubject)."'
      AND m.id_msg = t.id_first_msg
      AND b.id_board = t.id_board
      AND s.variable='recycle_board'
      AND s.value!=b.id_board
      ORDER BY m.id_msg DESC
      LIMIT 1");
  }
  else {
    $request=$smcFunc['db_query']('', "select m.id_msg, m.body
      from {db_prefix}messages as m
      WHERE m.id_topic=".$topic_info['id_topic']."
      ORDER BY m.id_msg DESC
      LIMIT 1");
  }
  if($smcFunc['db_num_rows']($request)>0) {
    list ($id_msg, $message) = $smcFunc['db_fetch_row']($request);
  }
  else {
    $smcFunc['db_free_result']($request);
    emailError($txt['merge_create_topic_failed']); // Shouldn't happen
  }

  $smcFunc['db_free_result']($request);

  if(escapestring__recursive($message)!=$text) {
    switch($i) {
      case 0:
        $t=$text;
        if($debug) fwrite($fh, "\n\nDEBUGGER -> POST NOT INPUT CORRECTLY, Converting to UTF-8: \n\n".$t);
        break;
      case 1:
        if(function_exists('iconv_mime_decode')) //PHP5 only
          $t=iconv_mime_decode($text);
        elseif(strpos($t, '=?UTF-8?')!==false) //Leaves underscores instead of spaces for ISO encoding
          $t=mb_decode_mimeheader($text);
        else { //Cannot decode UTF8
          $t=imap_mime_header_decode($text);
          $t=$t[0]->text;
        }
        if($debug) fwrite($fh, "\n\nDEBUGGER -> POST NOT INPUT CORRECTLY, Mime Decoding and Trying Again: \n\n".$t);
        break;
      case 2:
        $t=strip_tags(str_replace("<br />", "\n", $text));
        if($debug) fwrite($fh, "\n\nDEBUGGER -> POST NOT INPUT CORRECTLY, Using Non-HTML as Fallback: \n\n".$t);
        break;
    }
    $t=utf8_encode($t);
    $smcFunc['db_query']('', "update {db_prefix}messages set body='$t' where id_msg=$id_msg");
  }
  else {
    if($debug) fwrite($fh, "\n\nDEBUGGER -> POST IS OK!");
    break;
  }
}

if($debug) fclose($fh);

if($topic_info['id_topic']>0) {
	
	// Enable notifications for the person who replied?
	if($modSettings['mailinglist_autonotify'])
		$smcFunc['db_query']('', "INSERT INTO {db_prefix}log_notify (id_member, id_topic) VALUES (".$id_member.", ".$topic_info['id_topic']") ON DUPLICATE DO NOTHING");
		
	sendNotifications($topic_info['id_topic'], 'reply'); // Moddie: send out notifications as well
}
else {
	
	
	// Shared with announce and notifications
	
	$notifiedMembers=array(-1);
	
	$board = $topic_info["id_board"];
	
	// Get new topic
	$request=$smcFunc['db_query']('', "select m.id_topic, m.id_msg, m.subject, m.body
		from {db_prefix}messages as m CROSS JOIN {db_prefix}topics as t CROSS JOIN {db_prefix}boards AS b CROSS JOIN {db_prefix}settings as s
		WHERE m.id_topic=t.id_topic
		AND m.subject='".($tsubject)."'
		AND m.id_msg = t.id_first_msg
		AND b.id_board = t.id_board
		AND s.variable='recycle_board'
		AND s.value!=b.id_board
		ORDER BY m.id_msg DESC
		LIMIT 1");
	if($smcFunc['db_num_rows']($request)>0) {
		list ($topic, $id_msg, $subject, $message) = $smcFunc['db_fetch_row']($request);
	}
	else  {
		$smcFunc['db_free_result']($request);
		emailError($txt['merge_create_topic_failed']); // Shouldn't happen
	}
	$smcFunc['db_free_result']($request);
	
	// Setup attachments
	$attach=array();
	foreach($attachIDs as $v)
		$attach[]=$scripturl."?action=dlattach;topic=$topic;attach=".$v;
	

	// Censor the subject and body...
	censorText($subject);
	censorText($message);

	$subject = un_htmlspecialchars($subject);
	
// pftq / Mailing List: Allow HTML
	$message = strtr(parse_bbc($message), array('<br />' => "<br />\n", '</div>' => "</div>\n", '</p>'=>"</p>\n", '</li>' => "</li>\n"));
	
	
	// Does the person want auto-notifications for replies to topics started?
	$aNotify=$smcFunc['db_query']('', "SELECT value FROM {db_prefix}themes WHERE id_member=$id_member and variable='auto_notify'");
	if($smcFunc['db_num_rows']($aNotify)>0) {
		$autoNotify=$smcFunc['db_fetch_assoc']($aNotify);
	}
	else $autoNotify=array('value'=>0);
	$smcFunc['db_free_result']($aNotify);
	
	// Or override via mod setting to enable notifications for the person who sent this
	if($modSettings['mailinglist_autonotify']||$autoNotify['value']==1)
		$smcFunc['db_query']('', "INSERT INTO {db_prefix}log_notify (id_member, id_topic) VALUES (".$id_member.", ".$topic.") ON CONFLICT DO NOTHING");
	
	// Integration with Mail Read Tracker
	if(file_exists('mailread.php')) {
		$mailread=true;
		$message.="<img src=\"{$boardurl}/mailread.php?member={mailreadMEMBERVAR};post=".$id_msg."\" alt='' width='1px' height='1px' />";
	}
	else $mailread=false;
	
	
	// Notify Subscribed to Board
	// Copied from Post.php
	
	// Find the members with notification on for this board.
	$members=$smcFunc['db_query']('', "
			SELECT
			mem.id_member, mem.email_address, mem.notify_regularity, mem.notify_send_body, mem.lngfile,
		ln.sent, mem.id_group, mem.additional_groups, b.member_groups, mem.id_post_group
		". /* Integration w/ Notify Group */ (isset($txt['notifygroup'])? ", ng.id_board ":"")."
		FROM {db_prefix}members AS mem
		CROSS JOIN {db_prefix}boards AS b
		LEFT JOIN {db_prefix}log_notify AS ln on (ln.id_board=b.id_board and mem.id_member = ln.id_member)
		".(isset($txt['notifygroup'])? "
		LEFT JOIN {db_prefix}notifygroup as ng on (ng.id_board=b.id_board and (
			 mem.id_group=ng.id_group OR
			 mem.id_post_group=ng.id_group OR
			 FIND_IN_SET(ng.id_group, mem.additional_groups)))":"")."
		WHERE (ln.id_board IN ({array_int:board_list})
				".(isset($txt['notifygroup'])? "OR ng.id_board IN ({array_int:board_list})":"").")
			".($modSettings['mailinglist_notifyownreplies']? "":
			"AND mem.id_member != {int:current_member}"). /* pftq / Mailing List: Send own posts ? */ "
			AND mem.is_activated = {int:is_activated}
			AND mem.notify_types != {int:notify_types}
			AND mem.notify_regularity < {int:notify_regularity}
		AND mem.notify_announcements=1
		GROUP BY mem.id_member
		ORDER BY mem.lngfile",
		array(
			'current_member' => $id_member,
			'board_list' => array($board),
			'is_activated' => 1,
			'notify_types' => 4,
			'notify_regularity' => 2,
		)	 
		);
		
	// Remember to send to the thread starter if not a member
	$guestThread=false;
	if($id_member==0) $guestThread=true;	
	
	while ($rowmember = $smcFunc['db_fetch_assoc']($members))
	{
		// Avoid inf loop with delivery system
		if($guestThread&&!(strpos($subject, ":")===false&&strpos($subject, "fail")===false&&strpos($subject, "delivery")===false))
			break;
	
		if ($rowmember['id_group'] != 1)
		{
			$allowed = explode(',', $rowmember['member_groups']);
			$rowmember['additional_groups'] = explode(',', $rowmember['additional_groups']);
			$rowmember['additional_groups'][] = $rowmember['id_group'];
			$rowmember['additional_groups'][] = $rowmember['id_post_group'];

			if (count(array_intersect($allowed, $rowmember['additional_groups'])) == 0)
				continue;
		}

		loadLanguage('Post', empty($rowmember['lngfile']) || empty($modSettings['userLanguage']) ? $language : $rowmember['lngfile'], false);

		// Setup the string for adding the body to the message, if a user wants it.
		$body_text =  $message . "\n\n";

		// Send only if once is off or it's on and it hasn't been sent.
	// pftq / Mailing List: Prevent spam filters from tagging this as spam. - Copied from ManageNews
	
	$body_text = 
			$body_text .
			strtr(parse_bbc(
			($attach? "<small>\n\n".$txt['mailinglist_attached']." \n".implode("\n", $attach)."</small>":"").
			
			"\n\n========================================\n".
			(!empty($rowmember['notify_regularity']) && empty($rowmember['sent'])? $txt['notify_boards_once'] . "\n\n" : "").
			$txt['mailinglist_announce'] . " \n" . $scripturl . '?topic=' . $topic . ".0 \n\n".
			($mailinglist_autoannounce||$modSettings['mailinglist_announcenotify']||$autoNotify['value']==1? 
				$txt['mailinglist_announce2']:$txt['mailinglist_announce3']). " \n" . $scripturl . '?action=notify;topic=' . $topic . ".0 \n \n".
			// Integrate with Notify Group
			(isset($txt['notifygroup_groupEmail'])? ($rowmember['id_board']!=$board? $txt['notify_boardsUnsubscribe'] . ": \n" . $scripturl . '?action=notifyboard;board=' . $board . ".0\n\n" : $txt['notifygroup_groupEmail'].': '.$scripturl."?action=disableNotify\n\n"):"")
			.$txt['regards_team']), array('<br />' => "<br />\n", '</div>' => "</div>\n", '</p>'=>"</p>\n", '</li>' => "</li>\n"));
	
	if (preg_match('~\<html~i', $body_text) == 0)
	{
		if (preg_match('~\<body~i', $body_text) == 0)
			$body_text = '<html><head><title>' . $subject . '</title></head>' . "\n" . '<body>' . $body_text . '</body></html>';
		else
			$body_text = '<html>' . $body_text . '</html>';
	}
	
	// pftq / Mailing List: sendmail strips slashes for some reason
		//$body_text = addslashes($body_text);
	
	if($mailread) { // Integration with Mail Read
	
		if($guestThread) {
			// Clear old logs
			$smcFunc['db_query']('', "delete from {db_prefix}mailread where id_msg=$id_msg and id_member=0");
					
			// Insert new log
			$smcFunc['db_query']('', "INSERT INTO {db_prefix}mailread (id_member, id_msg) values(0, $id_msg)");
			$body_text2=str_replace('{mailreadMEMBERVAR}', 0, $body_text);
		}
	
		// Clear old logs
		$smcFunc['db_query']('', "delete from {db_prefix}mailread where id_msg=$id_msg and id_member=".$rowmember['id_member']);
				
		// Insert new log
		$smcFunc['db_query']('', "INSERT INTO {db_prefix}mailread (id_member, id_msg) values(".$rowmember['id_member'].", $id_msg)");
		$body_text=str_replace('{mailreadMEMBERVAR}', $rowmember['id_member'], $body_text);
	}
		
		
		if($modSettings['mailinglist_encodesubject'])
			$ss='=?UTF-8?B?'.base64_encode($subject).'?=';
		else $ss=$subject;

		// pftq / Mailing List: Send HTML formatted body + normal subject from the poster's name
			sendmail($rowmember['email_address'], $ss, $body_text, un_htmlspecialchars($user_info['name']), 't' . $topic, true);
			
		if($guestThread) {
			sendmail($email, $ss, $body_text2, un_htmlspecialchars($user_info['name']), 't' . $topic, true);
			$guestThread=false;
		}
			
		// pftq / Mailing List: Auto Enable Notifications for Members Announced to
		//if($mailinglist_autoannounce)
			$smcFunc['db_query']('', "INSERT INTO {db_prefix}log_notify (id_member, id_topic) VALUES (".$rowmember['id_member'].", ".$topic.") ON CONFLICT DO NOTHING");
			
		// Don't announce to them if they've already been notified
		$notifiedMembers[]=$rowmember['id_member'];
	}
	$smcFunc['db_free_result']($members);

	// Sent!
	$smcFunc['db_query']('', "
		UPDATE {db_prefix}log_notify
		SET sent = 1
		WHERE id_board = $board
			AND id_member != $id_member");
	
			
	////////////////////////////////////
	
	// Announce Topic
	// If new topic, email it out (otherwise, what's the point of emailing it to the forum?)
	// Much of this based on the AnnouncementSend() function after modification by the mailing list mod
	if($mailinglist_autoannounce) {
		
		// Get groups
		$groups=$smcFunc['db_fetch_assoc']($smcFunc['db_query']('', "select member_groups from {db_prefix}boards where id_board=".$topic_info['id_board']));
		$groups = array_merge(explode(",", $groups['member_groups']), array(1));
		foreach ($groups as $key => $value) { 
		  if (is_null($value) || $value=="") { 
			unset($groups[$key]); 
		  } 
		}
		
		// Message, topic, etc already retreived during check for board notifications
		
		$mestemp="";
			
		// Select the email addresses for this batch.
		$request = $smcFunc['db_query']('', "
			SELECT mem.id_member, mem.email_address, mem.lngfile
			FROM {db_prefix}members AS mem
			WHERE mem.id_member >=0 " . (!empty($modSettings['allow_disableannounce']) ? '
				AND mem.notify_announcements = 1' : '') . "
				AND mem.is_activated = 1
				AND (mem.id_group IN (" . implode(', ', $groups) . ") OR mem.id_post_group IN (" . implode(', ', $groups) . ") OR FIND_IN_SET(" . implode(", mem.additional_groups) OR FIND_IN_SET(", $groups) . ", mem.additional_groups))
				AND (mem.id_member NOT IN (". implode(', ', $notifiedMembers) ."))
			ORDER BY mem.id_member");
		
			$announcements=array();
			
			// Loop through all members that'll receive an announcement in this batch.
			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				$cur_language = empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile'];
		
				// If the language wasn't defined yet, load it and compose a notification message.
				if (!isset($announcements[$cur_language]))
				{
					loadLanguage('Post', $cur_language, false);
					
					// pftq / Mailing List: Prevent spam filters from tagging this as spam. - Copied from ManageNews
					
					$mestemp = $message . parse_bbc(
					($attach? "<small>\n\n".$txt['mailinglist_attached']." \n".implode("\n", $attach)."</small>":"").
					"\n \n======================================== \n" . $txt['mailinglist_announce'] . " \n" . $scripturl . '?topic=' . $topic . ".0 \n \n".$txt['mailinglist_announce2']. " \n" . $scripturl . '?action=notify;topic=' . $topic . ".0 \n \n".$txt['mailinglist_disable']." \n" . $scripturl . "?action=disableNotify \n \n" . $txt['regards_team']);
					
					if (preg_match('~\<html~i', $mestemp) == 0)
					{
						if (preg_match('~\<body~i', $mestemp) == 0)
							$mestemp = '<html><head><title>' .$subject. '</title></head>' . "\n" . '<body>' . $mestemp . '</body></html>';
						else
							$mestemp = '<html>' . $mestemp . '</html>';
					}
					
					// pftq / Mailing List: sendmail strips slashes for some reason
						//$mestemp = addslashes($mestemp);
		
					$announcements[$cur_language] = array(
						'subject' => $subject,
						'body' => $mestemp,
						'recipients' => array(),
					);
				}
		
				$announcements[$cur_language]['recipients'][$row['id_member']] = $row['email_address'];
				// pftq / Mailing List: Auto Enable Notifications for Members Announced to
				$smcFunc['db_query']('', "INSERT INTO {db_prefix}log_notify (id_member, id_topic) VALUES (".$row['id_member'].", ".$topic.") ON CONFLICT DO NOTHING");
			}
			$smcFunc['db_free_result']($request);
			
			$request = $smcFunc['db_query']('', "
				SELECT m.id_msg
				FROM ({db_prefix}messages AS m, {db_prefix}topics AS t)
				WHERE t.id_topic = $topic
					AND m.id_msg = t.id_first_msg");
			list ($id_msg) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);
		
			// pftq / Mail Read: Customize ping to website per member
			function mailRead_sendmail($recipients, $subject, $body, $from=null, $msgid=null, $html=false, $pri=1, $hotmail=null) {
				global $topic, $db_prefix, $id_msg, $smcFunc;

				foreach($recipients as $member=>$recipient) {
					// Clear old logs
					$smcFunc['db_query']('', "delete from {db_prefix}mailread where id_msg=$id_msg and id_member=$member");
					
					// Insert new log
					$smcFunc['db_query']('', "INSERT INTO {db_prefix}mailread (id_member, id_msg) values($member, $id_msg)");
					sendmail($recipient, $subject, str_replace('{mailreadMEMBERVAR}', $member, $body), $from, $msgid, $html, $pri, $hotmail);
				}
			}
			
			// Remember to send to the thread starter if not a member
			$guestThread=false;
			if($id_member==0) $guestThread=true;
			
			// For each language send a different mail.
			foreach ($announcements as $lang => $mail) {
				
				if($guestThread&&!(strpos($mail['subject'], ":")===false&&strpos($mail['subject'], "fail")===false&&strpos($mail['subject'], "delivery")===false))
					break; // Don't want to start an infinite email loop with the delivery system
			
				if($modSettings['mailinglist_encodesubject'])
					$ss='=?UTF-8?B?'.base64_encode($mail['subject']).'?=';
				else $ss=$mail['subject'];
				
				if($guestThread) {
					$mail['recipients'][0]=$email;
					$guestThread=false;
				}
			
				if($mailread) mailRead_sendmail($mail['recipients'], $ss, $mail['body'], un_htmlspecialchars($user_info['name']), null, true);
				else sendmail($mail['recipients'], $ss, $mail['body'], un_htmlspecialchars($user_info['name']), null, true);
			}
	}
}

// Email the user upon an error.
function emailError($msg = '', $bounceEmail=true)
{
	global $email, $tsubject, $txt, $webmaster_email, $user_settings, $language;
	$cur_language = empty($user_settings['lngfile']) || empty($modSettings['userLanguage']) ? $language : $user_settings['lngfile'];
	loadLanguage('index', $cur_language, false);
	loadLanguage('Errors', $cur_language, false);
	
	// Sometimes sendmail is "undefined", not sure.  Extra caution just incase
	@include_once(dirname(__FILE__) . '/Sources/Subs-Post.php');
	
	if($bounceEmail) {
		// Use send mail so SMTP is used as needed
		if(function_exists('sendmail'))
			sendmail($email, $txt['error_occured'], "\"$tsubject\" \n\n$msg");
		else
			mail($email, $txt['error_occured'], "\"$tsubject\" \n\n$msg", "From: ".$webmaster_email);
	}
		
	// Log these errors so we know
	log_error($email."\n\"$tsubject\" \n\n$msg");

	die;
}
?>
