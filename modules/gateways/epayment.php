<?php
/*
Gecad ePayment module for WHMCS
version 0.92, 2014.05.19
Copyright (C) 2010-2014  Stefaniu Criste - www.hangarhosting.net

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.

License also available at:	license.txt
Changelog available at:		changelog.txt

*/

/* merge the LiveUpdate class file (obtained from PayU)	*/
require_once("epayment/LiveUpdate.class.php");

/*  this is the configuration function					*/
function epayment_config() {
    $configarray = array(
		"FriendlyName"	=> array("Type" => "System", "Value"=>"Credit Card via PayU"),
		"username"		=> array("FriendlyName" => "Merchant username", "Type" => "text", "Size" => "20", ),
		"secret"		=> array("FriendlyName" => "Merchant secret", "Type" => "text", "Size" => "20", ),
		"testmode"		=> array("FriendlyName" => "Test Mode", "Type" => "yesno", "Description" => "Tick this to set test mode", ),
    );
	return $configarray;
}

/* we are building the payment link (well, actually a form) in order to send the data to PayU */
function epayment_link($params) {

	#############################################################
	# DEBUGGING aid
	#
	# if you encounter problems, uncomment the print_r line below
	# ATTENTION: not to be used in live environments
	#############################################################
	# print_r($params);
	#############################################################


	# Modify the variables below to suit your needs

	# TAX variables
	# 8<--------------------------------------------------------------------------------------------------

	$vat_tax = 19;						/* local VAT tax, in percents;		*/

	# 8<--------------------------------------------------------------------------------------------------


	# Client special variables
	# these variables vary, according to your implementation
	# please check your custom fields in order to match the desired variable
	# 8<--------------------------------------------------------------------------------------------------

	$user_cui = $params['clientdetails']['customfields2']; 	/* VAT number 				*/
	$user_reg = $params['clientdetails']['customfields3']; 	/* Register of Commerce ID		*/
	$user_cnp = $params['clientdetails']['customfields6']; 	/* personal code (CNP in Romanian, use it wisely)		*/ 

	# 8<--------------------------------------------------------------------------------------------------


	######################################################################################################
	#												     #
	#		You shoult NOT modify below unless you know what you`re doing			     #
	#												     #
	######################################################################################################



	# Gateway Specific Variables
	######################################################################################################
	$gatewaysecret		= htmlspecialchars_decode($params['secret']);
	$gatewayusername	= $params['username'];
	$gatewaytestmode	= $params['testmode'];
	######################################################################################################


	# Invoice Variables
	######################################################################################################
	$invoiceid		= $params['invoiceid'];				// the invoice ID, as passed by WHMCS
	$description	= $params['description'];			// description of the invoice
	$amount			= $params['amount'];				// amount to pay, as passed to the gateway
	$currency		= $params['currency'];				// currency passed to the gateway
	$baseamount		= $params['basecurrencyamount'];	// amount to pay, in customer's currency: ##.## (may differ)
	$basecurrency	= $params['basecurrency'];			// customer`s currency code
	$baserate		= $amount/$baseamount;				// rate
	$duedate		= $params['duedate'];				// invoice due date
	$paynow			= $params['langpaynow'];			// the string "Pay Now", in customer's language
	######################################################################################################


	# Client Variables
	######################################################################################################
	$userid			= $params['clientdetails']['userid'];
	$firstname		= $params['clientdetails']['firstname'];
	$lastname		= $params['clientdetails']['lastname'];
	$email			= $params['clientdetails']['email'];
	$address1		= $params['clientdetails']['address1'];
	$address2		= $params['clientdetails']['address2'];
	$city			= $params['clientdetails']['city'];
	$state			= $params['clientdetails']['state'];
	$postcode		= $params['clientdetails']['postcode'];
	$country		= $params['clientdetails']['country'];
	$phone			= $params['clientdetails']['phonenumber'];
	$companyname	= $params['clientdetails']['companyname'];
	######################################################################################################


	# System Variables
	######################################################################################################
	$systemurl	= $params['systemurl'];
	######################################################################################################


	#################################################
	# This is the code to be submitted to the gateway
	##################################################

	# instantiate a new object with the merchant secret key
	$myLiveUpdate	= new LiveUpdate($gatewaysecret);
	$myLiveUpdate->setMerchant($gatewayusername);

	# order reference number is same as the invoice number
	$myLiveUpdate->setOrderRef($invoiceid);

	# set the date as now
	# this is set as the duedate
	# $myOrderDate = $duedate;
	$myOrderDate	= date("Y-m-d H:i:s");
	$myLiveUpdate->setOrderDate($myOrderDate);

	# set the epayment products
	# there will always be a single epayment product, the invoice
	$PName		= array();
	$PName[]	= $description;
	$myLiveUpdate->setOrderPName($PName);

	/*
	 * ==========================================================================
	 * ORDER PRODUCTS GROUPS - set the GROUPS for products
	 * ==========================================================================
	 */
	$PGroup		= array();
	$PGroup[]	= $userid;
	$myLiveUpdate->setOrderPGroup($PGroup);

	/*
	 * ==========================================================================
	 * ORDER PRODUCT CODES - set product codes and check for errors
	 * ==========================================================================
	 */
	$PCode		= array();
	$PCode[]	= $invoiceid;
	$myLiveUpdate->setOrderPCode($PCode);

	/*
	 * ==========================================================================
	 * ORDER PRODUCT ADDITIONAL INFO - set product additional information and
	 * check for orders. 
	 * THIS IS AN OPTIONAL FILED
	 * note that is hardcoded in romanian
	 * added in 0.6
	 * send base currency as a second channel so when callback is called, we will
	 * set the payment in the correct currency
	 * ==========================================================================
	 */
	 $PInfo		= array();
	 $PInfo[]	= $baseamount."|".$basecurrency."|".$baserate ;		// amount to pay in user's currency
	 $myLiveUpdate->setOrderPInfo($PInfo);
	/*
	 * ==========================================================================
	 * ORDER PRODUCT PRICES - set prices for each product and check for errors
	 * if customer is in Romania, price will be recalculated to re-get the VAT
	 * ==========================================================================
	 */

	$PPrice		= array();
	if ('RO' == $country) {
	    $PPrice[]	= $amount/(1+$vat_tax/100);
	} else {
	    $PPrice[]	= $amount;
	}
	$myLiveUpdate->setOrderPrice($PPrice);

	// $PPriceType	= array();
	// $PPriceType[]	= 'GROSS';
	// $myLiveUpdate->setOrderPType($PPriceType);

	/*
	 * ==========================================================================
	 * ORDER PRODUCT QTY - set quantity for each product and check for errors
	 * ==========================================================================
	 */
	$PQTY		= array();
	$PQTY[]		= 1;
	$myLiveUpdate->setOrderQTY($PQTY);

	/*
	 * ==========================================================================
	 * ORDER PRODUCT VAT - set VAT for each product and check for errors
	 * ==========================================================================
	 */
	$PVAT		= array();
	if ('RO' == $country) {
	    $PVAT[]	= $vat_tax;
	} else {
	    $PVAT[]	= 0;
	}
	$myLiveUpdate->setOrderVAT($PVAT);

	/*
	 * ==========================================================================
	 * ORDER SHIPPING
	 * If you don't sent order shipping cost, ePayment system will calculate the 
	 * shipping price. To perform this, set the order shipping to any order less 
	 * than 0.
	 *
	 * When ordershipping is 0, you can set it to 0 or not. By default, the order
	 * shipping is 0. This means that this cost will be assumed.
	 *
	 * In any other case just set the shipping with a positive value.
	 * ==========================================================================
	 */
	
	/*
	 * EXAMPLE - order shipping is not sent 
	 *
	 *	$PShipping = -1;
	 *	$myLiveUpdate->setOrderShipping($PShipping);
	 */
	 
	//$PShipping = 0.145;
	//$myLiveUpdate->setOrderShipping($PShipping);
	$PShipping = -1;
	$myLiveUpdate->setOrderShipping($PShipping);

	/*
	 * ==========================================================================
	 * ORDER CURRENCY
	 * ==========================================================================
	 */
	 $PCurrency 	= $currency;
	 $myLiveUpdate->setPricesCurrency($PCurrency);



	 
	/*
	 * ==========================================================================
	 * ORDER DESTINATION CITY
	 * ==========================================================================
	 */
	$PDestinationCity = $city;
	$myLiveUpdate->setDestinationCity($PDestinationCity);
	
	/*
	 * ==========================================================================
	 * ORDER DESTINATION STATE
	 * ==========================================================================
	 */
	$PDestinationState = $state;
	$myLiveUpdate->setDestinationState($PDestinationState);
	
	/*
	 * ==========================================================================
	 * ORDER DESTINATION COUNTRY CODE
	 * ==========================================================================
	 */
	$PDestinantionCountryCode = $country;
	$myLiveUpdate->setDestinationCountry($PDestinantionCountryCode);


	/*
	 * ==========================================================================
	 * ORDER PAY METHOD
	 * ==========================================================================
	 */
	$PPayMethod 	= 'CCVISAMC';
	$myLiveUpdate->setPayMethod($PPayMethod);

	/*
	 * ==========================================================================
	 * If you want to sent in the live update form the billing information, you 
	 * will have to build an array with the following keys, respecting the 
	 * order and the names. Even for an emtpy key, put it into array and set it
	 * to a blank string. ('');
	 * COUNTRY code - not OK
	 * ==========================================================================
	 */
	$billing = array(
		"billFName"		=> $firstname,
		"billLName"		=> $lastname,
		"billCISerial"		=> '',
		"billCINumber"		=> '',
		"billCIIssuer"		=> '',
		"billCNP"		=> $user_cnp,
		"billCompany"		=> $companyname,
		"billFiscalCode" 	=> $user_cui,
		"billRegNumber" 	=> $user_reg,
		"billBank" 		=> '',
		"billBankAccount" 	=> '',
		"billEmail" 		=> $email,
		"billPhone" 		=> $phone,
		"billFax" 		=> '',
		"billAddress1"		=> $address1,
		"billAddress2"		=> $address2,
		"billZipCode"		=> $postcode,
		"billCity"		=> $city,
		"billState"		=> $state,
		"billCountryCode"	=> $country			
	);
	$myLiveUpdate->setBilling($billing);


	$delivery = array(
		"deliveryFName"			=> $firstname,
		"deliveryLName"			=> $lastname,
		"deliveryCompany"		=> $companyname,
		"deliveryPhone"			=> $phone,
		"deliveryAddress1"		=> $address1,
		"deliveryAddress2"		=> $address2,
		"deliveryZipCode"		=> $postcode,
		"deliveryCity"			=> $city,
		"deliveryState"			=> $state,
		"deliveryCountryCode"		=> $country
	);
	$myLiveUpdate->setDelivery($delivery);


	# set the language (almost HARD CODED)
	if (($params['clientdetails']['language'] == "Romanian") or
	    ($params['clientdetails']['language'] == "romanian") or
	    ($params['clientdetails']['language'] == "Default")) {
		$PLanguage = 'ro';
	} else {
		$PLanguage = 'en';
	}

	$myLiveUpdate->setLanguage($PLanguage);

	# is it in test mode ?
	if ($gatewaytestmode == 'on')
	{ $myLiveUpdate->setTestMode(true);
	} else {
	$myLiveUpdate->setTestMode(false);
	}


	$code = '<form name="frmForm" action="https://secure.payu.ro/order/lu.php" method="post">
'. 
	    $myLiveUpdate->getLiveUpdateHTML() .'<input type="submit" value="'.$paynow.'"></form>';
	return $code;
}

?>