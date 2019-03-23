<?php
/*
Code based on
SMF Notify Group

by pftq
www.pftq.com/smf-notifygroup/
Version 1 (Dec.5 2009)

for SMF Forums 1.1.x

Upgraded for SMF2 (Jan.12 2011)
*/

if (!defined('SMF'))
	die('Hacking attempt...');
	
function mailingList_unsub() {
	global $smcFunc, $context, $scripturl, $txt, $db_prefix;
	
	
	loadLanguage('Profile');
	$context['page_title'] = $context['forum_name']." - ".$txt['notification'];
	
	if (!isset($_POST['email'])||!isset($_POST['set'])) {
		$context['email']="";
		$context['set']='';
		$context['username']='';
	}
	else {
		$context['email']=trim($_POST['email']);
		$context['set']=$_POST['set'];
		
		$q=$smcFunc['db_query']('',"select id_member, real_name, notify_announcements from {db_prefix}members where email_address like '".$context['email']."'");
		
		if($smcFunc['db_num_rows']($q)>0) {
			
			$a=$smcFunc['db_fetch_assoc']($q);
			$context['username']=$a['real_name'];
			$context['user_id']=$a['id_member'];
			
			if($a['notify_announcements']!=$context['set']) {
				$smcFunc['db_query']('',"update {db_prefix}members set notify_announcements=".$context['set']." where email_address like '".$context['email']."'");
				
				log_error($txt['mailingList_prefUpdated']." ".$context['username']." / ".$context['email']." \n\n".($context['set']==0? $txt['mailingList_setOff']:$txt['mailingList_setOn']));
				}
		}
		else {
			$context['username']=false;
			log_error($txt['mailingList_prefUpdated']." ? / ".$context['email']." \n\n".$txt['mailingList_emailNotExist']);
		}
	}
	
	loadTemplate('mailingList_unsub');
}
?>