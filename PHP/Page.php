<?php
class Page {
        var $userParams, $templates, $Session, $partsDrawn, $embedCache, $tplVarHook;
	/*
	 ::loadTemplate($name, $file) - Load a HTML template into the template array
	 ::
         ::getField($param) - Return the value of a template variable
 	 ::setField($name, $value) - Set one (or more) template variables. Can be passed both $name & $value, or just $name as an associative array.
         ::addVarHook - Registers a callback to the PHP function $hookFunc() when the tpl variable '$key' is requested.
         ::returnPart($component, $localVars=array(), $template = "main")
  	 ::displayPart($component, $localVars=array(), $template = "main")

	 ::throwError($httpCode, $errorText) - Throw a HTTP error (403, 503 currently implemented)
	 ::redirectHome() - Do a Location: redirect back to $htmlRoot
	*/


	// Create page. SkipAuth = Skip Authentication
        function Page($authRequired = 1, $templateFile = "main.html") {
		global $hostConfig, $Config;
		global $htmlRoot, $webRoot;	// Legacy Global Vars - Copied from hostConfig

		// Create some initial variables
		$this->templates = array();
		$this->userParams = array();
		$this->partsDrawn = array();
		$this->embedCache = array();
		$this->tplVarHook = array();
		$this->userAccess = 0;

		// Set template defaults
		$htmlRoot = $hostConfig['htmlRoot'];		$this->setField("htmlRoot", $htmlRoot);
		$webHost = $hostConfig['webHost'];		$this->setField("webhost", "http://$webHost/");

		// Version Info
		$this->setField("VERCODE", "<I>Blue</I>.web");
		$this->setField("VERINFO", "0.1");
		$this->setField("CURDATE", "" . date("m/d/y") . "");
		$this->setField("CURTIME", "" . date("h:i") . "");

		if (isset($hostConfig['webSlug'])) {	// Optional Slug (Appended to TITLE, and displayed near clock)
			$this->setField("WEBTITLE", "{$hostConfig['webTitle']} ({$hostConfig['webSlug']})");		// Page Title
			$this->setField("WEBSLUG", '<span class="error-message">&nbsp;*' . $hostConfig['webSlug'] . '*&nbsp;</span>');
		}  else
			$this->setField("WEBTITLE", $hostConfig['webTitle']);		// Page Title

  	        if (!$hostConfig['enableDev'] == true) 
			$this->setField("DEVONLY", "style='display: none;'");
		else
			$this->setField("DEVONLY", "alt='DevOnly'");


		$this->loadTemplate("main", $templateFile);
	}

	// ::getField - Return the value of a template variable
        function getField($param) {
		return (isset($this->userParams[$param]) ? $this->userParams[$param] : "");

        }

	// ::setField($name, $value) - Set one (or more) template variables. You can either pass both ($name, $value) to set a single item, or multiple
	//	vars can be set by passing an assoc array as $name. $value is optional when passing an array. Example: $Page->setField(array( "k1"=>"v1", "k2"=>"v2") );
        function setField($name, $value="") {
		if (!is_array($name)) 				// Set/Change a single tpl variable
	                $this->userParams[$name] = $value;
		else
			foreach($name as $param => $value)	// Set/Change multiple tpl variables...
		                $this->userParams[$param] = $value;
	}

	// ::addVarHook - Registers a callback to $hookFunc() when the tpl variable '$key' is requested. An example is included below...
        function addVarHook($key, $hookFunc) {		//			** function exampleHook($key, $oVal) {$val = $oVal; return $val;}
                $this->tplVarHook[$key] = $hookFunc;	//			** $this->addVarHook("PANELWIDTH", "Page::exampleHook");

        }

	// Load a HTML template into the template array
	function loadTemplate($templateName, $templateFile) {
		global $pageRoot;

		$this->templates[$templateName] = new Template("$pageRoot/HTML/" . $templateFile, $this);
	}

	// Redirect functions
	function redirectHome() {
		global $Framework, $hostConfig;
		header("Location: {$hostConfig['htmlRoot']}");
		exit(0);
	}

