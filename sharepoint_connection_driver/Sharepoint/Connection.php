<?php
/**
 * A class for HTTP connections to Sharepoint
 * 
 * For some reason Curl couldn't handle NTLM authentication...
 * 
 * Modified the code from http://forums.fedoraforum.org/showthread.php?t=230535
 * 
 * @author Tuomas Angervuori <tuomas.angervuori@gmail.com>
 * @license http://opensource.org/licenses/LGPL-3.0 LGPL v3
 */

namespace Sharepoint;

require_once(dirname(__FILE__) . '/Exception.php');

use \cURL\Request;

class Connection {
	
	function __construct($host, $user = null, $password = null, $domain='', $workstation='') {
		
		$userData = explode('@',$user);
		if(isset($userData[1])) {
			$domain = $userData[1];
		}
		
		$this->host = $host;
		$this->port = $port;
		$this->user = $userData[0];
		$this->pass = $password;
		$this->domain = $domain;
		$this->workstation = $workstation;
	}
	
	public function get($uri, array $headers = array()) {
		return $this->request($uri, 'get', null, $headers);
	}
	
	public function post($uri, $data, array $headers = array()) {
		return $this->request($uri, 'post', $data, $headers);
	}
	
	public function put($uri, $data, array $headers = array()) {
		$return = $this->request($uri, 'put', $data, $headers);
		return $return;
	}
	
	public function head($uri, array $headers = array()) {
		return $this->request($uri, 'head', null, $headers);
	}
	
	public function delete($uri, array $headers = array()) {
		return $this->request($uri, 'delete', null, $headers);
	}
	
	public function request($uri, $method = 'get', $data = null, array $headers = []) {
		
		$response = array(
			'status' => 404,
			'statusMessage' => 'Status unknown',
			'headers' => array(),
			'body' => null
		);
		
		$options = [
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_FOLLOWLOCATION => 1,
            CURLOPT_USERAGENT => 'Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)',
            CURLOPT_HTTPAUTH => CURLAUTH_NTLM,            
			CURLOPT_USERPWD => "{$this->user}:{$this->pass}",
			CURLOPT_TIMEOUT => 10,
			CURLOPT_CUSTOMREQUEST => strtoupper($method)
		];

		// append the data
		if(!is_null($data)){
			$options[CURLOPT_POSTFIELDS] = $data;
		}

		// append the headers
		foreach($headers as $key => $header){
			$header = is_array($header)?implode(';', $header):$header;
			$options[CURLOPT_HTTPHEADER][] = "{$key}: {$header}";
		}

		// Create the request URI
		$uri = self::_getPath($uri);
		$uri = str_replace('://', '[:::]', "{$this->host}/{$uri}");
		$uri = preg_replace('/([\w\d\%\-\_\+\/]{0,})(\/\/)(\1)/', '$1', $uri);
		$uri = str_replace('[:::]', '://', $uri);

		// Initialize the request object
		$request = new Request($uri);
	
		// Set other request options
		foreach ($options as $key => $value) {
			$request->getOptions()->set($key, $value);
		}
	
		// Send the request
		$r = $request->send();			

		$response['body'] = $r->getContent();
		
		
		// Format the response
		if(!$r->hasError()){
			$response['status'] = $r->getInfo(CURLINFO_RESPONSE_CODE);
			// $response['statusMessage'] = $r->getInfo(CURLINFO_HTTP_CODE);
		}

		return $response;
	}

	/**
	 * Returns the path component and parameters from the url
	 */
	protected static function _getPath($url) {
		$url = parse_url($url);
		$path = $url['path'];
		if(isset($url['query'])) {
			$path .= '?' . $url['query'];
		}
		$path = str_replace('//','/',$path);
		return $path;
	}
}

/**
 * Exceptions thrown from Connection
 */
class ConnectionException extends Exception { }
