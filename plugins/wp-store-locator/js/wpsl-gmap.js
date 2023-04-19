var wpsl = wpsl || {};

wpsl.gmaps = {};

/**
 * This is only used to init the map after the
 * user agreed to load Google Maps in combination
 * with the Borlabs Cookie plugin.
 *
 * @since 2.2.22
 * @returns {void}
 */
function wpslBorlabsCallback() {
	var mapsLoaded;

	mapsLoaded = setInterval( function() {
		if ( typeof google === 'object' && typeof google.maps === 'object' ) {
			clearInterval( mapsLoaded );
			initWpsl();
		}
	}, 500 );
}

/**
 * Callback required by Google Maps.
 */
function wpslCallback() {
	jQuery( document ).ready( function( $ ) {
		initWpsl();
	})
}

function initWpsl() {

	// Create the maps
	jQuery( ".wpsl-gmap-canvas" ).each( function ( mapIndex ) {
		var mapId = jQuery( this ).attr( "id" );

		wpsl.gmaps.init( mapId, mapIndex );
	});

	// Init JS from the WPSL add-ons.
	if ( typeof wpslAddons === 'object' ) {
		for ( const key in wpslAddons ) {
			if ( wpslAddons.hasOwnProperty( key ) ) {
				wpslAddons[key].init()
			}
		}
	}
}

