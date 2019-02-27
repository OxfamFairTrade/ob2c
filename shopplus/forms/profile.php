<!DOCTYPE html>
<html lang="nl">

<head>
	
	<meta charset="UTF-8">
	<title>Jouw profiel | Oxfam-Wereldwinkels</title>
	<meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1">
	<meta property="og:title" content="Jouw profiel | Oxfam-Wereldwinkels">
	<meta property="og:url" content="https://shop.oxfamwereldwinkels.be/shopplus/forms/profile">
	<meta property="og:description" content="">
	<meta property="og:image" content="">
	
	<link rel="shortcut icon" type="image/x-icon" href="">
	<link rel="stylesheet" href="css/bootstrap-4.1.3.css">
	<link rel="stylesheet" href="css/style.css">

	<script async src="https://www.googletagmanager.com/gtag/js?id=UA-XXXXXX-1"></script>
	<script>
		window.dataLayer = window.dataLayer || [];
		function gtag(){dataLayer.push(arguments);}
		gtag('js', new Date());
		gtag('config', 'UA-XXXXXX-1');
	</script>
	
</head>

<body>

<div id="form">
	<div class="jumbotron d-flex align-items-center px-0 py-5 mb-5">
		<div class="container p-0">
			<div class="row m-0">
				<div class="col">
					<img src="images/oww-logo-groen.png" class="logo mx-auto d-block">
					<img src="images/vinkje.png" class="mx-auto d-none">
				</div>
			</div>
			<form @submit.prevent="sendData" class="rounded seethrough p-3" method="post" novalidate>
				<div class="row mt-3">
					<div class="col text-center">
						<h1 class="title mb-0">Teken de petitie</h1>
						<h3 v-if="counted && online" v-cloak class="title mb-0">net als {{ subscriberCount }} andere burgers</h3>
						<p class="pt-4">Miljoenen boeren die de cacao voor onze Belgische chocolade telen, verdienen geen leefbaar inkomen.</p>
						<p>Achter 's lands favoriete lekkernij gaat nog steeds een donkere waarheid schuil van extreme armoede, toenemende kinderarbeid en massale ontbossing. En dat ondanks de beloften van de grote chocoladebedrijven om deze problemen aan te pakken.</p>
						<p>Respect voor mensenrechten en milieu mag geen vrijblijvend engagement zijn, in geen enkele sector.</p>
					</div>
				</div>
				<div style="border: 3px dashed black;">
					<div class="form-row">
						<div class="col text-center font-weight-bold px-2">
							<p class="pt-3">Beste politici,</p>
							<p clas="p-1">Draai niet rond de pot. Zorg voor wetgeving en een bindend VN-verdrag dat bedrijven verplicht om de mensenrechten en het milieu doorheen hun toeleveringsketens te respecteren.</p>
							<noscript>
								<p class="text-danger p-1">Opgepast: het formulier is momenteel niet actief! Schakel JavaScript in en herlaad de pagina.</p>
							</noscript>
						</div>
					</div>
					<div class="form-row">
						<div class="col text-center">
							<div class="form-check form-check-inline">
								<input :disabled="showResult" v-model="gender" class="form-check-input" type="radio" id="V" value="V">
								<label for="V" class="form-check-label">Vrouw</label>
							</div>
							<div class="form-check form-check-inline">
								<input :disabled="showResult" v-model="gender" class="form-check-input" type="radio" id="M" value="M">
								<label for="M" class="form-check-label">Man</label>
							</div>
							<div class="form-check form-check-inline">
								<input :disabled="showResult" v-model="gender" class="form-check-input" type="radio" id="X" value="X">
								<label for="X" class="form-check-label">Anders</label>
							</div>
						</div>
					</div>
					<div class="form-row justify-content-center">
						<div class="col-md-4 m-2">
							<input v-model.trim="fname" ref="fname" :class="{ 'is-valid': fnameIsValid && fnameValidated, 'is-invalid': !fnameIsValid && fnameValidated }" @keyup="checkFname" :disabled="showResult" type="text" class="form-control" name="fname" id="fname" placeholder="Voornaam *" value="" maxlength="35" required>
							<div v-if="!fnameIsValid && fnameValidated" v-cloak class="feedback">Gelieve je voornaam in te geven</div>
						</div>
						<div class="col-md-4 m-2">
							<input v-model.trim="lname" ref="lname" :class="{ 'is-valid': lnameIsValid && lnameValidated, 'is-invalid': !lnameIsValid && lnameValidated }" @keyup="checkLname" :disabled="showResult" type="text" class="form-control" name="lname" id="lname" placeholder="Familienaam *" value="" maxlength="35" required>
							<div v-if="!lnameIsValid && lnameValidated" v-cloak class="feedback">Gelieve je familienaam in te geven</div>
						</div>
					</div>
					<div class="form-row justify-content-center">
						<div class="col-md-4 m-2">
							<input v-model.trim="email" ref="email" :class="{ 'is-valid': emailIsValid && emailValidated, 'is-invalid': !emailIsValid && emailValidated }" @focusout="checkEmail" :disabled="showResult" type="email" class="form-control" name="email" id="email" placeholder="E-mailadres *" maxlength="50" required>
							<div v-if="!emailIsValid && emailValidated" v-cloak class="feedback">Geef een geldig e-mailadres in</div>
						</div>
						<div class="col-md-4 m-2">
							<!-- Expliciet vergelijken met true of false om valse positieven bij empty te vermijden -->
							<input v-model.trim="year" :class="{ 'is-valid': yearIsValid == true, 'is-invalid': yearIsValid == false }" @keyup="checkYear" :disabled="showResult" type="number" min="1900" max="2006" class="form-control" name="year" id="year" placeholder="Geboortejaar" maxlength="4">
							<div v-if="!yearIsValid && yearValidated" v-cloak class="feedback">Geef een geldig jaartal in (min. 12 jaar)</div>
						</div>
					</div>
					<div class="form-row">
						<div class="col text-center">
							<div class="form-check form-check-inline">
								<input :disabled="showResult" v-model="newsletter" :disabled="showResult" class="form-check-input" type="checkbox" name="newsletter" id="newsletter">
								<label for="newsletter" class="form-check-label">Abonneer mij ook op de maandelijkse nieuwsbrief</label>
							</div>
						</div>
					</div>
					<div class="row align-items-center text-center m-2">
						<div class="col-md-7 p-2">
							<small>
								<div v-if="!formIsValid">Je hebt nog niet alle vereiste velden ingevuld. Nog even volhouden!</div>
								<div>We gebruiken je gegevens enkel voor deze petitie. <a href="https://www.oxfamwereldwinkels.be/nl/privacybeleid" target="_blank">Lees ons privacybeleid.</a></div>
							</small>
						</div>
						<div class="col-md-5 p-2">
							<button :disabled="buttonDisabled" type="submit" class="btn btn-primary">Ja, ik onderschrijf deze oproep</button>
						</div>
					</div>
					<transition name="slide-fade" mode="out-in">
						<div v-if="showResult" v-cloak class="row text-center m-2">
							<div class="col pt-2">
								<h3 class="title green">Bedankt om onze petitie te ondertekenen!</h3>
								<h5 class="green">Lees meer over de uitdagingen van de cacaosector op <a href="https://www.oxfamwereldwinkels.be/nl/cacaoketen" target="_blank">oxfamwereldwinkels.be/cacao</a>.</h5>
							</div>
						</div>
					</transition>
				</div>
			</form>
		</div>
	</div>
	<nav id="admin-pane" class="navbar navbar-expand-md fixed-bottom text-light py-1" :class="{ 'bg-dark': showAdmin }" style="line-height: 1em;">
		<template v-if="!showAdmin">
			<ul class="navbar-nav small">
				<li @click="toggleAdmin" class="nav-item rounded seethrough"><a class="nav-link link-style text-primary px-2 py-1">Toon status <span class="flipper">&rarr;</span></a></li>
			</ul>
		</template>
		<template v-else>
			<ul class="navbar-nav align-items-center small w-50">
				<li class="nav-item mr-auto" @click="toggleAdmin">
					<a class="nav-link link-style text-primary py-1"><span class="flipper">&larr;</span> Verberg status</a>
				</li>
				<li class="nav-item mr-auto">
					<a class="nav-link py-1">{{ localCount }} in lokale wachtrij</a>
				</li>
				<li class="nav-item mr-auto">
					<a class="nav-link py-1">{{ externalCount }} reeds doorgestuurd</a>
				</li>
				<li v-if="online" class="nav-item mr-auto">
					<a class="nav-link py-1">{{ subscriberCount }} unieke ondertekenaars</a>
				</li>
			</ul>
			<ul class="navbar-nav navbar-right align-items-center small w-50">
				<li class="nav-item ml-auto">
					<a :class="{ 'text-success': online, 'text-warning': !online }" class="nav-link py-1" href="https://firebase.google.com" title="Handtekeningen worden indien nodig in de lokale wachtrij geplaatst" target="_blank">Centrale database {{ onlineStatus }}</a>
				</li>
				<li v-if="!cacheApi" class="nav-item ml-auto">
					<a class="nav-link text-danger py-1" href="https://caniuse.com/#feat=serviceworkers" title="Cache API wordt niet ondersteund door deze browser" target="_blank">Offline werken niet mogelijk</a>
				</li>
				<select v-model="shop" class="nav-item ml-auto my-auto" id="shop">
					<option value="">(selecteer je winkel)</option>
					<option v-for="shop in sortedShops" :value="shop">{{ shop }}</option>
				</select>
			</ul>
		</template>
	</nav>