	// Throw a HTTP Error (404, 503 currently implemented)
        function throwError($code, $errorText) {
                $httpErrors = array(0 => "HTTP/1.0 418 I'm a Teapot", 404 => "HTTP/1.0 404 Not Found", 503 => "HTTP/1.0 503 Internal Server Error");
                $responseString = (isset($httpErrors[$code]) ? $httpErrors[$code] : $httpErrors[0]);
		header($responseString);
                echo "<BR/><CENTER><H1>$responseString</H1></CENTER><HR/>$errorText";
                exit(0);
        } 

        function parseVars($part, $localVars = array()) {
		if (substr($this->userParams['htmlRoot'], -1) == "/") {
			$part = str_replace('[[htmlRoot]]/', '[[htmlRoot]]', $part);
		}

                foreach ($localVars as $key => $value) {
			$value = trim($value, " \t\n\r\0\x0B\"");
			$key = trim($key);
			if (isset($this->tplVarHook[$key])) 	// Run tplVarFudge() hook, if present.
				$value = call_user_func($this->tplVarHook[$key], $key, trim($value));

                        $part = str_replace('[['.$key.']]', trim($value), $part);
		}

                foreach ($this->userParams as $key => $value)
                        $part = str_replace('[['.$key.']]', trim($value), $part);

                // Clean up unmatched vars, and use any default value specified (eg, as [[VARIABLE defaultValue]])
         	preg_match_all( "/\[\[(\w+[^\s])(.*?[^\]])?\]\]/", $part, $matches, PREG_SET_ORDER);
                foreach($matches as $key => $set) {
			$varKey = trim($set[1]);
			$varReplace = !isset($set[2]) ? "" : $set[2];

			if (isset($localVars[$varKey])) 
				$varReplace = $localVars[$varKey];
			if (isset($this->tplVarHook[$varKey])) 			if (isset($this->tplVarHook[$key])) 	// Run tplVarFudge() hook, if present.
				$varReplace = call_user_func($this->tplVarHook[$varKey], trim($varKey), trim($varReplace));

                        $part = str_replace(trim($set[0]), trim($varReplace), $part);
                }

		// Last ditch cleanup of unmatched vars..
                return eregi_replace("\[\[[a-zA-Z_]+\]\]", "", $part);
        }


	function parseFile($fileName, $localVars = array()) {
       		$part = @file_get_contents($fileName);
		return $this->parseVars($part, $localVars);
	}

	// Return parsed part as string
        function returnPart($component, $localVars=array(), $template = "main") {
	  // Wrap in a try {} statement, as we may be called from an error handler
	  // and don't want to create a race condition / infinite loop ;)
	  try {
		$part = "";
		if (!is_array($component)) $component = array($component);

		// Fetch all the components
       		foreach($component as $tComponent) 
                        $part .= $this->fetchEmbed($template, $tComponent);

		// Parse variables
                $part = $this->parseVars($part, $localVars);

                // Handle any embed/nested templates (embeds may be specified in SQL 'templates' table, or within the std. flat-file templates)
		// The tpl syntax for these is {{template::component}}. Template variables can be supplied in a 'query-string' format, like so:
		//	{{template::component someVar=123&otherVar=456}} (spaces _ARE_ permitted both in values, and surrounding the ampersand)
	        $part = preg_replace_callback('|\{\{(.*)\:\:(\w+[^\s])[\s]?(.*)?\}\}|', function ($matches) use ($localVars) {
			global $Framework;
			if ($matches[3]) {
				parse_str($matches[3], $embedVars);
			        $localVars = array_unique(array_merge($localVars, $embedVars));
			}
		        return $Framework->Page->parseVars($Framework->Page->fetchEmbed($matches[1], $matches[2]), $localVars);
                }, $part);

		// Mark this component as displayed		
		$this->partsDrawn[$component] = 1;

                return trim($part) . "\n";
	  } catch (Exception $e) {
		echo "<H1>Except.1</h1>\n";
		return;
	  }
        }

	// Display Part (lazy echo)
        function displayPart($component, $localVars=array(), $template = "main") {
	  // Wrap in a try {} statement, as we may be called from an error handler
	  // and don't want to create a race condition / infinite loop ;)
	  try {
		$part = "";
		if (!$this->templates[$template]) {
			echo "Unknown template: $template | $component<BR>";
			return;
		}
                echo $this->returnPart($component, $localVars, $template);
	  } catch (Exception $e) {
			echo "<H1>Exception</h1>";
		return;
	  }
        }

