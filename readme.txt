=== Wonom Slider ===
Contributors: wonomdigital
Tags: slider, hero, banner, woocommerce, elementor, campaign
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.9.5
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

= 1.9.5 - 2026-11-19 =
* Parandus: ingliskeelne avaleht (`/en/`) on tühjendatavate lehtede hulgas. WPML-i keele avaleht võetakse nüüd WPML-i enda keelevahetaja aadressist.
* Muudatus: ühte liiki eelvaatepäringuid on serveri poole teel korraga ainult üks. Kui klõpsata kiiresti, saadetakse uus päring alles siis, kui eelmine on lõppenud, ja vahepealsed muudatused liidetakse üheks päringuks. Slaidi eelvaade joonistatakse 0,25 s pärast viimast klõpsu.
* Muudatus: kui WP-Cron ei käi (nagu testsaidil), küsib avatud admin-leht ajastatud tühjenduse ise, kui see on käes. Ka siis tehakse ainult üks tühjendus.

= 1.9.4 - 2026-11-19 =
Serveri koormuse vähendamine pärast 19.11 serveririket (nõuded N1–N7).
* **Vahemälu tühjendatakse ainult slaideriga lehtedel**, mitte kogu saidil. Need lehed on avaleht igas keeles, lehed, kust leiti slaideri vidin, plokk või lühikood, lehed, kus slaiderit näidati, ja seadetes lisatud aadressid. FlyingPress tühjendab ja eellaadib ainult need lehed. Kogu saidi eellaadimist ei käivitata. Seaded → Lehe vahemälu näitab, milliseid lehti tühjendatakse.
* **Elementori CSS-i ei tühjendata enam.** Slaideri stiilid tulevad plugina enda failidest.
* **Cloudflare saab ainult nende lehtede aadressid**, mitte kunagi „kõik tühjaks“. Testsaidilt (test., staging. jne) Cloudflare'i päringut ei tehta, isegi kui token on sisestatud.
* **Üks tühjendus ühe salvestamise kohta.** Tühjendus toimub 30 s pärast viimast salvestust; slaidide ja seadete korraga salvestamine ning mitu kampaaniamuudatust järjest teevad ühe tühjenduse.
* **Plugina uuendamine järgib automaatse tühjenduse seadet.** Kui see on väljas, ei tühjendata midagi.
* **Admini eelvaated hoiavad serveri protsesse vähem kinni.**
* Vanad eelvaatepäringud katkestatakse.
* Server teeb ühe kasutaja kohta korraga ainult ühe eelvaate ja annab kiire „hõivatud“ vastuse, mille peale admin proovib hetke pärast uuesti.
* Ükski eelvaade ei oota üle 10 s.
* Ülemine eelvaade küsitakse serverist ainult pärast salvestamist või eelvaate valikute muutmist.
* Admin-leht ei renderda avamisel enam kõiki slaide. Pisipildid laaditakse alles nähtavale tulles ja väikeses mõõdus.

= 1.9.3 - 2026-11-18 =
* Parandus: lohistamisega sätitud laius (nt 1,15×) on lahtri laiuse valikus näha ka pärast lahtrite ümberjärjestamist.
* Parandus: eelvaate ⇄ pide ja paigutusjoon on vähendatud eelvaates tavalises suuruses, et neid oleks lihtne haarata.

= 1.9.2 - 2026-11-18 =
* Uus: kollaaži saab slaidi eelvaates hiirega sättida.
* Fotost kinni võttes nihutad fotot tema lahtri sees (sama mis fookuspunkt, eraldi arvutile ja mobiilile).
* Kahe lahtri vahelist serva lohistades muudad nende laiust sujuvalt; mobiilivaates muutub mobiili laius.
* Foto kohal ilmuvast ⇄ pidemest (või värvipinnast endast) lohistades viid lahtri teise kohta.
* Muudatus: lahtri laius salvestub 0,05 täpsusega, et lohistatud laius jääks täpselt selliseks.

= 1.9.1 - 2026-11-18 =
* Uus: värvipinna laiuseks saab valida ka 0,25× (kitsas värviriba, nt slaideri servades).

= 1.9.0 - 2026-11-18 =
* Uus: kollaaži saab lisada **värvipinna** – tühja lahtri, kus paistab slaidi taustavärv ja millele saab tekstitööriistaga sõnumi panna. Nii saab teha bänneri „foto – sõnum – foto“ ilma pilditöötlusprogrammita. Värvipinda saab lohistada kuhu tahes, ka servadesse (nt värv – foto – värv – foto – värv).
* Uus: igal kollaaži lahtril on laius „piltides“ (0,5×–4×), nt foto 1×, värvipind 3×, foto 1×.
* Uus: mobiilis saab valida „Vali iga lahtri jaoks“ ning määrata iga lahtri all, kas ja kui laialt seda mobiilis näidatakse (nt üks foto + värvipind).
* Uus: värvipinna kõrval olev foto sulandub sujuvalt taustaks; sulandumise laiust saab muuta (Hajutus värvipinda %, 0 = terav serv).
* Uus: nupp „Tekst siia“ paigutab tekstiploki värvipinna keskele (arvuti või mobiili vaate jaoks).
* Uus: taustaks saab valida ka kahe värvi ülemineku (vasakult paremale, ülalt alla või diagonaalis).
* Uus: värvivalijate kõrval on pipett (Chrome/Edge), millega saab värvi võtta otse ekraanilt, nt eelvaate fotolt.
* Parandus: vahekaart näitas „Kollaaž (2–5 pilti)“, kuigi lubatud on 7 lahtrit.
* Parandus: mitme foto korraga valimine ei kirjuta enam üle järgmisi lahtreid, vaid lisab fotod valitud lahtri järele.

= 1.8.3 - 2026-11-18 =
* Parandus: slaidide lehe ülemine eelvaade ei vilgu enam kitsamas aknas ega vähendatud brauseri suumiga. Eelvaate raamis ilmus ümardamise tõttu korraks kerimisriba, mis tegi slaideri kitsamaks, riba kadus jälle ja nii kordus see mitukümmend korda sekundis. Nüüd ei kuva eelvaate raam kunagi kerimisriba.

= 1.8.2 - 2026-11-18 =
* Parandus: kui brauseri akna suurus või suum muutus, läks slaidide lehe ülemine eelvaade liiga laiaks – slaider nihkus paremale, osa jäi kasti taha peitu ja leht hakkas külgsuunas kerima. Nüüd täidab eelvaade alati kasti ja arvutab vähenduse uuesti.

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

Full history: CHANGELOG.md on GitHub.
