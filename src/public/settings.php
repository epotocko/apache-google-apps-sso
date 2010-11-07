<?php
	// Base url to protect
	GApps_Session::$BASE_URL = 'http://mydomain.com/sso/';
	
	// Google Apps Domain Name
	GApps_Session::$DOMAIN_NAME = 'mydomain.com';

	// Memcache settings - must be the same as Auth_memCookie_Memcached_AddrPort
	//GApps_Session::$MEMCACHE_HOST = 'localhost';
	//GApps_Session::$MEMCACHE_PORT = 11211;
	
	// Session time-to-live
	//GApps_Session::$SESSION_TTL = 86400;
	
	// Session Cookie name - must be the same as Auth_memCookie_CookieName
	//GApps_Session::$COOKIE_NAME = 'GOOG_SESSIONID';
	