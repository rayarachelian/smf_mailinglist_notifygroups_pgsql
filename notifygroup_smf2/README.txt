SMF Notify Group

by pftq
www.pftq.com/smf-notifygroup/
Version 2.2 (May 28 2012)

for SMF Forums 2.0.x

ABOUT:
	This is a short script I wrote so I can turn on notification of a topic or board for an entire membergroup.  The script works by letting you first select membergroups and then the members within those groups that you want to enable (or disable) notification for.  You can also subscribe groups themselves for boards that tend to have varying members move across groups, and the mod will show the groups and members currently subscribed to a board or topic.  Currently, only members who can moderate the forum (moderators, admins, or groups with moderate_forum permissions) would be able to use the script.
	
	The mod integrates beautifully with the Mailing List mod if you want to also convert your board into a full listserv/mailing list.  It's even more useful as of 1.4 because with a 'group' subscribed to a board instead of a list of members, you can have topics posted in the forums and emailed out automatically without the use of "Announce Topic".
     
INSTALL:
	
	Copy the notifygroup folder to your forum Packages folder (where you installed your forum).
	
	Go to Packages in your Administration Center to install.
	
	To access the script, go to: yourforums/index.php?action=notifygroup
	
	Also, 'Notify Group' link should now be visible in the topic or board display for Admins.  The appearance can be changed by modifying the theme files.  If you have a custom theme, you'll want to copy the changes to your Display.template.php and MessageIndex.template.php files.
	
OPTIONAL:
    The script works best if you add a "Notify Group" button to your theme, so you can get to the script faster without selecting a board or topic.  To do this, open the Display.template.php and MessageIndex.template.php files of your theme.  If you do not have one, then you don't have to do this for that file because SMF will automatically used the modified version from the default theme.
    
    In Display.template.php:
    
    Find '	// Allow adding new buttons easily.'. Above it, insert the following:
    
		// pftq: Notify group
				if($context['can_moderate_forum'])
				$normal_buttons['notifygroup'] = array('text' => 'notifygroup', 'image' => '', 'lang' => true, 'url' => $scripturl . '?action=notifygroup;topic='.$context['current_topic']);
		
	
     In MessageIndex.template.php:
     
     Find '	// They can only mark read if they are logged in and it's enabled!'. Above it, insert the following:
     
		// pftq: Notify group
				if($context['can_moderate_forum'])
				$normal_buttons['notifygroup'] = array('text' => 'notifygroup', 'image' => 'markread.gif', 'lang' => true, 'url' => $scripturl . '?action=notifygroup;topic=b' . $context['current_board'] . '.0');
     
     If you know HTML, you can modify the code to include an actual button if you want.  It's up to you. :)
	
THINGS TO KEEP IN MIND:
	1. A lot of the posts I read about for why SMF did not include this feature was that having too many notications at once would overload the server.  (Imagine every new reply emails all the members on your board.)
	This means that while the "Check All" button is there, you probably don't *actually* want to check all the groups or members.  However, I don't know how powerful your server is or how many members you have.  What you do is ultimately your decision.
	
	2. Enabling notifications for an entire group (as opposed to members within the group) will force all members in the group to receive notifications.  This means individual members will not be able to opt out (unless they're no longer part of the membergroup subscribed or they disable all notifications in their profile), so be careful in how you use this.
	

*****************
Last updated:
2.2
20120708: Added check-all boxes for batch mode.

2.2
20120528: Added batch mode to subscribe/unsubscribe members and groups to multiple boards/topics.

2.1
20120109: Some conformations to SMF spec: Removed hardcoded of a few overlooked text strings, used SMF string functions.

2.0
20120102: Ported to SMF2.

1.7
20120102: Fixed unsubscribe page not working without the Mailing List mod.  Changed "Disable Announcements and Important Notifications" to disable all notifications (otherwise, members don't unsubscribe from anything as they still receive emails if they were subscribed by the admin).

1.6
20110716: Fixed notifications for individuals not working if no groups are assigned for notifications.

1.5
20110714: Fixed implementation of groups.  Was originally emailing all groups that could view the topic/board.

1.4
20110709: Implemented notifying actual 'groups' - that is, you can enable notifications for membergroups as opposed to just members within groups.  Boxes don't check automatically for selecting members/groups to notify.

1.3
20110430: Fixed NotifyGroup not showing up in board index.

1.2
20110108: Uses realName (display names) instead of memberName (unchangeable username) to be consistent with SMF.
20101204: Allowed members with moderating permissions to use Notify Group (besides just Admins).

1.1
20101127: Added board notifications on request.  Also added ability to unsubscribe individual members on the notifygroup page by clicking near their name.

1.0
20100714: Order groups alphabetically.  Overlooked.
20100710: Quick coding changes requested by SMF.
20100129: Divided up code between queries and output, source folder and themes folder, under SMF request.
20091228: Updated to work inside the SMF template.
20091222: Updated coding style for SMF mod approval.
20091205: Created initial script for topics only.