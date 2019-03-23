<?php

function template_main() {

	global $context, $scripturl, $txt, $db_prefix;
	
	echo '
		<table cellpadding="3" cellspacing="0" border="0" width="100%" class="tborder">
		<tr><td class="titlebg">'.$txt['notification'].'</td></tr>
		<tr><td class="windowbg"><div style="text-align:center; margin-bottom:10px;">';
	
	if ($context['username']) 
		echo $txt['notifygroup_prefUpdated']."<b><a href='".$scripturl."?action=profile;u=".$context['user_id']."'>".$context['username']."</a></b>";
		
	elseif($context['username']===false) 
		echo $txt['notifygroup_emailNotExist'];
	
	else
		echo $txt['notifygroup_enterEmail'];
	
	echo "</div>
	
	<form method='post' action='?action=disableNotify' style='width:400px; margin:auto; text-align:center; margin-top:15px;'>
			
			".$txt['prompt_text_email'].": <input type='text' size='48' id='email' name='email' value='".$context['email']."' />
			<br />
			
			<select id='set' name='set'>
			<option value='0' ".($context['set']==0? "selected='selected'":"").">".$txt['notifygroup_setOff']."</option>
			<option value='1' ".($context['set']==1? "selected='selected'":"").">".$txt['notifygroup_setOn']."</option>
			</select>
			<br /><input type='submit' value='".$txt['save']."' /></form>
	<br /><br /><br />
	</td></tr></table>";
}

?>