<?php

/* Google Account details */
$config['appName']  = 'Application Name';
$config['appEmail'] = '123456789012@developer.gserviceaccount.com';
$config['clientID'] = '123456789012.apps.googleusercontent.com';
$config['keyPath']  = 'application/keys/qwertyuiop123456789-privatekey.p12';
$config['profileID'] = 'ga:12345678';

// Google_AnalyticsServices are only instantiated using the private key above.
// Details below are common but not necessary in this Pthread's example.
$config['accountID'] = 'UA-12345678-1';
$config['domain'] = "'auto'";
