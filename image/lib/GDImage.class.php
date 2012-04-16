<?php
/*
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
 * A wrapper for GD functions (mostly image[...])
 *
 * @author Nico Bergemann
 */

class GDImage {
	protected $rImage;
	
	public function __construct($iWidth = null, $iHeight = null) {
		// make sure the GD extension is loaded
		if(!self::isAvailable()) {
			throw new RuntimeException('GD extension required');
		}
		
		if($iWidth != null) {
			$this->create($iWidth, $iHeight != null ? $iHeight : $iWidth);
		}
	}
	
	public static function isAvailable() {
		return extension_loaded('gd') && function_exists('gd_info');
	}
	
	public function create($iWidth, $iHeight) {
		$this->rImage = imagecreatetruecolor($iWidth, $iHeight);
	}
	
	public function destroy() {
		imagedestroy($this->rImage);
	}
	
	public function getWidth() {
		return imagesx($this->rImage);
	}
	
	public function getHeight() {
		return imagesy($this->rImage);
	}
	
	public function getHandle() {
		return $this->rImage;
	}
	
	public function getInfo() {
		return gd_info();
	}
	
	public function loadFont($sFontFile) {
		return imageloadfont($sFontFile);
	}
	
	public function drawText($sText, $iFont, $iColor, $iX, $iY) {
		$aText = explode("\n", $sText);
		$iLineHeight = imagefontheight($iFont);
		
		foreach($aText as $sLine) {
			imagestring($this->rImage, $iFont, $iX, $iY, $sLine, $iColor);
			$iY += $iLineHeight;
		}
	}
	
	public function drawTextFt($sText, $sFontFile, $fSize, $fAngle, $iColor, $iX, $iY, $aExtra = array()) {
		return imagefttext($this->rImage, $fSize, $fAngle, $iX, $iY, $iColor, $sFontFile, $sText, $aExtra);
	}
	
	public function drawTextTtf($sText, $sFontFile, $fSize, $fAngle, $iColor, $iX, $iY) {
		return imagettftext($this->rImage, $fSize, $fAngle, $iX, $iY, $iColor, $sFontFile, $sText);
	}
	
	public function getTtfTextBounds($sText, $sFontFile, $fSize, $fAngle) {
		return imagettfbbox($fSize, $fAngle, $sFontFile, $sText);
	}
	
	public function getTtfTextDim($sText, $sFontFile, $fSize, $fAngle) {
		return self::convertBoundsToDim($this->getTtfTextBounds($sText, $sFontFile, $fSize, $fAngle));
	}
	
	public function getFtTextBounds($sText, $sFontFile, $fSize, $fAngle) {
		return imageftbbox($fSize, $fAngle, $sFontFile, $sText);
	}
	
	public function getFtTextDim($sText, $sFontFile, $fSize, $fAngle) {
		return self::convertBoundsToDim($this->getFtTextBounds($sText, $sFontFile, $fSize, $fAngle));
	}
	
	private static function convertBoundsToDim($aBounds) {
		$iMinX = min(array($aBounds[0], $aBounds[2], $aBounds[4], $aBounds[6]));
		$iMaxX = max(array($aBounds[0], $aBounds[2], $aBounds[4], $aBounds[6]));
		$iMinY = min(array($aBounds[1], $aBounds[3], $aBounds[5], $aBounds[7]));
		$iMaxY = max(array($aBounds[1], $aBounds[3], $aBounds[5], $aBounds[7]));
		
		return array(
			'left' => ($iMinX >= -1) ? -abs($iMinX + 1) : abs($iMinX + 2),
			'top' => abs($iMinY),
			'width' => $iMaxX - $iMinX,
			'height' => $iMaxY - $iMinY
		);
	}
	
	public function drawRectangle($iX1, $iY1, $iX2, $iY2, $iColor) {
		return imagerectangle($this->rImage, $iX1, $iY1, $iX2, $iY2, $iColor);
	}
	
	public function drawLine($iX1, $iY1, $iX2, $iY2, $iColor) {
		return imageline($this->rImage, $iX1, $iY1, $iX2, $iY2, $iColor);
	}
	
