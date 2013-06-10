<?php 

/**
 * Generates a random string from given seed characters. If no seed characters 
 * are provided the function defaults to using upper and lowecase alphabetical
 * characters.
 *
 * @param  int          $length      the length of the string
 * @param  string|array $seed_chars  an array of characters to be used to populate
 *   the string 
 * @return string                    the random string
 */
function rand_string($length, $seed_chars = NULL)
{
	// Default chars
	if (empty($seed_chars))
		$seed_chars = implode('', array_merge(range('A','Z'), range('a','z')));

	$rand_string = NULL;
	$seed_chars_count = strlen($seed_chars);
	for ($i = 0; $i < $length; $i++) {
		$rand_char    = $seed_chars[mt_rand(0, $seed_chars_count-1)];
		$rand_string .= $rand_char;
	}
	return $rand_string;
}

/**
 * Generate a unique file path
 *
 * @param  string $directory the file's directory
 * @param  string $extension the file's extension 
 * @return string            a unique file path
 */
function unique_path($directory = './', $extension = '')
{
	$filename = rand_string(mt_rand(3,10));
	while ( file_exists($directory.$filename.$extension) ){
		$filename = rand_string(mt_rand(3,10));
	}
	return $directory.$filename.$extension;
}

/**
 * Returns the value of a given key from a given array, and optionally removes
 * that key from the array.
 *
 * @param  string $key   the key to extract from the array
 * @param  array  $array the array to extact the key from
 * @param  bool   $unset (optional) remove the key from the array
 * @return mixed         the value of the extracted key
 */
function extract_value_for_key_in_array($key, &$array, $unset = TRUE)
{
	if (array_key_exists($key, $array)) {
		$value = $array[$key];
		if ($unset)
			unset($array[$key]);
		return $value;
	}
	return NULL;
}

/**
 * Creates RFC 4648 'base64url' encoded strings
 *
 * @link   http://tools.ietf.org/html/rfc4648#section-4
 * @param  string $data data to be encoded
 * @return string       encoded data
 */
function base64url_encode($data) { 
	return rtrim(strtr(base64_encode($data), '+/', '-_'), '='); 
} 

/**
 * Decodes an RFC 4648 'base64url' encoded string
 *
 * @param  string $data data to be decoded
 * @return string       decoded data
 */
function base64url_decode($data)
{ 
	return base64_decode( str_pad( strtr($data, '-_', '+/')
	                             , strlen($data) % 4
	                             , '='
	                             , STR_PAD_RIGHT ) ); 
}

/**
 * Creates SGML Namesafe encoded string
 *
 * @link http://www.w3.org/TR/html4/types.html#type-name
 * @param  string $data data to be encoded
 * @return string       encoded data
 */
function sgml_namesafe_encode($data)
{
	return rand_string(1) . $data;
}

/**
 * Decodes an SGML Namesafe encoded string
 *
 * @param  string $data data to be decoded
 * @return string       decoded data
 */
function sgml_namesafe_decode($data)
{
	return substr($data, 1);
}

/**
 * Prevent a file from being accessed directly by producing a 404 page
 */
function preventDirectAccess()
{
	$request = end(explode('/', $_SERVER['REQUEST_URI']));
	$file    = end(explode('/', $_SERVER['SCRIPT_NAME']));
	if ($request == $file) {
		header('HTTP/1.0 404 Not Found');
		exit();
	}
}