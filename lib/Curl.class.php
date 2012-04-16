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

/**
 * Class Curl
 *
 * cURL object wrapper for basic downloading functions
 */
class Curl {
	private $rCurlSession;
	private $rOutputFile;

	public function __construct($sURL) {
		// make sure the cURL extension is loaded
		if(!self::isAvailable()) {
			throw new RuntimeException('cURL extension required');
		}
	
		$this->rCurlSession = curl_init($sURL);
	}
	
	public static function isAvailable() {
		return extension_loaded('curl') && function_exists('curl_init');
	}
	
	protected function setOption($iOpt, $value) {
		curl_setopt($this->rCurlSession, $iOpt , $value);
	}

	protected function getInfo($iOpt) {
		return curl_getinfo($this->rCurlSession, $iOpt);
	}

	public function setOutputFile($file) {
		if(is_resource($file)) {
			$this->rOutputFile = $file;
			$this->setOption(CURLOPT_FILE, $file);
		} else {
			$this->rOutputFile = fopen($file, 'w+b');
			$this->setOption(CURLOPT_FILE, $this->rOutputFile);
		}
	}

	public function setReturnTransfer($bReturn) {
		$this->setOption(CURLOPT_RETURNTRANSFER, $bReturn);
	}

	public function setUserAgent($sUA) {
		$this->setOption(CURLOPT_USERAGENT, $sUA);
	}

	public function setTimeout($iTimeout) {
		$this->setOption(CURLOPT_TIMEOUT, $iTimeout);
	}

	public function setConnectTimeout($iTimeout) {
		$this->setOption(CURLOPT_CONNECTTIMEOUT, $iTimeout);
	}

	public function getHttpCode() {
		return $this->getInfo(CURLINFO_HTTP_CODE);
	}

	public function getErrorMessage() {
		return curl_error($this->rCurlSession);
	}

	public function start() {
		return curl_exec($this->rCurlSession);
	}

	public function close() {
		curl_close($this->rCurlSession);

		if(is_resource($this->rOutputFile)) {
			fclose($this->rOutputFile);
		}
	}
}
?>