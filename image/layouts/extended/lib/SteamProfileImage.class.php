<?php
/**
 *	Written by Nico Bergemann <barracuda415@yahoo.de>
 *	Copyright 2008 Nico Bergemann
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
		$this->oCommonConfig = FileConfig::getInstance('common.cfg');
		
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
			'default_avatar'		=> 'default_av.jpg',
			'warning'				=> 'warning.png',
			'error'					=> 'error.png',
			'frame_avatar_ingame'	=> 'frame_avatar_ingame.png',
			'frame_avatar_offline'	=> 'frame_avatar_offline.png',
			'frame_avatar_online'	=> 'frame_avatar_online.png',
			'frame_avatar_private'	=> 'frame_avatar_offline.png',
			'frame_game'			=> 'frame_game.png',
			'frame_icon'			=> 'frame_icon.png'
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
		$iStatusTextX			= $this->oThemeConfig->getInteger('theme.text.status.x');
		$iStatusTextY			= $this->oThemeConfig->getInteger('theme.text.status.y');
		$iInfoTextX				= $this->oThemeConfig->getInteger('theme.text.info.x');
		$iInfoTextY				= $this->oThemeConfig->getInteger('theme.text.info.y');
		$iImageAvatarX			= $this->oThemeConfig->getInteger('theme.image.avatar.x');
		$iImageAvatarY			= $this->oThemeConfig->getInteger('theme.image.avatar.y');
		$iImageGameX			= $this->oThemeConfig->getInteger('theme.image.game.x');
		$iImageGameY			= $this->oThemeConfig->getInteger('theme.image.game.y');
		$iImageIconsGameX		= $this->oThemeConfig->getInteger('theme.image.icon.games.x');
		$iImageIconsGameY		= $this->oThemeConfig->getInteger('theme.image.icon.games.y');
		$iImageIconsGroupX		= $this->oThemeConfig->getInteger('theme.image.icon.groups.x');
		$iImageIconsGroupY		= $this->oThemeConfig->getInteger('theme.image.icon.groups.y');
		
		// load background
		$this->loadPng($this->aThemeFiles['background']);
		
		// enable alpha
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
					$sCurrentGame = wordwrap($sCurrentGame, 27);
					$sStatus = "In-Game\n$sCurrentGame";
					break;

				case 'online':
					$sStatusCode = 'online';
					$sStatus = 'Online';
					break;

				case 'offline':
					$sStatus = str_replace(': ', ":\n", (string)$xmlData->stateMessage);
					break;

				default:
					throw new RuntimeException('Unable to determinate player status.');
			}
		}
		
		// avatar image
		$sAvatarUrl = (string)$xmlData->avatarFull;
		$this->drawAvatar($iImageAvatarX, $iImageAvatarY, $sAvatarUrl, $sStatusCode);
		
		// status text
		$sName = (string)$xmlData->steamID;
		$this->drawStatus($iStatusTextX, $iStatusTextY, $sName, $sStatus, $sStatusCode);

		if($sStatusCode == 'private') {
			// private profiles don't have anything of interest
			return;
		} else if($sStatusCode == 'ingame') {
			// game image
			$sGameImageUrl = (string)$xmlData->inGameInfo->gameLogo;
			$this->drawGame($iImageGameX, $iImageGameY, $sGameImageUrl);
		} else {
			// group icons
			if($xmlData->groups->group != null) {
				$aGroupIcons = array();
				
				foreach($xmlData->groups->group as $group) {
					// add primary group icon as the first one
					if($group['isPrimary'] == '1') {
						array_unshift($aGroupIcons, (string)$group->avatarIcon);
						continue;
					}
				
					// add three random group icons
					if(count($aGroupIcons) < 3) {
						array_push($aGroupIcons, (string)$group->avatarIcon);
					}
				}
				
				// remove last random icon, if the primary group was found later
				if(count($aGroupIcons) == 4) {
					array_pop($aGroupIcons);
				}
				
				$this->drawIconCluster($iImageIconsGroupX, $iImageIconsGroupY, $aGroupIcons, "Groups");
			}
		}
		
		// info text
		$iInfoStringLimit = 25;
		$sMemberSince = $this->limitString((string)$xmlData->memberSince, $iInfoStringLimit);
		$sPlayingTime = $this->limitString((string)$xmlData->hoursPlayed2Wk, $iInfoStringLimit);
		$sSteamRating = $this->limitString((string)$xmlData->steamRating, $iInfoStringLimit);
		$sLocation = (string)$xmlData->location;
		$aLocation = explode(',', $sLocation);
		$sCountry = $this->limitString(trim(array_pop($aLocation)), $iInfoStringLimit);
		
		// avoid blank lines
		if($sCountry == '') {
			$sCountry = '---';
		}
		
		$sInfoTextLeft = "Member since:\nPlaying time:\nSteam rating:\nCountry:";
		$sInfoTextRight = "$sMemberSince\n$sPlayingTime past 2 weeks\n$sSteamRating\n$sCountry";
		$this->drawInfo($iInfoTextX, $iInfoTextY, $sInfoTextLeft, $sInfoTextRight);

		// game icons
		if(count($xmlData->xpath('/profile/mostPlayedGames/mostPlayedGame')) != 0) {
			$aGameIcons = array();
			
			foreach($xmlData->mostPlayedGames->mostPlayedGame as $mostPlayedGame) {
				$aGameIcons[] = (string)$mostPlayedGame->gameIcon;
			}
		
			$this->drawIconCluster($iImageIconsGameX, $iImageIconsGameY, $aGameIcons, "Games");
		}
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
	
	private function createPlaceholderImage($iWidth, $iHeight) {
		$image = $this->createBlankImage($iWidth, $iHeight);
		
		// draw error icon
		$errorIcon = new GDImage();
		$errorIcon->loadPng($this->aThemeFiles["warning"]);
		$image->copy($errorIcon,
			$image->getWidth() / 2 - $errorIcon->getWidth() / 2,
			$image->getHeight() / 2  - $errorIcon->getHeight() / 2);
		$errorIcon->destroy();
		
		return $image;
	}
	
	private function drawAvatar($iX, $iY, $sAvatarUrl, $sStatusCode) {
		// settings
		$iImageSize = 100;
		$iAvatarImageSrcWidth = 184;
		$iAvatarImageSrcHeight = $iAvatarImageSrcWidth;
		$iAvatarImageDstWidth = 96;
		$iAvatarImageDstHeight = $iAvatarImageDstWidth;
	
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
		
		// draw avatar
		$image->copyResampled($avatarImage, 2, 2, 0, 0,
			$iAvatarImageDstWidth, $iAvatarImageDstHeight,
			$iAvatarImageSrcWidth, $iAvatarImageSrcHeight);
		$avatarImage->destroy();
		
		// draw frame	
		$avatarFrameImage = new GDImage();
		$avatarFrameImage->loadPng($this->aThemeFiles["frame_avatar_$sStatusCode"]);
		$image->copy($avatarFrameImage, 0, 0);
		$avatarFrameImage->destroy();
		
		// copy to main image
		$this->copy($image, $iX, $iY);
		$image->destroy();
	}
	
	private function drawStatus($iX, $iY, $sName, $sStatus, $sStatusCode) {
		// settings
		$iImageWidth = 128;
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
	
	private function drawGame($iX, $iY, $sGameImageUrl) {
		// settings
		$iImageWidth = 124;
		$iImageHeight = 48;
		$iGameImageSrcWidth = 184;
		$iGameImageSrcHeight = 69;
		$iGameImageDstWidth = $iImageWidth - 4;
		$iGameImageDstHeight = $iImageHeight - 4;
		
		// new blank image
		$image = $this->createBlankImage($iImageWidth, $iImageHeight);
		
		try {
			// load and cache game image
			$gameImageFile = $this->loadJpegUrl($sGameImageUrl);
			$gameImage = new GDImage();
			$gameImage->loadJpeg($gameImageFile->getPath());
		} catch(Exception $e) {
			// create placeholder
			$gameImage = $this->createPlaceholderImage($iGameImageSrcWidth, $iGameImageSrcHeight);
		}
		
		// draw game image
		$image->copyResampled($gameImage, 2, 2, 0, 0,
			$iGameImageDstWidth, $iGameImageDstHeight,
			$iGameImageSrcWidth, $iGameImageSrcHeight);
		$gameImage->destroy();
		
		// draw frame
		$gameFrameImage = new GDImage();
		$gameFrameImage->loadPng($this->aThemeFiles["frame_game"]);
		$image->copy($gameFrameImage, 0, 0);
		$gameFrameImage->destroy();
		
		// copy to main image
		$this->copy($image, $iX, $iY);
		$image->destroy();
	}
	
	private function drawInfo($iX, $iY, $sTextLeft, $sTextRight) {
		// settings
		$iImageWidth = 190;
		$iImageHeight = 54;
		$iFontSizeNormal = $this->oThemeConfig->getInteger('theme.text.size.normal');
		$sFontFileNormal = $this->oThemeConfig->getString('theme.text.font.normal');
		$sFontColorInfo1 = $this->oThemeConfig->getString('theme.text.color.info1');
		$sFontColorInfo2 = $this->oThemeConfig->getString('theme.text.color.info2');
		$bFontAntiAlias = $this->oThemeConfig->getInteger('theme.text.anti-alias');
		$fLineSpacing = $this->oThemeConfig->getFloat('theme.text.line-spacing');
		
		// new blank image
		$image = $this->createBlankImage($iImageWidth, $iImageHeight);
		
		// allocate colors
		$iFontColorInfo1 = $image->getColorHex($sFontColorInfo1, $bFontAntiAlias);
		$iFontColorInfo2 = $image->getColorHex($sFontColorInfo2, $bFontAntiAlias);
		
		// draw text
		$image->drawTextFT($sTextLeft, $sFontFileNormal, $iFontSizeNormal,
			0, $iFontColorInfo1, 0, $iFontSizeNormal, array('linespacing' => $fLineSpacing));
		$image->drawTextFT($sTextRight, $sFontFileNormal, $iFontSizeNormal,
			0, $iFontColorInfo2, 74, $iFontSizeNormal, array('linespacing' => $fLineSpacing));
		
		// copy to main image
		$this->copy($image, $iX, $iY);
		$image->destroy();
	}
	
	private function drawIconCluster($iX, $iY, $aIconUrls, $sText) {
		// settings
		$iImageWidth = 116;
		$iImageHeight = 46;
		$iIconImageSize = 32;
		$iImageIconsX = 0;
		$iImageIconsY = 10;
		$sFontColorInfo1 = $this->oThemeConfig->getString('theme.text.color.info1');
		$iFontSizeNormal = $this->oThemeConfig->getInteger('theme.text.size.normal');
		$sFontFileNormal = $this->oThemeConfig->getString('theme.text.font.normal');
		$bFontAntiAlias = $this->oThemeConfig->getInteger('theme.text.anti-alias');
		$iIconSpacing = $this->oThemeConfig->getInteger('theme.image.icon.spacing');
		
		// new blank image
		$image = $this->createBlankImage($iImageWidth, $iImageHeight);
		
		// allocate color
		$iFontColor = $image->getColorHex($sFontColorInfo1, $bFontAntiAlias);
		
		// draw text
		$image->drawTextFT($sText, $sFontFileNormal, $iFontSizeNormal,
			0, $iFontColor, 0, $iFontSizeNormal);
		
		// load icon frame
		$iconFrameImage = new GDImage();
		$iconFrameImage->loadPng($this->aThemeFiles["frame_icon"]);
		
		foreach($aIconUrls as $sIconUrl) {
			// check for basic validity
			if(substr($sIconUrl, 0, 7) != 'http://' || substr($sIconUrl, -4) != '.jpg') {
				continue;
			}
		
			try {
				// load and cache icon
				$iconFile = $this->loadJpegUrl($sIconUrl);
				$iconImage = new GDImage();
				$iconImage->loadJpeg($iconFile->getPath());
			} catch(Exception $e) {
				// create placeholder
				$iconImage = $this->createPlaceholderImage($iIconImageSize, $iIconImageSize);
			}
			
			$image->copy($iconImage, $iImageIconsX + 2, $iImageIconsY + 2);
			$iconImage->destroy();
			
			// draw icon frame
			$image->copy($iconFrameImage, $iImageIconsX, $iImageIconsY);
			
			// move next icon to the right
			$iImageIconsX += $iIconImageSize + $iIconSpacing;
		}
		
		$iconFrameImage->destroy();
		
		// copy to main image
		$this->copy($image, $iX, $iY);
		$image->destroy();
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
		$errorIcon->loadPng($this->aThemeFiles["error"]);
		$this->copyResampled($errorIcon, 16, $this->getHeight() / 2 - 12, 0, 0,
			24, 24,
			$errorIcon->getWidth(), $errorIcon->getHeight());
		$errorIcon->destroy();
		
		// allocate color
		$iFontColor = $this->getColorHex($sFontColorError, $bFontAntiAlias);
		
		// draw text
		$this->drawTextFT($sMessage, $sFontFileNormal, $iFontSizeNormal,
			0, $iFontColor, 48, $this->getHeight() / 2, array('linespacing' => $fLineSpacing));
	}
	
	private function limitString($sString, $iLimit) {
		if(strlen($sString) > $iLimit) {
			return substr($sString, 0, $iLimit - 3).'...';
		} else {
			return $sString;
		}
	}
}
?>