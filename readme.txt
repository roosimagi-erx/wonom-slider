=== Wonom Slider ===
Contributors: wonomdigital
Tags: slider, hero, banner, woocommerce, elementor, campaign
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.11.1
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

= 1.11.1 - 2026-11-26 =
### Eelvaade jääb tekstide muutmise ajal nähtavale
Seni oli slaidi eelvaade redaktori ülaosas. Teksti suuruse muutmiseks tuli alla kerida ja tulemuse nägemiseks jälle üles. Nüüd näeb muudatust samas vaates.
* **Eelvaade kerib kaasa.** Kui kerid tekstide ja kujunduse juurde, jääb väiksem eelvaade Wonom Slideri päise alla ekraani ülaossa. Suuruse, fondi või värvi muudatus on seal kohe näha. Kui kerid tagasi üles, läheb eelvaade oma kohale tagasi.
* **Nupp „Eelvaade kerib kaasa“** eelvaate kohal lülitab selle sisse või välja. Kinnitatud eelvaate nurgas on ✕, mis lülitab selle samuti välja. Valik jääb meelde.
* **Telefonivaates** näidatakse kinnitatud eelvaates telefoni slaidi päris suuruses, aga ainult tekstiploki ümbrust, et väljadele jääks ruumi.
* **Madalal ekraanil** (alla 700 px kõrgusega aknas) eelvaade kaasa ei keri.
* **Klõps eelvaate tekstil viib selle välja juurde.** Klõps pealkirjal, sildil, väikesel real või tekstil kerib redaktori selle rea juurde ja paneb kursori suuruse väljale, telefonivaates mobiili suuruse väljale. Suurust saab kohe nooleklahvidega muuta. Klõps nupul kerib nupu rea juurde.
* Parandus: lihtne klõps eelvaates ei muuda enam tekstiploki paigutust „Vabaks“ ega märgi slaidi muudetuks. Seda teeb ainult päris lohistamine.
* Parandus: kui eelvaade joonistatakse uuesti, hoiab see oma kõrgust, nii et all olevad väljad ei hüppa.

= 1.11.0 - 2026-11-24 =
### Kellaajad nagu Kampaaniaribas: lõpp 00:00 tähendab südaööd
Seni loeti slaidi lõpu minut slaidi sisse: lõpp 23:59 kestis südaööni, aga lõpp 00:00 kestis kella 00:01-ni. Kampaaniariba loeb aega teistpidi ja nii tuli kahes pluginas samale ajale panna erinev kellaaeg. Kui slaidi lõpuks pandi 00:00, oli vahetuse ajal üks minut kaks kampaaniaslaidi korraga eetris.
* **Uus reegel (sama mis Kampaaniaribas):** algus kuulub slaidi sisse, lõpp mitte. Slaid on eetris alguse hetkest kuni lõpu hetkeni, lõpu hetk kuulub juba järgmisele slaidile. Päeva lõpuni kestva slaidi lõpuks pane **järgmine kuupäev kell 00:00**, näiteks 24.–25.11 kestval slaidil algus 24.11 00:00 ja lõpp 26.11 00:00. Järgmine slaid algab samal hetkel ja vahetus on täpne, ilma kattumise ja vaheta.
* **Juba sisestatud ajad ei muutu.** Uuendamisel muudetakse olemasolevad lõpud kujul „23:59“ kujule „järgmine päev 00:00“ (sama hetk, uues kirjapildis). Kui sisestad edaspidi lõpuks 23:59, salvestatakse see samuti järgmise päeva 00:00-na.
* **Automaatne kampaaniaslaid** kasutab nüüd täpselt Kampaaniariba lõpuhetke. Varem lisandus sellele üks minut.
* Kiirvalikud „+7 päeva“, „+14 päeva“ ja „Kuu lõpp“ annavad nüüd järgmise päeva 00:00. Kalendris ei näidata kell 00:00 lõppevat slaidi enam järgmise päeva lahtris. Lõpu välja all on selgitus.
### Tühjendus ootab serveri vaiksed ajad ära
* **Uus seade „Vaiksed ajad“** (Seaded → Lehe vahemälu). Kui slaideriga lehtede tühjendus langeb mõnda neist vahemikest, tehakse see kohe pärast vahemiku lõppu. Vaikimisi on seal poe serveri sünkide ajad (iga tund :28–:33, 08:55–09:35, 13:55–14:50, 15:55–16:35, 23:55–00:25, 01:55–02:35). Salvestada võib ka nendel aegadel, ainult uus slaid ilmub avalehele siis vahemiku lõpus. Tühjaks jäetud väli lülitab ootamise välja.
* Seadetes on näha, milleni tühjendus lükati. Lahti olev slaideri admin-leht käivitab selle õigel ajal ise.
* Käsitsi nupp „Tühjenda vahemälu kohe“ töötab endiselt kohe.
### Muu
* Parandus: kui plugin paigaldatakse ZIP-failist (deaktiveeri → laadi üles → aktiveeri), tühjendatakse pärast aktiveerimist slaideriga lehed (kui automaatne tühjendus on sees). Varem jäid vahemällu vana versiooni lehed ilma tulevaste slaidideta, kuni slaiderit korra salvestati. Nupuga „Uuenda“ toimis see ka varem.
* Uus: slaidide nimekirjas ja redaktoris on hoiatus, kui slaidi teise keele (nt inglise) tekst puudub või on sõna-sõnalt sama mis mõnel teisel slaidil, kuigi eestikeelne tekst on erinev. Nii jääb silma kopeeritud slaidi parandamata tõlge.
* Arendajale: filter `wonom_slider_quiet_ranges` muudab vaikseid aegu.