	public function drawPixel($iX1, $iY1, $iColor) {
		return imagesetpixel($this->rImage, $iX1, $iY1, $iColor);
	}
	
	public function fill($iX, $iY, $iColor) {
		return imagefill($this->rImage, $iX, $iY, $iColor);
	}
	
	public function copy(GDImage $Image, $iX1, $iX2, $iY1 = 0, $iY2 = 0, $iWidth = null, $iHeight = null) {
		if($iWidth == null) {
			$iWidth = $Image->getWidth();
		}
		
		if($iHeight == null) {
			$iHeight = $Image->getHeight();
		}
	
		return imagecopy($this->rImage, $Image->getHandle(), $iX1, $iX2, $iY1, $iY2, $iWidth, $iHeight);
	}
	
	public function copyResized(GDImage $Image, $iX1, $iX2, $iY1, $iY2, $iDstWidth, $iDstHeight, $iWidth, $iHeight) {
		return imagecopyresized($this->rImage, $Image->getHandle(), $iX1, $iX2, $iY1, $iY2, $iDstWidth, $iDstHeight, $iWidth, $iHeight);
	}
	
	public function copyResampled(GDImage $Image, $iX1, $iX2, $iY1, $iY2, $iDstWidth, $iDstHeight, $iWidth, $iHeight) {
		return imagecopyresampled($this->rImage, $Image->getHandle(), $iX1, $iX2, $iY1, $iY2, $iDstWidth, $iDstHeight, $iWidth, $iHeight);
	}
	
	public function setAntiAlias($bAntiAlias) {
		return imageantialias($this->rImage, $bAntiAlias);
	}
	
	public function setAlphaBlending($bAlpha) {
		imagealphablending($this->rImage, $bAlpha);
	}
	
	public function setSaveAlpha($bSaveAlpha) {
		imagesavealpha($this->rImage, $bSaveAlpha);
	}
	
	public function getColor($iR, $iG, $iB, $bAntiAlias = true) {
		return imagecolorallocate($this->rImage, $iR, $iG, $iB) * ($bAntiAlias ? 1 : -1);
	}
	
	public function getColorArray($aColor, $bAntiAlias = true) {
		return imagecolorallocate($this->rImage, $aColor[0], $aColor[1], $aColor[2]) * ($bAntiAlias ? 1 : -1);
	}
	
	public function getColorHex($sColor, $bAntiAlias = true) {
		return $this->getColorArray(sscanf($sColor, '#%2x%2x%2x')) * ($bAntiAlias ? 1 : -1);
	}
	
	public function getColorTransparent() {
		return imagecolortransparent($this->rImage);
	}
	
	public function getColorAt($iX, $iY) {
		return imagecolorat($this->rImage, $iX, $iY);
	}
	
	public function loadGd($sFile) {
		$this->rImage = imagecreatefromgd($sFile);
	}
	
	public function loadGd2($sFile) {
		$this->rImage = imagecreatefromgd2($sFile);
	}
	
	public function loadPng($sFile) {
		$this->rImage = imagecreatefrompng($sFile);
	}

	public function loadGif($sFile) {
		$this->rImage = imagecreatefromgif($sFile);
	}
	
	public function loadJpeg($sFile) {
		$this->rImage = imagecreatefromjpeg($sFile);
	}
	
	public function loadString($sImage) {
		$this->rImage = imagecreatefromstring($sImage);
	}

	public function toPng($sOutputFile = null) {
		if($sOutputFile == null) {
			header('Content-Type: image/png');
		}
		return imagepng($this->rImage, $sOutputFile);
	}

	public function toJpeg($sOutputFile = null, $iQuality = 80) {
		if($sOutputFile == null) {
			header('Content-Type: image/jpeg');
		}
		return imagejpeg($this->rImage, $sOutputFile, $iQuality);
	}

	public function toGif($sOutputFile = null) {
		if($sOutputFile == null) {
			header('Content-Type: image/gif');
		}
		return imagegif($this->rImage, $sOutputFile);
	}
}
?>