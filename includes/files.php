<?php
require_once('functions.php');

// Removes PHP header
// OR add `php_flag expose_php Off` to php.ini
// header_remove("X-Powered-By");

$file = new File();

preventDirectAccess();

$ext = pathinfo($_SERVER['REQUEST_URI'], PATHINFO_EXTENSION);
switch (strtolower($ext)) {
	case 'js':
		$file->setFiletype('script');
		break;
	case 'css':
		$file->setFiletype('style');
		break;
	case 'gif':
		$file->setFiletype('image');
		break;
	case 'html':
		$file->setFiletype('page');
		break;
} 

echo $file->output();

class File
{
	protected $_storage;
	protected $_filetype;
	protected $_headers;
	protected $_fileContents;

	public function __construct() {
		session_start();
		$this->_storage = &$_SESSION['LURE'];
	}

	protected function _sendHeaders()
	{
		if ( isset($this->_headers) ){
			foreach ($this->_headers as $header) {
				header($header);
			}
		}
	}

	public function setFiletype($filetype)
	{
		$this->_filetype = $filetype;
		switch ($filetype) {
			case 'script':
				$this->_headers[] = 'Content-Type: text/javascript';
				$this->_setFileContents($this->script());
				break;
			case 'style':
				$this->_headers[] = 'Content-Type: text/css';
				$this->_setFileContents($this->style());
				break;
			case 'image':
				$this->_headers[] = 'Content-Type: image/gif';
				$this->_setFileContents($this->image());
				break;
			case 'page':
				$this->_setFileContents($this->page());
				break;
		}
	}

	protected function _setFileContents($data)
	{
		$this->_fileContents = $data;
	}

	protected function _getFileContents()
	{
		return $this->_fileContents;
	}

	public function output()
	{
		$this->_sendHeaders($this->_headers);
		return $this->_getFileContents();
	}

	/**
	 * Generates minified JavaScript source which appends a verification field 
	 * to all forms and removes LURE elements from the DOM.
	 *
	 * @return string JavaScript source
	 */
	function script()
	{
		if ( !isset($this->_storage['TESTS']['SCRIPT']['RESULT']) ) {
			$this->_storage['TESTS']['SCRIPT']['RESULT'] = TRUE;
		}

		$DOM_class_name = isset($this->_storage['DOM_CLASS_NAME'])?$this->_storage['DOM_CLASS_NAME']:'';
		$script_url  = 'http' . (!empty($_SERVER['HTTPS'])?'s':'') . '://' 
		               . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];

		$user_events = NULL;
		if (!isset($this->_storage['TESTS']['USER_EVENTS'])) {
			$user_events  = array(
					 'mousedown'   ,'mouseup'     ,'click'       ,'dblclick'
					,'scroll'      ,'mousemove'   ,'mouseover'   ,'mouseout'
					,'mouseenter'  ,'mouseleave'  ,'keypress'    ,'keydown'
					,'keyup'       ,'touchmove'   ,'touchstart'  ,'touchend'
				);
			$user_events[] = 'decoy'; // For additional bot stoppage
			shuffle($user_events);
			$this->_storage['TESTS']['USER_EVENTS'] = $user_events;	
		} else {
			$user_events = $this->_storage['TESTS']['USER_EVENTS'];
		}
		$user_events[array_search('decoy', $user_events)] = NULL;

		$test_name = NULL;
		if (!isset($this->_storage['TESTS']['SCRIPT_EXE'])) {
			$test_name = rand_string(mt_rand(3,10));
			$this->_storage['TESTS']['SCRIPT_EXE'] = $test_name;
		} else {
			$test_name = $this->_storage['TESTS']['SCRIPT_EXE'];
		}

		/*$vars = array(
			'events'        => "['".implode("','", $user_events)."']",
			'hidden_field'  => '[]',
			'field_value'   => "''",
			'index'         => '0',
			'scripts'       => 'd.scripts',
			'forms'         => 'd.forms',
			'node_list'     => "d.getElementsByClassName('$DOM_class_name')"
		);

		foreach ($vars as $key => $value) {
			$vars[$key] = array(
				'name'  => rand_string(1),
				'value' => $value
			);
		}*/

