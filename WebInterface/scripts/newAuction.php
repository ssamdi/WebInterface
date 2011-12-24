<?php
	session_start();
	if (!isset($_SESSION['User'])){
		header("Location: login.php");
	}
	$isAdmin = $_SESSION['Admin'];
	$user = $_SESSION['User'];
	$canSell = $_SESSION['canSell'];
	if ($canSell == false){
		$_SESSION['error'] = 'You do not have permission to sell items.';
		header("Location: ../myauctions.php");
	}
	require 'config.php';
	require 'itemInfo.php';
	require_once '../classes/EconAccount.php';
	require_once '../classes/Item.php';
	if ($useTwitter == true){require_once 'twitter.class.php';}
	$itemId = mysql_real_escape_string(stripslashes($_POST['Item']));
	$item = new Item($itemId);
	$player = new EconAccount($user, $useMySQLiConomy, $iConTableName);
	$sellPrice = round($_POST['Price'], 2);
	
	if ($sellPrice > $maxSellPrice){ $sellPrice == $maxSellPrice; }
	$sellQuantity = floor($_POST['Quantity']);
	
    if ($sellQuantity <= 0){
		$_SESSION['error'] = 'Quantity was not a valid number.';
		header("Location: ../myauctions.php");
	}
	if ($sellPrice <= 0)
	{
		$_SESSION['error'] = 'Price was not a valid number.';
		header("Location: ../myauctions.php");
	}
	else{
		if (is_numeric($sellPrice)){	
			if ((is_numeric($sellQuantity))&&($sellQuantity > 0)){
				$sellQuantity = round($sellQuantity);
				if ($item->quantity >= $sellQuantity)
				{
					$item->changeQuantity(0 - $sellQuantity);
					$itemFee = (($item->marketprice/100)*$auctionFee)*$sellQuantity;
					if ($player->money >= $itemFee){
						if ($sellQuantity > 0)
						{
							$timeNow = time();
							$player->spend($itemFee, $useMySQLiConomy, $iConTableName);
							$itemQuery = mysql_query("INSERT INTO WA_Auctions (name, damage, player, quantity, price, created) VALUES ('$item->name', '$item->damage', '$item->owner', '$sellQuantity', '$sellPrice', '$timeNow')");
							$queryLatestAuction = mysql_query("SELECT id FROM WA_Auctions ORDER BY id DESC");
							list($latestId)= mysql_fetch_row($queryLatestAuction);
						}
						if ($item->quantity == 0)
						{
							$item->delete();
						}
						if ($useTwitter == true){
							try{
							$twitter = new Twitter($consumerKey, $consumerSecret, $accessToken, $accessTokenSecret);
							$twitter->send('[WA] Auction Created: '.$user.' is selling '.$sellQuantity.' x '.$itemFullName.' for '.$currencyPrefix.$sellPrice.$currencyPostfix.' each. At '.date("H:i:s").'. '.$shortLinkToAuction.' #webauction');
							}catch (Exception $e){
								//normally means you reached the daily twitter limit.
							}
						
						}
						$queryEnchants=mysql_query("SELECT * FROM WA_EnchantLinks WHERE itemId='$item->id' AND itemTableId ='0'"); 
						while(list($idk,$enchIdk, $tableIdk, $itemIdk)= mysql_fetch_row($queryEnchants))
						{ 
							$updateEnch = mysql_query("INSERT INTO WA_EnchantLinks (enchId, itemTableId, itemId) VALUES ('$enchIdk', '1', '$latestId')");
						}
												
						$_SESSION['success'] = "You auctioned $sellQuantity $itemFullName for ".$currencyPrefix.$sellPrice.$currencyPostfix." each, the fee was ".$currencyPrefix.$itemFee.$currencyPostfix;
						header("Location: ../myauctions.php");
					}else
					{
					  $_SESSION['error'] = 'Fee cost '.$currencyPrefix.$itemFee.$currencyPostfix.', you did not have enough money.';
					  header("Location: ../myauctions.php");
					}
				}else
				{
				    $_SESSION['error'] = 'You do not have enough of that item.';
					header("Location: ../myauctions.php");
				}
			}else
			{
				$_SESSION['error'] = 'Quantity was not an integer.';
				header("Location: ../myauctions.php");
			}
		}else
		{
			$_SESSION['error'] = 'Price was not an integer.';
			header("Location: ../myauctions.php");
		}
	}
	
?>