<?php
$smcFunc['db_query']('', "create table IF NOT EXISTS {db_prefix}notifygroup (id_group int not null, id_topic int not null, id_board int not null, primary key (id_group, id_topic, id_board))");
$smcFunc['db_query']('', "INSERT INTO {db_prefix}notifygroup (id_group, id_topic, id_board) values(0,0,0) ON CONFLICT DO NOTHING");
?>
