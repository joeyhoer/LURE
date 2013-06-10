<?php
/**
 * Client class holds information about the client's requests, capabilities,
 * and actions
 *
 * @package LURE
 */
class Client
{
	protected $_settings;
	protected $_storage;
	protected $_now;
	protected $_timestamp;

	private $_ipAddress;

	private $_hasCookie;
	private $_hasHallpass;
	private $_hasValidHoneypot;
	private $_hasRequestedImage;
	private $_hasRequestedStyle;
	private $_hasRequestedScript;
	private $_hasExecutedScript;
	private $_hasFiredUserEvent;
	private $_isInTime;
	private $_hasValidRequestMethod;
	private $_hasValidReferer;
	private $_hasValidUserAgent;

	private $_approvedServers;
	private $_approvedRequestMethods;
	private $_submissionWindow;

	function __construct($settings='')
	{
		$this->init();
	}

	// Read private/protected properties (readonly)
	function __get($property) {
        if ( property_exists($this, '_'.$property) )
            return $this->{'_'.$property};
        user_error('Invalid property: ' . __CLASS__ . '->' . $property);
    }

    // Prevent external assignment of private/protected properties (readonly)
    function __set($property, $value) {
    	if ( property_exists($this, '_'.$property) )
        	user_error('Can\'t set readonly property: ' . __CLASS__ . '->' . $property);
        else
        	user_error('Invalid property: ' . __CLASS__ . '->' . $property);
    }

	function init($settings = '') {
		$this->_parse_settings($settings);

		$this->_storage                = &$_SESSION['LURE'];
		$this->_now                    = time();
		$this->_timestamp              = $this->_storage['TIMESTAMP'];

		$this->_ipAddress              = $this->_detectIpAddress();
		$this->_hasCookie              = $this->_hasCookie();
		$this->_hasHallpass            = $this->_hasHallpass('LURE');
		$this->_hasValidHoneypot       = $this->_hasValidHoneypot();
		$this->_hasRequestedImage      = $this->_hasRequestedImage();
		$this->_hasRequestedStyle      = $this->_hasRequestedStyle();
		$this->_hasRequestedScript     = $this->_hasRequestedScript();
		$this->_hasExecutedScript      = $this->_hasExecutedScript();
		$this->_hasFiredUserEvent      = $this->_hasFiredUserEvent();
		
		$this->_isInTime               = $this->_isInTime($this->_settings['submission_window']);
		$this->_hasValidRequestMethod  = $this->_hasValidRequestMethod();
		$this->_hasValidReferer        = $this->_hasValidReferer();
		$this->_hasValidUserAgent      = $this->_hasValidUserAgent();

		unset($_POST[$this->_storage['TESTS']['SCRIPT_EXE']]);
	}

	protected function _parse_settings($settings)
	{
		if (!is_array($settings)){
			parse_str($settings, $settings);
		}

		// Default settings
		$defaults = array(
			// Define the valid referring domain names & IP Addresses
			'referers'          => array($_SERVER['SERVER_NAME']
			                            ,$_SERVER['SERVER_ADDR']),
			// Define the valid request methods
			'request_methods'   => array('POST'),
			// Define a DOM class name to be used to identify elements
			'dom_class_name'    => rand_string(mt_rand(3,10)),
			// Define a name to be used for the test cookie
			'cookie_name'       => rand_string(mt_rand(3,10)),
			// Define a window in which submissions should be considered valid
			'submission_window' => array('min' => 5,
			                             'max' => 3600),
		);

		$this->_settings = array_merge($defaults, $settings);
	}
	
	/**
	 * Detects if cookies are enabled
	 * 
	 * @return BOOL
	 */
	private function _hasCookie()
	{
		$cookie_name = $this->_storage['TESTS']['COOKIE'];
		if (array_key_exists($cookie_name, $_COOKIE)) {
			setcookie($cookie_name, '0', ($this->_now-1), '/');
			return TRUE;
		}
		// Should never come to this…
		if (!empty($_COOKIE))
			return TRUE;
		return FALSE;
	}

	/**
	 * Detects if session variables are working
	 * …is this too much?
	 * 
	 * @return BOOL
	 */
	private function _hasHallpass($hallpass)
	{
		if ( isset($_SESSION[$hallpass]) )
			return TRUE;
		return FALSE;
	}

