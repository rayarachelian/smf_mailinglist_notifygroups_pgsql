<?php

function template_main() {

	global $smcFunc, $context, $scripturl, $txt, $db_prefix;
		
	echo '
		<table cellpadding="3" cellspacing="0" border="0" width="100%" class="tborder">
		<tr><td class="titlebg">'.$txt['notifygroup'].'</td></tr>
		<tr><td class="windowbg">';
	
	if (empty($_REQUEST['topic'])) {
    if(empty($_REQUEST['batch'])) {
      echo "<a href='?action=notifygroup;batch=true'>".$txt['batch']."</a><br /><br />";
      echo "<form method='post' action='?action=notifygroup'>
        ".$txt['select_topic'].": <select id='topic' name='topic'>";
      foreach($context['notifygroup_board'] as $bb) {
        echo "<option value='b".$bb['id']."'>".$smcFunc['strtoupper']($bb['name']).":</option>";
        foreach($context['notifygroup_topics'][$bb['id']] as $a)
          echo "<option value='".$a['id_topic']."'>- ".$a['subject']."</option>";
      }
      echo "</select>";
    }
		else {
      $c=0;
      echo "<a href='?action=notifygroup'>".$txt['reset']."</a><br /><br />";
      echo "
			<script type='text/javascript'>
			function checkbatch(arr, namer) {
				for(var i=0;i<arr.length;i++) {
					document.getElementById(arr[i]).checked=document.getElementById(namer).checked;
				}
			}
			var batchlines=[];
			</script>";
			$jscript="";
      echo "<form method='post' action='?action=notifygroup'>
      <input type='hidden' id='batch' name='batch' value='true' />
      <input type='hidden' id='topic' name='topic' value='unused' />
        ".$txt['select_topic']." (".$txt['batch']."): <br />";
      foreach($context['notifygroup_board'] as $bb) {
        echo "<input type='checkbox' id='topic$c' name='topic$c' value='b".$bb['id']."' />".$smcFunc['strtoupper']($bb['name']).":<br />";
        $jscript.= "batchlines[".($c)."]='topic".$c."';";
        $c++;
        foreach($context['notifygroup_topics'][$bb['id']] as $a) {
          echo "<input type='checkbox' id='topic$c' name='topic$c' value='".$a['id_topic']."' />- ".$a['subject']."<br />";
          $jscript.= "batchlines[".($c)."]='topic".$c."';";
          $c++;
        }
      }
		}
		echo "<script type='text/javascript'>
          $jscript
          </script>";
		echo "<input type='checkbox' id='check_all' name='check_all' onclick='checkbatch(batchlines, \"check_all\");' /> ".$txt['check_all']."<br />";
		echo "<input type='submit' value='".$txt['go_caps']."' /></form><br /><br />";
	}
	else {
		echo "<table width='100%'><tr><td width='50%' style='vertical-align:top;'><a href='?action=notifygroup'>".$txt['reset']."</a>";
		if(empty($_REQUEST['batch'])) echo " | <a href='$scripturl?".($context['notifytopic']? "topic=".$context['topic']:"board=".$context['board'])."'>".$txt['go_to_'.($context['notifytopic']? 'topic':'board')]."</a>";
		else echo " | <a href='?action=notifygroup;batch=true'>".$txt['batch']."</a>";
		if($context['notifygroup_page']>0) 
      echo " | <a href='?action=notifygroup;topic=".$context['topic'].(!empty($_REQUEST['batch'])? ";batch=true;".$context['batchString']:"")."'>".$txt['reselect_group']."</a>";
		
		echo "<br />";
		
		echo "<form method='post' action='?action=notifygroup'><b>".($context['notifygroup_page']==0? $txt["select_group"]:$txt["select_member"]);
		if(empty($_REQUEST['batch'])) 
      echo "<input type='hidden' id='topic' name='topic' value='".$context['topic']."' />";
    else {
      echo "<input type='hidden' id='batch' name='batch' value='true' />
            <input type='hidden' id='topic' name='topic' value='unused' />";
      foreach($context['batchTopics'] as $k=>$topic)
        echo "<input type='hidden' id='topic$k' name='topic$k' value='$topic' />";
    }
		echo "<select name='sa' id='sa'>";
		echo "<option value='on' ".($context['sa']=="on"? "selected='selected'":"").">".$txt['notify']."</option>
		<option value='off' ".($context['sa']=="off"? "selected='selected'":"").">".$txt['denotify']."</option>";
		echo "</select>";
		echo ":</b><br />";
		echo "<input type='hidden' id='submit' name='submit' value='".($context['notifygroup_page']+1)."' />";
		echo $context['notifygroup_output'];
		echo "<input type='submit' value='".$txt['go_caps']."' /></form>";
		echo "</td><td width='50%' style='vertical-align:top;'>";
		
		if(empty($_REQUEST['batch'])) {
      echo "<b>".$txt['receiving_notification']."'".$context['subject']."':</b><br />";
      echo implode("<br />", $context['notifygroup_notifiedg']);
      if(count($context['notifygroup_notified'])>0&&count($context['notifygroup_notifiedg'])>0)
        echo "<br /><br />";
      echo implode("<br />", $context['notifygroup_notified']);
		}
		
		// Batch Mode
    else {
      foreach($context['batchTopics'] as $topic) {
        echo "<b>".$txt['receiving_notification']."'".$context['subject'][$topic]."':</b><br />";
        echo implode("<br />", $context['notifygroup_notifiedg'][$topic]);
        if(count($context['notifygroup_notified'][$topic])>0&&count($context['notifygroup_notifiedg'][$topic])>0)
          echo "<br /><br />";
        echo implode("<br />", $context['notifygroup_notified'][$topic]);
        echo "<br /><br />";
      }
    }
	}
	echo "</td></tr></table></td></tr></table>";
}

?>