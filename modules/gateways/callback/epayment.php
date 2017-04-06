<?php

# Required File Includes
include("../../../dbconnect.php");
include("../../../includes/functions.php");
include("../../../includes/gatewayfunctions.php");
include("../../../includes/invoicefunctions.php");

# the name of the gateway callback module in our case is "epayment"
$gatewaymodule = "epayment";

$GATEWAY = getGatewayVariables($gatewaymodule);

# Checks gateway module is active before accepting callback
if (!$GATEWAY["type"]) die("Module Not Activated");

# ToDO: Add an IP check here in order to accept callbacks only from Gecad
###############################################################################

/* initialize the debug report */
$debugreport = "";
foreach($_REQUEST as $k => $v){
	$debugreport.="$k => $v\n";
}

/* password set in module`s secret; used to calculate hash */
$pass		= htmlspecialchars_decode($GATEWAY["secret"]);

$result		= "";					// string for compute HASH for received data
$return		= ""; 					// string to compute HASH for return result
$signature	= $_POST["HASH"];		// HASH received
$body		= "";

/* read info received */
ob_start();
	while(list($key, $val) = each($_POST)){
		$$key=$val;
		/* get values */
		if($key != "HASH"){
			if(is_array($val)) $result .= ArrayExpand($val);
			else{
				$size	 = strlen(StripSlashes($val));
				$result	.= $size.StripSlashes($val);
			}
		}
	}
	$body = ob_get_contents();
ob_end_flush();


/* prepare response for ePayment */
$date_return = date("YmdGis");
$return = strlen($_POST["IPN_PID"][0]).$_POST["IPN_PID"][0].strlen($_POST["IPN_PNAME"][0]).$_POST["IPN_PNAME"][0];
$return .= strlen($_POST["IPN_DATE"]).$_POST["IPN_DATE"].strlen($date_return).$date_return;

/* function used to expand an array of values */
function ArrayExpand($array){
	$retval = "";
	for($i = 0; $i < sizeof($array); $i++){
		$size	 = strlen(StripSlashes($array[$i]));
		$retval	.= $size.StripSlashes($array[$i]);
	}
	return $retval;
}

/* function used to calculate the HMAC code */
function hmac ($key, $data){
   $b = 64; // byte length for md5
   if (strlen($key) > $b) {
       $key = pack("H*",md5($key));
   }
   $key  = str_pad($key, $b, chr(0x00));
   $ipad = str_pad('', $b, chr(0x36));
   $opad = str_pad('', $b, chr(0x5c));
   $k_ipad = $key ^ $ipad ;
   $k_opad = $key ^ $opad;
   return md5($k_opad  . pack("H*",md5($k_ipad . $data)));
}

/* prepare the data for payment */
$status		= $_POST["ORDERSTATUS"];
$invoiceid	= $_POST["REFNOEXT"];
$transid	= $_POST["REFNO"];
$amount		= $_POST["IPN_TOTALGENERAL"];	/* total amount paid */
$fee		= $_POST["IPN_COMMISSION"];	/* this is in RON */



# data sent in info
$baseinfo	= $_POST["IPN_INFO"];		// extra info sent via PInfo channel	
$basedata	= explode("|", $baseinfo[0]);	// exploded data into an array

$baseamount	= $basedata[0];
$basecurrency	= $basedata[1];
$baserate	= $basedata[2];

$basepaidamount	= $amount/$baserate;
$basepaidfee	= $fee/$baserate;

