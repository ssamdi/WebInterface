<?php
	
	$queryCheck=mysql_query("SELECT * FROM WA_StorageCheck ORDER BY id DESC");
	$checkCount = mysql_num_rows($queryCheck);
	if ($checkCount == 0)
	{
		$insert = mysql_query("INSERT INTO WA_StorageCheck (time) VALUES (0)");
		$queryCheck=mysql_query("SELECT * FROM WA_StorageCheck ORDER BY id DESC");
		//echo "Nothing found, creating 0 <br/>";
	}
	$queryCheck=mysql_query("SELECT * FROM WA_StorageCheck ORDER BY id DESC");
	$checkRow = mysql_fetch_row($queryCheck);
	//echo "Value Found is: $checkRow[1] <br/>";
	$now = time();
	
	if ($now > $checkRow[1])
	{	
		//echo "Time to check!<br/>";
		$queryPlayers=mysql_query("SELECT DISTINCT player FROM WA_Items");
		$players = array();
		while(list($player)= mysql_fetch_row($queryPlayers))
		{
			$players[$player] = 0;
		}
		$queryItems=mysql_query("SELECT * FROM WA_Items");
		while(list($id, $name, $damage, $player, $quantity)= mysql_fetch_row($queryItems))
		{
			$cost = $quantity * ($costPerItemPerDay / $numberOfChecksPerDay);
			$players[$player] += $cost;
		}
		//echo "<pre>";
		//print_r($players);
		//echo "</pre>";
		foreach ($players as $p => $v) {
			$account = new EconAccount($p, $useMySQLiConomy, $iConTableName);
			$account->money = $account->money - $v;
		}
		$next = $now + (86400 / $numberOfChecksPerDay);
		$insert = mysql_query("INSERT INTO WA_StorageCheck (time) VALUES ($next)");
	}	
?>