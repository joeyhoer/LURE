<?php 
require_once('config.php');
require_once('includes/client.php');
require_once('includes/functions.php');

/**
 * LURE - Logistic User/Robot Evaluation
 * Performs a variety of tests to determine if the client is a user or a robot.
 * A primary goal of LURE is user transparency; as such, most users will be 
 * unaware that tests are being performed.
 *
 * @package LURE
 * @author Joey Hoer 
 * @copyright 2013 Joey Hoer. All rights reserved.
 * @license http://opensource.org/licenses/bsd-license.php
 *
 */
class Lure
{
	public $client;
	protected $_settings;
	protected $_storage;
	protected $_now;
	protected $_timestamp;
	protected $_DOMClassName;

	function __construct($settings = '')
	{
		$this->init();

		$this->_settings  = $settings;

		if (!empty($settings)) {
			$this->_parse_args($settings);
		}
	}

	public function init() {
		session_start();
		$this->_storage = &$_SESSION['LURE'];
		$this->_now = time();

		if (isset($this->_storage['DOM_CLASS_NAME']))
			$this->_DOMClassName = $this->_storage['DOM_CLASS_NAME'];
		else {
			$this->_DOMClassName =
				$this->_storage['DOM_CLASS_NAME'] = rand_string(mt_rand(3,10));
		}

		$this->_cookie(rand_string(mt_rand(3,10)));

		#TODO: Run session_destroy(), or collect garbage at some point
	}

	public function evaluate()
	{
		$this->unspin();
		$this->_timestamp   = $this->_storage['TIMESTAMP'];
		$this->client       = new Client();
	}

	/**
	 * UTF-8, Base64URL and SGML Namesafe encode data
	 * Although the HTML5 spec allows almost any string to be used 
	 * as an HTML attributes, including those special characters and spaces,
	 * this looks much nicer and the HTML4 spec was less flexible.
	 *
	 * @link   http://www.w3.org/html/wg/drafts/html/master/dom.html#attributes
	 * @link   http://www.w3.org/html/wg/drafts/html/master/forms.html#attr-fe-name
	 * @param  string $data data to be encoded
	 * @return string        encoded data
	 */
	protected function encode($data)
	{
		return sgml_namesafe_encode(base64url_encode(utf8_encode($data)));
	}

	/**
	 * Decoded Base64URL, UTF-8, SGML Namesafe encoded data
	 * 
	 * @param  string $data data to be decoded
	 * @return string       decoded data
	 */
	protected function decode($data)
	{
		return utf8_decode(base64url_decode(sgml_namesafe_decode($data)));
	}

	/**
	 * Encrypt data
	 * 
	 * @param  string $data data to be encrypted
	 * @return string        encrypted data
	 */
	protected static function encrypt($data)
	{
		$key = AUTH_KEY;
		$td = mcrypt_module_open(MCRYPT_TRIPLEDES, '', MCRYPT_MODE_ECB, '');
		$iv = mcrypt_create_iv(mcrypt_enc_get_iv_size($td), MCRYPT_DEV_RANDOM);
		mcrypt_generic_init($td, $key, $iv);
		$encrypted_data = mcrypt_generic($td, $data);
		mcrypt_generic_deinit($td);
		mcrypt_module_close($td);
		return $encrypted_data;
	}

	/**
	 * Decrypt data 
	 * 
	 * @param  string $data data to be decrypted
	 * @return string       decrypted data
	 */
	protected static function decrypt($data)
	{
		$key = AUTH_KEY;
		$td = mcrypt_module_open(MCRYPT_TRIPLEDES, '', MCRYPT_MODE_ECB, '');
		$iv = mcrypt_create_iv (mcrypt_enc_get_iv_size($td), MCRYPT_DEV_RANDOM);
		mcrypt_generic_init($td, $key, $iv);
		$decrypted_data = mdecrypt_generic($td, $data);
		mcrypt_generic_deinit($td);
		mcrypt_module_close($td);
		return $decrypted_data;
	}

	/**
	 * Encrypt and encode data for use as HTML attributes and store data so it
	 * may be decrypted later
	 *
	 * @param  string $data
	 * @return string data to be encrypted and encoded 
	 */
	public function spin($data)
	{
		$encrypted_encoded_data = $this->encode($this->encrypt($data));
		$this->_storage['ENCRYPTED_NAMES'][] = $encrypted_encoded_data;
		return $encrypted_encoded_data;
	}

	/**
	 * Decrypt and decode POST data and resets the POST so it may be used as normal
	 *
	 * @return bool
	 */
	public function unspin() {
		$spun_keys = $this->_storage['ENCRYPTED_NAMES'];
		if ( !empty($spun_keys) ) {
			foreach ($spun_keys as $spun_key) {
					if ( isset($_POST[$spun_key]) ) {
						$value = $_POST[$spun_key];
						$unspun_key = $this->decrypt($this->decode($spun_key));
						unset($_POST[$spun_key]);
						$_POST[$unspun_key] = $value;
					}
			}
		}
		return TRUE;
	}

	############################################################################
	#pragma mark Setup
	############################################################################
	public function setup()
	{
			$this->_timestamp =
				$this->_storage['TIMESTAMP'] = time();
	}

