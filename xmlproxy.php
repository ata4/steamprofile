<?php
/**
 *	Written by Nico Bergemann <barracuda415@yahoo.de>
 *	Copyright 2011 Nico Bergemann
 *
 *	This program is free software: you can redistribute it and/or modify
 *	it under the terms of the GNU General Public License as published by
 *	the Free Software Foundation, either version 3 of the License, or
 *	(at your option) any later version.
 *
 *	This program is distributed in the hope that it will be useful,
 *	but WITHOUT ANY WARRANTY; without even the implied warranty of
 *	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *	GNU General Public License for more details.
 *
 *	You should have received a copy of the GNU General Public License
 *	along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

// check for required PHP version 
define('PHP_VERSION_REQUIRED', '5.0.0');
// commend out in productive environments
define('DEBUG', true);

if(version_compare(PHP_VERSION, PHP_VERSION_REQUIRED, '<')) {
	$sResponse = sprintf('PHP %s is not supported (required: PHP %s or higher)', PHP_VERSION, PHP_VERSION_REQUIRED);
	
	// print XML-formatted error
	header('Content-Type: application/xml');
	echo '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
	echo '<response><error><![CDATA['.$sResponse.']]></error></response>';
	exit();
}

// load error exception handling
require_once 'lib/error_exceptions.php';

// load autoincluder
require_once 'lib/ClassPath.class.php';
ClassPath::add('lib');
ClassPath::add('ajax/lib');

// start application
$App = new SteamProfileXMLProxyApp();
$App->run();
?>