const cacheName = 'petitie-v4';

const cacheAssets = [
	'.',
	'index.html',
	'css/bootstrap-4.1.3.css',
	'css/style.css',
	'scripts/bootstrap-4.1.3.js',
	'scripts/globals.js',
	'scripts/jquery-3.3.1.js',
	'scripts/lodash-4.17.10.js',
	'scripts/vue-2.6.7-dev.js',
	'images/background-boterham.jpg',
	'images/oww-logo-groen.png',
	'images/vinkje.png',
	'images/oxfam.ttf'
];

self.addEventListener( 'install', function(event) {
	event.waitUntil(
		caches.open(cacheName).then( function(cache) {
			console.log('Cache service worker is up!');
			return cache.addAll(cacheAssets);
		})
	);
});

self.addEventListener( 'activate', function(event) {
	event.waitUntil(
		caches.keys().then( function(cacheNames) {
			return Promise.all(
				cacheNames.filter( function(currentName) {
					if ( currentName != cacheName ) {
						return true;
					}
				}).map( function(currentName) {
					console.log('Deprecated cache ', currentName, ' deleted');
					return caches.delete(currentName);
				})
			);
		})
	);
});

self.addEventListener( 'fetch', function(event) {
	event.respondWith(
		// Eerst cache opzoeken, met fallback naar netwerk
		caches.match(event.request).then( function(response) {
			if (response) {
				return response;
			}
			console.log('Request ', event.request.url, ' over network');
			return fetch(event.request);
		}).catch( function(error) {
			console.log('Both cache and network failed!');
		})
	);
});