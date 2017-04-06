## payu4whmcs

PayU / Gecad ePayment module for WHMCS
version 0.95, 2015.12.24
Copyright (C) 2010-2017  Stefaniu Criste - https://hangar.hosting

### WARNING: THIS PACKAGE IS NO LONGER MAINTAINED

PayU's policy of selecting their customers does not include our company,
therefore we cannot continue to maintain this module. 


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



## INSTALLATION

************************************************************************
1) BACKUP your WHMCS file structure and database!  You have been warned!
************************************************************************

2) Verify your backup.

3) Copy "modules" folder into your <whmcs root> folder
The files should be added along with the other ones in the corresponding folders
- in the folder <whmcs_root>/modules/gateways you should see a folder called epayment, with only one file inside: LiveUpdate.class.php
- in the folder <whmcs_root>/modules/gateways you should see a file called epayment.php
- in the folder <whmcs_root>/modules/gateways/callback you should see a file called epayment.php

4) Open the file <whmcs_root>/modules/gateways/epayment.php for editing
Verify lines 52-72 and modify the values to suit your needs
- VAT value
- customer specific fields


5) activate the module in the WHMCS interface > Setup > Payment Gateways
Fill the information as needed:

[Show on order form]:        tick checkbox if you want the payment method to appear on order forms;
[Display name]:              choose a suitable name for the gateway (e.q. Credit Card)
[Secret Key]:                the one received from PayU
[Merchant Name]:             your merchant ID, as received from PayU;
[Test Mode]:                 tick checkbox when testing the gateway
[Convert To For Processing]  must be set to RON


6) access your PayU control panel and at "Account Management" choose "Account Settings"
Verify that "Notifications" section has "Email Text & IPN" radio button checked and at
"Send notifications for" check only "Authorized orders". Save settings


7) Also, click on second tab "IPN Settings".
Enter the address for the callback function
https://[whmcs_address]/modules/gateways/callback/epayment.php
Do not forget to replace [whmcs_address] with the actual internet address of your WHMCS instance
Save settings


8) Logoff from your PayU Control Panel


9) Check into your whmcs database and issue the folowing query

`select * from tblpaymentgateways where gateway="epayment" and setting="type";`

gateway|setting|value|order|
---------|---------|----------|-------|
epayment | type    | Invoices |     1 |

if "value" is "CC", please change it to "Invoices" (sometimes WHMCS does NOT set it right at the first installation)







### UPGRADING

Upgrading from previous versions is pretty straightforward

************************************************************************
1) BACKUP your WHMCS files and database!  You have been warned!
************************************************************************

2) Verify your backup
3) Copy the folder /modules into your <whmcs root>, overwriting the existing files

This should be all. Now you should do the first tests
Tick on "Test Mode" in WHMCS > Payment Gateways, to make sure nothing bad happens.
Define a dummy user and generate an invoice in WHMCS.
Try to pay the invoice using ePayment gateway in test mode and check the Gateway logs for details
If all OK, untick the "Test Mode" checkbox in Payment Gateways
