<?php

/**
 *     Written by Nico Bergemann <barracuda415@yahoo.de>
 *     Copyright 2011 Nico Bergemann
 *
 *     This program is free software: you can redistribute it and/or modify
 *     it under the terms of the GNU General Public License as published by
 *     the Free Software Foundation, either version 3 of the License, or
 *     (at your option) any later version.
 *
 *     This program is distributed in the hope that it will be useful,
 *     but WITHOUT ANY WARRANTY; without even the implied warranty of
 *     MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *     GNU General Public License for more details.
 *
 *     You should have received a copy of the GNU General Public License
 *     along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

require_once 'lib/net/Curl.php';

class HTTPLoader extends Curl {

    public function __construct($sUrl, $sApp, $sExtra) {
        parent::__construct($sUrl);

        $aCURLVersion = curl_version();
        $this->setUserAgent($sApp . ' (' . $sExtra . '; PHP ' . PHP_VERSION . '; cURL ' . $aCURLVersion['version'] . ')');

        // setting CURLOPT_FOLLOWLOCATION in safe_mode could raise a warning,
        // catch it
        try {
            $this->setFollowLocation(TRUE);
            $this->setMaxRedirects(3);
        } catch (Exception $e) {
        }
    }

    public function start() {
        $content = parent::start();

        // FALSE means cURL failed
        if ($content === FALSE) {
            throw new Exception('cURL error: ' . $this->getErrorMessage());
        }

        // anything else than status code 2xx is most likely bad
        $iHttpCode = $this->getHTTPCode();
        if ($iHttpCode < 200 || $iHttpCode > 299) {
            throw new Exception('Server error: ' . HTTPHeader::getHTTPCodeString($iHttpCode));
        }

        return $content;
    }

}

?>