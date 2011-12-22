<?php
	session_start();
	if (!isset($_SESSION['User'])){
		header("Location: login.php");
	}
	$user = trim($_SESSION['User']);
	$canBuy = $_SESSION['canBuy'];
	if ($canBuy == false){
		$_SESSION['error'] = 'You do not have permission to buy that.';
		header("Location: ../index.php");
	}
	require 'config.php';
	require 'itemInfo.php';
    require_once '../classes/EconAccount.php';
	if ($useTwitter == true){require_once 'twitter.class.php';}

   
    $numberLeft = 0;

    $player = new EconAccount($user, $useMySQLiConomy, $iConTableName);


	$itemId = $_POST['ID'];
    $queryAuctions=mysql_query("SELECT * FROM WA_Auctions WHERE id='$itemId'");
	list($id, $itemName, $itemDamage, $itemOwner, $itemQuantity, $itemPrice)= mysql_fetch_row($queryAuctions);

    $owner = new EconAccount($itemOwner, $useMySQLiConomy, $iConTableName);
	$queryEnchantLinks = mysql_query("SELECT * FROM WA_EnchantLinks WHERE itemId = '$itemId' AND itemTableId = '1'");
		//return mysql_num_rows($queryEnchantLinks);
	$itemEnchantsArray = array ();
		
	while(list($idt, $enchIdt, $itemTableIdt, $itemIdt)= mysql_fetch_row($queryEnchantLinks))
	{  
		$itemEnchantsArray[] = $enchIdt;
			
	}


    if (is_numeric($_POST['Quantity']) AND $_POST['Quantity'] != 0)
    {
	    $buyQuantity = mysql_real_escape_string(stripslashes(round(abs($_POST['Quantity']))));
    }
    elseif ($_POST['Quantity'] < 0)
    {
        $_SESSION['error'] = "Please enter a quantity greater than 0";
        header("Location: ../index.php");
        return;
    }
    else{
        $buyQuantity = $itemQuantity;
    }

    $totalPrice = round($itemPrice*$buyQuantity, 2);
	$numberLeft = $itemQuantity-$buyQuantity;

	if ($numberLeft < 0){
        $_SESSION['error'] = "You are attempting to purchase more than the maximum available";
		header("Location: ../index.php");
	}
	else{

	$itemFullName = getItemName($itemName, $itemDamage);
	if ($player->money >= $totalPrice){
		if ($user != $itemOwner){
			$timeNow = time();
			$player->money = $player->money - $totalPrice;
			$owner->money = $owner->money + $totalPrice;

            $player->saveMoney($useMySQLiConomy, $iConTableName);
            $owner->saveMoney($useMySQLiConomy, $iConTableName);
            $alertQuery = mysql_query("INSERT INTO WA_SaleAlerts (seller, quantity, price, buyer, item) VALUES ('$itemOwner', '$buyQuantity', '$itemPrice', '$user', '$itemFullName')");

			if ($sendPurchaceToMail){
				$maxStack = getItemMaxStack($itemName, $itemDamage);
				while($buyQuantity > $maxStack)
				{
					$buyQuantity -= $maxStack;
					$itemQuery = mysql_query("INSERT INTO WA_Mail (name, damage, player, quantity) VALUES ('$itemName', '$itemDamage', '$user', '$maxStack')");
					$queryLatestAuction = mysql_query("SELECT id FROM WA_Mail ORDER BY id DESC");
					list($latestId)= mysql_fetch_row($queryLatestAuction);
					$queryEnchantLinks=mysql_query("SELECT enchId FROM WA_EnchantLinks WHERE itemId='$itemId' AND itemTableId=1"); 
					while(list($enchId)= mysql_fetch_row($queryEnchantLinks))
					{ 
						$queryEnchants=mysql_query("SELECT * FROM WA_EnchantLinks WHERE itemId='$itemId' AND itemTableId ='1'"); 
						while(list($idk,$enchIdk, $tableIdk, $itemIdk)= mysql_fetch_row($queryEnchants))
						{ 
							$updateEnch = mysql_query("INSERT INTO WA_EnchantLinks (enchId, itemTableId, itemId) VALUES ('$enchIdk', '2', '$latestId')");
						}
					}
				}
				if ($buyQuantity > 0)
				{
					$itemQuery = mysql_query("INSERT INTO WA_Mail (name, damage, player, quantity) VALUES ('$itemName', '$itemDamage', '$user', '$buyQuantity')");
					$queryLatestAuction = mysql_query("SELECT id FROM WA_Mail ORDER BY id DESC");
					list($latestId)= mysql_fetch_row($queryLatestAuction);
					$queryEnchantLinks=mysql_query("SELECT enchId FROM WA_EnchantLinks WHERE itemId='$itemId' AND itemTableId=1"); 
					while(list($enchId)= mysql_fetch_row($queryEnchantLinks))
					{ 
						$queryEnchants=mysql_query("SELECT * FROM WA_EnchantLinks WHERE itemId='$itemId' AND itemTableId ='1'"); 
						while(list($idk,$enchIdk, $tableIdk, $itemIdk)= mysql_fetch_row($queryEnchants))
						{ 
							$updateEnch = mysql_query("INSERT INTO WA_EnchantLinks (enchId, itemTableId, itemId) VALUES ('$enchIdk', '2', '$latestId')");
						}
					}
				}
				$queryLatestAuction = mysql_query("SELECT id FROM WA_Mail ORDER BY id DESC");
				list($latestId)= mysql_fetch_row($queryLatestAuction);
			}else{
				$queryPlayerItems =mysql_query("SELECT * FROM WA_Items WHERE player='$user'");
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
					$newQuantity = $buyQuantity + $stackQuant;
					$itemQuery = mysql_query("UPDATE WA_Items SET quantity='$newQuantity' WHERE id='$stackId'");
				}else
				{
					$itemQuery = mysql_query("INSERT INTO WA_Items (name, damage, player, quantity) VALUES ('$itemName', '$itemDamage', '$user', '$buyQuantity')");
					$queryLatestAuction = mysql_query("SELECT id FROM WA_Items ORDER BY id DESC");
					list($latestId)= mysql_fetch_row($queryLatestAuction);
					
						$queryEnchants=mysql_query("SELECT * FROM WA_EnchantLinks WHERE itemId='$itemId' AND itemTableId ='1'"); 
						while(list($idk,$enchIdk, $tableIdk, $itemIdk)= mysql_fetch_row($queryEnchants))
						{ 
							$updateEnch = mysql_query("INSERT INTO WA_EnchantLinks (enchId, itemTableId, itemId) VALUES ('$enchIdk', '0', '$latestId')");
						}
					
				}
			}
            if ($numberLeft != 0)
            {
			    $itemDelete = mysql_query("UPDATE WA_Auctions SET quantity='$numberLeft' WHERE id='$itemId'");
            }else{
                $itemDelete = mysql_query("DELETE FROM WA_Auctions WHERE id='$itemId'");
            }
			$logPrice = mysql_query("INSERT INTO WA_SellPrice (name, damage, time, buyer, seller, quantity, price) VALUES ('$itemName', '$itemDamage', '$timeNow', '$user', '$itemOwner', '$buyQuantity', '$itemPrice')");
			$queryLatestAuction = mysql_query("SELECT id FROM WA_SellPrice ORDER BY id DESC");
			list($latestId)= mysql_fetch_row($queryLatestAuction);
			
				$queryEnchants=mysql_query("SELECT * FROM WA_EnchantLinks WHERE itemId='$itemId' AND itemTableId ='1'"); 
				while(list($idk,$enchIdk, $tableIdk, $itemIdk)= mysql_fetch_row($queryEnchants))
				{ 
					$updateEnch = mysql_query("INSERT INTO WA_EnchantLinks (enchId, itemTableId, itemId) VALUES ('$enchIdk', '3', '$latestId')");
				}
			$base = isTrueDamage($itemName, $itemDamage);
			if ($base > 0){
				$queryEnchantLinksMarket = mysql_query("SELECT * FROM WA_EnchantLinks WHERE itemTableId = '4'");
				$foundIt = false;
				if (mysql_num_rows($queryEnchantLinks) == 0){
					$queryMarket1=mysql_query("SELECT * FROM WA_MarketPrices WHERE name='$itemName' AND damage='0' ORDER BY id DESC");
					$maxId = -1;
					while(list($idm, $namem, $damagem, $timem, $pricem, $refm)= mysql_fetch_row($queryMarket1))
					{	
						$queryMarket2 = mysql_query("SELECT * FROM WA_EnchantLinks WHERE itemId = '$idm' AND itemTableId = '4'");
						if (mysql_num_rows($queryMarket2)== 0){
							if ($idm > $maxId){
								$maxId = $idm;
								$foundIt = true;
							}	
						}
					}
					if ($foundIt){
						$queryMarket=mysql_query("SELECT * FROM WA_MarketPrices WHERE id = '$maxId' ORDER BY id DESC");
						$foundIt = true;
					}
				}
				else {
					$queryMarket1=mysql_query("SELECT * FROM WA_MarketPrices WHERE name='$itemName' AND damage='0' ORDER BY id DESC");
					$maxId = -1;
					$foundIt = false;
					while(list($idm, $namem, $damagem, $timem, $pricem, $refm)= mysql_fetch_row($queryMarket1))
					{
						$marketEnchantsArray = array ();
						$queryMarket2 = mysql_query("SELECT enchId FROM WA_EnchantLinks WHERE itemId = '$idm' AND itemTableId = '4'");
						while(list($enchIdt)= mysql_fetch_row($queryMarket2))
						{
							if ($idm > $maxId){
								$marketEnchantsArray[] = $enchIdt;
							
							}
						}
						if((array_diff($itemEnchantsArray, $marketEnchantsArray) == null)&&(array_diff($marketEnchantsArray, $itemEnchantsArray) == null)){
							$maxId = $idm;
							$foundIt = true;
						}
					
					}
					if ($foundIt){
						$queryMarket=mysql_query("SELECT * FROM WA_MarketPrices WHERE id = '$maxId' ORDER BY id DESC");
						$foundIt = true;
					}
				
				}
				if ($foundIt == false){
						$queryMarket=mysql_query("SELECT * FROM WA_MarketPrices WHERE id = '-1' ORDER BY id DESC");
					}

			}else{
				$queryMarket=mysql_query("SELECT * FROM WA_MarketPrices WHERE name='$itemName' AND damage='$itemDamage' ORDER BY id DESC");	

			}
			$countMarket = mysql_num_rows($queryMarket);
			if ($countMarket == 0){
				//market price not found
				$newMarketPrice = $itemPrice;
				$marketCount = $buyQuantity;
			}else{
				//found get first item
				
				$rowMarket = mysql_fetch_row($queryMarket);
				$marketId = $rowMarket[0];
				$marketPrice = $rowMarket[4];
				$marketCount = $rowMarket[5];
				$newMarketPrice = (($marketPrice*$marketCount)+$totalPrice)/($marketCount+$buyQuantity);
				$marketCount = $marketCount+$buyQuantity;
				
			}
			if ($base > 0){
				
				$newMarketPrice = ($newMarketPrice/($base - $itemDamage))*$base;
				
				$insertMarketPrice = mysql_query("INSERT INTO WA_MarketPrices (name, damage, time, marketprice, ref) VALUES ('$itemName', '0', '$timeNow', '$newMarketPrice', '$marketCount')");
				$queryLatestAuction = mysql_query("SELECT id FROM WA_MarketPrices ORDER BY id DESC");
				list($latestId)= mysql_fetch_row($queryLatestAuction);
				
					$queryEnchants=mysql_query("SELECT * FROM WA_EnchantLinks WHERE itemId='$itemId' AND itemTableId ='1'"); 
					while(list($idk,$enchIdk, $tableIdk, $itemIdk)= mysql_fetch_row($queryEnchants))
					{ 
						$updateEnch = mysql_query("INSERT INTO WA_EnchantLinks (enchId, itemTableId, itemId) VALUES ('$enchIdk', '4', '$latestId')");
					}
				
			}else{

				$insertMarketPrice = mysql_query("INSERT INTO WA_MarketPrices (name, damage, time, marketprice, ref) VALUES ('$itemName', '$itemDamage', '$timeNow', '$newMarketPrice', '$marketCount')");
			}
			if ($useTwitter == true){
				try{
				$twitter = new Twitter($consumerKey, $consumerSecret, $accessToken, $accessTokenSecret);
				$twitter->send('[WA] Item Bought: '.$user.' bought '.$buyQuantity.' x '.$itemFullName.' for '.$currencyPrefix.$itemPrice.$currencyPostfix.' each from '.$itemOwner.'. At '.date("H:i:s").'. '.$shortLinkToAuction.' #webauction');
				}catch (Exception $e){
			   		//may have reached twitter daily limit
				}
			}
            $_SESSION['success'] = "You purchased $buyQuantity $itemFullName from $itemOwner for ".$currencyPrefix.$totalPrice.$currencyPostfix.".";
			header("Location: ../index.php");

		}else {
            $_SESSION['error'] = 'You cannnot buy your own items.';
			header("Location: ../index.php");
		}
	}else{
        $_SESSION['error'] = 'You do not have enough money.';
        header("Location: ../index.php");
	}
	}

?>