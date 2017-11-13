<?php

	include('./redis.class.php');
	$redis = new My_Redis();
	
	$res = $redis->sadd('set21',time());
	$redis->sremAll('set21');
	echo $redis->scard('set21');
	$res = $redis->smembers('set21');
	var_dump($res)
?>;