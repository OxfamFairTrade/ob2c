# Oxfam B2C

## Features

- Centrale productdatabase met nationale producten, die gesynchroniseerd worden naar alle onderliggende lokale webshops
- Maandelijkse update van alle productgegevens via CSV export/import uit OFT-site 
- Volledig gescheiden financiële afhandeling (één Mollie-account per webshop)
- Live ophalen van voedingswaarden en partnerinfo uit OFT-site via WordPress API (zodat info niet gedupliceerd hoeft te worden)
- Winkelzoeker op postcodebasis (o.b.v. gegevens in OWW-site, nog te switchen naar OBE-site), die de klant naar de juiste webshop leidt voor thuislevering
- Nationale kortingsregels, die gesynchroniseerd worden naar alle onderliggende lokale webshops
- Optie voor lokale beheerders om extra eigen assortiment toe te voegen
- Ondersteuning voor digitale cadeaubonnen (incl. crediteringsflow)
- Exports per webshop

## Bezorgdheden

Voorlopige oplossingen zijn vaak de meest definitieve. Terwijl de site gebouwd werd voor een testfase van 1 jaar (later verlengd tot 2 jaar) is het platform straks 6 jaar oud. Tijdens corona steeg het aantal webshops enorm, en kwam de wens om ook non-food online te gaan verkopen. Beide beslissingen hadden een grote impact op de database, die inmiddels ruim 2 GB groot is, omwille van het inefficiënte datamodel.

De maandelijkse update van de nationale productdata neemt daardoor inmiddels zo'n 4 uur in beslag. Het platform is eentalig Nederlands maar valt in zijn huidige opzet moeilijk op te schalen naar een meertalig platform. (Zo dienen kortingsregels nu al 'vertaald' te worden naar de lokale product-ID's.) En hoewel WordPress Multisite zich uitstekent leent tot verdere personalisatie per webshop (bv. toevoegen van eigen pagina's) is hier in de praktijk weinig vraag naar en bleef deze functionaliteit dus afgeschermd voor lokale beheerders.

## Toekomst

Indien het decentrale webshopmodel behouden wordt, geniet een opzet met één grote gemeenschappelijke productdatabase (met voor elke webshop een eigen voorraadwaarde i.p.v. een volledige duplicatie van het moederproduct) de voorkeur. Optioneel kunnen lokale beheerders hier nog steeds eigen producten in aanmaken, die enkel zichtbaar zijn in welbepaalde webshops. Voor tweedehandswinkels (waar elk product uniek is) moet de creatie van lokale producten super vlot kunnen gebeuren.

Voor bestellingen kan ook met een centrale tabel gewerkt worden, waarbij lokale beheerders en assistenten enkel 'view/edit'-rechten krijgen op hun lokale orders. Belangrijk pijnpunt is wel dat de online betalingen gestort moeten worden op de rekening van de lokale vzw. Ideeën om de tegoeden op een centrale rekening te ontvangen en via een crediteringsflow door te storten naar de lokale winkels stootten op een (begrijpelijke) njet van de boekhouding.

Uiteraard kan er ook bekeken worden om alsnog met een sympathieke externe afhandelaar te werken, die matcht met ons profiel.

## Dependencies

Zie oplijsting in composer.json!
