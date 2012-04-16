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

class SteamProfileImage extends GDImage {
	private $oCommonConfig;
	private $oThemeConfig;
	private $aThemeFiles;
	private $oJpgAssetCache;

    public function createProfile($sProfileUrl, $sBaseDir, $sCurrentTheme) {
		parent::__construct();
		
		// load global config
		$this->oCommonConfig = FileConfig::getInstance('image.cfg');
		
		// load image config
		$oImageConfig = FileConfig::getInstance('image.cfg');
		
		// set default theme
		$sDefaultTheme = $oImageConfig->getString('image.theme.default', 'default');
	
		// set theme path
		$sDefaultThemePath = "$sBaseDir/themes/$sDefaultTheme";
		$sCurrentThemePath = "$sBaseDir/themes/$sCurrentTheme";
		
		if(!file_exists($sDefaultThemePath)) {
			throw new RuntimeException('Default theme folder not found');
		}
		
		if(!file_exists($sCurrentThemePath)) {
			$sCurrentThemePath = $sDefaultThemePath;
		}
		
		// init cache
		$sCacheDir = $this->oCommonConfig->getString('cache.dir', 'cache');
		$this->oJpgAssetCache = new Cache($sCacheDir, -1, 'jpg');
		
		// the required files for the default theme
		$this->aThemeFiles = array(
			'background'			=> 'background.png',
			'background_fade'		=> 'background_fade.png',
			'default_avatar'		=> 'default_av.jpg',
			'error'					=> 'error.png',
			'frame_avatar_ingame'	=> 'iconholder_ingame.png',
			'frame_avatar_offline'	=> 'iconholder_offline.png',
			'frame_avatar_online'	=> 'iconholder_online.png',
			'frame_avatar_private'	=> 'iconholder_offline.png',
		);
		
		// check for existing theme files
		foreach($this->aThemeFiles as $sKey => $sFile) {
			if(!file_exists("$sDefaultThemePath/$sFile")) {
				throw new RuntimeException("Missing default theme file '$sDefaultThemePath/$sFile'");
			}
		
			if(file_exists("$sCurrentThemePath/$sFile")) {
				$this->aThemeFiles[$sKey] = "$sCurrentThemePath/$sFile";
			} else {
				$this->aThemeFiles[$sKey] = "$sDefaultThemePath/$sFile";
			}
		}
		
		// set theme config paths
		$sDefaultThemeConfigFile = "$sDefaultThemePath/theme.cfg";
		$sCurrentThemeConfigFile = "$sCurrentThemePath/theme.cfg";
		
		if(!file_exists($sDefaultThemeConfigFile)) {
			throw new RuntimeException('Default theme config not found');
		}
		
		// load default config
		$this->oThemeConfig = FileConfig::getInstance($sDefaultThemeConfigFile);
		
		// merge default config with selected theme config, if existing
		if($sCurrentTheme !== $sDefaultTheme && file_exists($sCurrentThemeConfigFile)) {
			$this->oThemeConfig->merge(FileConfig::getInstance($sCurrentThemeConfigFile));
		}
		
		// init settings
		$iTextStatusX		= $this->oThemeConfig->getInteger('theme.text.status.x');
		$iTextStatusY		= $this->oThemeConfig->getInteger('theme.text.status.y');
		$iImageAvatarX		= $this->oThemeConfig->getInteger('theme.image.avatar.x');
		$iImageAvatarY		= $this->oThemeConfig->getInteger('theme.image.avatar.y');
		$bShowGameBG		= $this->oThemeConfig->getBoolean('theme.background.game');
			
		// load background
		$this->loadPng($this->aThemeFiles['background']);
		
		// enable alpha
		$this->setAlphaBlending(true);
		$this->setSaveAlpha(true);
		
		try {
			// load XML data
			$xmlData = $this->loadXmlData($sProfileUrl);
		} catch(Exception $e) {
			// that didn't work :(
			$this->drawErrorMessage($e->getMessage());
			throw new SteamProfileImageException('Can\'t load XML data', 0, $e);
		}
		
		// check if the server has returned any errors
		if(count($xmlData->xpath('/response')) != 0) {
			$this->drawErrorMessage((string) $xmlData->error);
			throw new SteamProfileImageException('Steam server error: '.(string) $xmlData->error);
		}
		
		// check for expected XML structure
		if(count($xmlData->xpath('/profile')) == 0) {
			$this->drawErrorMessage('Invalid Steam Community data');
			throw new SteamProfileImageException('Invalid Steam Community data');
		}
		
		$sStatusCode = 'offline';
		
		// determinate status
		if((string)$xmlData->privacyState != 'public') {
			$sStatusCode = 'private';
			$sStatus = 'This profile is private';
		} else {
			// get the player's status for text and color
			switch((string)$xmlData->onlineState) {
				case 'in-game':
					$sStatusCode = 'ingame';
					$sCurrentGame = $xmlData->inGameInfo == null? '' : (string)$xmlData->inGameInfo->gameName;
					$sStatus = "In-Game\n$sCurrentGame";
					break;

				case 'online':
					$sStatusCode = 'online';
					$sStatus = 'Online';
					break;

				case 'offline':
					$sStatus = (string)$xmlData->stateMessage;
					break;

				default:
					throw new RuntimeException('Unable to determinate player status.');
			}
		}
		
		if($bShowGameBG && $sStatusCode == 'ingame') {
			// game background
			$sGameBGUrl = (string)$xmlData->inGameInfo->gameLogoSmall;
			$this->drawGameBackground($sGameBGUrl);
		}
		
		// avatar image
		$sAvatarUrl = (string)$xmlData->avatarIcon;
		$this->drawAvatar($iImageAvatarX, $iImageAvatarY, $sAvatarUrl, $sStatusCode);
		
		// status text
		$sName = (string)$xmlData->steamID;
		$this->drawStatus($iTextStatusX, $iTextStatusY, $sName, $sStatus, $sStatusCode);
	}
	
