<?php
	/* CamEngine.php		- Core DVR Frontend Interface.
					  Loads all dependencies and sets up the tool environment.	*/

	require_once("PHP/Framework.php");	// Core framework
	require_once("PHP/Utils.php");		// Helper Tools

	/* Fix Input Variables */
	if (!isset($_REQUEST['Camera'])) $_REQUEST['Camera'] = "";
	if (!isset($_REQUEST['date']) || ($_REQUEST['date'] == ""))   $_REQUEST['date'] = date("Y-m-d");
	
	/* Init the Page, and register core template variables */
	$Page = new Page();
	$Page->setField(array("CAMERA" => $_REQUEST['Camera'], "DATE_CUR" => $_REQUEST['date']));					        /* Current Request */
	$Page->setField(array("DATE_NEXT" => dateOffset($_REQUEST['date'], "+1 day"), "DATE_PREV" => dateOffset($_REQUEST['date'], "-1 day"))); /* Date Next/Prev */

	///////////////////////////////////////////////////////
	/* DVR_Engine Class				     */
	///////////////////////////////////////////////////////
	class DVR_Engine {
		private static $baseFolder, $snapFolder;
		var $camList;

	        function __construct($baseFolder, $snapFolder) {
			if (!file_exists($baseFolder) || !file_exists($snapFolder)) 
				Framework::debug(DEBUG_FATAL, "Failed to create DVR_Engine class - Specified folders do not exist!\nBase Folder: $baseFolder\nSnap Folder: $snapFolder\n");

			$this->baseFolder = $baseFolder;
			$this->snapFolder = $snapFolder;

			if (isset($_REQUEST['date']) && ($_REQUEST['date'] != ""))
				$this->searchDate = $_REQUEST['date'];

			$this->camList = $this->loadCameraList($_REQUEST['date']);
		}

		/* ::loadCameraList()	- Find snapshot folders dated $this->searchDate, and return a list of cameras (derived from base folder names) */
		function loadCameraList() {		 
	                global $hostConfig, $Framework;

			$camIndex = 0;
                	foreach(glob($this->snapFolder . "/*") as $CameraPath) {
                        	$pathBits = explode("/", $CameraPath);
                       		$camName = $pathBits[sizeof($pathBits)-1];
                        	$dirName = glob("$CameraPath/*/*" . $this->searchDate . "*/01/pic/");
                        	if ($dirName) {
					$camFiles = scandir($dirName[0], SCANDIR_SORT_DESCENDING);
		                        $camInfo = array("index" => $camIndex, "folder" => $dirName[0], "name" => $camName,
							 "snapLast" => date ("F d Y H:i:s.", filemtime("{$dirName[0]}/{$files[0]}")),
							 "snapCount" =>  str_pad(count($camFiles)-2, 5, " ", STR_PAD_BOTH));	// -2 to 'uncount' ".." and "."

					$camList[$camName] = $camInfo;
					$camIndex++;
				}
	                }

	                krsort($camList);
	                return $camList;
        	}

	        function sendCameraImage($camName, $ImageName, $mode = "full") {
        	        global $hostConfig;

	                // Verify Camera definition was loaded..
                	if (!isset($this->camList[$camName])) 
        	                return Framework::debug(DEBUG_FATAL, "<CENTER><H1>Requested Camera [$camName] not found</h1></center>");

	                // Verify Image file exists
                	$ImageFile = $this->camList[$camName]['folder'] . "/$ImageName";
	                if (!file_exists($ImageFile))
        	                return Framework::debug(DEBUG_FATAL, "<CENTER><H1>Requested Image not found</h1></center>");

	                // Return correct varient of image..
	                switch($mode) {
	                        case 'thumb':
	                                return makeThumbnail($ImageFile, "{$hostConfig['Folder_Base']}/thumb", $hostConfig['ThumbSize']);
	                                break;
	                        case 'full':
	                                return sendFile_Rangable($this->camList[$camName]['folder'] . "/$ImageName", "image/jpeg");
	                                break;	
	                        default:
	                                Framework::debug(DEBUG_FATAL, "Unknown 'Mode' parameter");
        	        }
        	}
	}