= 1.10.0 - 2026-11-19 =
### Ajastatud slaid vahetub täpselt õigel hetkel, ilma vahemälu tühjendamata
Seni otsustas server lehe kokkupanemise hetkel, millised slaidid on eetris. Vahemälus olev leht näitas seetõttu vana seisu, kuni ajastatud töö (cron) lehe uuesti tühjendas. Kui cron käis iga 5 minuti tagant, vahetus slaid mõni minut pärast õiget aega.
* **Tulevased slaidid on lehel ette olemas.** Slaid, mis algab lähema 14 päeva jooksul, pannakse lehele kohe kaasa, peidetult ja koos oma algus- ja lõpuajaga. Külastaja brauser näitab seda täpselt alguse hetkel ja peidab lõppenud slaidi täpselt lõpu hetkel. See toimib ka vahemälust tulnud lehel ja ka siis, kui leht on külastajal juba lahti: slaid vahetub ilma lehte uuesti laadimata.
* **Vahetuse hetkel ei tühjendata enam midagi.** Kampaania algus südaööl ei vaja ei croni ega vahemälu tühjendust. Slaideriga lehed tühjendatakse varuks alles 35 minutit hiljem (ainult need lehed), et leht oleks õige ka ilma skriptita.
* **„Automaatne kampaania“ slaid teab ka järgmist kampaaniat.** Slaid pannakse lehele iga praeguse ja lähema 14 päeva jooksul algava kampaania kohta. Korraga on näha üks: kui üks kampaania lõpeb ja teine algab samal hetkel, vahetub slaid kohe.
* **Tulevase slaidi pilte ette ei laadita.** Pildid laaditakse umbes kaks minutit enne slaidi algust. Kui külastaja avab pärast vahetust vana vahemälus oleva lehe, näidatakse kohe õiget slaidi ja selle pilt laaditakse esimesena.
* **Vale kellaga seade.** Brauser kasutab seadme kella. Kui vahetus on lähedal, võrdleb ta seda korra poe serveri kellaga (staatilise faili päisest, ilma PHP-päringuta) ja kui seadme kell on üle kahe minuti vale, arvestab erinevust.
* **Lõpuaeg kehtib minuti lõpuni.** Slaid, mille lõpp on 23:59, on eetris kuni südaööni. Varem kadus see kell 23:59:00, nii et 00:00 algava slaidini jäi minutine vahe.
### Muu
* Parandus: pärast plugina välja- ja sisselülitamist ajastatakse slaidide varutühjendus ise uuesti. Varem tuli selleks slaiderit korra salvestada.
* Muudatus: kui ajastatud tühjendus on hiljaks jäänud (cron ei käinud), pannakse see admin-lehe avamisel järjekorda nagu pärast salvestamist. Varem tehti tühjendus kohe selle lehe avamise ajal.
* Muudatus: DeepL-i tõlkepäringu ajapiir on 15 sekundit (oli 20).
* Arendajale: filter `wonom_slider_tick_delay` muudab varutühjenduse viivitust (vaikimisi 2100 sekundit).

= 1.9.7 - 2026-11-19 =
* Muudatus: plugina asendamine ZIP-i üleslaadimisega („Asenda praegune versioon üleslaadituga“) käitub nagu uuendus. Kui automaatne tühjendus on sees, tühjendatakse 30 s pärast slaideriga lehed. Kui see on väljas, ei tühjendata midagi.

= 1.9.6 - 2026-11-19 =
* Parandus: serveri eelvaatelukk on nüüd aatomiline (andmebaasi `INSERT IGNORE`). Kui sama kasutaja eelvaatepäringuid tuleb korraga mitu, renderdab server ainult ühte ja teised saavad kohe vastuse „hõivatud“. Varem võisid päris samaaegsed päringud luku kontrollist mööda minna.

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

Full history: CHANGELOG.md on GitHub.
