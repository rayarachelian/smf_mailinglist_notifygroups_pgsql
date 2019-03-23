<?php
/*
SMF Notify Group

by pftq
www.pftq.com/smf-notifygroup/
*/

if (!defined('SMF'))
	die('Hacking attempt...');
	
function notifygroup() {
	global $smcFunc, $context, $scripturl, $txt, $db_prefix;

	if(!allowedTo('moderate_forum')) redirectexit();
	else {
		if (empty($_REQUEST['topic'])) {
			$context['page_title'] = $context['forum_name']." - ".$txt['notifygroup'];
			$context['notifygroup_board']=array(); $context['notifygroup_topics']=array();
			$b=$smcFunc['db_query']('',"select b.id_board, b.name from {db_prefix}boards as b order by b.board_order");
			while($bb=$smcFunc['db_fetch_assoc']($b)) {
				$context['notifygroup_board'][]=array('name'=>$bb['name'], 'id'=>$bb['id_board']);
				$context['notifygroup_topics'][$bb['id_board']]=array();
				$t=$smcFunc['db_query']('',"select t.id_topic, m.subject from {db_prefix}messages as m, {db_prefix}topics as t where t.id_first_msg=m.id_msg and t.id_board=".$bb['id_board']." order by m.poster_time DESC");
				while($a=$smcFunc['db_fetch_assoc']($t))
					$context['notifygroup_topics'][$bb['id_board']][]=array('subject'=>$a['subject'], 'id_topic'=>$a['id_topic']);
				$smcFunc['db_free_result']($t);
			}
			$smcFunc['db_free_result']($b);
		}
		elseif(empty($_REQUEST['batch'])) {
			$context['topic']=$_REQUEST['topic'];
			if (empty($_REQUEST['sa']))
				$context['sa']='on';
			else $context['sa']=$_REQUEST['sa'];
			$context['notifygroup_output']="";
			$context['notifygroup_notified']=array();
			$context['notifygroup_notifiedg']=array();
			if(is_numeric($context['topic'])) $context['notifytopic']=true;
			else {
				$context['notifytopic']=false;
				$context['board']=str_replace("b", "", $context['topic']);
			}
			
			if($context['notifytopic'])
				$msg=$smcFunc['db_fetch_assoc']($smcFunc['db_query']('',"select m.subject from {db_prefix}messages as m, {db_prefix}topics as t where t.id_first_msg=m.id_msg and t.id_topic=".$context['topic'].""));
			else
				$msg=$smcFunc['db_fetch_assoc']($smcFunc['db_query']('',"select name as subject from {db_prefix}boards where id_board='".$context['board']."'"));
			
			$context['page_title'] = $context['forum_name']." - ".$txt['notifygroup'].": ".$msg['subject'];
			$context['subject']=$msg['subject'];
			
			if(empty($_REQUEST['submit'])) $context['notifygroup_page']=0;
			else $context['notifygroup_page']=$_REQUEST['submit'];
			
			$temp=""; $c=0;
			
			$context['notifygroup_output'].= "
			<script type='text/javascript'>
			function checkall(arr, namer) {
				for(var i=0;i<arr.length;i++) {
					document.getElementById(arr[i]).checked=document.getElementById(namer).checked;
				}
			}
			var check=[];";
			
			if($context['notifygroup_page']==0) {
				$groups=$smcFunc['db_query']('',"select id_group, group_name from {db_prefix}membergroups order by group_name");
				while($g=$smcFunc['db_fetch_assoc']($groups)) {
					$temp.="<input type='checkbox' id='checkg_".$g['id_group']."' name='checkg_".$g['id_group']."' /> ".$g['group_name']." <br />";
					$context['notifygroup_output'].= "check[".($c++)."]='checkg_".$g['id_group']."';";
				}
			}
			elseif($context['notifygroup_page']>0) {
				$groups=$smcFunc['db_query']('',"select id_group, group_name from {db_prefix}membergroups");
				$memberbank=array();
				
				while($g=$smcFunc['db_fetch_assoc']($groups)) {
					if(isset($_REQUEST['checkg_'.$g['id_group']])&&$_REQUEST['checkg_'.$g['id_group']]=='on') {
						$temp.="<input type='checkbox' id='checkm_g".$g['id_group']."' ".(/*$context['notifygroup_page']==1||*/(isset($_REQUEST['checkm_g'.$g['id_group']])&&$_REQUEST['checkm_g'.$g['id_group']]=='on')? "checked='checked'":"")." name='checkm_g".$g['id_group']."' />".$g['group_name'].":<br />";
						$g=$g['id_group'];
						$temp.="<input type='hidden' id='checkg_$g' name='checkg_$g' value='on' />";

						if($context['notifygroup_page']>1&&isset($_REQUEST['checkm_g'.$g])&&$_REQUEST['checkm_g'.$g]=='on') {
						
							if ($context['sa']== 'on')
							{
								$smcFunc['db_query']('',"INSERT INTO {db_prefix}notifygroup (id_group, ".($context['notifytopic']? 'id_topic':'id_board').") VALUES (".$g.", ".($context['notifytopic']? $context['topic']:$context['board']).") ON CONFLICT DO NOTHING");
							}
							elseif($context['sa']=="off")
							{
								$smcFunc['db_query']('',"DELETE FROM {db_prefix}notifygroup WHERE id_group = ".$g." AND ".($context['notifytopic']? 'id_topic='.$context['topic']:'id_board='.$context['board'])." LIMIT 1");
							}
						}
				
						$members=$smcFunc['db_query']('',"select id_member, real_name from {db_prefix}members where {db_prefix}members.id_group=$g or {db_prefix}members.id_post_group=$g or FIND_IN_SET($g, additional_groups) order by {db_prefix}members.real_name");
						
						while($m=$smcFunc['db_fetch_assoc']($members)) {
							if(!in_array($m['id_member'], $memberbank)) {
								$temp.= "&nbsp;&nbsp;&nbsp;&nbsp;<input type='checkbox' id='checkm_".$m['id_member']."' ".(/*$context['notifygroup_page']==1||*/(isset($_REQUEST['checkm_'.$m['id_member']])&&$_REQUEST['checkm_'.$m['id_member']]=='on')? "checked='checked'":"")." name='checkm_".$m['id_member']."' /> ".$m['real_name']."<br />";
								$context['notifygroup_output'].= "check[".($c++)."]='checkm_".$m['id_member']."';";
								$memberbank[]=$m['id_member'];
								
								if($context['notifygroup_page']>1&&isset($_REQUEST['checkm_'.$m['id_member']])&&$_REQUEST['checkm_'.$m['id_member']]=='on') {
									if ($context['sa']== 'on')
									{
										$smcFunc['db_query']('',"INSERT INTO {db_prefix}log_notify (id_member, ".($context['notifytopic']? 'id_topic':'id_board').") VALUES (".$m['id_member'].", ".($context['notifytopic']? $context['topic']:$context['board']).") ON CONFLICT DO NOTHING");
									}
									elseif($context['sa']=="off")
									{
										$smcFunc['db_query']('',"DELETE FROM {db_prefix}log_notify WHERE id_member = ".$m['id_member']." AND ".($context['notifytopic']? 'id_topic='.$context['topic']:'id_board='.$context['board'])." LIMIT 1");
									}
								}
								
							
							}
							
						}
						$smcFunc['db_free_result']($members);
						
					}
				}
				$smcFunc['db_free_result']($groups);
	
			}
			
			$temp.= "<input type='checkbox' id='check_all' name='check_all' "./*($context['notifygroup_page']==1? "checked='checked'":"").*/" onclick='checkall(check, \"check_all\");' /> ".$txt['check_all'].($context['notifygroup_page']>=1? " ".$smcFunc['strtolower']($txt['members']):"")."<br />";
		
			$context['notifygroup_output'].= "</script>$temp";
			
			$notified=$smcFunc['db_query']('',"select mem.real_name, mem.id_member from {db_prefix}members as mem, {db_prefix}log_notify as l where mem.id_member=l.id_member and l.".($context['notifytopic']? 'id_topic='.$context['topic']:'id_board='.$context['board'])." order by mem.real_name");
			while($n=$smcFunc['db_fetch_assoc']($notified)) {
				if(isset($_REQUEST['del'])&&$_REQUEST['del']==$n['id_member'])
					$smcFunc['db_query']('',"DELETE FROM {db_prefix}log_notify WHERE id_member = ".$n['id_member']." AND ".($context['notifytopic']? 'id_topic='.$context['topic']:'id_board='.$context['board'])." LIMIT 1");
				else $context['notifygroup_notified'][]= "<a href='?action=notifygroup;topic=".$context['topic'].";sa=".$context['sa'].";del=".$n['id_member']."'>[X]</a> ".$n['real_name'];
			}
			$smcFunc['db_free_result']($notified);
			
			$notifiedg=$smcFunc['db_query']('',"select g.group_name, g.id_group from {db_prefix}membergroups as g, {db_prefix}notifygroup as l where g.id_group=l.id_group and l.".($context['notifytopic']? 'id_topic='.$context['topic']:'id_board='.$context['board'])." order by g.group_name");
			while($n=$smcFunc['db_fetch_assoc']($notifiedg)) {
				if(isset($_REQUEST['del'])&&$_REQUEST['del']=='g'.$n['id_group'])
					$smcFunc['db_query']('',"DELETE FROM {db_prefix}notifygroup WHERE id_group = ".$n['id_group']." AND ".($context['notifytopic']? 'id_topic='.$context['topic']:'id_board='.$context['board'])." LIMIT 1");
				else $context['notifygroup_notifiedg'][]= "<a href='?action=notifygroup;topic=".$context['topic'].";sa=".$context['sa'].";del=g".$n['id_group']."'>[X]</a> ".$n['group_name'];
			}
			$smcFunc['db_free_result']($notifiedg);

		}
		
		// Batch Mode
		else {
			$context['topic']=$_REQUEST['topic'];
			if (empty($_REQUEST['sa']))
				$context['sa']='on';
			else $context['sa']=$_REQUEST['sa'];
			$context['notifygroup_output']="";
			$context['notifygroup_notified']=array();
			$context['notifygroup_notifiedg']=array();
			if(is_numeric($context['topic'])) $context['notifytopic']=true;
			else {
				$context['notifytopic']=false;
				$context['board']=str_replace("b", "", $context['topic']);
			}
			
			$context['page_title'] = $context['forum_name']." - ".$txt['notifygroup'].": ".$txt['batch'];
			$context['subject']=array();
			
			if(empty($_REQUEST['submit'])) $context['notifygroup_page']=0;
			else $context['notifygroup_page']=$_REQUEST['submit'];
			
			$temp=""; $c=0;
			
			$context['notifygroup_output'].= "
			<script type='text/javascript'>
			function checkall(arr, namer) {
				for(var i=0;i<arr.length;i++) {
					document.getElementById(arr[i]).checked=document.getElementById(namer).checked;
				}
			}
			var check=[];";
			
			$cTopic=0; $topicsList=array(); $boardsList=array();
			$context['batchTopics']=array(); $context['batchString']="";
			$boards=$smcFunc['db_fetch_assoc']($smcFunc['db_query']('',"select count(*) as c from {db_prefix}boards"));
			$topics=$smcFunc['db_fetch_assoc']($smcFunc['db_query']('',"select count(*) as c from {db_prefix}topics"));
			for($cTopic=0; $cTopic<$boards['c']+$topics['c']; $cTopic++) {
        if(!empty($_REQUEST['topic'.$cTopic])) {
          if(is_numeric($_REQUEST['topic'.$cTopic])) $topicsList[]=$_REQUEST['topic'.$cTopic];
          else {
            $boardsList[]=str_replace("b", "", $_REQUEST['topic'.$cTopic]);
          }
          $context['batchTopics'][]=$_REQUEST['topic'.$cTopic];
          $context['batchString'].="topic".$cTopic."=".$_REQUEST['topic'.$cTopic].";";
        }
      }
			
			if($context['notifygroup_page']==0) {
				$groups=$smcFunc['db_query']('',"select id_group, group_name from {db_prefix}membergroups order by group_name");
				while($g=$smcFunc['db_fetch_assoc']($groups)) {
					$temp.="<input type='checkbox' id='checkg_".$g['id_group']."' name='checkg_".$g['id_group']."' /> ".$g['group_name']." <br />";
					$context['notifygroup_output'].= "check[".($c++)."]='checkg_".$g['id_group']."';";
				}
			}
			elseif($context['notifygroup_page']>0) {
				$groups=$smcFunc['db_query']('',"select id_group, group_name from {db_prefix}membergroups");
				$memberbank=array();
				
				while($g=$smcFunc['db_fetch_assoc']($groups)) {
					if(isset($_REQUEST['checkg_'.$g['id_group']])&&$_REQUEST['checkg_'.$g['id_group']]=='on') {
						$temp.="<input type='checkbox' id='checkm_g".$g['id_group']."' ".(/*$context['notifygroup_page']==1||*/(isset($_REQUEST['checkm_g'.$g['id_group']])&&$_REQUEST['checkm_g'.$g['id_group']]=='on')? "checked='checked'":"")." name='checkm_g".$g['id_group']."' />".$g['group_name'].":<br />";
						$g=$g['id_group'];
						$temp.="<input type='hidden' id='checkg_$g' name='checkg_$g' value='on' />";

						if($context['notifygroup_page']>1&&isset($_REQUEST['checkm_g'.$g])&&$_REQUEST['checkm_g'.$g]=='on') {
						
              foreach($topicsList as $topic) {
              
                if ($context['sa']== 'on')
                {
                  $smcFunc['db_query']('',"INSERT INTO {db_prefix}notifygroup (id_group, id_topic) VALUES (".$g.", ".$topic.") ON CONFLICT DO NOTHING");
                }
                elseif($context['sa']=="off")
                {
                  $smcFunc['db_query']('',"DELETE FROM {db_prefix}notifygroup WHERE id_group = ".$g." AND id_topic=".$topic." LIMIT 1");
                }
							
							}
							
							foreach($boardsList as $board) {
              
                if ($context['sa']== 'on')
                {
                  $smcFunc['db_query']('',"INSERT INTO {db_prefix}notifygroup (id_group, id_board) VALUES (".$g.", ".$board.") ON CONFLICT DO NOTHING");
                }
                elseif($context['sa']=="off")
                {
                  $smcFunc['db_query']('',"DELETE FROM {db_prefix}notifygroup WHERE id_group = ".$g." AND id_board=".$board." LIMIT 1");
                }
							
							}
							
						}
				
						$members=$smcFunc['db_query']('',"select id_member, real_name from {db_prefix}members where {db_prefix}members.id_group=$g or {db_prefix}members.id_post_group=$g or FIND_IN_SET($g, additional_groups) order by {db_prefix}members.real_name");
						
						while($m=$smcFunc['db_fetch_assoc']($members)) {
							if(!in_array($m['id_member'], $memberbank)) {
								$temp.= "&nbsp;&nbsp;&nbsp;&nbsp;<input type='checkbox' id='checkm_".$m['id_member']."' ".(/*$context['notifygroup_page']==1||*/(isset($_REQUEST['checkm_'.$m['id_member']])&&$_REQUEST['checkm_'.$m['id_member']]=='on')? "checked='checked'":"")." name='checkm_".$m['id_member']."' /> ".$m['real_name']."<br />";
								$context['notifygroup_output'].= "check[".($c++)."]='checkm_".$m['id_member']."';";
								$memberbank[]=$m['id_member'];
								
								if($context['notifygroup_page']>1&&isset($_REQUEST['checkm_'.$m['id_member']])&&$_REQUEST['checkm_'.$m['id_member']]=='on') {
                  
                  foreach($topicsList as $topic) {
								
                    if ($context['sa']== 'on')
                    {
                      $smcFunc['db_query']('',"INSERT INTO {db_prefix}log_notify (id_member, id_topic) VALUES (".$m['id_member'].", ".$topic.") ON CONFLICT DO NOTHING");
                    }
                    elseif($context['sa']=="off")
                    {
                      $smcFunc['db_query']('',"DELETE FROM {db_prefix}log_notify WHERE id_member = ".$m['id_member']." AND id_topic=".$topic." LIMIT 1");
                    }
									
									}
									
									foreach($boardsList as $board) {
								
                    if ($context['sa']== 'on')
                    {
                      $smcFunc['db_query']('',"INSERT INTO {db_prefix}log_notify (id_member, id_board) VALUES (".$m['id_member'].", ".$board.") ON CONFLICT DO NOTHING");
                    }
                    elseif($context['sa']=="off")
                    {
                      $smcFunc['db_query']('',"DELETE FROM {db_prefix}log_notify WHERE id_member = ".$m['id_member']." AND id_board=".$board." LIMIT 1");
                    }
									
									}
									
								}
								
							
							}
							
						}
						$smcFunc['db_free_result']($members);
						
					}
				}
				$smcFunc['db_free_result']($groups);
	
			}
			
			$temp.= "<input type='checkbox' id='check_all' name='check_all' "./*($context['notifygroup_page']==1? "checked='checked'":"").*/" onclick='checkall(check, \"check_all\");' /> ".$txt['check_all'].($context['notifygroup_page']>=1? " ".$smcFunc['strtolower']($txt['members']):"")."<br />";
		
			$context['notifygroup_output'].= "</script>$temp";
			
			foreach($context['batchTopics'] as $topic) {
			
        if(is_numeric($topic)) $notifytopic=true;
        else {
          $notifytopic=false;
          $board=str_replace("b", "", $topic);
        }
        $context['notifygroup_notified'][$topic]=array();
        $context['notifygroup_notifiedg'][$topic]=array();
        
        if($notifytopic)
          $msg=$smcFunc['db_fetch_assoc']($smcFunc['db_query']('',"select m.subject from {db_prefix}messages as m, {db_prefix}topics as t where t.id_first_msg=m.id_msg and t.id_topic=".$topic.""));
        else
          $msg=$smcFunc['db_fetch_assoc']($smcFunc['db_query']('',"select name as subject from {db_prefix}boards where id_board='".$board."'"));
          
        $context['subject'][$topic]=$msg['subject'];
			
        $notified=$smcFunc['db_query']('',"select mem.real_name, mem.id_member from {db_prefix}members as mem, {db_prefix}log_notify as l where mem.id_member=l.id_member and l.".($notifytopic? 'id_topic='.$topic:'id_board='.$board)." order by mem.real_name");
        while($n=$smcFunc['db_fetch_assoc']($notified)) {
          if($_REQUEST['topic']==$topic&&isset($_REQUEST['del'])&&$_REQUEST['del']==$n['id_member'])
            $smcFunc['db_query']('',"DELETE FROM {db_prefix}log_notify WHERE id_member = ".$n['id_member']." AND ".($context['notifytopic']? 'id_topic='.$context['topic']:'id_board='.$context['board'])." LIMIT 1");
          else $context['notifygroup_notified'][$topic][]= "<a href='?action=notifygroup;batch=true;topic=".$topic.";".$context['batchString']."sa=".$context['sa'].";del=".$n['id_member']."'>[X]</a> ".$n['real_name'];
        }
        $smcFunc['db_free_result']($notified);
        
        $notifiedg=$smcFunc['db_query']('',"select g.group_name, g.id_group from {db_prefix}membergroups as g, {db_prefix}notifygroup as l where g.id_group=l.id_group and l.".($notifytopic? 'id_topic='.$topic:'id_board='.$board)." order by g.group_name");
        while($n=$smcFunc['db_fetch_assoc']($notifiedg)) {
          if($_REQUEST['topic']==$topic&&isset($_REQUEST['del'])&&$_REQUEST['del']=='g'.$n['id_group'])
            $smcFunc['db_query']('',"DELETE FROM {db_prefix}notifygroup WHERE id_group = ".$n['id_group']." AND ".($context['notifytopic']? 'id_topic='.$context['topic']:'id_board='.$context['board'])." LIMIT 1");
          else $context['notifygroup_notifiedg'][$topic][]= "<a href='?action=notifygroup;batch=true;topic=".$topic.";".$context['batchString']."sa=".$context['sa'].";del=g".$n['id_group']."'>[X]</a> ".$n['group_name'];
        }
        $smcFunc['db_free_result']($notifiedg);
			
			}

		}
	}
	
	loadTemplate('notifygroup');
}
?>
