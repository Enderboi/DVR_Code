<?php
	require_once("Settings.php");		// Site Configuration
	require_once("Auth_Dummy.php");		// Authentication Daemon (DUMMY implemtnation)
	require_once("Page.php");		// Page Renderer Class

	error_reporting(E_COMPILE_ERROR|E_RECOVERABLE_ERROR|E_ERROR|E_CORE_ERROR);

	$Framework = new Framework();
	if (!(Framework::getInstance() === $Framework))
		die("[FRAMEWORK] Instancing Error - getInstance() returns a Framework instance other than the one I just created!\n" . "getInstance() = " . spl_object_hash(Framework::getInstance()) . "\n$Framework = " . spl_object_hash($Framework)) . "\n\n";;

	//
	class Framework {
		var $debugLevels, $debugTarget, $hostConfig;
		var $views;

                private static $instance = NULL;

		 function Framework() {
			global $Page, $argData, $viewClass, $viewConfig, $db, $Auth, $hostConfig;

			self::$instance = $this;			// Pseudo-singleton instancing
			$this->debugInit();				// Init Debug Logging

		        // Site Configuration - Autodetect Settings based on Hostname
			$hasConfig = ( !$this->detectHost(trim(`hostname -s`)) ) ? $this->detectHost(trim(`hostname`)) : -3;
		        if (!$hasConfig)
	                        $this->debug(DEBUG_FATAL, "Failed to detect server name [" . trim(`hostname -s`) . "]\nOK1=$ok1, OK2=$ok2\nROK=$rok\n");

			// Instance Authentication Daemon, if one is configured or loaded
			if (!class_exists("AuthSession"))
				$this->debug(DEBUG_FATAL, "No AuthSession class seems to be loaded");
			$Auth = new AuthSession();

			// Load View Handlers
		        $this->views = $this->findHandlers("Views/");

       			// Parse URL for argument data
        		$argData = (isset($_SERVER['PATH_INFO']) && (strlen($_SERVER['PATH_INFO']) > 2)) ? explode("/", $_SERVER['PATH_INFO']) : array();
        		array_shift($argData);

			// If a view was specified, load the handler
       			 if (isset($argData[0])) {
                		$viewConfig = $this->initHandler($argData[0]);
                		$viewClass = new $viewConfig['class'](true);
                		array_shift($argData);
        		} else 
				$viewClass = $viewConfig = "";

			return;
		}

                public static function getInstance() {
			$instance = self::$instance;

                        return $instance;
                }


	/////////////////
	// findHandlers($viewDir): Builds a index of available View Handlers, based on directory contents
	function findHandlers($viewDir) {
		$views = array();
		// Index available View Handlers
	        $viewDir = opendir($viewDir);
	        while($viewFH = readdir($viewDir)) {
	                if (preg_match('/v_(.*).php$/', $viewFH, $matches)) {
	                        $views[strtolower($matches[1])] = array("name" => $matches[1], 
	                                                                 "file" => "Views/$viewFH", 
	                                                                 "class" => "v_{$matches[1]}View");
	                }
	        }

		return $views;
	}

        // initHandler($viewName): Loads a View Handler and returns config ready for instancing
        function initHandler($viewName) {
                // Check for View Config
                if (!isset($this->views[$viewName]))      
                        $this->debug(DEBUG_FATAL, "Unknown View Type for: {$viewName}");
                $thisView = $this->views[$viewName];

                // Test for Handler Source existance, and load PHP class
                if (!file_exists($thisView['file']))   
                        $this->debug(DEBUG_FATAL, "Unknown View File for: {$viewName}");
                require_once($thisView['file']);

                // Ensure the PHP class we intend to instance actually exists!
                if (!class_exists($thisView['class'])) 
                        $this->debug(DEBUG_FATAL, "Unknown View Class for: {$thisView['class']}");

                return $thisView;
        }

	// detectHost() - Check if settings are defined for host '$whatServer', and if so populate $hostConfig
        function detectHost($whatServer) {
                global $hostConfig,  $Settings;
                global $pageRoot, $fileStore, $dbName, $mailDomain, $mailFromDomain, $mailServer, $webHost;

                // Defaults
                $mailDomain = $mailFromDomain = $mailServer = "localhost";

                if (!isset($Settings['Host'][$whatServer]))
                        return false;

                $hostConfig = array_replace($Settings['Host']['Default'], $Settings['Host'][$whatServer]);

                // Configure Legacy Globals
                $pageRoot = $hostConfig['pageRoot'];
                $fileStore = $hostConfig['fileStore'];
                $webHost = $hostConfig['webHost'];

                // Compute PHP Max Filesize Limit, and store in hostConfig
                $hostConfig['maxUpload'] = min((int)ini_get('upload_max_filesize'), (int)ini_get('post_max_size'),(int)ini_get('memory_limit'));

                return true;
        }

	/////////////////
	// qDebugInit(): Setup Debug Levels and Debug Logger
	function debugInit() {
		// Debug Levels (bitmask)
		$this->debugLevels = array("DEBUG_FATAL" => 1<<1, "DEBUG_WARN" => 1<<2, "DEBUG_SQL" => 1<<3, "DEBUG_CORE" => 1<<4);
		foreach($this->debugLevels as $debugLevel => $debugID) define($debugLevel, $debugID);

		// Debug Targets ($debugTarget > 0 = File Handle)
		define('DEBUG_STDOUT', -1); define('DEBUG_SYSLOG', -2);
		$this->debugTarget = DEBUG_STDOUT;
	}

	// debugSetTarget($target): Change Debug Log Destination (can be DEBUG_STDIO, DEBUG_SYSLOG or a open File Handle)
	function debugSetTarget($target) {
		if ($target == "syslog" || $target == DEBUG_SYSLOG)
			$this->debugTarget = DEBUG_SYSLOG;
		else if ($target && fstat($target))
			$this->debugTarget = $target;
		else
			$this->debugTarget = DEBUG_STDOUT;
	}

	// debug($level, $messages): Print Debug info to selected $debugTarget. Can use either string or array for $message
	function debug($level, $messages) {
		global $Config;

		$myFramework = Framework::getInstance();

		$debugText = "<DIV CLASS='sortTable'><CENTER><B><U>Debug</U></B></CENTER><DIV CLASS='dataTable' WIDTH=50%><PRE><U>Messages:</U></PRE>";
		if (!is_array($messages)) $messages = array($messages);
		foreach($messages as $message) {
			if (is_array($message))
				$debugText .= "<UL><PRE>" . implode("\n<LI>", $message) . "</PRE></UL>"; 
			else 
				$debugText .= "<B>$message</B>\n";
		}

		$e = new Exception();
		$eStr = str_replace("{$_SERVER['DOCUMENT_ROOT']}/", '',  $e->getTraceAsString());
		$eStr = str_replace("#", "<LI>", $eStr);
		$debugText .= "<PRE><U>Backtrace:</U>\n<UL>$eStr</UL></PRE>";
		$debugText .= "</DIV>";

		switch($myFramework->debugTarget) {
			case DEBUG_STDOUT:
				echo $debugText; 
				break;
			case DEBUG_SYSLOG:
				syslog(LOG_NOTICE, str_replace("\n", " || ", strip_tags($debugText)));
				break;
			default:
				fputs($myFramework->debugTarget, $debugText);	// Write to file handle
		}

		if ($level & $myFramework->debugLevels['DEBUG_FATAL']) 
			die("Debug Fatal from " .__FILE__."::".__LINE__."\n");
	}
}
