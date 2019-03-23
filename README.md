# mailing_list_smf2 and notifygroups_smf2

These are two SMF mods that I wish to use, but my SMF 2.0.15 uses postgress 11 as a backend, it seems these use mysql specific upsert dialects, generating these exceptions:

From mailing list mod:
```
ERROR: syntax error at or near "IGNORE"
LINE 1: INSERT IGNORE INTO smf_settings (variable, value) values('ma...
^
File: /var/www/html/Packages/temp/mailinglist_smf2/emailpost_setup.php
Line: 21
```

From notify:
```
Database Error
ERROR: syntax error at or near "IGNORE"
LINE 1: INSERT IGNORE INTO smf_notifygroup (id_group, id_topic, id_b...
^
File: /var/www/html/Packages/temp/notifygroup_setup.php
Line: 3
```

So unfortunately INSERT IGNORE generates errors, but replacing these with INSERT ... ON CONFLICT DO NOTHING in the sql statements these mods use fixes the issue.

Putting this here incase anyone else runs into the same issue.

Discussion on support forum here: 
[https://www.pftq.com/forums/index.php?topic=4604]: https://www.pftq.com/forums/index.php?topic=4604
