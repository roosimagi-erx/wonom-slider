=== Wonom Slider ===
Contributors: wonomdigital
Tags: slider, hero, banner, woocommerce, elementor, campaign
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight, easy-to-manage hero slider for WooCommerce home pages. Multiple slides, scheduled campaign banners, separate mobile images, multilingual texts, Elementor widget, GitHub auto-updates.

== Description ==

* One screen, one card per slide – no layer/timeline editor.
* Scheduled campaign slides (start/end, site timezone) with a calendar overview.
* Separate mobile image, aspect ratio, sizes, alignment and "image only" mode.
* Texts per language (Polylang / WPML auto-detected, or a manual list), optional DeepL translation.
* Elementor widget, shortcode `[wonom_slider]`, Gutenberg block and `wonom_slider()` template tag.
* ~9 kB JS + ~11 kB CSS unminified (about 5 kB gzipped), no jQuery/Swiper on the front end; first image eager + fetchpriority=high.
* Automatic updates from GitHub Releases.

== Changelog ==

= 1.8.1 - 2026-11-18 =
* Parandus: slaidide lehe ülemine eelvaade ei värise enam. Eelvaade joonistati suures ekraani laiuses ja vähendati CSS-i „transform“ abil, mistõttu fotod ja tekstid värelesid slaidide vahetumise ajal, eriti suurel monitoril ja vähendatud brauseri suumiga. Nüüd vähendab brauser lehe otse (CSS zoom) ja eelvaade on terav ja rahulik; mõõdud ja kärbe on samad mis poes.

= 1.8.0 - 2026-11-18 =
* Parandus: nupp „Kontrolli uuendusi kohe“ ei pane enam kõiki pluginaid uuesti kontrollima. Varem kustutati WordPressi kogu uuenduste nimekiri ja käivitati kõigi pluginate kontroll (ka wordpress.org-i päring), mis võib suures poes aeguda (HTTP 524). Nüüd värskendatakse ainult Wonom Slideri enda kirjet.
* Parandus: plugina kustutamisel eemaldatakse alati tehnilised seaded, sh GitHubi, DeepL-i ja Cloudflare'i võtmed, ajutised andmed ja ajastatud sündmused. Slaidid, slaideri seaded ja kampaaniate seosed kustutatakse nagu enne ainult siis, kui „Kustuta andmed eemaldamisel“ on sees.
* Uus: Seaded → Lehe vahemälu näitab, millal WP-Cron viimati päriselt käis, ja hoiatab, kui see on üle tunni seisnud. Kui ajastatud vahemälu tühjendus on cron'i seisaku tõttu hilinenud, tehakse see ära järgmisel admin-lehe avamisel.
* Muudatus: admin-liidese tekstid tõlgitakse nüüd WordPressi standardse JS-tõlke (wp.i18n) kaudu; kaasas on .po-, .mo- ja JSON-tõlkefailid. Liidese välimus ja tekstid ei muutu.
* Parandus: peidetud elemendid (atribuut hidden) jäävad admin-lehel alati peidetuks.
* readme.txt: muudatuste logi genereeritakse nüüd CHANGELOG.md-st, „Tested up to: 7.1“.

= 1.7.12 - 2026-11-10 =
* Parandus: eelvaade, lava ja pisipildid kasutavad slaideri kuju, mis poes päriselt eetris on (esimene eetris olev pildiga slaid). Varem võttis lava kuju muudetavast slaidist endast ja eelvaade linnukesega „Näita ka ajastatud ja väljalülitatud slaide“ esimesest, ka aegunud slaidist, nii et need võisid poest erineda.

= 1.7.11 - 2026-11-10 =
* Muudatus: lava, slaidide pisipildid ja vaikimisi eelvaade joonistatakse nüüd sinu enda brauseriakna mõõtudes (nupp „Sinu ekraan“). Mida näed redaktoris, seda näed samal ekraanil ka poes 100 % suumiga. Varem kasutati tüüpilise sülearvuti mõõte ja lava oli suurel monitoril palju madalam kui pood. Akna suuruse muutmisel joonistatakse eelvaated uuesti.
* Eelvaate nupud „Sülearvuti“ ja „Suur ekraan“ jäävad alles, et saaksid kontrollida ka teisi ekraane.

= 1.7.10 - 2026-11-10 =
* Parandus: kollaaži täitmise vihje ümardab nüüd õigesti: „fotorida on slaidist madalam“ ilmub ainult siis, kui see päriselt nii on, ja sülearvuti jaoks pakutakse õiget lisafotode arvu (5 fotoga: lisa 2).

= 1.7.9 - 2026-11-10 =
* Parandus: „Näita fotosid tervena“ režiimis on fotod alati kõrvuti (seadistatud vahega). Hajumise ja hägu üleminek kattis seni iga foto serva 90 px ulatuses, mistõttu fotod polnud päriselt terved ja rida täitis seitsme fotoga ainult umbes 70 % laiusest. Ülemineku valik on selles režiimis välja lülitatud ja selgitusega.

