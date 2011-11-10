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
	if ($useTwitter == true){require_once 'twitter.class.php';}
	$itemNameFull = explode(":", $_POST['Item']);
	$sellName = $itemNameFull[0];
	$sellDamage = $itemNameFull[1];
	$player = new EconAccount($user, $useMySQLiConomy, $iConTableName);
	$marketPrice = getMarketPrice($sellName, $sellDamage);
	$sellPrice = round($_POST['Price'], 2);
	$itemFullName = getItemName($sellName, $sellDamage);
	if ($sellPrice > $maxSellPrice){ $sellPrice == $maxSellPrice; }
	$sellQuantity = floor($_POST['Quantity']);
	$maxStack = getItemMaxStack($sellName, $sellDamage);
	
	$queryItems=mysql_query("SELECT * FROM WA_Items WHERE player='$user' AND name='$sellName' AND damage='$sellDamage'");
	list($id, $itemName, $itemDamage, $itemOwner, $itemQuantity)= mysql_fetch_row($queryItems);
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
				if ($itemQuantity >= $sellQuantity)
				{
					$itemsLeft = $itemQuantity - $sellQuantity;
					$itemFee = (($marketPrice/100)*$auctionFee);
					if ($player->money >= $itemFee){
						if ($sellQuantity > 0)
						{
							$timeNow = time();
							$player->money = $player->money - $itemFee;
							$player->saveMoney($useMySQLiConomy, $iConTableName);
							$itemQuery = mysql_query("INSERT INTO WA_Auctions (name, damage, player, quantity, price, created) VALUES ('$sellName', '$sellDamage', '$user', '$sellQuantity', '$sellPrice', '$timeNow')");
						}
						if ($itemsLeft == 0)
						{
							$itemDelete = mysql_query("DELETE FROM WA_Items WHERE id='$id'");
						}
						else
						{
							$itemUpdate = mysql_query("UPDATE WA_Items SET quantity='$itemsLeft' WHERE id='$id'");
						}
						if ($useTwitter == true){
							$twitter = new Twitter($consumerKey, $consumerSecret, $accessToken, $accessTokenSecret);
							$twitter->send('[WA] Auction Created: '.$user.' is selling '.$sellQuantity.' x '.$itemFullName.' for '.$currencyPrefix.$sellPrice.$currencyPostfix.' each. At '.date("H:i:s").'. '.$shortLinkToAuction.' #webauction');
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