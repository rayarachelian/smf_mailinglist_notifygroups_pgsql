<?php

$vars=array(
"mailinglist_board"=>'1',
"mailinglist_prefix"=>'',
"mailinglist_autoannounce"=>'1',
"mailinglist_announcefrom"=>'0',
"mailinglist_announcenotify"=>'1',
'mailinglist_autonotify'=>'1',
'mailinglist_notifyownreplies'=>'1',
'mailinglist_numreplies'=>'10',
'mailinglist_ticketsys'=>'1',
'mailinglist_allowguests'=>'0',
'mailinglist_htmlemails'=>'0',
'mailinglist_encodesubject'=>'0',
'mailinglist_postvar'=>'',
);

foreach($vars as $var=>$val)
	if($smcFunc['db_num_rows']($smcFunc['db_query']('', "select variable from {db_prefix}settings where variable='$var'"))==0)
		$smcFunc['db_query']('', "INSERT INTO {db_prefix}settings (variable, value) values('$var', '$val') ON CONFLICT DO NOTHING");
		
chmod('emailpost.php', 0755);

?>
