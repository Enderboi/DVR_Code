<?php
/*	AuthSession_Dummy	-	Authentication Handler, implements a DUMMY AuthSession() class.
					'DUMMY' always returns successful authentication. 

					You can base a new implementation off this skeleton - simply replace the
					'$authResult = true' assignation in the login() function with a real test.
*/
	
class AuthSession_Dummy {
	var $access, $data, $db, $timeout;

	/* Constants */
	const AUTH_NONE = 0;
	const AUTH_OK = 1;
	const AUTH_EXPIRED = 2;
	const AUTH_FAILED = 3;

	/* AuthSession([$timeout]):	$session - Session timeout (seconds) [OPTIONAL] */
	function __construct($timeout = 1200) {
		// Create the session (FIXME: Why SAPI cli check?)
	        if (session_id() === '' && PHP_SAPI !== 'cli')
        	    session_start();
		$this->timeout = $timeout; 	// Set session timeout
	}

	// Verify password against database
	public function login($login, $password, $remote_ip, $http_host) {
		global $Config;

		/* TODO: Implement Authentication HERE! */
		$authResult = true;

		// If authenticate successful (or we're in debug noAuth mode), return session data.
		if ($authResult || $Config['__Auth_Never_Fails']) 
                        return array("username" => $login, "password" => $password);

		// Otherwise, return fail
	        return false;
	}

	// Check we are logged in
	public function isLoggedIn() {
			if (!isset($_SESSION["status"]))
				return false;

                        if (isset($_SESSION["status"]) && $_SESSION["status"] == 1) {
				$this->loginOK = $this->login($_SESSION["login"], $_SESSION["password"], 1);

				return $this->loginOK;
                        } 
			return false;
	}

	// Create new session
	public function newSession($login, $password) {
		$_SESSION["login"] = $login;
		$_SESSION["password"] = $password;
		$_SESSION["status"] = 1;
	}

	// End session, unset and clear all session state, regenerate new SID
	public function endSession() {
	        unset($_SESSION["login"]);
       		unset($_SESSION["password"]);
        	unset($_SESSION["status"]);

		$this->createID();
	        session_unset();
		if (session_id() != "")
        		session_destroy();
	        return true;
	}

	// Generate new session ID for security
	function createID() {
		if (!headers_sent()) 
			session_regenerate_id();
	}

        function getUserData($col) {
                return $this->data[$col];
        }

        function canAccess($area) {
                global  $adminLevels;
                if (!isset($adminLevels[$area]))
                        return false;

                if ($this->access & $adminLevels[$area])
                        return true;

                return false;
        }
}

class_alias('AuthSession_Dummy', 'AuthSession');
