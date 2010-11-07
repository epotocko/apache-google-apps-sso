= apache-google-apps-sso

Single sign on for Apache with Google Apps accounts using OAuth.  


== How it works

Auth MemCookie checks HTTP requests coming into Apache for a cookie.  If the cookie is present, 
the value of the cookie (sesson id) is used to lookup a session in memcache.  If the session 
is found, the HTTP request is handled by Apache.  If the session cookie is missing from the 
request or missing from memcache, the user is redirected to a Google Apps login page. If the user
returns from the google apps login page with a valid token, a session id is added to the cookie and
the session is stored in memcache so it can be read by Auth MemCookie.

Auth MemCookie adds http headers to requests from authenticated users that can be used to obtain
information about the current user.
The following headers are available (using $_SERVER in PHP):
MCAC_UserName => The users username
MCAC_Email => The users google apps email address
MCAC_Name => The users last name
MCAC_GivenName => The users first name


== Requirements

Apache 2.x
PHP 5.x
memcached (http://code.google.com/p/memcached/)
Auth MemCookie 1.0.2 (http://authmemcookie.sourceforge.net/)
php-openid (https://github.com/openid/php-openid)


== Setup

Get php-openid and copy the Auth directory to a directory that is in your php include path
Copy the src/GApps directory to a directory that is in your php include path
Copy the contents of the src/public directory to a directory that is being served by apache (auth in this example)
Edit src/public/settings.php
Update your apache config files for Auth MemCookie (http://authmemcookie.sourceforge.net/)


== Example Configuration: Protecting a path

Protecting the following urls: http://mydomain.com/protected/*
Google Apps Domain: mydomain.com
Assuming the files that should have restricted access are in: /var/www/html/protected
mkdir /var/www/html/auth
cp -rf php-openid/Auth /var/www/html/auth
cp -rf apache-google-apps-sso/src/GApps /var/www/html/auth
cp -rf apache-google-apps-sso/src/public/* /var/www/html/auth/.

Edit /var/www/html/auth/settings.php:
	GApps_Session::$BASE_URL = 'http://mydomain.com/auth/';
	GApps_Session::$DOMAIN_NAME = 'mydomain.com';

Edit /etc/httpd/conf.d/auth.conf (assuming DocumentRoot "/var/www/html"):
	LoadModule mod_auth_memcookie_module modules/mod_auth_memcookie.so
	<IfModule mod_auth_memcookie.c>
	  <Location /protected/>
		Auth_memCookie_CookieName GOOG_SESSIONID
		Auth_memCookie_Memcached_AddrPort 127.0.0.1:11211
		ErrorDocument 401 "/auth/login.php"
		Auth_memCookie_Authoritative on
		AuthType Cookie
		AuthName "GApps Login"
	  </Location>
	</IfModule>

	<Location "/protected/">
	  require valid-user
	</Location>

	
== Example Configuration: Protecting everything

Protecting the following urls: http://mydomain.com/*
Follow the steps in the configuration above except the apache config instructions.

Edit /etc/httpd/conf.d/auth.conf (assuming DocumentRoot "/var/www/html"):
	LoadModule mod_auth_memcookie_module modules/mod_auth_memcookie.so
	<IfModule mod_auth_memcookie.c>
	  <Location />
		Auth_memCookie_CookieName GOOG_SESSIONID
		Auth_memCookie_Memcached_AddrPort 127.0.0.1:11211
		ErrorDocument 401 "/auth/login.php"
		Auth_memCookie_Authoritative on
		AuthType Cookie
		AuthName "GApps Login"
	  </Location>
	</IfModule>

    <LocationMatch "^/(?!auth)">
        Require valid-user
    </LocationMatch>

== Additional Information
http://authmemcookie.sourceforge.net/
http://code.google.com/p/google-apps-sso-sample/
http://code.google.com/googleapps/domain/sso/openid_reference_implementation.html