        // Fetch embedded template from DB, if present - otherwise try to fall back on a flat-file template
        function fetchEmbed($template, $component) {
			// We've already looked this pair up once, if embedCache is populated..
			if (!isset($this->embedCache[$template][$component])) {
				// Check SQL for embedded template resource
				//$res = db_fetch_assoc("SELECT template FROM templates WHERE section='$template' AND name='$component'");
				$res = array();
				// Cache results in embedCache (either SQL-returned template, or 'false' if not found in-DB)
				$this->embedCache[$template][$component] = (isset($res['template'])) ? stripslashes($res['template']) : -2;
			} 

                        $cacheContents = $this->embedCache[$template][$component];

			// Return $cacheContents, if valid - otherwise, fall back to $template->getPart()..
			$results = ($cacheContents && ($cacheContents == -2)) ? $this->templates[$template]->getPart($component) : $cacheContents;

			return $results;
        }


	function errorMessage($message) {
		return '<DIV CLASS="error-message" STYLE="width: 50%;  margin: 0 auto;">' . $message . '</DIV>';
	}

	function panelSize($gridSize) {
		$gridSizes = array("100%" => "col-md-12", "91%" =>  "col-md-11", "83%" =>  "col-md-10", "75%" => "col-md-9", "66%" => "col-md-5", "50%" => "col-md-6", "42%" => "col-md-5", "33%" => "col-md-4", "25%" => "col-md-3", "10%" => "col-md-1");
		if (!isset($gridSizes[$gridSize])) {
			echo "<script>console.log('panelOpen($title): Invalid Grid Size - $gridSize\n');</script>\n";
			$gridSize = "10%";
		}
		return $gridSizes[$gridSize];
	}

	// panelOpen: Open a bootstrap panel (optional custom class)
	function panelOpen($title, $gridSize = "50%", $panelClass = "primary") {
		$gridClass = $this->panelSize($gridSize);
		$this->displayPart("panelOpen", array("TITLE" => $title, "PANELCLASS" => $gridClass));		
	}

	function panelClose() {
		$this->displayPart("panelClose");
	}


	// Fatal Error Handler
	// TODO: Clean up connections to PostGres and OX Session Daemon
	function internalError($errStr, $partsToPrint) {
		$partsToDraw = array();

		// Build Error - use Templated Dialog, if available, else fallback.
		$errMsg = $this->returnPart("alert_error", array("ERROR" => $errStr, "CLOSE" => "hidden"));
		if (strlen($errMsg) < 5) $errMsg = "<div class='alert alert-error'>&& $errStr</div>\n";

		// If we haven't at least printed the two main headers yet, attempt to do so (and push footers into ToDraw array)
		if (!isset($this->partsDrawn['header']))  	   echo $this->returnPart("header"); 		 $partsToDraw[] = "footer";
		if (!isset($this->partsDrawn['content_header']))   echo $this->returnPart("content_header"); 	 array_unshift($partsToDraw, "content_footer");
		$partsToDraw = array_unique(array_merge($partsToDraw, $partsToPrint));

		// Print Error Message
		echo $errMsg;

		// Print any trailing template elements, either autodetected above or passed by code
		foreach($partsToDraw as $part) $this->displayPart($part);
		exit();
	}
}

class Template {
	var $templates;

        function Template($template) {
		$this->templates = array();

		$guts = file($template);

		if ($guts == false) 
			die("Couldn't open template: $template");

		$curTemplate = "UNDEFINED";
		foreach($guts as $line) {
			if (ereg ("^<!-- BEGIN TEMPLATE \'(.*)?\'", $line, $regs)) {
				$curTemplate = $regs[1];
				$this->templates[$curTemplate] = "";
				continue;
			}
			if (ereg ("^<!-- END TEMPLATE \'(.*)?\'", $line, $regs)) 
				continue;

			if ($curTemplate == "UNDEFINED")
				continue;
			$this->templates[$curTemplate] .= $line;
		}
	}

	function hasPart($component) {
		return isset($this->templates[$component]);
	}

	function getPart($component) {
		return $this->templates[$component];
	}
}