	private function loadXmlData($sUrl) {
		// settings
		$iTimeout = $this->oCommonConfig->getInteger('downloader.timeout', 10);
	
		// load XML data
		$XmlLoader = new HttpProfileLoader($sUrl, SteamProfileApp::AGENT, 'Image');
		$XmlLoader->setTimeout($iTimeout);
		$XmlLoader->setFilterCtlChars(true);
		$XmlLoader->setTrimExtra(false);
		$sXml = $XmlLoader->start();
		$XmlLoader->close();
		
		return simplexml_load_string($sXml);
	}
	
	private function loadJpegUrl($sUrl) {
		$cacheFile = $this->oJpgAssetCache->getFile($sUrl);
		
		// do we already have a cached version of this image?
		if(!$cacheFile->isCached()) {
			$iTimeout = $this->oCommonConfig->getInteger('downloader.timeout', 10);
			$imageLoader = new HttpLoader($sUrl, SteamProfileApp::AGENT, 'Image');
			$imageLoader->setTimeout($iTimeout);
			$imageLoader->setOutputFile($cacheFile->getPath());
			$imageLoader->start();
			$imageLoader->close();
		}
		
		return $cacheFile;
	}
	
	private function createBlankImage($iWidth, $iHeight) {
		// blank transparent image
		$image = new GDImage($iWidth, $iHeight);
		$image->fill(0, 0, $image->getColorTransparent());
		
		return $image;
	}
	
	private function drawAvatar($iX, $iY, $sAvatarUrl, $sStatusCode) {
		// settings
		$iImageSize = 40;
	
		// new blank image
		$image = $this->createBlankImage($iImageSize, $iImageSize);
		
		$avatarImage = new GDImage();
		
		try {
			// load and cache avatar
			$avatarFile = $this->loadJpegUrl($sAvatarUrl);
			$avatarImage->loadJpeg($avatarFile->getPath());
		} catch(Exception $e) {
			// load placeholder
			$avatarImage->loadJpeg($this->aThemeFiles['default_avatar']);
		}
		
		// draw frame	
		$avatarFrameImage = new GDImage();
		$avatarFrameImage->loadPng($this->aThemeFiles["frame_avatar_$sStatusCode"]);
		$image->copy($avatarFrameImage, 0, 0);
		$avatarFrameImage->destroy();
		
		// draw avatar
		$image->copy($avatarImage, 4, 4);
		$avatarImage->destroy();
		
		// copy to main image
		$this->copy($image, $iX, $iY);
		$image->destroy();
	}
	