	/**
	 * Detect automated field population with a honeypot
	 * 
	 * @return BOOL
	 */
	private function _hasValidHoneypot()
	{
		$honeypots = $this->_storage['TESTS']['HONEYPOTS'];
		$found_invalid_honeypot = FALSE;
		foreach ($honeypots as $honeypot){
			if (  isset($_POST[$honeypot])
			   && $_POST[$honeypot] != NULL
			   && !$found_invalid_honeypot ){
				$found_invalid_honeypot = TRUE;	
			}
			unset($_POST[$honeypot]);
		}
		if ($found_invalid_honeypot)
			return FALSE;
		return TRUE;
	}
	
	/**
	 * Detects if a hidden anchor URL was requested
	 * 
	 * @return BOOL
	 */
	private function _hasRequestedBlackhole()
	{
		$page = $this->_storage['TESTS']['BLACKHOLE'];
		if ($page)
			return TRUE;
		return FALSE;
	}
	
	/**
	 * Detects if an image was requested
	 * 
	 * @return BOOL
	 */
	private function _hasRequestedImage()
	{
		if (isset($this->_storage['TESTS']['IMAGE']['RESULT'])
			&& $this->_storage['TESTS']['IMAGE']['RESULT'])
			return TRUE;
		return FALSE;
	}

	/**
	 * Detects if an external stylesheet was requested
	 * 
	 * @return BOOL
	 */
	private function _hasRequestedStyle()
	{
		if (isset($this->_storage['TESTS']['STYLE']['RESULT'])
		   && $this->_storage['TESTS']['STYLE']['RESULT'])
			return TRUE;
		return FALSE;
	}
	
	/**
	 * Detect if an external script was requested
	 *
	 * @return BOOL
	 */
	private function _hasRequestedScript()
	{
		if (isset($this->_storage['TESTS']['SCRIPT']['RESULT'])
		   && $this->_storage['TESTS']['SCRIPT']['RESULT'])
			return TRUE;
		return FALSE;
	}
	
	/**
	 * Detect if JavaScript has executed
	 * While a small number of users may browse with JavaScript disabled
	 * (estimated less than two percent of users worldwide), the inability
	 * to execute JavaScript may be indicative of automated systems.
	 *
	 * @return BOOL
	 */
	private function _hasExecutedScript()
	{
		$post_var = $this->_storage['TESTS']['SCRIPT_EXE'];
		if (isset($_POST[$post_var]))
			return TRUE;
		return FALSE;
	}

	/**
	 * Detect if any JavaScript user events have fired
	 * Users generally perform a number of events before submitting a form 
	 * (e.g. click, scroll, keypress, mouse/touchmove); while bots can be 
	 * designed to simulate these events, many are not.
	 * 
	 * @return BOOL
	 */
	private function _hasFiredUserEvent($event=NULL)
	{
		$post_var     = $this->_storage['TESTS']['SCRIPT_EXE'];
		$user_events  = $_POST[$post_var];
		$length       = count($this->_storage['TESTS']['USER_EVENTS']);
		if (preg_match("/^[01]{" . $length . "}$/", $user_events)) {
			$user_events = str_split($user_events);
			array_walk($user_events, 'intval');
			$user_events = array_combine($this->_storage['TESTS']['USER_EVENTS'],
			                             $user_events);
			if (!$user_events['decoy']){
				ksort($user_events);
				// $this->firedUserEvents = $user_events;
				return TRUE;
			}
		}
		return FALSE;
	}

	/**
	 * Detect if the form was submitted in a reasonable timeframe
	 * By defining a window in which a submission should be considered valid,
	 * user submissions, which generally take longer as the user requires time 
	 * to evaluates content, can be differentiated from bot submissions;
	 * while bots can be designed to delay requests, many do not.
	 * NOTE: §1194.22 (p) "When a timed response is required, the user shall be
	 * alerted and given sufficient time to indicate more time is required."
	 * 
	 * @param  array  window minimum and maximum number of seconds the form could take 
	 *   to complete
	 * @return bool
	 */
	private function _isInTime($window)
	{
		if ( !isset($this->_timestamp) ) return FALSE;
		if (  $this->_now >= ($this->_timestamp + $window['min'])
		   || $this->_now <= ($this->_timestamp + $window['max']) )
			return TRUE;
		return FALSE;
	}

