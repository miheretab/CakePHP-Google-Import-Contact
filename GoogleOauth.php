<?php
/**
 * Class GoogleOauth
 *
 * Import Contacts
 *
 * @author Miheretab Alemu
 */
class GoogleOauth {
 
	private function curl_file_get_contents($url) {
		$curl = curl_init();
		$userAgent = 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; .NET CLR 1.1.4322)';

		curl_setopt($curl,CURLOPT_URL,$url);	//The URL to fetch. This can also be set when initializing a session with curl_init().
		curl_setopt($curl,CURLOPT_RETURNTRANSFER,TRUE);	//TRUE to return the transfer as a string of the return value of curl_exec() instead of outputting it out directly.
		curl_setopt($curl,CURLOPT_CONNECTTIMEOUT,5);	//The number of seconds to wait while trying to connect.	

		curl_setopt($curl, CURLOPT_USERAGENT, $userAgent);	//The contents of the "User-Agent: " header to be used in a HTTP request.
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, TRUE);	//To follow any "Location: " header that the server sends as part of the HTTP header.
		curl_setopt($curl, CURLOPT_AUTOREFERER, TRUE);	//To automatically set the Referer: field in requests where it follows a Location: redirect.
		curl_setopt($curl, CURLOPT_TIMEOUT, 10);	//The maximum number of seconds to allow cURL functions to execute.
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);	//To stop cURL from verifying the peer's certificate.

		$contents = curl_exec($curl);
		curl_close($curl);
		return $contents;
	}

	/*
	* secured with new google oauth https://developers.google.com/google-apps/contacts/v3/
	* client id and secret id needed here
	*/
	public function getContacts($auth_code, $client_id, $client_secret, $redirect_uri, $max_results = 5000) {				
		$fields=array(
			'code'=>  urlencode($auth_code),
			'client_id'=>  urlencode($client_id),
			'client_secret'=>  urlencode($client_secret),
			'redirect_uri'=>  urlencode($redirect_uri),
			'grant_type'=>  urlencode('authorization_code')
		);
		$post = '';
		foreach($fields as $key=>$value) { $post .= $key.'='.$value.'&'; }
		$post = rtrim($post,'&');
		 
		$curl = curl_init();
		curl_setopt($curl,CURLOPT_URL,'https://accounts.google.com/o/oauth2/token');
		curl_setopt($curl,CURLOPT_POST,5);
		curl_setopt($curl,CURLOPT_POSTFIELDS,$post);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER,TRUE);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER,FALSE);
		$result = curl_exec($curl);
		curl_close($curl);
		 
		$response = json_decode($result);
		$accesstoken = $response->access_token;
		 
		$url = 'https://www.google.com/m8/feeds/contacts/default/full?max-results='.$max_results.'&oauth_token='.$accesstoken;
		$xmlresponse = $this->curl_file_get_contents($url);
		if((strlen(stristr($xmlresponse,'Authorization required'))>0) && (strlen(stristr($xmlresponse,'Error '))>0)) //At times you get Authorization error from Google.
		{
			$error['error'] = __("OOPS !! Something went wrong. Please try reloading the page.");
			return $error['error'];
		}

		
		$doc = new DOMDocument;
		$doc->recover = true;
		$doc->loadXML($xmlresponse);

		$xpath = new DOMXPath($doc);
		$xpath->registerNamespace('gd', 'http://schemas.google.com/g/2005');
		$emails = $xpath->query('//gd:email');
		
		$xml = new SimpleXMLElement($xmlresponse);
		
		$result = array();
		$result['ownerEmail'] = (string)$xml->id;
		
		$contacts = array();
		$i = 0;
		foreach ($emails as $email) {
			$name = (string)$email->parentNode->getElementsByTagName('title')->item(0)->textContent;
			$email = (string)$email->getAttribute('address');
			$contacts[$i]['name'] = $name != '' ? $name : $email;
			$contacts[$i++]['email'] = $email;
		}
		
		$result['contacts'] = $contacts;

		return $result;
	}
	
	/*
	* less secured with new google oauth
	* for this user have to enable https://www.google.com/settings/security/lesssecureapps
	* no client id and secret id needed here, only username and password
	*/
	public function getGmailContacts($username, $password, $max_results = 5000) {
		$email = $username . "@gmail.com";

		// ref: http://code.google.com/apis/accounts/docs/AuthForInstalledApps.html

		// step 1: login
		$login_url = "https://www.google.com/accounts/ClientLogin";
		$fields = array(
			'Email' => $email,
			'Passwd' => $password,
			'service' => 'cp', // <== contact list service code
			'source' => 'test-google-contact-grabber',
			'accountType' => 'GOOGLE',
		);
		
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL,$login_url);
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS,$fields);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); 
		$result = curl_exec($curl);

		$returns = array();

		foreach (explode("\n",$result) as $line)
		{
			$line = trim($line);
			if (!$line) continue;
			list($k,$v) = explode("=",$line,2);

			$returns[$k] = $v;
		}

		curl_close($curl);	
		
		// step 2: grab the contact list
		$url = "https://www.google.com/m8/feeds/contacts/$email/full?&max-results=".$max_results;

		$header = array(
			'Authorization: GoogleLogin auth=' . $returns['Auth'],
		);

		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); 

		$xmlresponse = curl_exec($curl);
		curl_close($curl);
		
		$doc = new DOMDocument;
		$doc->recover = true;
		$doc->loadXML($xmlresponse);

		$xpath = new DOMXPath($doc);
		$xpath->registerNamespace('gd', 'http://schemas.google.com/g/2005');
		$emails = $xpath->query('//gd:email');		
		
		$xml = new SimpleXMLElement($xmlresponse);

		$result = array();
		$result['ownerEmail'] = (string)$xml->id;
		
		$contacts = array();
		$i = 0;		
		foreach ($emails as $email) {
			$name = (string)$email->parentNode->getElementsByTagName('title')->item(0)->textContent;
			$email = (string)$email->getAttribute('address');
			$contacts[$i]['name'] = $name != '' ? $name : $email;
			$contacts[$i++]['email'] = $email;
		}
		
		$result['contacts'] = $contacts;		
		
		return $result;
	}

}
?>