	/**
	 * Generate HTML <img> markup for an external image
	 * Used to test the client for external image requests; many bots do not
	 * perform these requests
	 *
	 * @return string HTML <img> markup for an external image 
	 */
	public function image()
	{
		if ( !isset($this->_storage['TESTS']['IMAGE']) ) {
			$image_uri = unique_path('../img/', '.gif');
			$this->_storage['TESTS']['IMAGE']['NAME'] = $image_uri;
		}
		return "<img class='" . $this->_DOMClassName 
		          . "' src='" . $this->_storage['TESTS']['IMAGE']['NAME'] ."'>";
	}
	
	/**
	 * Generate HTML <link> markup for an external stylesheet
	 * Used to test the client for external stylesheet requests; many bots do
	 * not perform these requests
	 *
	 * @return string HTML <link> markup for an external stylesheet
	 */
	public function style()
	{
		if ( !isset($this->_storage['TESTS']['STYLE']) ) {
			$style_uri = unique_path('../css/', '.css');
			$this->_storage['TESTS']['STYLE']['NAME'] = $style_uri;
		}
		return "<link rel='stylesheet' "
		          . "href='" . $this->_storage['TESTS']['STYLE']['NAME'] . "'>";
	}
	
	/**
	 * Generate HTML <script> markup for an external script
	 * Used to test the client for external script requests; many bots do not
	 * perform these requests
	 *
	 * @return string HTML <script> markup for an external stylesheet
	 */
	public function script()
	{
		if ( !isset($this->_storage['TESTS']['SCRIPT']) ) {
			$script_uri = unique_path('../js/', '.js');
			$this->_storage['TESTS']['SCRIPT']['NAME'] = $script_uri;
		}
		return "<script async "
		         . "src='" . $this->_storage['TESTS']['SCRIPT']['NAME'] . "'>"
		         . "</script>";
	}
	
	/**
	 * Generate HTML <a> markup for a blackhole link
	 * A blackhole link is a hidden anchor tag that, if followed, flags the
	 * client as a possible bot
	 *
	 * @param  string $text the TextNode of the blackhole link
	 * @return string       HTML <a> markup for a blackhole link
	 */
	public function blackhole($text = 'Click Here')
	{
		if ( !isset($this->_storage['TESTS']['BLACKHOLE']) ) {
			$blackhole_uri = unique_path('../', '.html');
			$this->_storage['TESTS']['BLACKHOLE'] = $blackhole_uri;
		}
		return "<a class='" . $this->_DOMClassName 
		       . "' href='" . $this->_storage['TESTS']['BLACKHOLE'] 
		             . "'>" . $text . "</a>";
	}

	/**
	 * Generate HTML <input> markup for a honeypot field
	 * A honeypot is a hidden input field that, if submitted with a non-empty
	 * value, flags the client as a possible bot
	 * Using a generic name (e.g. 'email' or 'lastname') will increase 
	 * probability of bots submitting a non-empty in the field
	 *
	 * @param  string $name the name attribute of the honeypot field
	 * @return string       HTML <input> markup for a honeypot field
	 */
	public function honeypot($name)
	{
		if ( !isset($this->_storage['TESTS']['HONEYPOTS'])
		   || !in_array($name, $this->_storage['TESTS']['HONEYPOTS']) )
			$this->_storage['TESTS']['HONEYPOTS'][] = $name;
		return "<input class='" . $this->_DOMClassName . 
		             "' name='" . $name . "' autocomplete='off'>";
	}
	
	/**
	 * Set a test cookie
	 * Used to test the client for acceptance of cookies; many bots do not
	 * accept cookies.
	 * Other $_COOKIE variables may be set, however their destruction may occur
	 * during the script's execution. To ensure the cookie persists, a new
	 * cookie must be generated.
	 *
	 * @param  string $name the name of the cookie
	 * @return bool           
	 */
	protected function _cookie($name)
	{
		if (!isset($this->_storage['TESTS']['COOKIE'])) {
			$this->_storage['TESTS']['COOKIE'] = $name;
			return setcookie($name, '1', 0, '/');
		}
		return FALSE;
	}
	
	private function _parse_args($args, $defaults = '') {

	}

	############################################################################
	#pragma mark Evalution
	############################################################################

	/**
	 * Determines if client is a robot
	 * 
	 * @return bool is the client a robot
	 */
	public function isRobot()
	{
		/*'IP Address'           => $this->client->ipAddress,             // 1 Point
		'Has Cookie'           => $this->client->hasCookie,             // 0.5 Point
		'Has Hallpass'         => $this->client->hasHallpass,           // 1 Point
		'Passed Honeypot'      => $this->client->hasValidHoneypot,      // 1 Point
		'Loaded Image'         => $this->client->hasRequestedImage,     // 1 Point
		'Loaded Style'         => $this->client->hasRequestedStyle,     // 1 Point
		'Loaded Script'        => $this->client->hasRequestedScript,    // 1 Point
		'Executed Script'      => $this->client->hasExecutedScript,     // 1 Point
		'Fired User Event'     => $this->client->hasFiredUserEvent,     // 1 Point
		'Is In Time'           => $this->client->isInTime,              // 1 Point
		'Is Valid Request'     => $this->client->hasValidRequestMethod, // 1 Point
		'Has Valid Referer'    => $this->client->hasValidReferer,       // 1 Point
		'Has Valid User Agent' => $this->client->hasValidUserAgent      // 1 Point
		return FALSE;*/
	}
	
	#TODO: Add Database Logging
	#TODO: Analyize variable(s) for common SPAM keywords, HTML Tags, URLs, etc.
}