		$javascript = '';

		// Initalize all global variables
		$javascript .= 'var ' 
		                 . "n=['" . implode("','", $user_events) . "']," 
		                 . "q=[]," 
		                 . "v=''," 
		                 . "i=0," 
		                 . "s=d.scripts," 
		                 . "f=d.forms," 
		                 . "h=d.getElementsByClassName('" . $DOM_class_name . "');" 
		             . "for(var i=0;i<n.length;i++)v+='0';";

		// Append a hidden field to all forms
		// Save the fields so that the values may be updated later
		$javascript .= "for(var i=0;i<f.length;i++){" 
		                 . "var x=d.createElement('input');" 
		                 . "x.setAttribute('type','hidden');" 
		                 . "x.name='" . $test_name . "';" 
		                 . "x.value=v;"
		                 . "q[i]=f[i].appendChild(x);" 
		             . "}";

		// A function to update the values of the hidden field (set above)
		$javascript .= "function u(i,a){" 
		                 . "v=v.substr(0,i)+a+v.substr(i+1);" 
		                 . "q.forEach(function(e){e.value=v})" 
		             . "}";

		// Detect user events
		$javascript .= "n.forEach(function(e,i){" 
		                 . "window.addEventListener(e,function(){" 
		                     . "u(i,'1');" 
		                     . "this.removeEventListener(e,arguments.callee)" 
		                 . "})" 
		             . "});";

		// Remove this script from the DOM
		// Intend to use `document.currentScript` in the future
		$javascript .= "for(var i=0;i<s.length;i++){" 
		                     . "if(s[i].src=='" . $script_url ."')" 
		                     . "s[i].parentNode.removeChild(s[i])" 
		             . "}";

		// Remove other LURE elements from the DOM 
		// NOTE: `getElementsByClassName()` (chosen for speed) returns a live 
		// node list, so it is being traversed with a `while` loop.
		$javascript .= "i=0;while(i<h.length){" 
		                 . "if(h[i].tagName!='INPUT')" 
		                     . "h[i].parentNode.removeChild(h[i]);" 
		                 . "else " 
		                     . "i++;" 
		             . "}";

		// Load an external JavasScript test file
		// $javascript .= "var s=d.createElement('script');" 
		//              . "s.scr='//**********;" 
		//              . "m.parentNode.insertBefore(s, m);'";
		
		// Wrap the script in a loader
		return "window.addEventListener('DOMContentLoaded'," 
		         . "(function(d){" . $javascript . "})(document)" 
		     . ")";
	}

	/**
	 * Generate an ultra-optimized GIF
	 *
	 * @link http://probablyprogramming.com/2009/03/15/the-tiniest-gif-ever
	 * @return string GIF source
	 */
	function image()
	{
		if ( !isset($this->_storage['TESTS']['IMAGE']['RESULT']) ) {
			$this->_storage['TESTS']['IMAGE']['RESULT'] = TRUE;
		}
		return base64_decode('R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=');
	}

	/**
	 * Generate a minified CSS document
	 * The styles in this document will visually hide elements from users
	 *
	 * @return string CSS source
	 */
	function style()
	{
		if ( !isset($this->_storage['TESTS']['STYLE']['RESULT']) ) {
			$this->_storage['TESTS']['STYLE']['RESULT'] = TRUE;
		}
		$css = '';
		if ($DOM_class_name = $this->_storage['DOM_CLASS_NAME']) {
			$css .= '.' . $DOM_class_name . '{display:none}';
		}
		return $css;
	}

	/**
	 * Generate a small, valid HTML5 document
	 *
	 * @return string HTML source
	 */
	function page()
	{
		if ( !isset($this->_storage['TESTS']['BLACKHOLE']['RESULT']) ) {
			$this->_storage['TESTS']['BLACKHOLE']['RESULT'] = TRUE;
		}
		return '<!doctype html>';
	}
}