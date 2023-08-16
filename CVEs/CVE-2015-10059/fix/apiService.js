/**
 * Service for communication with the API.
 *  
 * @class app.api.apiService
 * @memberOf app.api
 * @example apiService.listCountries(function(response) { var countries = response.data });	
 */

/**
 * Factory to create the controller.
 * 
 * @function factory
 * @memberOf app.api.apiService
 * @param {string} Service name
 * @param {fn} Service function
 * @param {$http} $http - Angulars $http object
 * @param {$localStorage} $localStorage - Interface to the local storage
 */

ngApp.factory('apiService', ['$http', '$localStorage',function($http, $localStorage) {

	/**
	 * Expiration  time in seconds for each API function.
	 * 
	 * @var {object} expirationTime - Expiration time
	 * @memberOf app.api.apiService
	 * @private
	 */
	var expirationTime = {
		listCountries: 60,
		listCities: 60,
		listPlacesByCity: 60,
	};

	/**
	 * If url in localStorage and not expired return this data,
	 * else get data using $http request.
	 * 
	 * @private
	 * @function get
	 * @memberOf app.api.apiService
	 * @param {string} URL
	 * @returns nothing
	 */
	var get = function(url, fn, callback) {
		var now = new Date(); // current date and time
		if(!$localStorage[fn] // not cached before
			|| !$localStorage[fn].loadTime // or: loadTime invalid
			||  (now - Date.parse($localStorage[fn].loadTime)) / 1000 > expirationTime[fn]) { // or: cache expired because: now - loadTime > expirationTime
			$http.get(url).success(function(data, status) { // get using $http, execute a function
				data.loadTime = now; // now loaded
				$localStorage[fn] = data; // store to localStorage
				callback($localStorage[fn]); // execute callback with data from cache and cache time
			}).error(function(data, status) { // failed to get data
				if(!$localStorage[fn]) { // nothing set
					$localStorage[fn] = { // fallback
							data: {}, // no data
							loadTime: undefined, // undefined load time
							status: status // error status
					};
				} // else: use old data
				callback($localStorage[fn]); // execute callback with fallback data from cache
			});
		} else { // cached and is up to date
			callback($localStorage[fn]); // execute callback with data from cache and cache time
		}		
	};

	/**
	 * Decode the string into a safe placeholder.
	 * If it contains unsafe characters an empty string will be returned.
	 * 
	 * @private
	 * @function placeholder
	 * @memberOf app.api.apiService
	 * @param {string} placeholder - A maybe unsafe placeholder used in an $http Request
	 * @returns {string} Safe Placeholder
	 */
	var safe = function(placeholder) {
		var reg = /^[a-zA-Z0-9\-_]+$/;
		if(reg.test) {
			return placeholder;
		} else {
			return "";
		}
	};

	return {
		/**
		 * URL to the API
		 * @var {string} URL
		 * @memberOf app.api.apiService 
		 */
		url: 'api/',

		/**
		 * API-key wich should be used to communicated with the API.
		 *
		 * @var {string} API-key
		 * @memberOf app.api.apiService
		 */
		apikey: '',

		/**
		 * Language wich should be returned by the API.
		 *
		 * @var {string} Language
		 * @memberOf app.api.apiService
		 */
		lang: 'de',

		/**
		 * Executes a callback with a list of all countries as parameter
		 *
		 * @function listCountries
		 * @memberOf app.api.apiService
		 * @see {@link http://veganguide.org/api|vg.browse.listCountries}
		 * @param {fn} callback - Function to execute
		 */
		listCountries: function(callback) {
			get('api/JSON_Dummies/Countries.json', 'listCountries', callback);
			///get(this.url + '?apikey=' + this.apikey + '&lang=' + this.lang, 'listCountries', callback);
		},

		/**
		 * Executes a callback with a list of all cities as parameter
		 *
		 * @function listCities
		 * @memberOf app.api.apiService
		 * @see {@link http://veganguide.org/api|vg.browse.listCities}
		 * @param {string} country - Cities should be in this country
		 * @param {fn} callback - Function to execute
		 */
		listCities: function(country, callback) {
			get('api/JSON_Dummies/Cities_Germany.json', 'listCities', callback);
			//get(this.url + '?apikey=' + this.apikey + '&lang=' + this.lang + '&country=' + safe(country), 'listCities', callback);
		},

		/**
		 * Executes a callback with a list of all places as parameter
		 *
		 * @function listPlacesByCity
		 * @memberOf app.api.apiService
		 * @see {@link http://veganguide.org/api|vg.browse.listCities}
		 * @param {string} country - Cities should be in this country
		 * @param {string} city - Cities should be in this city
		 * @param {fn} callback - Function to execute
		 */
		listPlacesByCity: function(country, city, callback) {
			get('api/JSON_Dummies/Lokale_Leipzig.json', 'listPlacesByCity',  callback);
			//get(this.url + '?apikey=' + this.apikey + '&lang=' + this.lang + '&country=' + safe(country) + '&city=' + safe(city),  callback);
		}
	};
}]);
