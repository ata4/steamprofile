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

class HttpLoader extends Curl {
	public function __construct($sUrl, $sApp, $sExtra) {
		parent::__construct($sUrl);
		
		$aCURLVersion = curl_version();
		$this->setUserAgent($sApp.' ('.$sExtra.'; PHP '.PHP_VERSION.'; cURL '.$aCURLVersion['version'].')');
		
		// setting CURLOPT_FOLLOWLOCATION in safe_mode will raise a warning
		if(ini_get('safe_mode') == 'Off' || ini_get('safe_mode') === 0) {
			$this->setOption(CURLOPT_FOLLOWLOCATION, true);
			$this->setOption(CURLOPT_MAXREDIRS, 3);
		}
	}
	
	public function start() {
		$content = parent::start();
		
		// false means cURL failed
		if($content === false) {
			throw new Exception('cURL error: '.$this->getErrorMessage());
		}
		
		// anything else than status code 2xx is most likely bad
		$iHttpCode = $this->getHttpCode();
		if($iHttpCode < 200 || $iHttpCode > 299) {
			throw new Exception('Server error: '.HttpHeader::getHttpCodeString($iHttpCode));
		}
		
		return $content;
	}
}
?>