/* HASH for data received */
$hash =  hmac($pass, $result);
$body .= $result."\r\n\r\nHash: ".$hash."\r\n\r\nSignature: ".$signature."\r\n\r\nReturnSTR: ".$return;


	/* build the data to be sent HTTP_POST for IDN
	 *
	 **/
	$idn			= array();
	$idn['MERCHANT']	= $GATEWAY["username"];
	$idn['ORDER_REF']	= $_POST["REFNO"];
	$idn['ORDER_AMOUNT']	= $_POST["IPN_TOTALGENERAL"];
	$idn['ORDER_CURRENCY']	= $_POST["CURRENCY"];
	$idn['IDN_DATE']	= date("Y-m-d H:i:s");

	$idn_hstring		 = strlen($idn['MERCHANT']).$idn['MERCHANT'];
	$idn_hstring		.= strlen($idn['ORDER_REF']).$idn['ORDER_REF'];
	$idn_hstring		.= strlen($idn['ORDER_AMOUNT']).$idn['ORDER_AMOUNT'];
	$idn_hstring		.= strlen($idn['ORDER_CURRENCY']).$idn['ORDER_CURRENCY'];
	$idn_hstring		.= strlen($idn['IDN_DATE']).$idn['IDN_DATE'];


	// calculate the HASH value
	$idn_hash		= hash_hmac('md5',$idn_hstring,$pass);


	// add the HASH to the array
	$idn['ORDER_HASH']	= $idn_hash;
	$idn_url		= "https://secure.payu.ro/order/idn.php";


/* test if data is not corrupted */
if($hash == $signature){
	echo "Verified OK!";
    /* send OK response to ePayment  */
    $result_hash =  hmac($pass, $return);
	echo "<EPAYMENT>".$date_return."|".$result_hash."</EPAYMENT>";

	/* Must add condition check for payment status */
	/* ******************************************* */
	# Checks invoice ID is a valid invoice number or ends processing
	$invoiceid = checkCbInvoiceID($invoiceid,$GATEWAY["name"]);
	# Checks transaction number isn`t already in the database and ends processing if it does
	checkCbTransID($transid);
	switch ($status) {
		case "PAYMENT_AUTHORIZED":
			# OK
			# Apply Payment to Invoice: invoiceid, transactionid, amount paid, fees, modulename
			addInvoicePayment($invoiceid,$transid,$basepaidamount,$basepaidfee,$gatewaymodule);

			# this is the point where I  send IDN HTTP_POST confirmation to PayU
			$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $idn_url);
				curl_setopt($ch, CURLOPT_TIMEOUT, 10);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $idn);
				$output = curl_exec($ch);
			curl_close($ch);

			# Save to Gateway Log: name, debugreport, status
			logTransaction($GATEWAY["name"],$debugreport."\n".$output,"Payment Authorized");
			# Save to Gateway Log: name, debugreport, status
			# logTransaction($GATEWAY["name"],$debugreport,"Payment Authorized");
			break;
		case "PAYMENT_RECEIVED":
			# OK
			# technically is the same as previous, but we keep a hook for future expansion, just in case
			# Apply Payment to Invoice: invoiceid, transactionid, amount paid, fees, modulename
			# addInvoicePayment($invoiceid,$transid,$amount,$fee,$gatewaymodule);
			# Save to Gateway Log: name, debugreport, status
			logTransaction($GATEWAY["name"],$debugreport,"Payment Received");
			break;
		case "TEST":
			# Save to Gateway Log: name, debugreport, status

			# this is the point where I send IDN HTTP_POST confirmation to PayU
			$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $idn_url);
				curl_setopt($ch, CURLOPT_TIMEOUT, 10);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $idn);
				$output = curl_exec($ch);
				// $info = curl_getinfo($ch);
			curl_close($ch);

			# Save to Gateway Log: name, debugreport, status
			logTransaction($GATEWAY["name"],$debugreport."\n".$output,"Test Payment");

			# logTransaction($GATEWAY["name"],$debugreport."baserate=".$baserate,"Test Payment");
			break;
		default:
			# Error
			# Save to Gateway Log: name, debug data, status
			logTransaction($GATEWAY["name"],$debugreport,"Error");
		}
	/* ******************************************* */
} else {
    /* log the issue warning */
	logTransaction($GATEWAY["name"],$debugreport,"Error");
}
?>
