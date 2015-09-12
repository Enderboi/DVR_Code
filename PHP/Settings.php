<?php
// Per-Host Configuration
$Settings['Host']['takato'] = array("Folder_Base" => "/media/Video/Private/Camera", "pageRoot" => "/var/www/html/camera", "htmlRoot" => "/camera", "authModule" => "", "webSlug" => " **TAKATO** ");
$Settings['Host']['dvrdev'] = array("Folder_Base" => "/camera", "pageRoot" => "/var/www/html", "htmlRoot" => "/", "authModule" => "", "webSlug" => " **DVRDEV** ");

// Global Default Configuration (overridden by host-specific options above...)
$Settings['Host']['Default'] = array("Folder_Base" => "/camera", "Folder_Snap" => "Snapshot", "Folder_Cache" => "thumb", "ThumbSize" => array("200", "125"));
