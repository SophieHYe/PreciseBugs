<?php 
require '../sql/connect.php';
require '../conf/global.php';
$ip = $_SERVER['REMOTE_ADDR'];

if($ip == "127.0.0.1" || $ip == "::1" || $ip == ''){
	
	if($sysguard){
		echo "DEBUG: System Guard Online";
			
		// Money Transaction Checker
		$chktrans = "SELECT * FROM bank_transactions WHERE watchdog_approve = '0' AND wd_scanned = '0'";
		$result2 = $conn->query($chktrans);
		$num_rows = mysqli_num_rows($result2);
		if($num_rows > 0){
			
			$syslogsql = "INSERT INTO gamelogs(userid,username,type) VALUES('9', 'SYSTEM', 'Watchdog Performing Scan: Bank Transactions')";
			$result = $conn->query($syslogsql);
			
			while($row = $result2->fetch_assoc()) {
				$transactionid = $row['id'];
				$senderid = $row['userid'];
				$recieverid = $row['recvid'];
				$amount = $row['amount'];
				
				$checkUserIp = "SELECT regip FROM accounts WHERE id = '$senderid' limit 1";
				$chkipResult = $conn->query($checkUserIp);
				
				while ($rowip = mysqli_fetch_assoc($chkipResult)) {
					$account1ip = $rowip["regip"];
				};
				
				$checkUserIp2 = "SELECT regip FROM accounts WHERE id = '$recieverid' limit 1";
				$chkipResult2 = $conn->query($checkUserIp2);			
				
				while ($rowip2 = mysqli_fetch_assoc($chkipResult2)) {
					$account2ip = $rowip2["regip"];
				};
				
				if($senderid == $recieverid && $amount < 0 && $account1ip == $account2ip){
					// Known bank transfer logic (A negative transaction charge is sent to the user to subtract out the dollar amount from their account.)
					$WD_APP = "UPDATE bank_transactions SET watchdog_approve = '1', wd_scanned = '1' WHERE id = '$transactionid'";
					$result = $conn->query($WD_APP);

				};
				if($senderid == $recieverid && $amount > 0 && $account1ip == $account2ip){
					// Known bank transfer logic (Post the new balance to the user as if they deposited the money themselves.)
					$WD_APP = "UPDATE bank_transactions SET watchdog_approve = '1', wd_scanned = '1' WHERE id = '$transactionid'";
					$result = $conn->query($WD_APP);

				};
				if($senderid != $recieverid && $amount < 0 && $account1ip == $account2ip){
					// ACCOUNT CAUGHT | User has created another account and sent the funds to alt account
					$WD_DNY = "UPDATE bank_transactions SET watchdog_approve = '0', wd_scanned = '1' WHERE id = '$transactionid'";
					$result = $conn->query($WD_DNY);
					
					$sqlsearchuname = "SELECT username FROM accounts WHERE id = '$senderid' limit 1";
					$result22 = $conn->query($sqlsearchuname);
					$row22 = $result22->fetch_assoc();
					$username = $row22['username'];
					
					$WD_ALRT = "INSERT INTO gamelogs(userid,username,type) VALUES ('$senderid', '$username', 'MULTI-ACCOUNT MONEY TRANSFER DETECTED!!!')";
					$result = $conn->query($WD_ALRT);
				};
				if($senderid != $recieverid && $amount > 0 && $account1ip == $account2ip){
					// ACCOUNT CAUGHT | User has created another account and sent the funds to alt account
					$WD_DNY = "UPDATE bank_transactions SET watchdog_approve = '0' AND wd_scanned = '1' WHERE id = '$transactionid";
					$result = $conn->query($WD_DNY);
					
					$sqlsearchuname = "SELECT username FROM accounts WHERE id = '$senderid' limit 1";
					$result22 = $conn->query($sqlsearchuname);
					$row22 = $result22->fetch_assoc();
					$username = $row22['username'];
					
					$WD_ALRT = "INSERT INTO gamelogs(userid,username,type) VALUES ('$senderid', '$username', 'MULTI-ACCOUNT MONEY TRANSFER DETECTED!!!')";
					$result = $conn->query($WD_ALRT);
				};
				if($senderid != $recieverid && $amount > 0 && $account1ip != $account2ip){
					// Known bank transfer logic ( Legit Transfer )
					$WD_APP = "UPDATE bank_transactions SET watchdog_approve = '1', wd_scanned = '1' WHERE id = '$transactionid'";
					$result = $conn->query($WD_APP);				
				};
				if($senderid == $recieverid && $amount > 0 && $account1ip != $account2ip){
					$WD_APP = "UPDATE bank_transactions SET watchdog_approve = '1', wd_scanned = '1' WHERE id = '$transactionid'";
					$result = $conn->query($WD_APP);		
				};
				if($senderid != $recieverid && $amount < 0 && $account1ip != $account2ip){
					$WD_APP = "UPDATE bank_transactions SET watchdog_approve = '1', wd_scanned = '1' WHERE id = '$transactionid'";
					$result = $conn->query($WD_APP);
				};
			}		
		}
		else {
			// Nothing to do, do nothing.
		}
	}
		
	else{
		echo "DEBUG: System Guard Offline";
	};
}

else {
	echo "403 Forbidden : System Access Only";
};
?>