<?php

// Authentication key & salt: using unique passwords for both fields will decrease the likelihood of a robot submitting valid data to your form (which makes them easier to detect and block)
define('AUTH_KEY', 'SUPER SECRET PASSWORD');
define('AUTH_SALT', 'SUPER SECRET PASSWORD');

/* 
$settings = array(
		// Define the valid referring domain names & IP Addresses
		"referers"          => array($_SERVER['SERVER_NAME'], $_SERVER['SERVER_ADDR']),
		// Define the valid request methods
		"request_methods"   => array('POST'),
		// Define a DOM class name to be used to identify elements
		"dom_class_name"    => rand_string(mt_rand(3,10)),
		// Define a name to be used for the test cookie
		"cookie_name"       => rand_string(mt_rand(3,10)),
		// Define a window in which submissions should be considered valid
		"submission_window" => array("minimum" => 10,
		                             "maximum" => 3600),
	);
*/