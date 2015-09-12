<?php   /* index.php                                                                                            [       DVR-Web Framework       ]
                - Main Programme Loop / Logic	                                                                [  (c) ender@enderboi.com 2015  ]       */


	require_once("CamEngine.php");

	$DVR_Engine = new DVR_Engine($hostConfig['Folder_Base'], $hostConfig['Folder_Base'] . "/" . $hostConfig['Folder_Snap']);

	// If a filename has been specified, handle it as a request for a Thumbnail or Full Frame, and return to avoid printing any HTML
	if (isset($_REQUEST['file'])) 
		return $DVR_Engine->sendCameraImage($_REQUEST['Camera'], basename($_REQUEST['file']), $_REQUEST['mode']);

	// Build HTML for infoPane (eg, Camera List) widget
	$infoPane = "";
	foreach($DVR_Engine->camList as $camName => $camInfo)
		$infoPane .= $Page->returnPart("camera_InfoLine", array("CAM_NAME" => $camName, "CAM_INDEX" => $camInfo['index'], "htmlRoot" => $hostConfig['htmlRoot'],
					  	"CAM_SNAPLAST" => $camInfo['snapLast'],  "CAM_SNAPCOUNT" => $camInfo['snapCount']));

	// Display Page Header
	$Page->displayPart("header", array( "Header_Left" => $infoPane) );

	// If a specific camera is specified, build a custom $camList array containing *only* the specified camera. And show all images.
	$camList = $DVR_Engine->camList;
	$imgLimit = 8;
        if (isset($_REQUEST['Camera']) && (strlen($_REQUEST['Camera']) > 2)) {
		$camList = array($camList[$_REQUEST['Camera']]);
		$imgLimit = "all";	// Show all images, since we've drilled down to a specific camera..
	}

	// For each Camera in our list, show a image gallery
        foreach($camList as $camName => $camInfo) {
		echo "<TR><TD COLSPAN=5 style='background-color: lightblue'><CENTER>Last <B>$imgLimit</B> snapshots from <B>[{$camInfo['name']}]</B></CENTER></TD></TR>\n";
		ShowPictureStrip($camInfo, $imgLimit);
		echo "<TR><TD COLSPAN=5>&nbsp;</TD></TR>\n";
        }
	$Page->displayPart("footer");

        exit(0);


	function ShowPictureStrip($Camera = -1, $Limit = -1) {
		global $DVR_Engine;

		$Limit = (int)$Limit;
		$cellCount = $totalCount = 0;
		$rowLength = 4;

		// $endRow - Anonymous helper function, will close of rows ensuring the correct number of cells have been output..
		$endRow = function() use (&$cellCount, $rowLength) {$left = $rowLength - $cellCount; $cellCount = 0; return ($left ? str_repeat("<TD> </TD>", $left) : "") . "</TR>\n\n";};

		echo "<TR>";

		// Build file list
		$camFolder = $Camera['folder'];
		$camName = $Camera['name'];
		$camIndex = $Camera['index'];
	        $files = scandir($camFolder, SCANDIR_SORT_DESCENDING);

		foreach($files as $fname) {
			if ($fname == "." || $fname == "..")
				continue;

			$cellCount++;
			$totalCount++;
			$picInfo = date ("F d Y H:i:s.", filemtime("{$camFolder}/$fname"));

			//<A HREF='index.php?mode=full&file=$fname&Camera=$Camera&date=$dateString'>
			echo "<TD><IMG CLASS=imgLink SRC='index.php?mode=thumb&file=$fname&Camera=$camName&date={$_REQUEST['date']}'><br/><font color='blue'>[@]&nbsp;&nbsp; $picInfo</font></TD>\n";

			if ($cellCount >= $rowLength) 			echo $endRow() . "<TR>\n";  	// End of row - next line!
			if (($Limit > 0) && ($totalCount >= $Limit))	break;				// We've printed the requested # of images, abort out.
		}

		echo $endRow();
	}
