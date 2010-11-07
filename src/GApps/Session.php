<?php

/*
Copyright 2010 Eddie Potocko

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

     http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.
*/

require_once "GApps/OpenID/Discovery.php";
require_once "Auth/OpenID/AX.php";
require_once "Auth/OpenID/Consumer.php";
require_once "Auth/OpenID/MemcachedStore.php";
require_once "Auth/OpenID/PAPE.php";

class GApps_Session {

	/* Settings */

	// Base url to protect
	public static $BASE_URL = '';

	// Google Apps Domain Name
	public static $DOMAIN_NAME = null;
	
	// Memcache settings - must be the same as Auth_memCookie_Memcached_AddrPort
	public static $MEMCACHE_HOST = 'localhost';
	public static $MEMCACHE_PORT = 11211;
	
	// Session time-to-live in seconds
	public static $SESSION_TTL = 86400;
	
	// Session Cookie name - must be the same as Auth_memCookie_CookieName
	public static $COOKIE_NAME = 'GOOG_SESSIONID';

	private $store;
	private $consumer;
	private $memcache;

	/**
	 * Makes sure a user has a valid session.  If the user is not logged in 
	 * they are redirected to the google apps login page.  If they are 
	 * returning from the login page, they are redirected to the original
	 * page they were trying to access.
	 * 
	 * @return GApps_Session
	 */
	public static function authenticate() {
		$session = new GApps_Session();
		if(isset($_GET['auth_action']) && $_GET['auth_action'] == 'login') {
			$_SESSION['LOGIN_URL'] ='http'.'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']; 
			$session->login();
		}
		else if(isset($_GET['openid_mode'])) {
			$session->onReturn();
		}
		else {
			$session->getSession();
		}
		return $session;
	}
	
	public function GApps_Session() {
		session_start();
		$this->memcache = memcache_connect(GApps_Session::$MEMCACHE_HOST, GApps_Session::$MEMCACHE_PORT);
		$this->store = new Auth_OpenID_MemcachedStore($this->memcache);
		$this->consumer = new Auth_OpenID_Consumer($this->store);
		new GApps_OpenID_Discovery($this->consumer);
	}

	public function getSession() {
		if(!isset($_SESSION['OPENID_AUTH']) || $_SESSION['OPENID_AUTH'] !== true) {
			$_SESSION['LOGIN_URL'] = 'http'.'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
			$this->login();
		}
		return $_SESSION;
	}
	
	public function login() {
		$auth = $this->consumer->begin(GApps_Session::$DOMAIN_NAME);
		$attributes = array(
			Auth_OpenID_AX_AttrInfo::make('http://axschema.org/contact/email', 2, 1, 'email'),
			Auth_OpenID_AX_AttrInfo::make('http://axschema.org/namePerson/first', 1, 1, 'firstname'),
			Auth_OpenID_AX_AttrInfo::make('http://axschema.org/namePerson/last', 1, 1, 'lastname')
		);

		$ax = new Auth_OpenID_AX_FetchRequest;
		foreach($attributes as $attr){
			$ax->add($attr);
		}
		$auth->addExtension($ax);

		$url = $auth->redirectURL(GApps_Session::$BASE_URL, GApps_Session::$BASE_URL.'auth.php');
		header('Location: ' . $url);
		exit;
	}
	
	public function onReturn() {
		$response = $this->consumer->complete(GApps_Session::$BASE_URL.'auth.php');

		// set session variable depending on authentication result
		if ($response->status == Auth_OpenID_SUCCESS) {
			$_SESSION['OPENID_AUTH'] = true;
			$ax = new Auth_OpenID_AX_FetchResponse();
			$data = $ax->fromSuccessResponse($response)->data;
			$oid = $response->endpoint->claimed_id;
			$_SESSION['firstName'] = $data['http://axschema.org/namePerson/first'][0];
			$_SESSION['lastName'] = $data['http://axschema.org/namePerson/last'][0];
			$_SESSION['email'] = $data['http://axschema.org/contact/email'][0];

			$this->storeSession();

			header('Location: ' . $_SESSION['LOGIN_URL']);
			exit;
		} else {
			$_SESSION['OPENID_AUTH'] = false;
		}
		return $_SESSION['OPENID_AUTH'];
	}

	private function storeSession() {
		$ttl = GApps_Session::$SESSION_TTL;
		$sessionId = $this->createSessionId();

		list($username, $domain) = explode('@', $_SESSION['email']);
		$value="UserName=".$username."\r\n";
		$value.="Groups=".''."\r\n";
		$value.="RemoteIP=".$_SERVER["REMOTE_ADDR"]."\r\n";
		$value.="Expiration=".$ttl."\r\n";
		$value.="Email=".$_SESSION['email']."\r\n";
		$value.="Name=".$_SESSION['lastName']."\r\n";
		$value.="GivenName=".$_SESSION['firstName']."\r\n";
		$value.="OpenIdIdentity=".sha1($_GET['openid.identity'])."\r\n";

		$this->memcache->set($sessionId, $value, false, $ttl);
		setcookie(GApps_Session::$COOKIE_NAME, $sessionId, time() + $ttl, '/');
	}

	private function createSessionId() {
		return md5(uniqid(rand(), true).$_SERVER["REMOTE_ADDR"].time());
	}
}