	private function drawStatus($iX, $iY, $sName, $sStatus, $sStatusCode) {
		// settings
		$iImageWidth = 206;
		$iImageHeight = 64;
		$sFontColor	= $this->oThemeConfig->getString("theme.text.color.$sStatusCode");
		$iFontSizeNormal = $this->oThemeConfig->getInteger('theme.text.size.normal');
		$iFontSizeBold = $this->oThemeConfig->getInteger('theme.text.size.bold');
		$sFontFileNormal = $this->oThemeConfig->getString('theme.text.font.normal');
		$sFontFileBold = $this->oThemeConfig->getString('theme.text.font.bold');
		$bFontAntiAlias = $this->oThemeConfig->getInteger('theme.text.anti-alias');
		$fLineSpacing = $this->oThemeConfig->getFloat('theme.text.line-spacing');
	
		// new blank image
		$image = $this->createBlankImage($iImageWidth, $iImageHeight);
		
		// allocate color
		$iFontColorName = $iFontColorStatus = $this->getColorHex($sFontColor, $bFontAntiAlias);
		
		// Use "offline" color for name if private
		if($sStatusCode == 'private') {
			$sFontColorOffline = $this->oThemeConfig->getString("theme.text.color.offline");
			$iFontColorName = $this->getColorHex($sFontColorOffline, $bFontAntiAlias);
		}
		
		// draw text
		$image->drawTextFT($sName, $sFontFileBold, $iFontSizeBold,
			0, $iFontColorName, 0, $iFontSizeBold);
		$image->drawTextFT("\n".$sStatus, $sFontFileNormal, $iFontSizeNormal,
			0, $iFontColorStatus, 0, $iFontSizeNormal, array('linespacing' => $fLineSpacing));
		
		// copy to main image
		$this->copy($image, $iX, $iY);
		$image->destroy();
	}
	
	private function drawGameBackground($sGameBGUrl) {
		$gameBGImage = new GDImage();
		
		try {
			// load and cache game background
			$gameBGFile = $this->loadJpegUrl($sGameBGUrl);
			$gameBGImage->loadJpeg($gameBGFile->getPath());
		} catch(Exception $e) {
			// the game background doesn't work, but we don't mind
			return;
		}
		
		// draw game background
		$this->copyResampled($gameBGImage, $this->getWidth() - 128, 0, 0, 0, 128, 48, 120, 45);
		$gameBGImage->destroy();
		
		// draw fade background over game background
		$fadeBGImage = new GDImage();
		$fadeBGImage->loadPng($this->aThemeFiles['background_fade']);
		$this->copy($fadeBGImage, $this->getWidth() - 128, 0);
		$fadeBGImage->destroy();
	}
	
	private function drawErrorMessage($sMessage) {
		// settings
		$iFontSizeNormal = $this->oThemeConfig->getInteger('theme.text.size.normal');
		$sFontFileNormal = $this->oThemeConfig->getString('theme.text.font.normal');
		$sFontColorError = $this->oThemeConfig->getString('theme.text.color.error');
		$bFontAntiAlias = $this->oThemeConfig->getInteger('theme.text.anti-alias');
		$fLineSpacing = $this->oThemeConfig->getFloat('theme.text.line-spacing');
		
		// destroy old image
		$this->destroy();
		
		// create new image
		$this->loadPng($this->aThemeFiles['background']);
		
		// enable alpha
		$this->setAlphaBlending(true);
		$this->setSaveAlpha(true);
	
		// draw error icon
		$errorIcon = new GDImage();
		$errorIcon->loadPng($this->aThemeFiles['error']);
		$this->copy($errorIcon, 8, $this->getHeight() / 2 - 8);
		$errorIcon->destroy();
		
		// allocate color
		$iFontColor = $this->getColorHex($sFontColorError, $bFontAntiAlias);
		
		// draw text
		$this->drawTextFT($sMessage, $sFontFileNormal, $iFontSizeNormal,
			0, $iFontColor, 30, $this->getHeight() / 2, array('linespacing' => $fLineSpacing));
	}
}
?>