= 1.7.8 - 2026-11-10 =
* Uus: kollaaži valik „Fotode paigutus“. „Näita fotosid tervena“ hoiab iga foto oma kujus ja ei kärbi midagi: fotod on slaidi kõrgused (või veidi madalamad, kui need kokku slaiderist laiemaks läheksid) ja paigutatakse keskele, vaba ruum on slaidi taustavärviga. Redaktor näitab, mitu protsenti laiusest fotod suurel monitoril ja sülearvutil täidavad ning mitu fotot lisada, et laius täituks. Senine „Täida slaid (fotosid kärbitakse)“ jääb vaikimisi.
* Uus: kollaažis võib olla kuni 7 fotot (enne 5). Uus pesa tekib, kui eelmised on täis.
* Parandus: kui slaideri esimene slaid on kollaaž, arvutatakse slaideri kuju nüüd kõigi fotode tegelikest mõõtudest, mitte ainult esimese foto järgi.

= 1.7.7 - 2026-11-10 =
* Muudatus: arvutipildi juhend lähtub nüüd sellest, et slaider on igal ekraanil täies kõrguses (40 % ekraanist, nagu enne). Soovitus on kaks mõõtu: kogu pilt 2400 × 650 px (suurel monitoril paistab tervena) ja turvaala keskel 2400 × 460 px, mis paistab igal ekraanil. Turvaala on juhendis joonisel näha. Näod, tooted ja tekst käivad turvaala sisse, ülemisse ja alumisse ribasse ainult taevas, põrand või taust. 1.7.6 soovitatud 2400 × 460 tegi slaideri suurel monitoril madalaks ja kollaažidest lõigati liiga palju.
* Uus: eelvaates on nupp „Suur ekraan“ (brauseri ala 2560 × 1300 px) lisaks „Sülearvutile“, nii et näed nii kõige rohkem kui ka kõige vähem kärbitud vaadet.

= 1.7.6 - 2026-11-10 =
* Parandus: arvutipildi soovitus ja eelvaade lähtuvad nüüd tüüpilisest sülearvutist (brauseri ala 1536 × 730 px). Kõrguse piirang on protsent ekraani kõrgusest, nii et sülearvutil ja tavalisel 1080p monitoril on nähtav ala kõige laiem ja pilti kärbitakse kõige rohkem. 1.7.5 arvutas suurema ekraani järgi ja soovitas 2400 × 620, mis sülearvutil kaotas ikka umbes 20 % kõrgusest. 40 % piiranguga on õige mõõt 2400 × 460 px: see on igal levinud ekraanil terve, suuremal ekraanil on slaider lihtsalt madalam.
* Külgede suhe kuvatakse eesti keeles komaga (5,2 : 1, 4 : 5).

= 1.7.5 - 2026-11-10 =
* Parandus: arvutivaate eelvaade, lava ja slaidide pisipildid joonistatakse nüüd päris arvutilaiuses (brauseri ala 1600 × 1040 px) ja vähendatakse ekraanile. Varem joonistati need kitsamalt ja laial ekraanil tekkiv kärbe (pildi üla- ja alaosa) adminis ei paistnud. Nüüd näed adminis täpselt sama kärbet, mida näeb külastaja.
* Uus: Seaded → Pildi mõõdud. Juhend, mis mõõdus arvuti- ja mobiilipildid teha, arvutatud sinu kõrguse piirangutest (praeguste seadetega arvuti 2400 × 620 px, mobiil 1080 × 1350 px), koos reeglitega teksti, failivormingu ja kollaažifotode kohta. Pildivälja all olev soovitus kasutab samu arvutatud mõõte (enne oli seal vale 1920 × 660).

= 1.7.4 - 2026-11-10 =
* Tehniline: uued versioonid avaldatakse nüüd otse väljalaske skriptist (nagu Wonom KKK-l) ja GitHub kontrollib iga väljalaske üle. Kui kontroll ebaõnnestub, ei pakuta seda versiooni poodidele üldse. Plugina töös muudatusi pole.

= 1.7.3 - 2026-10-05 =
* Parandus: kui automaatsel kampaaniaslaidil pole ühtki kampaaniat võtta, tühjendatakse ka redaktoris slaidi varasemad tekstid, et vana sisu ei jääks kuhugi alles.

= 1.7.2 - 2026-10-05 =
* Muudatus: kampaaniaga seotud slaidil (valitud kampaania või automaatne) tuleb kogu sisu ainult kampaaniast. Slaidi enda varasemad tekstid (nt ingliskeelne väike rida või nupu tekst) ei jää enam alles: väljad, mida kampaanial pole, on tühjad igas keeles. Nuppu kampaaniaslaidil ei ole; kui kampaanial on link, on kogu slaid klõpsatav.

= 1.7.1 - 2026-10-05 =
* Uus: silt (sooduskoodi pill) on slaidi kaupa eraldi seadistatav: font, suurus arvutis ja mobiilis, paksus, suurtähed, tähevahe, tausta- ja tekstivärv, polsterdus ja nurgad. Tühi = senine vaikimisi (nupu värvid). Muudatus on laval kohe näha; värvi saab ✕-ga vaikimisi tagasi panna.

= 1.7.0 - 2026-10-05 =
* Uus: sisuallikas „Praegu eetris olev kampaania (automaatne)”. Slaid võtab alati Kampaaniaribal parasjagu eetris oleva kampaania (tekstid, sooduskood, link, ajakava), kampaaniat ei pea valima. Kui ühtki eetris pole, võtab slaid järgmise ajastatud kampaania ja on kuni selle alguseni peidetud (olek „Ootab kampaaniat”); kui ka tulemas pole ühtki, on slaid peidetud. Kampaania muutmine, kustutamine või lisamine Kampaaniaribal tühjendab vahemälu ja planeerib ajastuse ümber.

Full history: CHANGELOG.md on GitHub.
