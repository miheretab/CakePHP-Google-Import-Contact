Google Oauth Import Contact Vendor
========================

This is a simple vendor file that interfaces a CakePHP app with Google OAuth Contact API version 3.0<br/>
Google Contact API version 3.0 can be seen https://developers.google.com/google-apps/contacts/v3/

Compatibility:
--------------

Tested with CakePHP 2.x

Installation:
-------------

**Using git:**

You will need the Vendor. Using git, 
something like this:

	git clone git@github.com:miheretab/CakePHP-Google-Import-Contact.git APP/Vendor/GoogleOauth  

Configuration:
--------------

All configuration is in APP/Config/bootstrap.php.

**Required:** Set your Google Client Id and Secret Id <br/>
By creating project here https://console.developers.google.com <br/>
And enable Conact API and create Client Id, you can look at https://developers.google.com/console/help/ for any help:

```php
<?php
Configure::write('Google.clientId', 'GOOGLE CLIENT ID');
Configure::write('Google.clientSecret', 'GOOGLE CLIENT SECRET');
```

Usage
-----

```php
App::uses('GoogleOauth', 'Vendor');
```

-------------

Example:

Create these function in your controller

```php
public function index() { //you can name the function as you like

	$clientId = Configure::read('Google.clientId');
	$redirectUri = Router::url(array('action' => 'gmail'), true); 
	$this->set('clientId', $clientId);
	$this->set('redirectUri', $redirectUri);
}
```

make button in index.ctp (view for the above funciton)

```php
<?php echo $this->Html->link(__('Import Gmail Contacts'), 'https://accounts.google.com/o/oauth2/auth?client_id=' . $clientId . '&redirect_uri=' . $redirectUri . '&scope=https://www.google.com/m8/feeds/&response_type=code', array('class' => 'btn btn-info')); ?>
```

you can create your oen view for the funcit on below as you need

```php
public function gmail() { //you can name the function as you like
	if (isset($this->request->query['code'])) {
		$google = new GoogleOauth(); 
		
		$authCode = $this->request->query['code'];
		$clientId = Configure::read('Google.clientId');
		$clientSecret = Configure::read('Google.clientSecret');
		$redirectUri = Router::url(array('action' => 'gmail'), true);
		
		$result = $google->getContacts($authCode, $clientId, $clientSecret, $redirectUri);
		if(isset($result['error'])) {
			$this->Session->setFlash($result['error']);
		} else {
		
			$contacts = $result['contacts']; //here is list of contacts imported
			$this->set('contacts', $contacts); 
			$this->set('senderEmail', $result['ownerEmail']); //here is owner email (selected when authenticated)
			
		}
	}
}
```