jQuery( document ).ready( function( $ ) {
var geocoder, map, directionsDisplay, directionsService, autoCompleteLatLng,
	activeWindowMarkerId, infoWindow, markerClusterer, startMarkerData, startAddress,
	openInfoWindow = [],
	markersArray = [],
    mapsArray = [],
	markerSettings = {},
	directionMarkerPosition = {},
	mapDefaults = {},
	resetMap = false,
	streetViewAvailable = false,
    autoLoad = ( typeof wpslSettings !== "undefined" ) ? wpslSettings.autoLoad : "",
    userGeolocation = {},
    statistics = {
        enabled: ( typeof wpslSettings.collectStatistics !== "undefined" ) ? true : false,
        addressComponents: ''
	};

/** 
 * Set the underscore template settings.
 * 
 * Defining them here prevents other plugins 
 * that also use underscore / backbone, and defined a
 * different _.templateSettings from breaking the 
 * rendering of the store locator template.
 * 
 * @link	 http://underscorejs.org/#template
 * @requires underscore.js
 * @since	 2.0.0
 */	
_.templateSettings = {
	evaluate: /\<\%(.+?)\%\>/g,
	interpolate: /\<\%=(.+?)\%\>/g,
	escape: /\<\%-(.+?)\%\>/g
};

/**
 * Initialize Google Maps with the correct settings.
 *
 * @since   1.0.0
 * @param   {string} mapId    The id of the map div
 * @param   {number} mapIndex Number of the map
 * @returns {void}
 */
wpsl.gmaps.init = function( mapId, mapIndex ) {
    var mapOptions, mapDetails, settings, infoWindow, latLng,
		bounds, mapData, zoomLevel,
		defaultZoomLevel = Number( wpslSettings.zoomLevel ),
        maxZoom = Number( wpslSettings.autoZoomLevel );

	// Get the settings that belongs to the current map.
	settings = getMapSettings( mapIndex );

	/*
	 * This is the value from either the settings page,
	 * or the zoom level set through the shortcode.
	 */
    zoomLevel = Number( settings.zoomLevel );

    /*
     * If they are not equal, then the zoom value is set through the shortcode.
     * If this is the case, then we use that as the max zoom level.
     */
    if ( zoomLevel !== defaultZoomLevel ) {
        maxZoom = zoomLevel;
	}

	// Create a new infoWindow, either with the infobox libray or use the default one.
	infoWindow = newInfoWindow();

    geocoder	      = new google.maps.Geocoder();
    directionsDisplay = new google.maps.DirectionsRenderer();
    directionsService = new google.maps.DirectionsService();

	// Set the map options.
    mapOptions = {
		zoom: zoomLevel,
		center: settings.startLatLng,
		mapTypeId: google.maps.MapTypeId[ settings.mapType.toUpperCase() ],
		mapTypeControl: Number( settings.mapTypeControl ) ? true : false,
		streetViewControl: Number( settings.streetView ) ? true : false,
        gestureHandling: settings.gestureHandling,
		zoomControlOptions: {
			position: google.maps.ControlPosition[ settings.controlPosition.toUpperCase() + '_TOP' ]
		}
	};

    /**
     * When the gestureHandling is set to cooperative and the scrollWheel
     * options is also set, then the gestureHandling value is ingored.
     *
     * To fix this we only include the scrollWheel options when 'cooperative' isn't used.
     */
    if ( settings.gestureHandling !== 'cooperative' ) {
        mapOptions.scrollwheel = Number( settings.scrollWheel ) ? true : false;
    }

	// Get the correct marker path & properties.
	markerSettings = getMarkerSettings();

	map = new google.maps.Map( document.getElementById( mapId ), mapOptions );

	// Check if we need to apply a map style.
	maybeApplyMapStyle( settings.mapStyle );
	
	if ( ( typeof window[ "wpslMap_" + mapIndex ] !== "undefined" ) && ( typeof window[ "wpslMap_" + mapIndex ].locations !== "undefined" ) ) {
		bounds	= new google.maps.LatLngBounds(),
		mapData = window[ "wpslMap_" + mapIndex ].locations;

		// Loop over the map data, create the infowindow object and add each marker.
		$.each( mapData, function( index ) {
			latLng = new google.maps.LatLng( mapData[index].lat, mapData[index].lng );
			addMarker( latLng, mapData[index].id, mapData[index], false, infoWindow );
			bounds.extend( latLng );
		});

		// If we have more then one location on the map, then make sure to not zoom to far.
		if ( mapData.length > 1 ) {
            // Make sure we don't zoom to far when fitBounds runs.
            attachBoundsChangedListener( map, maxZoom );

            // Make all the markers fit on the map.
            map.fitBounds( bounds );
		}

        /*
         * If we need to apply the fix for the map showing up grey because
         * it's used in a tabbed nav multiple times, then collect the active maps.
         *
         * See the fixGreyTabMap function.
         */
        if ( _.isArray( wpslSettings.mapTabAnchor ) ) {
            mapDetails = {
                map: map,
                bounds: bounds,
				maxZoom: maxZoom
            };

            mapsArray.push( mapDetails );
		}
    }

	// Only run this part if the store locator exist and we don't just have a basic map.
	if ( $( "#wpsl-gmap" ).length ) {
		
		if ( wpslSettings.autoComplete == 1 ) {
			activateAutocomplete();
		}
		
		/*
		 * Not the most optimal solution, but we check the useragent if we should enable the styled dropdowns.
		 * 
		 * We do this because several people have reported issues with the styled dropdowns on
		 * iOS and Android devices. So on mobile devices the dropdowns will be styled according 
		 * to the browser styles on that device.
		 */
		if ( !checkMobileUserAgent() && $( ".wpsl-dropdown" ).length && wpslSettings.enableStyledDropdowns == 1 ) {
			createDropdowns();	
		} else {
			$( "#wpsl-search-wrap select" ).show();
			
			if ( checkMobileUserAgent() ) {
				$( "#wpsl-wrap" ).addClass( "wpsl-mobile" );
			} else {
				$( "#wpsl-wrap" ).addClass( "wpsl-default-filters" );
			}
		}

		// Check if we need to autolocate the user, or autoload the store locations.
		if ( !$( ".wpsl-search" ).hasClass( "wpsl-widget" ) ) {
            if ( wpslSettings.autoLocate == 1 ) {
				checkGeolocation( settings.startLatLng, infoWindow );
			} else if ( wpslSettings.autoLoad == 1 ) {
				showStores( settings.startLatLng, infoWindow );
			}
		}

		// Move the mousecursor to the store search field if the focus option is enabled.
		if ( wpslSettings.mouseFocus == 1 && !checkMobileUserAgent() ) {
			$( "#wpsl-search-input" ).focus();
		}

		// Bind store search button.
		searchLocationBtn( infoWindow );

		// Add the 'reload' and 'find location' icon to the map.
		mapControlIcons( settings, map, infoWindow );

		// Check if the user submitted a search through a search widget.
		checkWidgetSubmit();
	}

	// Bind the zoom_changed listener.
	zoomChangedListener();
};


/**
 * Activate the autocomplete for the store search.
 * 
 * @since 2.2.0
 * @link https://developers.google.com/maps/documentation/javascript/places-autocomplete
 * @returns {void}
 */
function activateAutocomplete() {
	var input, autocomplete, place,
		options = {};

	// Handle autocomplete queries submitted by the user using the 'enter' key.
	keyboardAutoCompleteSubmit();

    /**
	 * Check if we need to set the geocode component restrictions.
	 * This is automatically included when a fixed map region is
	 * selected on the WPSL settings page.
     */
	if ( typeof wpslSettings.geocodeComponents !== "undefined" && !$.isEmptyObject( wpslSettings.geocodeComponents ) ) {
		options.componentRestrictions = wpslSettings.geocodeComponents;

		/**
		 * If the postalCode is included in the autocomplete together with '(regions)' ( which is included ),
		 * then it will break it. So we have to remove it.
		 */
		options.componentRestrictions = _.omit( options.componentRestrictions, 'postalCode' );
	}

	// Check if we need to restrict the autocomplete data.
    if ( typeof wpslSettings.autoCompleteOptions !== "undefined" && !$.isEmptyObject( wpslSettings.autoCompleteOptions ) ) {
        for ( var key in wpslSettings.autoCompleteOptions ) {
            if ( wpslSettings.autoCompleteOptions.hasOwnProperty( key ) ) {
                options[key] = wpslSettings.autoCompleteOptions[key];
            }
        }
    }

	input		  = document.getElementById( "wpsl-search-input" );
	autocomplete = new google.maps.places.Autocomplete( input, options );

	autocomplete.addListener( "place_changed", function() {
		place = autocomplete.getPlace();

		/**
		 * Assign the returned latlng to the autoCompleteLatLng var.
		 * This var is used when the users submits the search.
		 */
		if ( place.geometry ) {
            autoCompleteLatLng = place.geometry.location;
		}
    });
}

/**
 * Make sure that the 'Zoom here' link in the info window 
 * doesn't zoom past the max auto zoom level.
 * 
 * The 'max auto zoom level' is set on the settings page.
 *
 * @since   2.0.0
 * @returns {void}
 */
function zoomChangedListener() {
	if ( typeof wpslSettings.markerZoomTo !== "undefined" && wpslSettings.markerZoomTo == 1 ) {
		google.maps.event.addListener( map, "zoom_changed", function() {
			checkMaxZoomLevel();
		});
	}
}

/**
 * Get the correct map settings.
 *
 * @since	2.0.0
 * @param	{number} mapIndex    Number of the map
 * @returns {object} mapSettings The map settings either set through a shortcode or the default settings 
 */
function getMapSettings( mapIndex ) {
	var j, len, shortCodeVal,
		settingOptions = [ "zoomLevel", "mapType", "mapTypeControl", "mapStyle", "streetView", "scrollWheel", "controlPosition" ], 
		mapSettings	= {
			zoomLevel: wpslSettings.zoomLevel,
			mapType: wpslSettings.mapType,
			mapTypeControl: wpslSettings.mapTypeControl,
			mapStyle: wpslSettings.mapStyle,
			streetView: wpslSettings.streetView,
			scrollWheel: wpslSettings.scrollWheel,
			controlPosition: wpslSettings.controlPosition,
            gestureHandling: wpslSettings.gestureHandling
		};	

	// If there are settings that are set through the shortcode, then we use them instead of the default ones.
	if ( ( typeof window[ "wpslMap_" + mapIndex ] !== "undefined" ) && ( typeof window[ "wpslMap_" + mapIndex ].shortCode !== "undefined" ) ) {
		for ( j = 0, len = settingOptions.length; j < len; j++ ) {
			shortCodeVal = window[ "wpslMap_" + mapIndex ].shortCode[ settingOptions[j] ];
			
			// If the value is set through the shortcode, we overwrite the default value.
			if ( typeof shortCodeVal !== "undefined" ) {
				mapSettings[ settingOptions[j] ] = shortCodeVal;
			}
		}
	}

	mapSettings.startLatLng = getStartLatlng( mapIndex );

	return mapSettings;
}

/**
 * Get the latlng coordinates that are used to init the map.
 *
 * @since	2.0.0
 * @param	{number} mapIndex    Number of the map
 * @returns {object} startLatLng The latlng value where the map will initially focus on 
 */
function getStartLatlng( mapIndex ) {
	var startLatLng, latLng, 
		firstLocation = "";
	
	/* 
	 * Maps that are added with the [wpsl_map] shortcode will have the locations key set. 
	 * If it exists we use the coordinates from the first location to center the map on. 
	 */
	if ( ( typeof window[ "wpslMap_" + mapIndex ] !== "undefined" ) && ( typeof window[ "wpslMap_" + mapIndex ].locations !== "undefined" ) ) {
		firstLocation = window[ "wpslMap_" + mapIndex ].locations[0];
	}
		
	/* 
	 * Either use the coordinates from the first location as the start coordinates 
	 * or the default start point defined on the settings page.
	 * 
	 * If both are not available we set it to 0,0 
	 */	
	if ( ( typeof firstLocation !== "undefined" && typeof firstLocation.lat !== "undefined" ) && ( typeof firstLocation.lng !== "undefined" ) ) {
		startLatLng = new google.maps.LatLng( firstLocation.lat, firstLocation.lng );
	} else if ( wpslSettings.startLatlng !== "" ) {
		latLng		= wpslSettings.startLatlng.split( "," );
		startLatLng = new google.maps.LatLng( latLng[0], latLng[1] );
    } else {
		startLatLng = new google.maps.LatLng( 0,0 );
    }
		
	return startLatLng;
}

/**
 * Create a new infoWindow object.
 * 
 * Either use the default infoWindow or use the infobox library.
 * 
 * @since  2.0.0
 * @return {object} infoWindow The infoWindow object
 */
function newInfoWindow() {
	var boxClearance, boxPixelOffset, 
		infoBoxOptions = {};
	
	// Do we need to use the infobox script or use the default info windows?
	if ( ( typeof wpslSettings.infoWindowStyle !== "undefined" ) && ( wpslSettings.infoWindowStyle == "infobox" ) ) {

		// See http://google-maps-utility-library-v3.googlecode.com/svn/trunk/infobox/docs/reference.html.
		boxClearance   = wpslSettings.infoBoxClearance.split( "," );
		boxPixelOffset = wpslSettings.infoBoxPixelOffset.split( "," );
		infoBoxOptions = {
			alignBottom: true,
			boxClass: wpslSettings.infoBoxClass,
			closeBoxMargin: wpslSettings.infoBoxCloseMargin,
			closeBoxURL: wpslSettings.infoBoxCloseUrl,
			content: "",
			disableAutoPan: ( Number( wpslSettings.infoBoxDisableAutoPan ) ) ? true : false,
			enableEventPropagation: ( Number( wpslSettings.infoBoxEnableEventPropagation ) ) ? true : false,
			infoBoxClearance: new google.maps.Size( Number( boxClearance[0] ), Number( boxClearance[1] ) ),
			pixelOffset: new google.maps.Size( Number( boxPixelOffset[0] ), Number( boxPixelOffset[1] ) ),
			zIndex: Number( wpslSettings.infoBoxZindex )
		};

		infoWindow = new InfoBox( infoBoxOptions );	
	} else {
		infoWindow = new google.maps.InfoWindow();
	}

	return infoWindow;
}

/**
 * Get the required marker settings.
 * 
 * @since  2.1.0
 * @return {object} settings The marker settings.
 */
function getMarkerSettings() {
	var markerProp,
		markerProps = wpslSettings.markerIconProps,
		settings	= {};

	// Use the correct marker path.
	if ( typeof markerProps.url !== "undefined" ) {
        settings.url = markerProps.url;
    } else if ( typeof markerProps.categoryMarkerUrl !== "undefined" ) {
        settings.categoryMarkerUrl = markerProps.categoryMarkerUrl;
    } else if ( typeof markerProps.alternateMarkerUrl !== "undefined" ) {
        settings.alternateMarkerUrl = markerProps.alternateMarkerUrl;
	} else {
		settings.url = wpslSettings.url + "img/markers/";
	}

	for ( var key in markerProps ) {
		if ( markerProps.hasOwnProperty( key ) ) {
			markerProp = markerProps[key].split( "," );

			if ( markerProp.length == 2 ) {
				settings[key] = markerProp;
			}
		}
	}
	
	return settings;
}

/**
 * Check if we have a map style that we need to apply to the map.
 * 
 * @since  2.0.0
 * @param  {string} mapStyle The id of the map
 * @return {void}
 */
function maybeApplyMapStyle( mapStyle ) {
	
	// Make sure the JSON is valid before applying it as a map style.
	mapStyle = tryParseJSON( mapStyle );

	if ( mapStyle ) {
		map.setOptions({ styles: mapStyle });
	}
}

/**
 * Make sure the JSON is valid. 
 * 
 * @link   http://stackoverflow.com/a/20392392/1065294 
 * @since  2.0.0
 * @param  {string} jsonString The JSON data
 * @return {object|boolean}	The JSON string or false if it's invalid json.
 */
function tryParseJSON( jsonString ) {
	
    try {
        var o = JSON.parse( jsonString );

        /* 
		 * Handle non-exception-throwing cases:
		 * Neither JSON.parse(false) or JSON.parse(1234) throw errors, hence the type-checking,
		 * but... JSON.parse(null) returns 'null', and typeof null === "object", 
		 * so we must check for that, too.
		 */ 
        if ( o && typeof o === "object" && o !== null ) {
            return o;
        }
    }
    catch ( e ) { }

    return false;
}

/**
 * Add the start marker and call the function that inits the store search.
 *
 * @since	1.1.0
 * @param	{object} startLatLng The start coordinates
 * @param	{object} infoWindow  The infoWindow object
 * @returns {void}
 */
function showStores( startLatLng, infoWindow ) {
	addMarker( startLatLng, 0, '', true, infoWindow ); // This marker is the 'start location' marker. With a storeId of 0, no name and is draggable
	findStoreLocations( startLatLng, resetMap, autoLoad, infoWindow );
}

/**
 * Compare the current useragent to a list of known mobile useragents ( not optimal, I know ).
 *
 * @since	1.2.20
 * @returns {boolean} Whether the useragent is from a known mobile useragent or not.
 */
function checkMobileUserAgent() {
	return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test( navigator.userAgent );	
}

/**
 * Check if Geolocation detection is supported. 
 * 
 * If there is an error / timeout with determining the users 
 * location, then we use the 'start point' value from the settings 
 * as the start location through the showStores function. 
 *
 * @since	1.0.0
 * @param	{object} startLatLng The start coordinates
 * @param	{object} infoWindow  The infoWindow object
 * @returns {void}
 */
function checkGeolocation( startLatLng, infoWindow ) {
		
	if ( navigator.geolocation ) {
		var geolocationInProgress, locationTimeout,
			keepStartMarker = false,
			timeout			= Number( wpslSettings.geoLocationTimeout );
	
		// Make the direction icon flash every 600ms to indicate the geolocation attempt is in progress.
		geolocationInProgress = setInterval( function() {
			$( ".wpsl-icon-direction" ).toggleClass( "wpsl-active-icon" );
		}, 600 );

		/* 
		 * If the user doesn't approve the geolocation request within the value set in 
		 * wpslSettings.geoLocationTimeout, then the default map is loaded.
		 * 
		 * You can increase the timeout value with the wpsl_geolocation_timeout filter. 
		 */
		locationTimeout = setTimeout( function() {
			geolocationFinished( geolocationInProgress );
			showStores( startLatLng, infoWindow );
		}, timeout );

		navigator.geolocation.getCurrentPosition( function( position ) {
			geolocationFinished( geolocationInProgress );
			clearTimeout( locationTimeout );
			
			/* 
			 * If the timeout is triggerd and the user later decides to enable 
			 * the geolocation detection again, it gets messy with multiple start markers. 
			 * 
			 * So we first clear the map before adding new ones.
			 */
			deleteOverlays( keepStartMarker ); 
			handleGeolocationQuery( startLatLng, position, resetMap, infoWindow );
			
			/*
			 * Workaround for this bug in Firefox https://bugzilla.mozilla.org/show_bug.cgi?id=1283563.
			 * to keep track if the geolocation code has already run.
			 * 
			 * Otherwise after the users location is determined succesfully the code 
			 * will also detect the returned error, and triggers showStores() to 
			 * run with the start location set in the incorrect location.
			 */ 
			
			$( ".wpsl-search").addClass( "wpsl-geolocation-run" );
		}, function( error ) {

			/* 
			 * Only show the geocode errors if the user actually clicked on the direction icon. 
			 * 
			 * Otherwise if the "Attempt to auto-locate the user" option is enabled on the settings page, 
			 * and the geolocation attempt fails for whatever reason ( blocked in browser, unavailable etc ). 
			 * Then the first thing the visitor will see on pageload is an alert box, which isn't very userfriendly.
			 * 
			 * If an error occurs on pageload without the user clicking on the direction icon,
			 * the default map is shown without any alert boxes.
			 */
			if ( $( ".wpsl-icon-direction" ).hasClass( "wpsl-user-activated" ) && !$( ".wpsl-search" ).hasClass( "wpsl-geolocation-run" ) ) {
				switch ( error.code ) {
					case error.PERMISSION_DENIED:
						alert( wpslGeolocationErrors.denied );
						break;
					case error.POSITION_UNAVAILABLE:
						alert( wpslGeolocationErrors.unavailable );
						break;
					case error.TIMEOUT:
						alert( wpslGeolocationErrors.timeout );
						break;
					default:
						alert( wpslGeolocationErrors.generalError );
						break;
				}

				$( ".wpsl-icon-direction" ).removeClass( "wpsl-active-icon" );
			} else if ( !$( ".wpsl-search" ).hasClass( "wpsl-geolocation-run" ) ) {
				clearTimeout( locationTimeout );
				showStores( startLatLng, infoWindow );
			}
		},
		{ maximumAge: 60000, timeout: timeout, enableHighAccuracy: true } );
	} else {
		alert( wpslGeolocationErrors.unavailable );
		showStores( startLatLng, infoWindow );
	}
}

/**
 * Clean up after the geolocation attempt finished.
 * 
 * @since	2.0.0
 * @param	{number} geolocationInProgress
 * @returns {void}
 */
function geolocationFinished( geolocationInProgress ) {
	clearInterval( geolocationInProgress );
	$( ".wpsl-icon-direction" ).removeClass( "wpsl-active-icon" );	
}

/**
 * Handle the data returned from the Geolocation API.
 * 
 * If there is an error / timeout determining the users location,
 * then we use the 'start point' value from the settings as the start location through the showStores function. 
 *
 * @since	1.0.0
 * @param	{object}  startLatLng The start coordinates
 * @param	{object}  position    The latlng coordinates from the geolocation attempt
 * @param	{boolean} resetMap    Whether we should reset the map or not
 * @param	{object}  infoWindow  The infoWindow object
 * @returns {void}
 */
function handleGeolocationQuery( startLatLng, position, resetMap, infoWindow ) {

	if ( typeof( position ) === "undefined" ) {
		showStores( startLatLng, infoWindow );
	} else {
		var latLng = new google.maps.LatLng( position.coords.latitude, position.coords.longitude );
		
		/* 
		 * Store the latlng from the geolocation for when the user hits "reset" again 
		 * without having to ask for permission again.
		 */
        userGeolocation = {
            position: position,
			newRequest: true
		};

		map.setCenter( latLng );
		addMarker( latLng, 0, '', true, infoWindow ); // This marker is the 'start location' marker. With a storeId of 0, no name and is draggable
		findStoreLocations( latLng, resetMap, autoLoad, infoWindow );
	}
}

/**
 * Handle clicks on the store locator search button.
 * 
 * @since	1.0.0
 * @todo disable button while AJAX request still runs.
 * @param	{object} infoWindow The infoWindow object
 * @returns {void}
 */
function searchLocationBtn( infoWindow ) {
	/* GEWIJZIGD: Verwijder .unbind( "click" ) NOG NODIG?? */
	$( "#wpsl-search-btn" ).unbind( "click" ).bind( "click", function( e ) {
		$( "#wpsl-search-input" ).removeClass();

		if ( !$( "#wpsl-search-input" ).val() ) {
			$( "#wpsl-search-input" ).addClass( "wpsl-error" ).focus();
		} else {
			resetSearchResults();

			/*
             * Check if we need to geocode the user input,
             * or if autocomplete is enabled and we already
             * have the latlng values.
             */
			if ( wpslSettings.autoComplete == 1 && typeof autoCompleteLatLng !== "undefined" ) {
				prepareStoreSearch( autoCompleteLatLng, infoWindow );
			} else {
				codeAddress( infoWindow );
			}
		}

		return false;
	});
}

/**
 * Force the open InfoBox info window to close
 * 
 * This is required if the user makes a new search, 
 * or clicks on the "Directions" link.
 *
 * @since  2.0.0
 * @return {void}
 */
function closeInfoBoxWindow() {
	if ( ( typeof wpslSettings.infoWindowStyle !== "undefined" ) && ( wpslSettings.infoWindowStyle == "infobox" ) && typeof openInfoWindow[0] !== "undefined" ) {
		openInfoWindow[0].close();
	}	
}

/**
 * Add the 'reload' and 'find location' icon to the map.
 *
 * @since  2.0.0
 * @param  {object} settings   Map settings
 * @param  {object} map		   The map object
 * @param  {object} infoWindow The info window object
 * @return {void}
 */
function mapControlIcons( settings, map, infoWindow ) {

	// Once the map has finished loading include the map control button(s).
	google.maps.event.addListenerOnce( map, "tilesloaded", function() {

		// Add the html for the map controls to the map.
		$( ".gm-style" ).append( wpslSettings.mapControls );

		if ( $( ".wpsl-icon-reset, #wpsl-reset-map" ).length > 0 ) {

			// Bind the reset map button.
			resetMapBtn( settings.startLatLng, infoWindow );

			/* 
			 * Hide it to prevent users from clicking it before 
			 * the store location are placed on the map. 
			 */
			$( ".wpsl-icon-reset" ).hide();
		}

		// Bind the direction button to trigger a new geolocation request.
		$( ".wpsl-icon-direction" ).on( "click", function() {
			$( this ).addClass( "wpsl-user-activated" );
			checkGeolocation( settings.startLatLng, infoWindow );
		});
	});
}

/**
 * Handle clicks on the "Reset" button.
 * 
 * @since	1.0.0
 * @param	{object} startLatLng The start coordinates
 * @param	{object} infoWindow  The infoWindow object
 * @returns {void}
 */
function resetMapBtn( startLatLng, infoWindow ) {
	$( ".wpsl-icon-reset, #wpsl-reset-map" ).on( "click", function() {
		var keepStartMarker = false,
			resetMap	    = true;

		/* 
		 * Check if a map reset is already in progress, 
		 * if so prevent another one from starting. 
		 */
		if ( $( this ).hasClass( "wpsl-in-progress" ) ) {
			return;
		}

		/* 
		 * When the start marker is dragged the autoload value is set to false. 
		 * So we need to check the correct value when the reset button is 
		 * pushed before reloading the stores. 
		 */
		if ( wpslSettings.autoLoad == 1 ) {
			autoLoad = 1;
		}

		// Check if the latlng or zoom has changed since pageload, if so there is something to reset.
		if ( ( ( ( map.getCenter().lat() !== mapDefaults.centerLatlng.lat() ) || ( map.getCenter().lng() !== mapDefaults.centerLatlng.lng() ) || ( map.getZoom() !== mapDefaults.zoomLevel ) ) ) ) {
			deleteOverlays( keepStartMarker );

			$( "#wpsl-search-input" ).val( "" ).removeClass();

			// We use this to prevent multiple reset request.
			$( ".wpsl-icon-reset" ).addClass( "wpsl-in-progress" );

			// If marker clusters exist, remove them from the map.
			if ( markerClusterer ) {
				markerClusterer.clearMarkers();
			}

			// Remove the start marker.
			deleteStartMarker();

			// Reset the dropdown values.
			resetDropdowns();

			if ( wpslSettings.autoLocate == 1 ) {
				handleGeolocationQuery( startLatLng, userGeolocation.position, resetMap, infoWindow );
			} else {
				showStores( startLatLng, infoWindow );
			}
		}

		// Make sure the stores are shown and the direction details are hidden.
		$( "#wpsl-stores" ).show();
		$( "#wpsl-direction-details" ).hide();
	});
}

/**
 * Remove the start marker from the map.
 *
 * @since   1.2.12
 * @returns {void}
 */
function deleteStartMarker() {
	if ( ( typeof( startMarkerData ) !== "undefined" ) && ( startMarkerData !== "" ) ) {
		startMarkerData.setMap( null );
		startMarkerData = "";
	}
}

/**
 * Reset the dropdown values for the max results, 
 * and search radius after the "reset" button is triggerd.
 * 
 * @since   1.1.0
 * @returns {void}
 */
function resetDropdowns() {
	var i, arrayLength, dataValue, catText, $customDiv, $customFirstLi, customSelectedText, customSelectedData,
		defaultFilters = $( "#wpsl-wrap" ).hasClass( "wpsl-default-filters" ),
		defaultValues  = [wpslSettings.searchRadius + ' ' + wpslSettings.distanceUnit, wpslSettings.maxResults],
		dropdowns	   = ["wpsl-radius", "wpsl-results"];

	for ( i = 0, arrayLength = dropdowns.length; i < arrayLength; i++ ) {
		$( "#" + dropdowns[i] + " select" ).val( parseInt( defaultValues[i] ) );
		$( "#" + dropdowns[i] + " li" ).removeClass();

		if ( dropdowns[i] == "wpsl-radius" ) {
			dataValue = wpslSettings.searchRadius;
		} else if ( dropdowns[i] == "wpsl-results" ) {
			dataValue = wpslSettings.maxResults;
		}

		$( "#" + dropdowns[i] + " li" ).each( function() {
			if ( $( this ).text() === defaultValues[i] ) {
				$( this ).addClass( "wpsl-selected-dropdown" );

				$( "#" + dropdowns[i] + " .wpsl-selected-item" ).html( defaultValues[i] ).attr( "data-value", dataValue );
			}
		});
	}

	/** 
	 * Reset the category dropdown.
	 * @todo look for other way to do this in combination with above code. Maybe allow users to define a default cat on the settings page?
	 */
	if ( $( "#wpsl-category" ).length ) {
		$( "#wpsl-category select" ).val( 0 );
		$( "#wpsl-category li" ).removeClass();
		$( "#wpsl-category li:first-child" ).addClass( "wpsl-selected-dropdown" );

		catText = $( "#wpsl-category li:first-child" ).text();

		$( "#wpsl-category .wpsl-selected-item" ).html( catText ).attr( "data-value", 0 );
	}

	// If any custom dropdowns exist, then we reset them as well.
	if ( $( ".wpsl-custom-dropdown" ).length > 0 ) {
		$( ".wpsl-custom-dropdown" ).each( function( index ) {
			
			// Check if we are dealing with the styled dropdowns, or the default select dropdowns.
			if ( !defaultFilters ) {
				$customDiv		   = $( this ).siblings( "div" );
				$customFirstLi	   = $customDiv.find( "li:first-child" );
				customSelectedText = $customFirstLi.text();
				customSelectedData = $customFirstLi.attr( "data-value" );

				$customDiv.find( "li" ).removeClass();
				$customDiv.prev().html( customSelectedText ).attr( "data-value", customSelectedData );	
			} else {
				$( this ).find( "option" ).removeAttr( "selected" );
			}
		});
	}
}

// Handle the click on the back button when the route directions are displayed.
$( "#wpsl-result-list" ).on( "click", ".wpsl-back", function() {	
	var i, len;

    // Remove the directions from the map.
    directionsDisplay.setMap( null );

    // Restore the store markers on the map.
    for ( i = 0, len = markersArray.length; i < len; i++ ) {
		markersArray[i].setMap( map );
    }

	// Restore the start marker on the map.
	if ( ( typeof( startMarkerData ) !== "undefined" )  && ( startMarkerData !== "" ) ) {
		startMarkerData.setMap( map );
	}

	// If marker clusters are enabled, restore them.
	if ( markerClusterer ) {
		checkMarkerClusters();			
	}

	map.setCenter( directionMarkerPosition.centerLatlng );
	map.setZoom( directionMarkerPosition.zoomLevel );	

    $( ".wpsl-direction-before, .wpsl-direction-after" ).remove();
    $( "#wpsl-stores" ).show();
    $( "#wpsl-direction-details" ).hide();

    return false;
});

/**
 * Show the driving directions.
 * 
 * @since	1.1.0
 * @param	{object} e The clicked elemennt
 * @returns {void}
 */
function renderDirections( e ) {
    var i, start, end, len, storeId;
	
	// Force the open InfoBox info window to close.
	closeInfoBoxWindow();

    /* 
     * The storeId is placed on the li in the results list, 
     * but in the marker it will be on the wrapper div. So we check which one we need to target.
     */
    if ( e.parents( "li" ).length > 0 ) {
		storeId = e.parents( "li" ).data( "store-id" );
    } else {
		storeId = e.parents( ".wpsl-info-window" ).data( "store-id" );
    }
	
	// Check if we need to get the start point from a dragged marker.
	if ( ( typeof( startMarkerData ) !== "undefined" )  && ( startMarkerData !== "" ) ) {
		start = startMarkerData.getPosition();
	}
	
	// Used to restore the map back to the state it was in before the user clicked on 'directions'.
	directionMarkerPosition = {
		centerLatlng: map.getCenter(),
		zoomLevel: map.getZoom()	
	};

    // Find the latlng that belongs to the start and end point.
    for ( i = 0, len = markersArray.length; i < len; i++ ) {
		
		// Only continue if the start data is still empty or undefined.
		if ( ( markersArray[i].storeId == 0 ) && ( ( typeof( start ) === "undefined" ) || ( start === "" ) ) ) {
			start = markersArray[i].getPosition();
		} else if ( markersArray[i].storeId == storeId ) {
			end = markersArray[i].getPosition();
		}
    }
	
    if ( start && end ) {
		$( "#wpsl-direction-details ul" ).empty();
		$( ".wpsl-direction-before, .wpsl-direction-after" ).remove();
		calcRoute( start, end );
    } else {
		alert( wpslLabels.generalError );
    } 
}

/**
 * Check what effect is triggerd once a user hovers over the store list. 
 * Either bounce the corresponding marker up and down, open the info window or ignore it.
 */
if ( $( "#wpsl-gmap" ).length ) {	
	if ( wpslSettings.markerEffect == 'bounce' ) {
		$( "#wpsl-stores" ).on( "mouseenter", "li", function() {
			letsBounce( $( this ).data( "store-id" ), "start" );
		});

		$( "#wpsl-stores" ).on( "mouseleave", "li", function() {	
			letsBounce( $( this ).data( "store-id" ), "stop" );
		});
	} else if ( wpslSettings.markerEffect == 'info_window' ) {
		$( "#wpsl-stores" ).on( "mouseenter", "li", function() {
			var i, len;

			for ( i = 0, len = markersArray.length; i < len; i++ ) {
				if ( markersArray[i].storeId == $( this ).data( "store-id" ) ) {
					google.maps.event.trigger( markersArray[i], "click" );
					map.setCenter( markersArray[i].position );
				}
			}
		});	
	}
}

/**
 * Let a single marker bounce.
 * 
 * @since	1.0.0
 * @param	{number} storeId The storeId of the marker that we need to bounce on the map
 * @param	{string} status  Indicates whether we should stop or start the bouncing
 * @returns {void}
 */
function letsBounce( storeId, status ) {
    var i, len, marker;

    // Find the correct marker to bounce based on the storeId.
    for ( i = 0, len = markersArray.length; i < len; i++ ) {
		if ( markersArray[i].storeId == storeId ) {
			marker = markersArray[i];
			
			if ( status == "start" ) {
				marker.setAnimation( google.maps.Animation.BOUNCE );	
			} else {
				marker.setAnimation( null );	
			}
		}
    }	
}

/**
 * Calculate the route from the start to the end.
 * 
 * @since	1.0.0
 * @param	{object} start The latlng from the start point
 * @param	{object} end   The latlng from the end point
 * @returns {void}
 */
function calcRoute( start, end ) {
    var legs, len, step, index, direction, i, j,
		distanceUnit, directionOffset, request,
		directionStops = "";
		
	if ( wpslSettings.distanceUnit == "km" ) {
		distanceUnit = 'METRIC';
	} else {
		distanceUnit = 'IMPERIAL';
	}

	request = {
		origin: start,
		destination: end,
		travelMode: wpslSettings.directionsTravelMode,
		unitSystem: google.maps.UnitSystem[ distanceUnit ] 
	};

    directionsService.route( request, function( response, status ) {
		if ( status == google.maps.DirectionsStatus.OK ) {
			directionsDisplay.setMap( map );
			directionsDisplay.setDirections( response );

			if ( response.routes.length > 0 ) {
				direction = response.routes[0];

				// Loop over the legs and steps of the directions.
				for ( i = 0; i < direction.legs.length; i++ ) {
					legs = direction.legs[i];

					for ( j = 0, len = legs.steps.length; j < len; j++ ) {
						step = legs.steps[j];
						index = j+1;
						directionStops = directionStops + "<li><div class='wpsl-direction-index'>" + index + "</div><div class='wpsl-direction-txt'>" + step.instructions + "</div><div class='wpsl-direction-distance'>" + step.distance.text + "</div></li>";
					}
				}

				$( "#wpsl-direction-details ul" ).append( directionStops ).before( "<div class='wpsl-direction-before'><a class='wpsl-back' id='wpsl-direction-start' href='#'>" + wpslLabels.back + "</a><div><span class='wpsl-total-distance'>" + direction.legs[0].distance.text + "</span> - <span class='wpsl-total-durations'>" + direction.legs[0].duration.text + "</span></div></div>" ).after( "<p class='wpsl-direction-after'>" + response.routes[0].copyrights + "</p>" );
				$( "#wpsl-direction-details" ).show();
				
				// Remove all single markers from the map.
				for ( i = 0, len = markersArray.length; i < len; i++ ) {
					markersArray[i].setMap( null );
				}
			
				// Remove the marker clusters from the map.
				if ( markerClusterer ) {
					markerClusterer.clearMarkers();
				}			
				
				// Remove the start marker from the map.
				if ( ( typeof( startMarkerData ) !== "undefined" ) && ( startMarkerData !== "" ) ) {
					startMarkerData.setMap( null );
				}

				$( "#wpsl-stores" ).hide();		
								
				// Make sure the start of the route directions are visible if the store listings are shown below the map.				
				if ( wpslSettings.templateId == 1 ) {
					directionOffset = $( "#wpsl-gmap" ).offset();
					$( window ).scrollTop( directionOffset.top );
				}
			}
		} else {
			directionErrors( status );
		}
    });
}

/**
 * Geocode the user input.
 * 
 * @since	1.0.0
 * @param	{object} infoWindow The infoWindow object
 * @returns {void}
 */
function codeAddress( infoWindow ) {
    var latLng, request = {};

    // Check if we need to set the geocode component restrictions.
	if ( typeof wpslSettings.geocodeComponents !== "undefined" && !$.isEmptyObject( wpslSettings.geocodeComponents ) ) {
		request.componentRestrictions = wpslSettings.geocodeComponents;

		if ( typeof request.componentRestrictions.postalCode !== "undefined" ) {
            request.componentRestrictions.postalCode = $( "#wpsl-search-input" ).val();
			
			// GEWIJZIGD: Af en toe lijken er bij Google tijdelijk problemen op te duiken met de Geocoding API NOG NODIG??
			// Postcodes worden daardoor niet correct vertaald te worden naar locaties, ook al beperken we ons tot België
			// Problematische postcodes (2020): 1500, 1540, 1541, 1570, 1600, 1620, 1630, 1640, 1650, 1671, 1673, 1700, 1701, 1730, 1740, 1741, 1745, 1755, 1760, 1770, 1790, 1800, 1818, 1830, 1840, 1850, 1852, 1860, 1861, 1880, 1931, 1933, 1934, 1950, 1970, 1980, 1981, 2000, 2018, 2020, 2242, 3321, 3501, 3724
			// Opgelet: switch vergelijkt case strict in JavaScript!
			switch( request.componentRestrictions.postalCode ) {
				case '2000':
					request.componentRestrictions = { country:"BE", locality:"Antwerpen" };
					break;
				case '3130':
					request.componentRestrictions = { country:"BE", locality:"Begijnendijk" };
					break;
				default:
					console.log("No postal code fixing necessary");
			}
        } else {
            request.address = $( "#wpsl-search-input" ).val();
		}
	} else {
        request.address = $( "#wpsl-search-input" ).val();
	}

    geocoder.geocode( request, function( response, status ) {
		if ( status == google.maps.GeocoderStatus.OK ) {

			if ( statistics.enabled ) {
				collectStatsData( response );
			}

			latLng = response[0].geometry.location;

            prepareStoreSearch( latLng, infoWindow );
		} else {
			geocodeErrors( status );
		}
    });
}

/**
 * Prepare a new location search.
 * 
 * @since	2.2.0
 * @param	{object} latLng 	The coordinates
 * @param	{object} infoWindow The infoWindow object.
 * @returns {void}
 */
function prepareStoreSearch( latLng, infoWindow ) {
	var autoLoad = false;

	// Add a new start marker.
	addMarker( latLng, 0, '', true, infoWindow );

	// Try to find stores that match the radius, location criteria.
	findStoreLocations( latLng, resetMap, autoLoad, infoWindow );
}

/**
 * Reverse geocode the passed coordinates and set the returned zipcode in the input field.
 *
 * @since	1.0.0
 * @param	{object} latLng The coordinates of the location that should be reverse geocoded
 * @returns {object} response The address components if the stats add-on is active.
 */
function reverseGeocode( latLng, callback ) {
    var userLocation,
		lat = latLng.lat().toFixed( 5 ),
		lng = latLng.lng().toFixed( 5 );

    latLng.lat = function() {
        return parseFloat( lat );
    };

    latLng.lng = function() {
        return parseFloat( lng );
    };

    geocoder.geocode( {'latLng': latLng }, function( response, status ) {
        if ( status == google.maps.GeocoderStatus.OK ) {

			if ( wpslSettings.autoLocate == 1 && userGeolocation.newRequest ) {
                userLocation = filterApiResponse( response );

				if ( userLocation !== "" ) {
					$( "#wpsl-search-input" ).val( userLocation );
				}

                /*
                 * Prevent the zip from being placed in the input field
                 * again after the users location is determined.
                 */
                userGeolocation.newRequest = false;
			}

            if ( wpslSettings.directionRedirect ) {
                startAddress = response[0].formatted_address;
            }

            // Prevent it from running on autoload when the input field is empty.
            if ( statistics.enabled && $( "#wpsl-search-input" ).val().length > 0 ) {
                if ( $.isEmptyObject( statistics.addressComponents ) ) {
                    collectStatsData( response );
                }
            }

            callback();
		} else {
			geocodeErrors( status );
		}
	});
}

/**
 * Collect the data for the statistics
 * add-on from the Google Geocode API.
 *
 * @since 2.2.18
 * @param response
 * @returns {void}
 */
function collectStatsData( response ) {
	var requiredFields, addressLength, responseType,
        countryCode, responseLength,
        missingFields = {},
        statsData 	   = {};

    countryCode = findCountryCode( response );

    /**
     * The UK is a special case how the city / town / region / country data
     * is structured in the Geocode API response. So we adjust the structure a bit.
     *
     * We later check which field contained the city / town data
     * and if necessary later move it to the correct one.
     */
    if ( countryCode == "GB" ) {
        requiredFields = {
            'city': 'postal_town',
            'city_locality': 'locality,political',
            'region': 'administrative_area_level_2,political',
            'country': 'administrative_area_level_1,political'
        };
    } else {
        requiredFields = {
            'city': 'locality,political',
            'region': 'administrative_area_level_1,political',
            'country': 'country,political'
        };
    }

    addressLength = response[0].address_components.length;

    // Loop over the first row in the API response.
    for ( i = 0; i < addressLength; i++ ) {
        responseType = response[0].address_components[i].types;

        for ( var key in requiredFields ) {
            if ( requiredFields[key] == responseType.join( "," ) ) {

                // In rare cases the long name is empty.
                if ( response[0].address_components[i].long_name.length > 0 ) {
                    statsData[key] = response[0].address_components[i].long_name;
                } else {
                    statsData[key] = response[0].address_components[i].short_name;
                }
            }
        }
    }

    /**
     * Check if we have the required fields. This is often the case after
     * grabbing the data from the first row, but in some cases we have to loop
     * through all the data to get all the required data.
     */
    for ( var key in requiredFields ) {
        if ( typeof statsData[key] === "undefined" ) {
            missingFields[key] = requiredFields[key];
        }
    }

    /**
     * In the UK the data we want is most of the time in the
     * postal_town ( city ) field, which is often set on the first row.
     *
     * If this field contains data then don't continue and ignore
     * the missing data in the locality field, which is more of a
     * backup in case the 'postal_town' is missing in the API response.
     */
    if ( countryCode == "GB" ) {
        if ( typeof missingFields.city_locality !== "undefined" && typeof missingFields.city === "undefined" ) {
            missingFields = {};
        }
    }

    /**
     * If one or more required fields are missing,
     * then loop through the remaining API data.
     */
    if ( Object.keys( missingFields ).length > 0 ) {
        responseLength = response.length;

        /**
         * Loop over the remaining API results,
         * but skip the first row since we already checked that one.
         */
        for ( i = 1; i < responseLength; i++ ) {
            addressLength = response[i].address_components.length;

            for ( j = 0; j < addressLength; j++ ) {
                responseType = response[i].address_components[j].types;

                for ( var key in missingFields ) {
                    if ( requiredFields[key] == responseType.join( "," ) ) {
                        statsData[key] = response[i].address_components[j].long_name;
                    }
                }
            }
        }
    }

    /**
     * In rare cases, and as far I know this only happens in the UK, the city / town name
     * is often set in the 'postal_town' ( city ) field in the Google API response.
     *
     * But in some cases the 'locality,political' ( city_locality ) field is also
     * set in the first row ( where it's located for locations in the rest of the world ).
     *
     * When both fields are set the 'locality,political' ( city_locality ) will contain more
     * accurate details, so we copy it's value back to the city field.
     */
    if ( typeof statsData.city_locality !== "undefined" && statsData.city_locality.length > 0 ) {
        statsData.city = statsData.city_locality;

        delete statsData.city_locality;
    }

    statistics.addressComponents = statsData;
}

/**
 * Grab the country name from the API response.
 *
 * @since 2.2.18
 * @param {object}  response 	 The API response
 * @return {string} countryCode The country code found in the API response.
 */
function findCountryCode( response ) {
	var responseType, countryCode = '';

    $.each( response[0].address_components, function( index ) {
        responseType = response[0].address_components[index].types;

        if ( responseType.join( ',' ) == 'country,political' ) {
            countryCode = response[0].address_components[index].short_name;

            return false;
        }
    });

	return countryCode;
}

/**
 * Filter out the zip / city name from the API response
 *
 * @since	1.0.0
 * @param	{object} response 	   The complete Google API response
 * @returns {string} userLocation Either the users zip / city name the user is located in
 */
function filterApiResponse( response ) {
    var i, j, responseType, addressLength, userLocation, filteredData = {},
		responseLength = response.length;

	for ( i = 0; i < responseLength; i++ ) {
		addressLength = response[i].address_components.length;

		for ( j = 0; j < addressLength; j++ ) {
			responseType = response[i].address_components[j].types;

			if ( ( /^postal_code$/.test( responseType ) ) || ( /^postal_code,postal_code_prefix$/.test( responseType ) ) ) {
				filteredData.zip = response[i].address_components[j].long_name;

				break;
			}

			if ( /^locality,political$/.test( responseType ) ) {
				filteredData.locality = response[i].address_components[j].long_name;
			}
		}

		if ( typeof filteredData.zip !== "undefined" ) {
			break;
		}
	}

	// If no zip code was found ( it's rare, but it happens ), then we use the city / town name as backup.
	if ( typeof filteredData.zip === "undefined" && typeof filteredData.locality !== "undefined" ) {
		userLocation = filteredData.locality;
	} else {
		userLocation = filteredData.zip;
	}

    return userLocation;
}

/**
 * Call the function to make the ajax request to load the store locations. 
 * 
 * If we need to show the driving directions on maps.google.com itself, 
 * we first need to geocode the start latlng into a formatted address.
 * 
 * @since	1.0.0
 * @param	{object}  startLatLng The coordinates
 * @param	{boolean} resetMap    Whether we should reset the map or not
 * @param	{string}  autoLoad    Check if we need to autoload all the stores
 * @param	{object}  infoWindow  The infoWindow object
 * @returns {void}
 */
function findStoreLocations( startLatLng, resetMap, autoLoad, infoWindow ) {

	if ( wpslSettings.directionRedirect == 1 || statistics.enabled ) {
        reverseGeocode( startLatLng, function() {
			makeAjaxRequest( startLatLng, resetMap, autoLoad, infoWindow );
		});
	} else {
		makeAjaxRequest( startLatLng, resetMap, autoLoad, infoWindow );
	}
}

/**
 * Make the AJAX request to load the store data.
 * 
 * @since	1.2.0
 * @param	{object}  startLatLng The latlng used as the starting point
 * @param	{boolean} resetMap    Whether we should reset the map or not
 * @param	{string}  autoLoad    Check if we need to autoload all the stores
 * @param	{object}  infoWindow  The infoWindow object
 * @returns {void}
 */
function makeAjaxRequest( startLatLng, resetMap, autoLoad, infoWindow ) {
	var latLng, noResultsMsg, ajaxData,
		storeData  = "",
		draggable  = false,
		template   = $( "#wpsl-listing-template" ).html(),
		$storeList = $( "#wpsl-stores ul" ),
		preloader  = wpslSettings.url + "img/ajax-loader.gif";

	ajaxData = collectAjaxData( startLatLng, resetMap, autoLoad );

    // Add the preloader.
	$storeList.empty().append( "<li class='wpsl-preloader'><img src='" + preloader + "'/>" + wpslLabels.preloader + "</li>" );

    $( "#wpsl-wrap" ).removeClass( "wpsl-no-results" );
		
	$.get( wpslSettings.ajaxurl, ajaxData, function( response ) {

	    // Remove the preloaders and no results msg.
        $( ".wpsl-preloader" ).remove();

		if ( response.length > 0 && typeof response.addon == "undefined" ) {

			// Loop over the returned locations.
			$.each( response, function( index ) {
				_.extend( response[index], templateHelpers ); 

				// Add the location maker to the map.
				latLng = new google.maps.LatLng( response[index].lat, response[index].lng );	
				addMarker( latLng, response[index].id, response[index], draggable, infoWindow );	

				// Create the HTML output with help from underscore js.
				storeData = storeData + _.template( template )( response[index] );
			});

			$( "#wpsl-result-list" ).off( "click", ".wpsl-directions" );

			// Remove the old search results.
			$storeList.empty();

			// Add the html for the store listing to the <ul>.
			$storeList.append( storeData );

			$( "#wpsl-result-list" ).on( "click", ".wpsl-directions", function() {

				// Check if we need to render the direction on the map.
				if ( wpslSettings.directionRedirect != 1 ) {
					renderDirections( $( this ) );

					return false;
				}
			});

			// Do we need to create a marker cluster?
			checkMarkerClusters();

			$( "#wpsl-result-list p:empty" ).remove();
		} else {
			addMarker( startLatLng, 0, '', true, infoWindow );
			
			noResultsMsg = getNoResultsMsg();

			$( "#wpsl-wrap" ).addClass( "wpsl-no-results" );
			
			$storeList.html( "<li class='wpsl-no-results-msg'>" + noResultsMsg + "</li>" );
		}
		
		/*
		 * Do we need to adjust the zoom level so that all the markers fit in the viewport,
		 * or just center the map on the start marker.
		 */
        if ( wpslSettings.runFitBounds == 1 ) {
            fitBounds();
		} else {
            map.setZoom( Number( wpslSettings.zoomLevel ) );
            map.setCenter( markersArray[0].position );
        }
		
		/*
		 * Store the default zoom and latlng values the first time 
		 * all the stores are added to the map.
		 * 
		 * This way when a user clicks the reset button we can check if the 
		 * zoom/latlng values have changed, and if they have, then we know we 
		 * need to reload the map.
		 */
		if ( wpslSettings.resetMap == 1 ) {
			if ( $.isEmptyObject( mapDefaults ) ) {
				google.maps.event.addListenerOnce( map, "tilesloaded", function() {
					mapDefaults = {
						centerLatlng: map.getCenter(),
						zoomLevel: map.getZoom()
					};
															
					/*
					 * Because the reset icon exists, we need to adjust 
					 * the styling of the direction icon. 
					 */
					$( "#wpsl-map-controls" ).addClass( "wpsl-reset-exists" );

					/*
					 * The reset initialy is set to hidden to prevent 
					 * users from clicking it before the map is loaded. 
					 */
					$( ".wpsl-icon-reset, #wpsl-reset-map" ).show();
				});
			}
			
			$( ".wpsl-icon-reset" ).removeClass( "wpsl-in-progress" );
		}
	});	
	
	// Move the mousecursor to the store search field if the focus option is enabled.
	if ( wpslSettings.mouseFocus == 1 && !checkMobileUserAgent() ) {
		$( "#wpsl-search-input" ).focus();
	}
}

/**
 * Collect the data we need to include in the AJAX request.
 * 
 * @since	2.2.0
 * @param	{object}  startLatLng The latlng used as the starting point
 * @param	{boolean} resetMap    Whether we should reset the map or not
 * @param	{string}  autoLoad    Check if we need to autoload all the stores
 * @returns {object}  ajaxData	  The collected data.
 */
function collectAjaxData( startLatLng, resetMap, autoLoad ) {
	var maxResult, radius, customDropdownName, customDropdownValue,
        customCheckboxName,
		categoryId	   = "",
		isMobile	   = $( "#wpsl-wrap" ).hasClass( "wpsl-mobile" ),
		defaultFilters = $( "#wpsl-wrap" ).hasClass( "wpsl-default-filters" ),
		ajaxData = {
			action: "store_search",
			lat: startLatLng.lat(),
			lng: startLatLng.lng()
		};
	
	/* 
	 * If we reset the map we use the default dropdown values instead of the selected values. 
	 * Otherwise we first make sure the filter val is valid before including the radius / max_results param
	 */
	if ( resetMap ) {
		ajaxData.max_results   = wpslSettings.maxResults;
		ajaxData.search_radius = wpslSettings.searchRadius;
	} else {
		if ( isMobile || defaultFilters ) {
			maxResult = parseInt( $( "#wpsl-results .wpsl-dropdown" ).val() );
			radius 	  = parseInt( $( "#wpsl-radius .wpsl-dropdown" ).val() );
		} else {
			maxResult = parseInt( $( "#wpsl-results .wpsl-selected-item" ).attr( "data-value" ) );
			radius    = parseInt( $( "#wpsl-radius .wpsl-selected-item" ).attr( "data-value" ) );
		}
		
		// If the max results or radius filter values are NaN, then we use the default value.
		if ( isNaN( maxResult ) ) {
			ajaxData.max_results = wpslSettings.maxResults;
		} else {
			ajaxData.max_results = maxResult;
		}
		
		if ( isNaN( radius ) ) {
			ajaxData.search_radius = wpslSettings.searchRadius;
		} else {
			ajaxData.search_radius = radius;
		}
		
		/* 
		 * If category ids are set through the wpsl shortcode, then we always need to include them.
		 * Otherwise check if the category dropdown exist, or if the checkboxes are used.
		 */
		if ( typeof wpslSettings.categoryIds !== "undefined" ) {
			ajaxData.filter = wpslSettings.categoryIds;
		} else if ( $( "#wpsl-category" ).length > 0 ) {
			if ( isMobile || defaultFilters ) {
				categoryId = parseInt( $( "#wpsl-category .wpsl-dropdown" ).val() );
			} else {
				categoryId = parseInt( $( "#wpsl-category .wpsl-selected-item" ).attr( "data-value" ) );				
			}

			if ( ( !isNaN( categoryId ) && ( categoryId !== 0 ) ) )  {
				ajaxData.filter = categoryId;
			}
		} else if ( $( "#wpsl-checkbox-filter" ).length > 0 ) {
			if ( $( "#wpsl-checkbox-filter input:checked" ).length > 0 ) {
				ajaxData.filter = getCheckboxIds();
			}
		}

		// Include values from custom dropdowns.
		if ( $( ".wpsl-custom-dropdown" ).length > 0 ) {
			$( ".wpsl-custom-dropdown" ).each( function( index ) {
				customDropdownName  = '';
				customDropdownValue = '';

				if ( isMobile || defaultFilters ) {
					customDropdownName  = $( this ).attr( "name" );
					customDropdownValue = $( this ).val();
				} else {
					customDropdownName  = $( this ).attr( "name" );
					customDropdownValue = $( this ).next( ".wpsl-selected-item" ).attr( "data-value" );
				}

				if ( customDropdownName && customDropdownValue ) {
					ajaxData[customDropdownName] = customDropdownValue;
				}
			});	
		}

		// Include values from custom checkboxes
        if ( $( ".wpsl-custom-checkboxes" ).length > 0 ) {
            $( ".wpsl-custom-checkboxes" ).each( function( index ) {
				customCheckboxName = $( this ).attr( "data-name" );

                if ( customCheckboxName ) {
                    ajaxData[customCheckboxName] = getCustomCheckboxValue( customCheckboxName );
                }
			});
        }
	}

   /*
	* If the autoload option is enabled, then we need to check if the included latlng 
	* is based on a geolocation attempt before including the autoload param.
	* 
	* Because if both the geolocation and autoload options are enabled, 
	* and the geolocation attempt was successful, then we need to to include
	* the skip_cache param. 
	* 
	* This makes sure the results don't come from an older transient based on the 
	* start location from the settings page, instead of the users actual location. 
	*/
    if ( autoLoad == 1 ) {
		if ( typeof userGeolocation.position !== "undefined" ) {
			ajaxData.skip_cache = 1;
		} else {
			ajaxData.autoload = 1;
			
			/* 
			 * If the user set the 'category' attr on the wpsl shortcode, then include the cat ids 
			 * to make sure only locations from the set categories are loaded on autoload.
			 */
			if ( typeof wpslSettings.categoryIds !== "undefined" ) {
				ajaxData.filter = wpslSettings.categoryIds;
			}
		}
	}
	
	// If the collection of statistics is enabled, then we include the searched value.
	if ( statistics.enabled && autoLoad == 0 ) {
		ajaxData.search = $( "#wpsl-search-input" ).val();
        ajaxData.statistics = statistics.addressComponents;
    }
	
	return ajaxData;
}

/**
 * Get custom checkbox values by data-name group.
 *
 * If multiple selection are made, then the returned
 * values are comma separated
 *
 * @since  2.2.8
 * @param  {string} customCheckboxName The data-name value of the custom checkbox
 * @return {string} customValue		   The collected checkbox values separated by a comma
 */
function getCustomCheckboxValue( customCheckboxName ) {
	var dataName    = $( "[data-name=" + customCheckboxName + "]" ),
		customValue = [];

	$( dataName ).find( "input:checked" ).each( function( index ) {
        customValue.push( $( this ).val() );
	});

	return customValue.join();
}

/**
 * Check which no results msg we need to show. 
 * 
 * Either the default txt or a longer custom msg.
 * 
 * @since  2.2.0
 * @return string noResults The no results msg to show.
 */
function getNoResultsMsg() {
	var noResults;
	
	if ( typeof wpslSettings.noResults !== "undefined" && wpslSettings.noResults !== "" ) {
		noResults = wpslSettings.noResults;
	} else {
		noResults = wpslLabels.noResults;
	}
	
	return noResults;
}

/**
 * Collect the ids of the checked checkboxes.
 * 
 * @since  2.2.0
 * @return string catIds The cat ids from the checkboxes.
 */
function getCheckboxIds() {
	var catIds = $( "#wpsl-checkbox-filter input:checked" ).map( function() {
		return $( this ).val();
	});
	
	catIds = catIds.get();
	catIds = catIds.join(',');
	
	return catIds;
}

/**
 * Check if cluster markers are enabled.
 * If so, init the marker clustering with the 
 * correct gridsize and max zoom.
 * 
 * @since  1.2.20
 * @return {void}
 */
function checkMarkerClusters() {
	if ( wpslSettings.markerClusters == 1 ) {
		var markers, markersArrayNoStart,
			clusterZoom = Number( wpslSettings.clusterZoom ),
			clusterSize = Number( wpslSettings.clusterSize );

		if ( isNaN( clusterZoom ) ) {
			clusterZoom = "";
		}
		
		if ( isNaN( clusterSize ) ) {
			clusterSize = "";
		}

        /*
         * Remove the start location marker from the cluster so the location
         * count represents the actual returned locations, and not +1 for the start location.
         */
		if ( typeof wpslSettings.excludeStartFromCluster !== "undefined" && wpslSettings.excludeStartFromCluster == 1 ) {
            markersArrayNoStart = markersArray.slice( 0 );
            markersArrayNoStart.splice( 0,1 );
        }

        markers = ( typeof markersArrayNoStart === "undefined" ) ? markersArray : markersArrayNoStart;

        markerClusterer = new MarkerClusterer( map, markers, {
			gridSize: clusterSize,
			maxZoom: clusterZoom
		});
	}
}

/**
 * Add a new marker to the map based on the provided location (latlng).
 * 
 * @since  1.0.0
 * @param  {object}  latLng		    The coordinates
 * @param  {number}  storeId		The store id
 * @param  {object}  infoWindowData The data we need to show in the info window
 * @param  {boolean} draggable      Should the marker be draggable
 * @param  {object}  infoWindow     The infoWindow object
 * @return {void}
 */
function addMarker( latLng, storeId, infoWindowData, draggable, infoWindow ) {
	var url, mapIcon, marker,
		keepStartMarker = true;

    if ( storeId === 0 ) {
        infoWindowData = {
            store: wpslLabels.startPoint
        };

        url = markerSettings.url + wpslSettings.startMarker;
    } else if ( typeof infoWindowData.alternateMarkerUrl !== "undefined" && infoWindowData.alternateMarkerUrl ) {
		url = infoWindowData.alternateMarkerUrl;
	} else if ( typeof infoWindowData.categoryMarkerUrl !== "undefined" && infoWindowData.categoryMarkerUrl ) {
		url = infoWindowData.categoryMarkerUrl;
	} else {
		url = markerSettings.url + wpslSettings.storeMarker;
	}

	mapIcon = {
		url: url,
		scaledSize: new google.maps.Size( Number( markerSettings.scaledSize[0] ), Number( markerSettings.scaledSize[1] ) ), //retina format
		origin: new google.maps.Point( Number( markerSettings.origin[0] ), Number( markerSettings.origin[1] ) ),
		anchor: new google.maps.Point( Number( markerSettings.anchor[0] ), Number( markerSettings.anchor[1] ) )
	};

    marker = new google.maps.Marker({
		position: latLng,
		map: map,
		optimized: false, //fixes markers flashing while bouncing
		title: decodeHtmlEntity( infoWindowData.store ),
		draggable: draggable,
		storeId: storeId,
		icon: mapIcon
	});	

	// Store the marker for later use.
	markersArray.push( marker );

    google.maps.event.addListener( marker, "click",( function( currentMap ) {
		return function() {
			
			// The start marker will have a store id of 0, all others won't.
			if ( storeId != 0 ) {

				// Check if streetview is available at the clicked location.
				if ( typeof wpslSettings.markerStreetView !== "undefined" && wpslSettings.markerStreetView == 1 ) {
					checkStreetViewStatus( latLng, function() {
						setInfoWindowContent( marker, createInfoWindowHtml( infoWindowData ), infoWindow, currentMap );
					});
				} else {
					setInfoWindowContent( marker, createInfoWindowHtml( infoWindowData ), infoWindow, currentMap );
				}
			} else {
				setInfoWindowContent( marker, wpslLabels.startPoint, infoWindow, currentMap );
			}

			google.maps.event.clearListeners( infoWindow, "domready" );
			
			google.maps.event.addListener( infoWindow, "domready", function() {
				infoWindowClickActions( marker, currentMap );
				checkMaxZoomLevel();
			});
		};
    }( map ) ) );
	
	// Only the start marker will be draggable.
	if ( draggable ) {
		google.maps.event.addListener( marker, "dragend", function( event ) {
			deleteOverlays( keepStartMarker );
			map.setCenter( event.latLng );
			reverseGeocode( event.latLng );
			findStoreLocations( event.latLng, resetMap, autoLoad = false, infoWindow );
		}); 
    }
}

/**
 * Decode HTML entities.
 * 
 * @link	https://gist.github.com/CatTail/4174511
 * @since	2.0.4
 * @param	{string} str The string to decode.
 * @returns {string} The string with the decoded HTML entities.
 */
function decodeHtmlEntity( str ) {
	if ( str ) {
		return str.replace( /&#(\d+);/g, function( match, dec) {
			return String.fromCharCode( dec );
		});
	}
};

// Check if we are using both the infobox for the info windows and have marker clusters.
if ( typeof wpslSettings.infoWindowStyle !== "undefined" && wpslSettings.infoWindowStyle == "infobox" && wpslSettings.markerClusters == 1 ) {
	var clusters, clusterLen, markerLen, i, j;
	
	/* 
	 * We need to listen to both zoom_changed and idle. 
	 * 
	 * If the zoom level changes, then the marker clusters either merges nearby 
	 * markers, or changes into individual markers. Which is the moment we 
	 * either show or hide the opened info window.
	 * 
	 * "idle" is necessary to make sure the getClusters() is up 
	 * to date with the correct cluster data.
	 */
	google.maps.event.addListener( map, "zoom_changed", function() {
		google.maps.event.addListenerOnce( map, "idle", function() {

			if ( typeof markerClusterer !== "undefined" ) {		
				clusters = markerClusterer.clusters_;

				if ( clusters.length ) {
					for ( i = 0, clusterLen = clusters.length; i < clusterLen; i++ ) {
						for ( j = 0, markerLen = clusters[i].markers_.length; j < markerLen; j++ ) {

							/* 
							 * Match the storeId from the cluster marker with the 
							 * marker id that was set when the info window was opened 
							 */
							if ( clusters[i].markers_[j].storeId == activeWindowMarkerId ) {

								/* 
								 * If there is a visible info window, but the markers_[j].map is null ( hidden ) 
								 * it means the info window belongs to a marker that is part of a marker cluster.
								 * 
								 * If that is the case then we hide the info window ( the individual marker isn't visible ).
								 *
								 * The default info window script handles this automatically, but the
								 * infobox library in combination with the marker clusters doesn't.
								 */
								if ( infoWindow.getVisible() && clusters[i].markers_[j].map === null ) {
									infoWindow.setVisible( false );
								} else if ( !infoWindow.getVisible() && clusters[i].markers_[j].map !== null ) {
									infoWindow.setVisible( true );
								}

								break;
							}
						}
					}
				}
			}
		});
	});
}

/**
 * Set the correct info window content for the marker.
 * 
 * @since	1.2.20
 * @param	{object} marker			   Marker data
 * @param	{string} infoWindowContent The infoWindow content
 * @param	{object} infoWindow		   The infoWindow object
 * @param	{object} currentMap		   The map object
 * @returns {void}
 */
function setInfoWindowContent( marker, infoWindowContent, infoWindow, currentMap ) {
	openInfoWindow.length = 0;
	
	infoWindow.setContent( infoWindowContent );
	infoWindow.open( currentMap, marker );
	
	openInfoWindow.push( infoWindow );

	/* 
	 * Store the marker id if both the marker clusters and the infobox are enabled.
	 * 
	 * With the normal info window script the info window is automatically closed 
	 * once a user zooms out, and the marker clusters are enabled, 
	 * but this doesn't happen with the infobox library. 
	 * 
	 * So we need to show/hide it manually when the user zooms out, 
	 * and for this to work we need to know which marker to target. 
	 */
	if ( typeof wpslSettings.infoWindowStyle !== "undefined" && wpslSettings.infoWindowStyle == "infobox" && wpslSettings.markerClusters == 1 ) {
		activeWindowMarkerId = marker.storeId;
		infoWindow.setVisible( true );
	}
}

/**
 * Handle clicks for the different info window actions like, 
 * direction, streetview and zoom here.
 * 
 * @since	1.2.20
 * @param	{object} marker		Holds the marker data
 * @param	{object} currentMap	The map object
 * @returns {void}
 */
function infoWindowClickActions( marker, currentMap ) {
	$( ".wpsl-info-actions a" ).on( "click", function( e ) {
		var maxZoom = Number( wpslSettings.autoZoomLevel );

		e.stopImmediatePropagation();
				
		if ( $( this ).hasClass( "wpsl-directions" ) ) {

			/* 
			 * Check if we need to show the direction on the map
			 * or send the users to maps.google.com 
			 */
			if ( wpslSettings.directionRedirect == 1 ) {
				return true;
			} else {
				renderDirections( $( this ) );
			}
		} else if ( $( this ).hasClass( "wpsl-streetview" ) ) {
			activateStreetView( marker, currentMap );
		} else if ( $( this ).hasClass( "wpsl-zoom-here" ) ) {
			currentMap.setCenter( marker.getPosition() );
			currentMap.setZoom( maxZoom );
		}
		
		return false;
	});
}

/**
 * Check if have reached the max auto zoom level.
 * 
 * If so we hide the 'Zoom here' text in the info window, 
 * otherwise we show it.
 * 
 * @since	2.0.0
 * @returns {void}
 */
function checkMaxZoomLevel() {
	var zoomLevel = map.getZoom();

	if ( zoomLevel >= wpslSettings.autoZoomLevel ) {
		$( ".wpsl-zoom-here" ).hide();
	} else {
		$( ".wpsl-zoom-here" ).show();
	}	
}
	
/**
 * Activate streetview for the clicked location.
 * 
 * @since	1.2.20
 * @param	{object} marker	    The current marker
 * @param	{object} currentMap The map object
 * @returns {void}
 */
function activateStreetView( marker, currentMap ) {
	var panorama = currentMap.getStreetView();
		panorama.setPosition( marker.getPosition() );
		panorama.setVisible( true );
				
	$( "#wpsl-map-controls" ).hide();
		
	StreetViewListener( panorama, currentMap );
}

/**
 * Listen for changes in the streetview visibility.
 * 
 * Sometimes the infowindow offset is incorrect after switching back from streetview.
 * We fix this by zooming in and out. If someone has a better fix, then let me know at
 * info at tijmensmit.com
 * 
 * @since	1.2.20
 * @param	{object} panorama   The streetview object
 * @param	{object} currentMap The map object
 * @returns {void}
 */
function StreetViewListener( panorama, currentMap ) {
	google.maps.event.addListener( panorama, "visible_changed", function() {
		if ( !panorama.getVisible() ) {
			var currentZoomLevel = currentMap.getZoom();
			
			$( "#wpsl-map-controls" ).show();
			
			currentMap.setZoom( currentZoomLevel-1 );
			currentMap.setZoom( currentZoomLevel );
		}
	});
}

/**
 * Check the streetview status.
 * 
 * Make sure that a streetview exists for 
 * the latlng for the open info window.
 * 
 * @since	1.2.20
 * @param	{object}   latLng The latlng coordinates
 * @param	{callback} callback
 * @returns {void}
 */
function checkStreetViewStatus( latLng, callback ) {
	var service = new google.maps.StreetViewService();

	service.getPanoramaByLocation( latLng, 50, function( result, status ) {
		streetViewAvailable = ( status == google.maps.StreetViewStatus.OK ) ? true : false;	
		callback();
	});
}

/**
 * Helper methods for the underscore templates.
 * 
 * @link	 http://underscorejs.org/#template
 * @requires underscore.js
 * @todo move it to another JS file to make it accessible for add-ons?
 * @since	 2.0.0
 */
var templateHelpers = {
	/**
	 * Make the phone number clickable if we are dealing with a mobile useragent.
	 * 
	 * @since	1.2.20
	 * @param	{string} phoneNumber The phone number
	 * @returns {string} phoneNumber Either just the plain number, or with a link wrapped around it with tel:
	 */
	formatPhoneNumber: function( phoneNumber ) {
		if ( ( wpslSettings.phoneUrl == 1 ) && ( checkMobileUserAgent() ) || wpslSettings.clickableDetails == 1 ) {
			phoneNumber = "<a href='tel:" + templateHelpers.formatClickablePhoneNumber( phoneNumber ) + "'>" + phoneNumber + "</a>";
		}

		return phoneNumber;
	},
	/**
	 * Replace spaces - . and () from phone numbers. 
	 * Also if the number starts with a + we check for a (0) and remove it.
	 * 
	 * @since	1.2.20
	 * @param	{string} phoneNumber The phone number
	 * @returns {string} phoneNumber The 'cleaned' number
	 */
	formatClickablePhoneNumber: function( phoneNumber ) {
		if ( ( phoneNumber.indexOf( "+" ) != -1 ) && ( phoneNumber.indexOf( "(0)" ) != -1 ) ) {
			phoneNumber = phoneNumber.replace( "(0)", "" );
		}

		return phoneNumber.replace( /(-| |\(|\)|\.|)/g, "" );	
	},
    /**
	 * Check if we need to make the email address clickable.
	 *
	 * @since 2.2.13
     * @param   {string} email The email address
	 * @returns {string} email Either the normal email address, or the clickable version.
     */
	formatEmail: function( email ) {
        if ( wpslSettings.clickableDetails == 1 ) {
            email = "<a href='mailto:" + email + "'>" + email + "</a>";
        }

		return email;
	},
	/**
	 * Create the html for the info window action.
	 * 
	 * @since	2.0.0
	 * @param	{string} id		The store id
	 * @returns {string} output The html for the info window actions
	 */
	createInfoWindowActions: function( id ) {
		var output, 
			streetView = "",
			zoomTo	   = "";

		if ( $( "#wpsl-gmap" ).length ) {
			if ( streetViewAvailable ) {
				streetView = "<a class='wpsl-streetview' href='#'>" + wpslLabels.streetView + "</a>";
			}

			if ( wpslSettings.markerZoomTo == 1 ) {
				zoomTo = "<a class='wpsl-zoom-here' href='#'>" + wpslLabels.zoomHere + "</a>";
			}

			output = "<div class='wpsl-info-actions'>" + templateHelpers.createDirectionUrl( id ) + streetView + zoomTo + "</div>";	
		}

		return output;
	},
	/**
	 * Create the url that takes the user to the maps.google.com page 
	 * and shows the correct driving directions.
	 * 
	 * @since	1.0.0
	 * @param	{string} id			  The store id
	 * @returns {string} directionUrl The full maps.google.com url with the encoded start + end address
	 */
	createDirectionUrl: function( id ) {
		var directionUrl, destinationAddress, zip,
			url = {};

		if ( wpslSettings.directionRedirect == 1 ) {

			// If we somehow failed to determine the start address, just set it to empty.
			if ( typeof startAddress === "undefined" ) {
				startAddress = "";
			}
			
			url.target = "target='_blank'";
			
			// If the id exists the user clicked on a marker we get the direction url from the search results.
			if ( typeof id !== "undefined" ) {
				url.src = $( "[data-store-id=" + id + "] .wpsl-directions" ).attr( "href" );
			} else {

				// Only add a , after the zip if the zip value exists.
				if ( this.zip ) {
					zip = this.zip + ", ";
				} else {
					zip = "";
				}

				destinationAddress = this.address + ", " + this.city + ", " + zip + this.country;

				url.src = "https://www.google.com/maps/dir/?api=1&origin=" + templateHelpers.rfc3986EncodeURIComponent( startAddress ) + "&destination=" + templateHelpers.rfc3986EncodeURIComponent( destinationAddress ) + "&travelmode=" + wpslSettings.directionsTravelMode.toLowerCase() + "";
			}
		} else {
			url = {
				src: "#",
				target: ""
			};
		}	

		directionUrl = "<a class='wpsl-directions' " + url.target + " href='" + url.src + "'>" + wpslLabels.directions + "</a>";

		return directionUrl;
	},
	/**
	 * Make the URI encoding compatible with RFC 3986.
	 * 
	 * !, ', (, ), and * will be escaped, otherwise they break the string.
	 * 
	 * @since	1.2.20
	 * @param	{string} str The string to encode
	 * @returns {string} The encoded string
	 */
	rfc3986EncodeURIComponent: function( str ) {  
		return encodeURIComponent( str ).replace( /[!'()*]/g, escape );  
	}
};

/**
 * Create the HTML template used in the info windows on the map.
 * 
 * @since	1.0.0
 * @param	{object} infoWindowData	The data that is shown in the info window (address, url, phone etc)
 * @returns {string} windowContent	The HTML content that is placed in the info window
 */
function createInfoWindowHtml( infoWindowData ) {
	var windowContent, template;

	if ( $( "#wpsl-base-gmap_0" ).length ) {
		template = $( "#wpsl-cpt-info-window-template" ).html();
	} else {
		template = $( "#wpsl-info-window-template" ).html();
	}

	windowContent = _.template( template )( infoWindowData ); //see http://underscorejs.org/#template

	return windowContent;
}

/**
 * Zoom the map so that all markers fit in the window.
 * 
 * @since	1.0.0
 * @returns {void}
 */
function fitBounds() {
    var i, markerLen, 
		maxZoom = Number( wpslSettings.autoZoomLevel ),
		bounds  = new google.maps.LatLngBounds();
		
    // Make sure we don't zoom to far.
    attachBoundsChangedListener( map, maxZoom );

    for ( i = 0, markerLen = markersArray.length; i < markerLen; i++ ) {
		bounds.extend ( markersArray[i].position );
    }

    map.fitBounds( bounds );
}

/**
 * Remove all existing markers from the map.
 * 
 * @since	1.0.0
 * @param	{boolean} keepStartMarker Whether or not to keep the start marker while removing all the other markers from the map
 * @returns {void}
 */
function deleteOverlays( keepStartMarker ) {
	var markerLen, i;
	
    directionsDisplay.setMap( null );
	
    // Remove all the markers from the map, and empty the array.
    if ( markersArray ) {
		for ( i = 0, markerLen = markersArray.length; i < markerLen; i++ ) {
			
			// Check if we need to keep the start marker, or remove everything.
			if ( keepStartMarker ) {
				if ( markersArray[i].draggable != true ) {
					markersArray[i].setMap( null );
				} else {
					startMarkerData = markersArray[i];
				}
			} else {
				markersArray[i].setMap( null );
			}
		}

		markersArray.length = 0;
    }
	
	// If marker clusters exist, remove them from the map.
	if ( markerClusterer ) {
		markerClusterer.clearMarkers();
	}
}

/**
 * Handle the geocode errors.
 * 
 * @since	1.0.0
 * @param   {string} status Contains the error code
 * @returns {void}
 */
function geocodeErrors( status ) {
    var msg;

    switch ( status ) {
		case "ZERO_RESULTS":
			msg = wpslLabels.noResults;
			break;	
		case "OVER_QUERY_LIMIT":
			msg = wpslLabels.queryLimit;
			break;	
		default:
			msg = wpslLabels.generalError;
			break;
    }

    alert( msg );	
}

/**
 * Handle the driving direction errors.
 * 
 * @since   1.2.20
 * @param   {string} status Contains the error code
 * @returns {void}
 */
function directionErrors( status ) {
    var msg;

    switch ( status ) {
		case "NOT_FOUND":
		case "ZERO_RESULTS":
			msg = wpslLabels.noDirectionsFound;
			break;	
		case "OVER_QUERY_LIMIT":
			msg = wpslLabels.queryLimit;
			break;
		default:
			msg = wpslLabels.generalError;
			break;
    }

    alert( msg );	
}

$( "#wpsl-stores" ).on( "click", ".wpsl-store-details", function() {	
	var i, len,
		$parentLi = $( this ).parents( "li" ),
		storeId   = $parentLi.data( "store-id" );

	// Check if we should show the 'more info' details.
	if ( wpslSettings.moreInfoLocation == "info window" ) {
		for ( i = 0, len = markersArray.length; i < len; i++ ) {
			if ( markersArray[i].storeId == storeId ) {
				google.maps.event.trigger( markersArray[i], "click" );
			}
		}
	} else {
		
		// Check if we should set the 'more info' item to active or not.
		if ( $parentLi.find( ".wpsl-more-info-listings" ).is( ":visible" ) ) {
			$( this ).removeClass( "wpsl-active-details" );
		} else {
			$( this ).addClass( "wpsl-active-details" );
		}		
		
		$parentLi.siblings().find( ".wpsl-store-details" ).removeClass( "wpsl-active-details" );
		$parentLi.siblings().find( ".wpsl-more-info-listings" ).hide();
		$parentLi.find( ".wpsl-more-info-listings" ).toggle();
	}

	/* 
	 * If we show the store listings under the map, we do want to jump to the 
	 * top of the map to focus on the opened infowindow 
	 */
	if ( wpslSettings.templateId != "default" || wpslSettings.moreInfoLocation == "store listings" ) {
		return false;
	}
});

/**
 * Create the styled dropdown filters.
 * 
 * Inspired by https://github.com/patrickkunka/easydropdown
 * 
 * @since	1.2.24
 * @returns {void}
 */
function createDropdowns() {
	var maxDropdownHeight = Number( wpslSettings.maxDropdownHeight );
		
	$( ".wpsl-dropdown" ).each( function( index ) {
		var	active, maxHeight, $this = $( this );
		
		$this.$dropdownWrap = $this.wrap( "<div class='wpsl-dropdown'></div>" ).parent();	
		$this.$selectedVal  = $this.val();							
		$this.$dropdownElem = $( "<div><ul/></div>" ).appendTo( $this.$dropdownWrap );
		$this.$dropdown     = $this.$dropdownElem.find( "ul" );
		$this.$options 	  	= $this.$dropdownWrap.find( "option" );
		
		// Hide the original <select> and remove the css class.
		$this.hide().removeClass( "wpsl-dropdown" );
		
		// Loop over the options from the <select> and move them to a <li> instead.
		$.each( $this.$options, function() {
			if ( $( this ).val() == $this.$selectedVal ) {
				active = 'class="wpsl-selected-dropdown"';
			} else {
				active = '';
			}

			$this.$dropdown.append( "<li data-value=" + $( this ).val() + " " + active + ">" + $( this ).text() + "</li>" );
		});	
		
		$this.$dropdownElem.before( "<span data-value=" + $this.find( ":selected" ).val() + " class='wpsl-selected-item'>" + $this.find( ":selected" ).text() + "</span>" );
		$this.$dropdownItem = $this.$dropdownElem.find( "li" );
		
		// Listen for clicks on the 'wpsl-dropdown' div.
		$this.$dropdownWrap.on( "click", function( e ) {

			// Check if we only need to close the current open dropdown.
			if ( $( this ).hasClass( "wpsl-active" ) ) {
				$( this ).removeClass( "wpsl-active" );

				return;
			}

			closeAllDropdowns();

			$( this ).toggleClass( "wpsl-active" );
			maxHeight = 0;

			// Either calculate the correct height for the <ul>, or set it to 0 to hide it.
			if ( $( this ).hasClass( "wpsl-active" ) ) {
				$this.$dropdownItem.each( function( index ) {
					maxHeight += $( this ).outerHeight();
				});

				$this.$dropdownElem.css( "height", maxHeight + 2 + "px" );
			} else {
				$this.$dropdownElem.css( "height", 0 );
			}

			// Check if we need to enable the scrollbar in the dropdown filter.
			if ( maxHeight > maxDropdownHeight ) {
				$( this ).addClass( "wpsl-scroll-required" );
				$this.$dropdownElem.css( "height", ( maxDropdownHeight ) + "px" );
			}

			e.stopPropagation();
		});
		
		// Listen for clicks on the individual dropdown items.
		$this.$dropdownItem.on( "click", function( e ) {
			
			// Set the correct value as the selected item.
			$this.$dropdownWrap.find( $( ".wpsl-selected-item" ) ).html( $( this ).text() ).attr( "data-value", $( this ).attr( "data-value" ) );	

			// Apply the class to the correct item to make it bold.
			$this.$dropdownItem.removeClass( "wpsl-selected-dropdown" );
			$( this ).addClass( "wpsl-selected-dropdown" );
			
			closeAllDropdowns();
			
			e.stopPropagation();
		});
	});	
	
	$( document ).click( function() {
		closeAllDropdowns();
	});
}

/**
 * Close all the dropdowns.
 * 
 * @since	1.2.24
 * @returns {void}
 */
function closeAllDropdowns() {
	$( ".wpsl-dropdown" ).removeClass( "wpsl-active" );
	$( ".wpsl-dropdown div" ).css( "height", 0 );	
}

/**
 * Check if the user submitted a search through a search widget.
 *
 * @since	2.1.0
 * @returns {void}
 */
function checkWidgetSubmit() {
	if ( $( ".wpsl-search" ).hasClass( "wpsl-widget" ) ) {
		$( "#wpsl-search-btn" ).trigger( "click" );
		$( ".wpsl-search" ).removeClass( "wpsl-widget" );
	}
}

/**
 * Check if we need to run the code to prevent Google Maps
 * from showing up grey when placed inside one or more tabs.
 *
 * @since 2.2.10
 * @return {void}
 */
function maybeApplyTabFix() {
	var mapNumber, len;

	if ( _.isArray( wpslSettings.mapTabAnchor ) ) {
		for ( mapNumber = 0, len = mapsArray.length; mapNumber < len; mapNumber++ ) {
			fixGreyTabMap( mapsArray[mapNumber], wpslSettings.mapTabAnchor[mapNumber], mapNumber );
		}
	} else if ( $( "a[href='#" + wpslSettings.mapTabAnchor + "']" ).length ) {
		fixGreyTabMap( map, wpslSettings.mapTabAnchor );
	}
}

/**
 * This code prevents the map from showing a large grey area if
 * the store locator is placed in a tab, and that tab is actived.
 *
 * The default map anchor is set to 'wpsl-map-tab', but you can
 * change this with the 'wpsl_map_tab_anchor' filter.
 *
 * Note: If the "Attempt to auto-locate the user" option is enabled,
 * and the user quickly switches to the store locator tab, before the
 * Geolocation timeout is reached, then the map is sometimes centered in the ocean.
 *
 * I haven't really figured out why this happens. The only option to fix this
 * is to simply disable the "Attempt to auto-locate the user" option if
 * you use the store locator in a tab.
 *
 * @since   2.2.10
 * @param   {object} currentMap	  The map object from the current map
 * @param   {string} mapTabAnchor The anchor used in the tab that holds the map
 * @param 	(int) 	 mapNumber    Map number
 * @link    http://stackoverflow.com/questions/9458215/google-maps-not-working-in-jquery-tabs
 * @returns {void}
 */
function fixGreyTabMap( currentMap, mapTabAnchor, mapNumber ) {
    var mapZoom, mapCenter, maxZoom, bounds, tabMap,
        returnBool = Number( wpslSettings.mapTabAnchorReturn ) ? true : false,
		$wpsl_tab  = $( "a[href='#" + mapTabAnchor + "']" );

    if ( typeof currentMap.maxZoom !== "undefined" ) {
        maxZoom = currentMap.maxZoom;
	} else {
        maxZoom = Number( wpslSettings.autoZoomLevel );
	}

	/*
	 * We need to do this to prevent the map from flashing if
	 * there's only a single marker on the first click on the tab.
	 */
	if ( typeof mapNumber !== "undefined" && mapNumber == 0 ) {
        $wpsl_tab.addClass( "wpsl-fitbounds" );
	}

	$wpsl_tab.on( "click", function() {
		setTimeout( function() {
            if ( typeof currentMap.map !== "undefined" ) {
                bounds = currentMap.bounds;
                tabMap = currentMap.map;
            } else {
            	tabMap = currentMap;
			}

            mapZoom   = tabMap.getZoom();
            mapCenter = tabMap.getCenter();

			google.maps.event.trigger( tabMap, "resize" );

			if ( !$wpsl_tab.hasClass( "wpsl-fitbounds" ) ) {

                //Make sure fitBounds doesn't zoom past the max zoom level.
                attachBoundsChangedListener( tabMap, maxZoom );

                tabMap.setZoom( mapZoom );
				tabMap.setCenter( mapCenter );

                if ( typeof bounds !== "undefined" ) {
                    tabMap.fitBounds( bounds );
                } else {
                	fitBounds();
				}

				$wpsl_tab.addClass( "wpsl-fitbounds" );
            }
        }, 50 );

        return returnBool;
    });
}

/**
 * Add the bounds_changed event listener to the map object
 * to make sure we don't zoom past the max zoom level.
 *
 * @since 2.2.10
 * @param object The map object to attach the event listener to
 * @returns {void}
 */
function attachBoundsChangedListener( map, maxZoom ) {
    google.maps.event.addListenerOnce( map, "bounds_changed", function() {
        google.maps.event.addListenerOnce( map, "idle", function() {
            if ( this.getZoom() > maxZoom ) {
                this.setZoom( maxZoom );
            }
        });
    });
}

/**
 * Handle keyboard submits when the autocomplete option is enabled.
 *
 * If we don't do this, then the search will break the second time
 * the user makes a search, selects the item with the keyboard
 * and submits it with the enter key.
 *
 * @since 2.2.20
 * @returns {void}
 */
function keyboardAutoCompleteSubmit() {
	$( "#wpsl-search-input" ).keypress( function( e ) {

		if ( e.which == 13 ) {
			resetSearchResults();
			codeAddress( infoWindow );

			return false;
		}
	});
}

/**
 * Reset all elements before a search is made.
 *
 * @since 2.2.20
 * @returns {void}
 */
function resetSearchResults() {
	var keepStartMarker = false;

	$( "#wpsl-result-list ul" ).empty();
	$( "#wpsl-stores" ).show();
	$( ".wpsl-direction-before, .wpsl-direction-after" ).remove();
	$( "#wpsl-direction-details" ).hide();

	resetMap = false;

	// Force the open InfoBox info window to close.
	closeInfoBoxWindow();

	deleteOverlays( keepStartMarker );
	deleteStartMarker();
}

});