<?php
namespace PrawnSalad\Nexmo;

/**
 * Class NexmoMessage handles the methods and properties of placing voice call.
 * 
 * Usage: $var = new NexoCall( $account_key, $account_password );
 * Methods:
 *     placeCall ( $to, $from, $answerUrl)
 *     
 */

class NexmoCall{

	// Nexmo account credentials
	private $nx_key = '';
	private $nx_secret = '';

	/**
	 * @var string Nexmo server URI
	 *
	 * We're sticking with the JSON interface here since json
	 * parsing is built into PHP and requires no extensions.
	 * This will also keep any debugging to a minimum due to
	 * not worrying about which parser is being used.
	 */
	var $nx_uri = 'https://rest.nexmo.com/call/json';

	
	/**
	 * @var array The most recent parsed Nexmo response.
	 */
	private $nexmo_response = '';
	

	/**
	 * @var bool If recieved an inbound message
	 */
	var $inbound_message = false;


	// Current message
	public $to = '';
	public $from = '';
	public $answerUrl = '';
	public $statusUrl = '';

	public $call_id = '';
	
	public $status = '';
	public $error_text;
	
	// A few options
	public $ssl_verify = false; // Verify Nexmo SSL before sending any message


	function __construct($api_key, $api_secret) {
		$this->nx_key = $api_key;
		$this->nx_secret = $api_secret;
	}



	/**
	 * Place a voice call to the specified number.
	 *
	 */
	function placeCall ($to, $from, $url) {
	
		// Making sure strings are UTF-8 encoded
		if ( !is_numeric($from) && !mb_check_encoding($from, 'UTF-8') ) {
			trigger_error('$from needs to be a valid UTF-8 encoded string');
			return false;
		}

		// Make sure $from is valid
		$from = $this->validateOriginator($from);

		// Send away!
		$post = array(
			'from' => $from,
			'to' => $to,
			'answer_url' => $url

		);
		
		return $this->sendRequest ( $post );
		
	}
	
	


	
	/**
	 * Prepare and send a new message.
	 */
	private function sendRequest ( $data ) {
		// Build the post data
		$data = array_merge($data, array('username' => $this->nx_key, 'password' => $this->nx_secret));
		$post = http_build_query($data);

		// If available, use CURL
		if (function_exists('curl_version')) {

			$to_nexmo = curl_init( $this->nx_uri );
			curl_setopt( $to_nexmo, CURLOPT_POST, true );
			curl_setopt( $to_nexmo, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $to_nexmo, CURLOPT_POSTFIELDS, $post );

			if (!$this->ssl_verify) {
				curl_setopt( $to_nexmo, CURLOPT_SSL_VERIFYPEER, false);
			}

			$from_nexmo = curl_exec( $to_nexmo );
			error_log(print_r(curl_getinfo($to_nexmo), true));
			curl_close ( $to_nexmo );

		} elseif (ini_get('allow_url_fopen')) {
			// No CURL available so try the awesome file_get_contents

			$opts = array('http' =>
				array(
					'method'  => 'POST',
					'header'  => 'Content-type: application/x-www-form-urlencoded',
					'content' => $post
				)
			);
			$context = stream_context_create($opts);
			$from_nexmo = file_get_contents($this->nx_uri, false, $context);

		} else {
			// No way of sending a HTTP post :(
			return false;
		}

		
		return $this->nexmoParse( $from_nexmo );
	 
	}
	
	
	/**
	 * Recursively normalise any key names in an object, removing unwanted characters
	 */
	private function normaliseKeys ($obj) {
		// Determine is working with a class or araay
		if (is_object($obj)) {
			$new_obj = new \stdClass();
			$is_obj = true;
		} else {
			$new_obj = array();
			$is_obj = false;
		}


		foreach($obj as $key => $val){
			// If we come across another class/array, normalise it
			if (is_object($val) || is_array($val)) {
				$val = $this->normaliseKeys($val);
			}
			
			// Replace any unwanted characters in they key name
			if ($is_obj) {
				$new_obj->{str_replace('-', '_', $key)} = $val;
			} else {
				$new_obj[str_replace('-', '_', $key)] = $val;
			}
		}

		return $new_obj;
	}


	/**
	 * Parse server response.
	 */
	private function nexmoParse ( $from_nexmo ) {
		$response = json_decode($from_nexmo);

		// Copy the response data into an object, removing any '-' characters from the key
		$response_obj = $this->normaliseKeys($response);

		if ($response_obj) {
			$this->nexmo_response = $response_obj;

			return $response_obj;

		} else {
			// A malformed response
			$this->nexmo_response = array();
			return false;
		}
		
	}


	/**
	 * Validate an originator string
	 *
	 * If the originator ('from' field) is invalid, some networks may reject the network
	 * whilst stinging you with the financial cost! While this cannot correct them, it
	 * will try its best to correctly format them.
	 */
	private function validateOriginator($inp){
		// Remove any invalid characters
		$ret = preg_replace('/[^a-zA-Z0-9]/', '', (string)$inp);

		if(preg_match('/[a-zA-Z]/', $inp)){

			// Alphanumeric format so make sure it's < 11 chars
			$ret = substr($ret, 0, 11);

		} else {

			// Numerical, remove any prepending '00'
			if(substr($ret, 0, 2) == '00'){
				$ret = substr($ret, 2);
				$ret = substr($ret, 0, 15);
			}
		}
		
		return (string)$ret;
	}

}
