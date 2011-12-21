<?php
	session_start();
	if (!isset($_SESSION['User'])){
		header("Location: login.php");
	}
	$user = $_SESSION['User'];
	$isAdmin = $_SESSION['Admin'];
	require 'config.php';
	
	$auctionId = mysql_real_escape_string(stripslashes($_GET['id']));
	$queryAuctions=mysql_query("SELECT * FROM WA_Auctions WHERE id='$auctionId'");
	list($id, $itemName, $itemDamage, $itemOwner, $itemQuantity, $itemPrice)= mysql_fetch_row($queryAuctions);
	$queryEnchantLinks = mysql_query("SELECT * FROM WA_EnchantLinks WHERE itemId = '$id' AND itemTableId = '1'");
		//return mysql_num_rows($queryEnchantLinks);
	$itemEnchantsArray = array ();
		
	while(list($idt, $enchIdt, $itemTableIdt, $itemIdt)= mysql_fetch_row($queryEnchantLinks))
	{  
		$itemEnchantsArray[] = $enchIdt;
			
	}
	$itemOwner = trim($itemOwner);
	//echo $itemOwner.":".$user;
	if ($isAdmin == "true"){
		$queryPlayerItems =mysql_query("SELECT * FROM WA_Items WHERE player='$itemOwner'");
		$foundItem = false;
		$stackId = 0;
		$stackQuant = 0;
		while(list($pid, $pitemName, $pitemDamage, $pitemOwner, $pitemQuantity)= mysql_fetch_row($queryPlayerItems))
				{	
					if($itemName == $pitemName)
					{
						if ($pitemDamage == $itemDamage)
						{
							$queryEnchantLinksMarket = mysql_query("SELECT * FROM WA_EnchantLinks WHERE itemTableId = '0' AND itemId = '$pid'");
							$marketEnchantsArray = array ();
							while(list($idt, $enchIdt, $itemTableIdt, $itemIdt)= mysql_fetch_row($queryEnchantLinksMarket))
							{  
								$marketEnchantsArray[] = $enchIdt;
							}	
							if((array_diff($itemEnchantsArray, $marketEnchantsArray) == null)&&(array_diff($marketEnchantsArray, $itemEnchantsArray) == null)){
								$foundItem = true;
								$stackId = $pid;
								$stackQuant = $pitemQuantity;
							}
						}
					}
				}
		if ($foundItem == true)
				{
					$newQuantity = $itemQuantity + $stackQuant;
					$itemQuery = mysql_query("UPDATE WA_Items SET quantity='$newQuantity' WHERE id='$stackId'");
				}else
				{
					$itemQuery = mysql_query("INSERT INTO WA_Items (name, damage, player, quantity) VALUES ('$itemName', '$itemDamage', '$itemOwner', '$itemQuantity')");
					$queryLatestAuction = mysql_query("SELECT id FROM WA_Items ORDER BY id DESC");
					list($latestId)= mysql_fetch_row($queryLatestAuction);
					
						$queryEnchants=mysql_query("SELECT * FROM WA_EnchantLinks WHERE itemId='$auctionId' AND itemTableId ='1'"); 
						while(list($idk,$enchIdk, $tableIdk, $itemIdk)= mysql_fetch_row($queryEnchants))
						{ 
							$updateEnch = mysql_query("INSERT INTO WA_EnchantLinks (enchId, itemTableId, itemId) VALUES ('$enchIdk', '0', '$latestId')");
						}
					
				}
		$itemDelete = mysql_query("DELETE FROM WA_Auctions WHERE id='$id'");
		$_SESSION['success'] = 'Removed auction successfully';
		header("Location: ../index.php");
	}else{
		$_SESSION['error'] = 'Error removing that auction.';
		header("Location: ../index.php");
	}
?>