</div>

<script src="scripts/globals.js"></script>
<!-- Slim NIET gebruiken aangezien we de AJAX-functies willen gebruiken richting MailChimp! -->
<script src="scripts/jquery-3.3.1.js"></script>
<script src="scripts/lodash-4.17.10.js"></script>
<script src="scripts/bootstrap-4.1.3.js"></script>
<script src="scripts/vue-2.6.7-dev.js"></script>

<script>
	function capitalize(str) {
		return str.charAt(0).toUpperCase() + str.substring(1, str.length).toLowerCase();
	}

	function titleCase(str) {
		return str.replace(/[^\ \-]+/g, capitalize);
	}

	function processQueue(records) {
		if ( records.length > 0 ) {
			for (var index in records) {
				pushSubscriber(records[index], true);
			}
		}
	}

	function pushSubscriber(newSubscriber, fromLocalStorage) {
		var fields = {
			gender: newSubscriber.gender,
			fname: newSubscriber.fname,
			lname: newSubscriber.lname,
			email: newSubscriber.email,
			shop: newSubscriber.shop,
			newsletter: newSubscriber.newsletter
		};

		// Registreer in MailChimp-database OF verstuur eenvoudige bedankingsmail (naargelang waarde newsletter)
		var petition = jQuery.post({
			url: 'https://shop.oxfamwereldwinkels.be/shopplus/test.php',
			data: fields,
		}).fail( function(error) {
			console.log(error);
			// Alsnog in de wachtrij zetten door toe te voegen aan de Vue-component
			form.localRecords.push(newSubscriber);
		}).done( function() {
			form.externalCount++;
			// Verwijder record uit de Vue-component en zo uit localStorage
			if ( fromLocalStorage ) {
				var records = form.localRecords;
				for (var index in records) {
					if ( newSubscriber.email == records[index].email ) {
						form.localRecords.splice(index);
					}
				}
			}
		});
	}

	var form;
	
	// Start Vue
	form = new Vue({
		el: '#form',
		data: {
			showAdmin: false,
			showResult: false,
			localRecords: [],
			externalCount: 0,
			subscriberCount: 0,
			interval: null,
			online: navigator.onLine || false,
			counted: false,
			cacheApi: false,
			gender: '',
			genderValidated: false,
			fname: '',
			fnameValidated: false,
			lname: '',
			lnameValidated: false,
			email: '',
			emailValidated: false,
			year: '',
			yearValidated: false,
			newsletter: false,
			shop: '',
			shops: globalShops,
		},
		mounted: function() {
			// Meteen 'form' gebruiken in dieperliggende functies zou op dit ogenblik ook al moeten lukken maar Chrome doet moeilijk
			var vm = this;

			if ( 'caches' in window ) {
				vm.cacheApi = true;
			}

			if ( localStorage.getItem('localRecords') ) {
				vm.localRecords = JSON.parse( localStorage.getItem('localRecords') );
			}
			localStorage.setItem( 'localRecords', JSON.stringify(vm.localRecords) );

			if ( localStorage.getItem('externalCount') ) {
				vm.externalCount = localStorage.getItem('externalCount');
			}

			if ( localStorage.getItem('participantShop') ) {
				vm.shop = localStorage.getItem('participantShop');
			}

			if ( vm.online ) {
				processQueue(vm.localRecords);
			}

			var param = ['fname','lname','email'];
			for ( var i = 0 ; i < param.length ; i++ ) {
				var str = location.search.split(param[i]+"=");
				if ( str.length > 1 ) {
					str = str[1].split("&");
					var value = str[0].split("%26");
					vm[param[i]] = value[0].replace( /(%20|\+)/g, " " );
					// Trigger validatie
					vm.$refs[param[i]].dispatchEvent(new Event('keyup'));
					vm.$refs[param[i]].dispatchEvent(new Event('focusout'));
				}
			}
		},
		computed: {
			sortedShops: function() {
				return _.orderBy(this.shops);
			},
			onlineStatus: function() {
				if (this.online) {
					return 'bereikbaar';
				} else {
					return 'onbereikbaar';
				}
			},
			fnameIsValid: function() {
				/* Check of er geen cijfers in voorkomen */
				if ( /^\D+$/.test(this.fname) ) {
					return true;
				} else {
					return false;
				}
			},
			lnameIsValid: function() {
				/* Check of er geen cijfers in voorkomen */
				if ( /^\D+$/.test(this.lname) ) {
					return true;
				} else {
					return false;
				}
			},
			emailIsValid: function() {
				/* Check of de sequentie 'apenstaartje - min. 2 letters - punt - min. 2 letters' erin voorkomt en GEEN komma's */
				if ( /^(\S+)@(\S+){2,}\.(\S+){2,}$/.test(this.email) && ! /,/.test(this.email) ) {
					return true;
				} else {
					return false;
				}
			},
			yearIsValid: function() {
				if ( this.yearValidated ) {
					if ( /^\d{4}$/.test(this.year) && parseInt(this.year) >= 1900 && parseInt(this.year) <= 2006 ) {
						return true;
					} else {
						return false;
					}
				}
				return null;
			},
			formIsValid: function() {
				// && this.gender != ''
				if ( this.fnameIsValid && this.lnameIsValid && this.emailIsValid ) {
					return true;
				} else {
					return false;
				}
			},
			buttonDisabled: function() {
				if ( !this.formIsValid || this.showResult ) {
					return true;
				} else {
					return false;
				}
			},
			localCount: function() {
				return this.localRecords.length;
			},
		},
		watch: {
			localRecords: function(val) {
				localStorage.setItem( 'localRecords', JSON.stringify(val) );
			},
			externalCount: function(val) {
				localStorage.setItem( 'externalCount', val );
			},
			shop: function(newVal, oldVal) {
				localStorage.setItem( 'participantShop', this.shop );
			},
		},
		methods: {
			checkFname: function() {
				if ( this.fname.length > 0 ) {
					this.fnameValidated = true;
				}
			},
			checkLname: function() {
				if ( this.lname.length > 0 ) {
					this.lnameValidated = true;
				}
			},
			checkEmail: function() {
				this.emailValidated = true;
			},
			checkYear: function() {
				// Validatie pas triggeren van zodra we 4 tekens ingevuld hebben
				if ( this.year.length >= 4 ) {
					this.yearValidated = true;
				}
			},
			toggleAdmin: function() {
				this.showAdmin = !this.showAdmin;
			},
			sendData: function() {
				var newSubscriber = {
					date: new Date().toString().split(' GMT')[0],
					gender: this.gender,
					fname: titleCase(this.fname),
					lname: titleCase(this.lname),
					email: this.email.toLowerCase(),
					year: this.year,
					shop: this.shop,
					newsletter: this.newsletter,
					language: 'NL'
				};
				
				if ( this.online ) {
					pushSubscriber(newSubscriber, false);
				} else {
					// Toevoegen aan de Vue-component volstaat!
					this.localRecords.push(newSubscriber);
				}
				
				// Toon de bedanking en blokkeer de huidige input
				this.showResult = true;
				// Scroll naar bottom
				jQuery('html, body').animate( { scrollTop: jQuery(document).height() }, 'slow' );
				// Reset de input na x aantal seconden voor de volgende ondertekenaar
				this.interval = setInterval( function() { this.resetData(); }.bind(this), 30000 );
			},
			resetData: function() {
				this.gender = '';
				this.fname = '';
				this.fnameValidated = null;
				this.lname = '';
				this.lnameValidated = null;
				this.email = '';
				this.emailValidated = null;
				this.year = '';
				this.yearValidated = null;
				this.newsletter = false;
				this.showResult = false;

				// Verhinder nieuwe resets
				clearInterval(this.interval);
				
				// Scroll naar top
				jQuery('html, body').animate( { scrollTop: 0 }, 'slow' );
			},
		},
	});

	// if ( 'caches' in window ) {
	// 	window.addEventListener( 'load', function() {
	// 		navigator.serviceWorker.register('sw-cache-latest.js')
	// 			.then( function(register) { console.log("Service worker registered!"); } )
	// 			.catch( function(error) { console.log("Service worker failed!"); } );
	// 	});
	// }
</script>

</body>

</html>