	/**
	 * Validate request method
	 * While multiple request methods exists, requests submitted by an HTML
	 * <form> will use either 'GET' or 'POST', and may use both; requests
	 * recieved via a non-approved method are indicative of automated systems.
	 *
	 * 
	 * @link http://www.w3.org/html/wg/drafts/html/master/forms.html#attr-fs-method
	 * @param  mixed $valid_methods a list of valid request methods
	 * @return bool
	 */
	private function _hasValidRequestMethod($valid_methods = '')
	{
		if ( !empty($valid_methods) ) {
			if ( !in_array($_SERVER['REQUEST_METHOD'], $valid_methods) )
				return FALSE; // Request method not approved
		}
		return TRUE;
	}
	
	/**
	 * Validate the referering domain name/IP address
	 * The referer identifies the address of the webpage (i.e. the URI)
	 * that linked to the resource being requested; while the referer can be 
	 * spoofed by both users and bots, requests orginating from non-approved
	 * are a servers and domain names are indicative of automated systems.
	 * 
	 * @param  mixed $valid_referers a list of valid referering domain names/IP addresses
	 * @return bool
	 */
	private function _hasValidReferer($valid_referers = '')
	{
		if ( !empty($valid_referers) ) {
			if ( !isset($_SERVER['HTTP_REFERER']) ) return FALSE; 
			else {
				$valid_referers = implode('|', $valid_referers);
				if ( !preg_match('/^https?:\/\/(' . $valid_referers . ')/', 
				                 $_SERVER['HTTP_REFERER']) )
					return FALSE; // Referer not approved
			}
		}
		return TRUE;
	}
	
	/**
	 * Validate the client's user agent
	 * A user agent holds information about the client's system and browser;
	 * while the user agent can be spoofed by both users and bots, an unusual 
	 * user agent may be indicative of automated systems.
	 * 
	 * @return bool
	 */
	private function _hasValidUserAgent()
	{
		if ( empty($_SERVER['HTTP_USER_AGENT']) ) return FALSE;
			
		#TODO: Add more bad user agent patterns
		$bad_patterns = array(
			 '<'       ,'>'           ,'\''       ,'&lt;'        ,'%0A'         
			,'%0D'     ,'%27'         ,'%3C'      ,'%3E'         ,'%00'         
			,'href\s'  ,'archiver'    ,'binlar'   ,'casper'      ,'checkprivacy' 
			,'clshttp' ,'cmsworldmap' ,'comodo'   ,'curl'        ,'diavol' 
			,'dotbot'  ,'email'       ,'extract'  ,'feedfinder'  ,'flicky' 
			,'grab'    ,'harvest'     ,'httrack'  ,'ia_archiver' ,'jakarta'
			,'kmccrew' ,'libwww'      ,'loader'   ,'miner'       ,'nikto' 
			,'nutch'   ,'planetwork'  ,'purebot'  ,'pycurl'      ,'python' 
			,'scan'    ,'skygrid'     ,'sucker'   ,'turnit'      ,'vikspider' 
			,'wget'    ,'winhttp'     ,'youda'    ,'zmeu'        ,'zune'
		);
		$bad_pattern = "(" . implode("|", $bad_patterns) . ")";
		if ( preg_match($bad_pattern, $_SERVER['HTTP_USER_AGENT']) )
			return FALSE; // Bad user agent found
		return TRUE;
	}


	/**
	 * Detect the true IP address
	 * 
	 * @return string the IP address
	 */
	private function _detectIpAddress()
	{
		if( !isset($_SERVER['REMOTE_ADDR']) ) return NULL;
		
		// Order is important
		$server_vars = array(
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR'
		);
		foreach ($server_vars as $server_var) {
			if (array_key_exists($server_var, $_SERVER)) {
				foreach (explode(',', $_SERVER[$server_var]) as $ip) {
					$ip = trim($ip);
					if ( filter_var( $ip
					               , FILTER_VALIDATE_IP
					               , FILTER_FLAG_IPV4
					               | FILTER_FLAG_NO_PRIV_RANGE
					               | FILTER_FLAG_NO_RES_RANGE  ) !== FALSE )
						return $ip;
				}
			}
		}
		return NULL;
	}

	/**
	 * Validate email addresses
	 * @return bool
	 */
	private function _validateEmailAddress($email, $record = 'MX')
	{
		if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
			list($user, $domain) = split('@', $email);
			return checkdnsrr($domain, $record);
		}
		return FALSE;
	}

	#TODO: Cross-refrence logs for return clients
	#TODO: Validate required fields?
	#TODO: Check for referer for SPAM
}