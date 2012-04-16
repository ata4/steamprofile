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

jQuery.fn.attrAppend = function(name, value) {
	var elem;
	return this.each(function(){
		elem = $(this);
		
		// append attribute only if extisting and not empty
		if (elem.attr(name) !== undefined && elem.attr(name) != "") {
			elem.attr(name, value + elem.attr(name));
		}
	});
};

function SteamProfile() {
	// path/file config
	var scriptFile = "steamprofile.js";
	var configFile = "steamprofile.xml";
	var proxyFile = "../xmlproxy.php";
	var basePath;
	var themePath;
	
	// language config
	var lang = "english";
	var langLocal = "english";
	var langData = {
		english : {
			loading : "Loading...",
			no_profile : "This user has not yet set up their Steam Community profile.",
			private_profile : "This profile is private.",
			invalid_data : "Invalid profile data.",
			join_game : "Join Game",
			add_friend : "Add to Friends",
			view_tf2items : "View TF2 Backpack"
		},
		german : {
			loading : "Lade...",
			no_profile : "Dieser Benutzer hat bisher kein Steam Community Profil angelegt.",
			private_profile : "Dieses Profil ist privat.",
			invalid_data : "Ungültige Profildaten.",
			join_game : "Spiel beitreten",
			add_friend : "Als Freund hinzufügen",
			view_tf2items : "TF2-Items ansehen"
		},
		portuguese : {
			loading : "Carregando...",
			no_profile : "This user has not yet set up their Steam Community profile.",
			private_profile : "This profile is private.",
			invalid_data : "Invalid profile data.",
			join_game : "Entrar",
			add_friend : "Adicionar à sua lista de amigos",
			view_tf2items : "Ver Itens do TF2"
		}
	};
	
	// misc config
	var loadLock = false;
	var configLoaded = false;
	var configData;
	var showGameBanner;
	var showSliderMenu;
	var showTF2ItemsIcon;

	// profile data
	var profiles = [];
	var profileCache = {};
	
	// template data
	var profileTpl;
	var loadingTpl;
	var errorTpl;

	this.init = function() {		
		if (typeof spBasePath == "string") {
			basePath = spBasePath;
		} else {
			// extract the path from the src attribute

			// get our <script>-tag
			var scriptElement = $('script[src$=\'' + scriptFile + '\']');
			
			// in rare cases, this script could be included without <script>
			if (scriptElement.length === 0) {
				return;
			}
			
			basePath = scriptElement.attr('src').replace(scriptFile, '');
		}
		
		// load xml config
		jQuery.ajax({
			type: 'GET',
			url: basePath + configFile,
			dataType: 'html',
			complete: function(request, status) {
				configData = $(request.responseXML);
				loadConfig();
			}
		});
	};
	
	this.refresh = function() {
		// make sure we already got a loaded config
		// and no pending profile loads
		if (!configLoaded || loadLock) {
			return;
		}
		
		// lock loading
		loadLock = true;
		
		// select profile placeholders
		profiles = $('.steamprofile[title]');
		
		// are there any profiles to build?
		if (profiles.length === 0) {
			return;
		}

		// store profile id for later usage
		profiles.each(function() {
			var profile = $(this);
			profile.data('profileID', $.trim(profile.attr('title')));
			profile.removeAttr('title');
		});

		// replace placeholders with loading template and make them visible
		profiles.empty().append(loadingTpl);
		
		// load first profile
		loadProfile(0);
	};
	
	this.load = function(profileID) {
		// make sure we already got a loaded config
		// and no pending profile loads
		if (!configLoaded || loadLock) {
			return;
		}
		
		// create profile base
		profile = $('<div class="steamprofile"></div>');
		
		// add loading template
		profile.append(loadingTpl);
		
		// load xml data
		jQuery.ajax({
			type: 'GET',
			url: getXMLProxyURL(profileID),
			dataType: 'xml',
			complete: function(request, status) {
				// build profile and replace placeholder with profile
				profile.empty().append(createProfile($(request.responseXML)));
			}
		});
		
		return profile;
	};
	
	this.isLocked = function() {
		return loadLock;
	};
	
	function getXMLProxyURL(profileID) {
		return basePath + proxyFile + '?id=' + escape(profileID) + '&lang=' + escape(lang);
	}
	
	function getConfigString(name) {
		return configData.find('vars > var[name="' + name + '"]').text();
	}
	
	function getConfigBool(name) {
		return getConfigString(name).toLowerCase() == 'true';
	}
	
	function loadConfig() {
		showSliderMenu = getConfigBool('slidermenu');
		showGameBanner = getConfigBool('gamebanner');
		showTF2ItemsIcon = getConfigBool('tf2items');
		lang = getConfigString('language');
		langLocal = lang;
		
		// fall back to english if no translation is available for the selected language in SteamProfile
		if (langData[langLocal] == null) {
			langLocal = "english";
		}
	
		// set theme stylesheet
		themePath = basePath + 'themes/' + getConfigString('theme') + '/';
		$('head').append('<link rel="stylesheet" type="text/css" href="' + themePath + 'style.css">');
		
		// load templates
		profileTpl = $(configData.find('templates > profile').text());
		loadingTpl = $(configData.find('templates > loading').text());
		errorTpl   = $(configData.find('templates > error').text());
		
		// add theme path to image src
		profileTpl.find('img').attrAppend('src', themePath);
		loadingTpl.find('img').attrAppend('src', themePath);
		errorTpl.find('img').attrAppend('src', themePath);
		
		// set localization strings
		profileTpl.find('.sp-joingame').attr('title', langData[langLocal].join_game);
		profileTpl.find('.sp-addfriend').attr('title', langData[langLocal].add_friend);
		profileTpl.find('.sp-viewitems').attr('title', langData[langLocal].view_tf2items);
		loadingTpl.append(langData[langLocal].loading);
		
		// we can now unlock the refreshing function
		configLoaded = true;
		
		// start loading profiles
		SteamProfile.refresh();
	}

	function loadProfile(profileIndex) {
		// check if we have loaded all profiles already
		if (profileIndex >= profiles.length) {
			// unlock loading
			loadLock = false;
			return;
		}
		
		var profile = $(profiles[profileIndex++]);
		var profileID = profile.data('profileID');
		
		if (profileCache[profileID] == null) {
			// load xml data
			jQuery.ajax({
				type: 'GET',
				url: getXMLProxyURL(profileID),
				dataType: 'xml',
				complete: function(request, status) {
					// build profile and cache DOM for following IDs
					profileCache[profileID] = createProfile($(request.responseXML));
					// replace placeholder with profile
					profile.empty().append(profileCache[profileID]);
					// load next profile
					loadProfile(profileIndex);
				}
			});
		} else {
			// the profile was build previously, just copy it
			var profileCopy = profileCache[profileID].clone();
			createEvents(profileCopy);
			profile.empty().append(profileCopy);
			// load next profile
			loadProfile(profileIndex);
		}
	}

	function createProfile(profileData) {
		if (profileData.find('profile').length !== 0) {
			var profile;
		
			if (profileData.find('profile > steamID').text() == '') {
				// the profile doesn't exists yet
				return createError(langData[langLocal].no_profile);
			} else {
				// profile data looks good
				profile = profileTpl.clone();
				var onlineState = profileData.find('profile > onlineState').text();
				
				// set state class, avatar image and name
				profile.find('.sp-badge').addClass('sp-' + onlineState);
				profile.find('.sp-avatar img').attr('src', profileData.find('profile > avatarIcon').text());
				profile.find('.sp-info a').append(profileData.find('profile > steamID').text());
				
				// set state message
				if (profileData.find('profile > visibilityState').text() == '1') {
					profile.find('.sp-info').append(langData[langLocal].private_profile);
				} else {
					profile.find('.sp-info').append(profileData.find('profile > stateMessage').text());
				}
				
				// set game background
				if (showGameBanner && profileData.find('profile > inGameInfo > gameLogoSmall').length !== 0) {
					profile.css('background-image', 'url(' + profileData.find('profile > inGameInfo > gameLogoSmall').text() + ')');
				} else {
					profile.removeClass('sp-bg-game');
					profile.find('.sp-bg-fade').removeClass('sp-bg-fade');
				}
				
				if (showSliderMenu) {
					if (profileData.find('profile > inGameInfo > gameJoinLink').length !== 0) {
						// add 'Join Game' link href
						profile.find('.sp-joingame').attr('href', profileData.find('profile > inGameInfo > gameJoinLink').text());
					} else {
						// the user is not in a multiplayer game, remove 'Join Game' link
						profile.find('.sp-joingame').remove();
					}
				
					if (showTF2ItemsIcon) {
						// add 'View Items' link href
						profile.find('.sp-viewitems')
							.attr('href', 'http://tf2items.com/profiles/' + profileData.find('profile > steamID64').text());
					} else {
						profile.find('.sp-viewitems').remove();
					}
					
					// add 'Add Friend' link href
					profile.find('.sp-addfriend')
						.attr('href', 'steam://friends/add/' + profileData.find('profile > steamID64').text());
					
					createEvents(profile);
				} else {
					profile.find('.sp-extra').remove();
				}
				
				// add other link hrefs
				profile.find('.sp-avatar a, .sp-info a.sp-name')
					.attr('href', 'http://steamcommunity.com/profiles/' + profileData.find('profile > steamID64').text());
			}
			
			return profile;
		} else if (profileData.find('response').length !== 0) {
			// steam community returned a message
			return createError(profileData.find('response > error').text());
		} else {
			// we got invalid xml data
			return createError(langData[langLocal].invalid_data);
		}
	}
	
	function createEvents(profile) {
		// add events for menu
		profile.find('.sp-handle').click(function() {
			profile.find('.sp-content').toggle(200);
		});
	}

	function createError(message) {
		var errorTmp = errorTpl.clone();
		errorTmp.append(message);	
		return errorTmp;
	}
}

$(document).ready(function() {
	SteamProfile = new SteamProfile();
	SteamProfile.init();
});