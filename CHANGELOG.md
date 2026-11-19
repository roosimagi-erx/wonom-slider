# Changelog

## 1.9.6 - 2026-11-19

- Parandus: serveri eelvaatelukk on nüüd aatomiline (andmebaasi `INSERT IGNORE`). Kui sama kasutaja eelvaatepäringuid tuleb korraga mitu, renderdab server ainult ühte ja teised saavad kohe vastuse „hõivatud“. Varem võisid päris samaaegsed päringud luku kontrollist mööda minna.

## 1.9.5 - 2026-11-19

- Parandus: ingliskeelne avaleht (`/en/`) on tühjendatavate lehtede hulgas. WPML-i keele avaleht võetakse nüüd WPML-i enda keelevahetaja aadressist.
- Muudatus: ühte liiki eelvaatepäringuid on serveri poole teel korraga ainult üks. Kui klõpsata kiiresti, saadetakse uus päring alles siis, kui eelmine on lõppenud, ja vahepealsed muudatused liidetakse üheks päringuks. Slaidi eelvaade joonistatakse 0,25 s pärast viimast klõpsu.
- Muudatus: kui WP-Cron ei käi (nagu testsaidil), küsib avatud admin-leht ajastatud tühjenduse ise, kui see on käes. Ka siis tehakse ainult üks tühjendus.

## 1.9.4 - 2026-11-19

Serveri koormuse vähendamine pärast 19.11 serveririket (nõuded N1–N7).

- **Vahemälu tühjendatakse ainult slaideriga lehtedel**, mitte kogu saidil. Need lehed on avaleht igas keeles, lehed, kust leiti slaideri vidin, plokk või lühikood, lehed, kus slaiderit näidati, ja seadetes lisatud aadressid. FlyingPress tühjendab ja eellaadib ainult need lehed. Kogu saidi eellaadimist ei käivitata. Seaded → Lehe vahemälu näitab, milliseid lehti tühjendatakse.
- **Elementori CSS-i ei tühjendata enam.** Slaideri stiilid tulevad plugina enda failidest.
- **Cloudflare saab ainult nende lehtede aadressid**, mitte kunagi „kõik tühjaks“. Testsaidilt (test., staging. jne) Cloudflare'i päringut ei tehta, isegi kui token on sisestatud.
- **Üks tühjendus ühe salvestamise kohta.** Tühjendus toimub 30 s pärast viimast salvestust; slaidide ja seadete korraga salvestamine ning mitu kampaaniamuudatust järjest teevad ühe tühjenduse.
- **Plugina uuendamine järgib automaatse tühjenduse seadet.** Kui see on väljas, ei tühjendata midagi.
- **Admini eelvaated hoiavad serveri protsesse vähem kinni.**
  - Vanad eelvaatepäringud katkestatakse.
  - Server teeb ühe kasutaja kohta korraga ainult ühe eelvaate ja annab kiire „hõivatud“ vastuse, mille peale admin proovib hetke pärast uuesti.
  - Ükski eelvaade ei oota üle 10 s.
  - Ülemine eelvaade küsitakse serverist ainult pärast salvestamist või eelvaate valikute muutmist.
  - Admin-leht ei renderda avamisel enam kõiki slaide. Pisipildid laaditakse alles nähtavale tulles ja väikeses mõõdus.

## 1.9.3 - 2026-11-18

- Parandus: lohistamisega sätitud laius (nt 1,15×) on lahtri laiuse valikus näha ka pärast lahtrite ümberjärjestamist.
- Parandus: eelvaate ⇄ pide ja paigutusjoon on vähendatud eelvaates tavalises suuruses, et neid oleks lihtne haarata.

## 1.9.2 - 2026-11-18

- Uus: kollaaži saab slaidi eelvaates hiirega sättida.
  - Fotost kinni võttes nihutad fotot tema lahtri sees (sama mis fookuspunkt, eraldi arvutile ja mobiilile).
  - Kahe lahtri vahelist serva lohistades muudad nende laiust sujuvalt; mobiilivaates muutub mobiili laius.
  - Foto kohal ilmuvast ⇄ pidemest (või värvipinnast endast) lohistades viid lahtri teise kohta.
- Muudatus: lahtri laius salvestub 0,05 täpsusega, et lohistatud laius jääks täpselt selliseks.

## 1.9.1 - 2026-11-18

- Uus: värvipinna laiuseks saab valida ka 0,25× (kitsas värviriba, nt slaideri servades).

## 1.9.0 - 2026-11-18

- Uus: kollaaži saab lisada **värvipinna** – tühja lahtri, kus paistab slaidi taustavärv ja millele saab tekstitööriistaga sõnumi panna. Nii saab teha bänneri „foto – sõnum – foto“ ilma pilditöötlusprogrammita. Värvipinda saab lohistada kuhu tahes, ka servadesse (nt värv – foto – värv – foto – värv).
- Uus: igal kollaaži lahtril on laius „piltides“ (0,5×–4×), nt foto 1×, värvipind 3×, foto 1×.
- Uus: mobiilis saab valida „Vali iga lahtri jaoks“ ning määrata iga lahtri all, kas ja kui laialt seda mobiilis näidatakse (nt üks foto + värvipind).
- Uus: värvipinna kõrval olev foto sulandub sujuvalt taustaks; sulandumise laiust saab muuta (Hajutus värvipinda %, 0 = terav serv).
- Uus: nupp „Tekst siia“ paigutab tekstiploki värvipinna keskele (arvuti või mobiili vaate jaoks).
- Uus: taustaks saab valida ka kahe värvi ülemineku (vasakult paremale, ülalt alla või diagonaalis).
- Uus: värvivalijate kõrval on pipett (Chrome/Edge), millega saab värvi võtta otse ekraanilt, nt eelvaate fotolt.
- Parandus: vahekaart näitas „Kollaaž (2–5 pilti)“, kuigi lubatud on 7 lahtrit.
- Parandus: mitme foto korraga valimine ei kirjuta enam üle järgmisi lahtreid, vaid lisab fotod valitud lahtri järele.

## 1.8.3 - 2026-11-18

- Parandus: slaidide lehe ülemine eelvaade ei vilgu enam kitsamas aknas ega vähendatud brauseri suumiga. Eelvaate raamis ilmus ümardamise tõttu korraks kerimisriba, mis tegi slaideri kitsamaks, riba kadus jälle ja nii kordus see mitukümmend korda sekundis. Nüüd ei kuva eelvaate raam kunagi kerimisriba.

## 1.8.2 - 2026-11-18

- Parandus: kui brauseri akna suurus või suum muutus, läks slaidide lehe ülemine eelvaade liiga laiaks – slaider nihkus paremale, osa jäi kasti taha peitu ja leht hakkas külgsuunas kerima. Nüüd täidab eelvaade alati kasti ja arvutab vähenduse uuesti.

## 1.8.1 - 2026-11-18

- Parandus: slaidide lehe ülemine eelvaade ei värise enam. Eelvaade joonistati suures ekraani laiuses ja vähendati CSS-i „transform“ abil, mistõttu fotod ja tekstid värelesid slaidide vahetumise ajal, eriti suurel monitoril ja vähendatud brauseri suumiga. Nüüd vähendab brauser lehe otse (CSS zoom) ja eelvaade on terav ja rahulik; mõõdud ja kärbe on samad mis poes.

## 1.8.0 - 2026-11-18

- Parandus: nupp „Kontrolli uuendusi kohe“ ei pane enam kõiki pluginaid uuesti kontrollima. Varem kustutati WordPressi kogu uuenduste nimekiri ja käivitati kõigi pluginate kontroll (ka wordpress.org-i päring), mis võib suures poes aeguda (HTTP 524). Nüüd värskendatakse ainult Wonom Slideri enda kirjet.
- Parandus: plugina kustutamisel eemaldatakse alati tehnilised seaded, sh GitHubi, DeepL-i ja Cloudflare'i võtmed, ajutised andmed ja ajastatud sündmused. Slaidid, slaideri seaded ja kampaaniate seosed kustutatakse nagu enne ainult siis, kui „Kustuta andmed eemaldamisel“ on sees.
- Uus: Seaded → Lehe vahemälu näitab, millal WP-Cron viimati päriselt käis, ja hoiatab, kui see on üle tunni seisnud. Kui ajastatud vahemälu tühjendus on cron'i seisaku tõttu hilinenud, tehakse see ära järgmisel admin-lehe avamisel.
- Muudatus: admin-liidese tekstid tõlgitakse nüüd WordPressi standardse JS-tõlke (wp.i18n) kaudu; kaasas on .po-, .mo- ja JSON-tõlkefailid. Liidese välimus ja tekstid ei muutu.
- Parandus: peidetud elemendid (atribuut hidden) jäävad admin-lehel alati peidetuks.
- readme.txt: muudatuste logi genereeritakse nüüd CHANGELOG.md-st, „Tested up to: 7.1“.

## 1.7.12 - 2026-11-10

- Parandus: eelvaade, lava ja pisipildid kasutavad slaideri kuju, mis poes päriselt eetris on (esimene eetris olev pildiga slaid). Varem võttis lava kuju muudetavast slaidist endast ja eelvaade linnukesega „Näita ka ajastatud ja väljalülitatud slaide“ esimesest, ka aegunud slaidist, nii et need võisid poest erineda.

## 1.7.11 - 2026-11-10

- Muudatus: lava, slaidide pisipildid ja vaikimisi eelvaade joonistatakse nüüd sinu enda brauseriakna mõõtudes (nupp „Sinu ekraan“). Mida näed redaktoris, seda näed samal ekraanil ka poes 100 % suumiga. Varem kasutati tüüpilise sülearvuti mõõte ja lava oli suurel monitoril palju madalam kui pood. Akna suuruse muutmisel joonistatakse eelvaated uuesti.
- Eelvaate nupud „Sülearvuti“ ja „Suur ekraan“ jäävad alles, et saaksid kontrollida ka teisi ekraane.

## 1.7.10 - 2026-11-10

- Parandus: kollaaži täitmise vihje ümardab nüüd õigesti: „fotorida on slaidist madalam“ ilmub ainult siis, kui see päriselt nii on, ja sülearvuti jaoks pakutakse õiget lisafotode arvu (5 fotoga: lisa 2).

## 1.7.9 - 2026-11-10

- Parandus: „Näita fotosid tervena“ režiimis on fotod alati kõrvuti (seadistatud vahega). Hajumise ja hägu üleminek kattis seni iga foto serva 90 px ulatuses, mistõttu fotod polnud päriselt terved ja rida täitis seitsme fotoga ainult umbes 70 % laiusest. Ülemineku valik on selles režiimis välja lülitatud ja selgitusega.

## 1.7.8 - 2026-11-10

- Uus: kollaaži valik „Fotode paigutus“. „Näita fotosid tervena“ hoiab iga foto oma kujus ja ei kärbi midagi: fotod on slaidi kõrgused (või veidi madalamad, kui need kokku slaiderist laiemaks läheksid) ja paigutatakse keskele, vaba ruum on slaidi taustavärviga. Redaktor näitab, mitu protsenti laiusest fotod suurel monitoril ja sülearvutil täidavad ning mitu fotot lisada, et laius täituks. Senine „Täida slaid (fotosid kärbitakse)“ jääb vaikimisi.
- Uus: kollaažis võib olla kuni 7 fotot (enne 5). Uus pesa tekib, kui eelmised on täis.
- Parandus: kui slaideri esimene slaid on kollaaž, arvutatakse slaideri kuju nüüd kõigi fotode tegelikest mõõtudest, mitte ainult esimese foto järgi.

## 1.7.7 - 2026-11-10

- Muudatus: arvutipildi juhend lähtub nüüd sellest, et slaider on igal ekraanil täies kõrguses (40 % ekraanist, nagu enne). Soovitus on kaks mõõtu: kogu pilt 2400 × 650 px (suurel monitoril paistab tervena) ja turvaala keskel 2400 × 460 px, mis paistab igal ekraanil. Turvaala on juhendis joonisel näha. Näod, tooted ja tekst käivad turvaala sisse, ülemisse ja alumisse ribasse ainult taevas, põrand või taust. 1.7.6 soovitatud 2400 × 460 tegi slaideri suurel monitoril madalaks ja kollaažidest lõigati liiga palju.
- Uus: eelvaates on nupp „Suur ekraan“ (brauseri ala 2560 × 1300 px) lisaks „Sülearvutile“, nii et näed nii kõige rohkem kui ka kõige vähem kärbitud vaadet.

## 1.7.6 - 2026-11-10

- Parandus: arvutipildi soovitus ja eelvaade lähtuvad nüüd tüüpilisest sülearvutist (brauseri ala 1536 × 730 px). Kõrguse piirang on protsent ekraani kõrgusest, nii et sülearvutil ja tavalisel 1080p monitoril on nähtav ala kõige laiem ja pilti kärbitakse kõige rohkem. 1.7.5 arvutas suurema ekraani järgi ja soovitas 2400 × 620, mis sülearvutil kaotas ikka umbes 20 % kõrgusest. 40 % piiranguga on õige mõõt 2400 × 460 px: see on igal levinud ekraanil terve, suuremal ekraanil on slaider lihtsalt madalam.
- Külgede suhe kuvatakse eesti keeles komaga (5,2 : 1, 4 : 5).

## 1.7.5 - 2026-11-10

- Parandus: arvutivaate eelvaade, lava ja slaidide pisipildid joonistatakse nüüd päris arvutilaiuses (brauseri ala 1600 × 1040 px) ja vähendatakse ekraanile. Varem joonistati need kitsamalt ja laial ekraanil tekkiv kärbe (pildi üla- ja alaosa) adminis ei paistnud. Nüüd näed adminis täpselt sama kärbet, mida näeb külastaja.
- Uus: Seaded → Pildi mõõdud. Juhend, mis mõõdus arvuti- ja mobiilipildid teha, arvutatud sinu kõrguse piirangutest (praeguste seadetega arvuti 2400 × 620 px, mobiil 1080 × 1350 px), koos reeglitega teksti, failivormingu ja kollaažifotode kohta. Pildivälja all olev soovitus kasutab samu arvutatud mõõte (enne oli seal vale 1920 × 660).

## 1.7.4 - 2026-11-10

- Tehniline: uued versioonid avaldatakse nüüd otse väljalaske skriptist (nagu Wonom KKK-l) ja GitHub kontrollib iga väljalaske üle. Kui kontroll ebaõnnestub, ei pakuta seda versiooni poodidele üldse. Plugina töös muudatusi pole.

## 1.7.3 - 2026-10-05

- Parandus: kui automaatsel kampaaniaslaidil pole ühtki kampaaniat võtta, tühjendatakse ka redaktoris slaidi varasemad tekstid, et vana sisu ei jääks kuhugi alles.

## 1.7.2 - 2026-10-05

- Muudatus: kampaaniaga seotud slaidil (valitud kampaania või automaatne) tuleb kogu sisu ainult kampaaniast. Slaidi enda varasemad tekstid (nt ingliskeelne väike rida või nupu tekst) ei jää enam alles: väljad, mida kampaanial pole, on tühjad igas keeles. Nuppu kampaaniaslaidil ei ole; kui kampaanial on link, on kogu slaid klõpsatav.

## 1.7.1 - 2026-10-05

- Uus: silt (sooduskoodi pill) on slaidi kaupa eraldi seadistatav: font, suurus arvutis ja mobiilis, paksus, suurtähed, tähevahe, tausta- ja tekstivärv, polsterdus ja nurgad. Tühi = senine vaikimisi (nupu värvid). Muudatus on laval kohe näha; värvi saab ✕-ga vaikimisi tagasi panna.

## 1.7.0 - 2026-10-05

- Uus: sisuallikas „Praegu eetris olev kampaania (automaatne)”. Slaid võtab alati Kampaaniaribal parasjagu eetris oleva kampaania (tekstid, sooduskood, link, ajakava), kampaaniat ei pea valima. Kui ühtki eetris pole, võtab slaid järgmise ajastatud kampaania ja on kuni selle alguseni peidetud (olek „Ootab kampaaniat”); kui ka tulemas pole ühtki, on slaid peidetud. Kampaania muutmine, kustutamine või lisamine Kampaaniaribal tühjendab vahemälu ja planeerib ajastuse ümber.

## 1.6.6 - 2026-10-05

- Parandus: 1.6.5 edasilükatud piltide atribuut `data-src` võeti WoodMarti enda lazy-load skripti poolt üle ja kõik pildid laaditi ikkagi kohe. Nüüd on atribuudid `data-wonom-src` / `data-wonom-srcset`, mida teised skriptid ei puutu.

## 1.6.5 - 2026-10-05

- Jõudlus: ainult esimese slaidi pildid laaditakse kohe. Teiste slaidide pildid (sh kollaaži pildid ja mobiilipilt) on HTML-is edasi lükatud (data-src) ja skript laadib need just enne näitamist: teine slaid brauseri jõudehetkel, ülejäänud vahetuse eel. Varem laadis leht kõigi slaidide täispildid korraga (esilehel 7,7 MB), sest läbipaistvad slaidid on vaate sees ja loading=lazy neid ei peatanud.

## 1.6.4 - 2026-10-05

- Parandus: slaidide nimekirja pisipilt on nüüd omaette dokument (sama meetod mis suurel eelvaatel ja laval): slaideri tegelik HTML ja stiilileht arvuti laiuses, vähendatult. Varem joonistus pisipilt lehe enda sees ja brauser võis sinna näidata vale sisu (nt lehe ülaosa teateid).
- Uus: kollaaži piltide järjekorda saab muuta lohistades – võta kinni pesa pealkirjast („Pilt 1”, „Pilt 2” …).

## 1.6.3 - 2026-10-05

- Uus: fookuspunkt eraldi arvutile ja mobiilile – nii ühe pildiga slaidil kui igal kollaaži pildil. Piltide plokis on lüliti „Fookuspunkt vaatele: Arvuti / Mobiil” (sama mis laval); sinine punkt = arvuti, lilla = mobiil. Lava uueneb kohe.

## 1.6.2 - 2026-10-05

- Parandus: eelvaade, lava ja pisipildid arvutavad kõrguse piirangu („% ekraanist”) tüüpilise ekraani järgi (arvuti 1080, tahvel 1024, mobiil 812 px), mitte iframe’i enda kõrgusest – 1.6.1-s kukkusid eelvaated seetõttu kokku.
- Uus: kollaažis kuni 5 pilti; mobiilis valik „kolme esimest”.

## 1.6.1 - 2026-10-05

- Uus: Seaded → Paigutus: maksimaalne kõrgus arvutile ja mobiilile (% ekraanist või px). Vaikimisi arvutis 40 % ekraanist; pilti kärbitakse slaidi fookuspunkti ümber, tekst jääb paigas. Suurel ekraanil ei võta slaider enam 70 % ekraanist.
- Muudatus: maksimaalne laius vaikimisi 1920 px (kõige levinum monitorilaius): sülearvutil täislaius, suuremal monitoril keskel.

## 1.6.0 - 2026-10-04

- Uus: sidumine Wonom Kampaaniaribaga. Slaidi sisu allikaks saab valida Kampaaniariba kampaania: pealkiri (1. rida), tekst (2. rida), väike rida (3. rida), silt (sooduskood), nupu link (koos automaatse kupongi rakendamisega) ja ajakava tulevad kampaaniast ning on slaidil lukus; mõlemad keeled kaasa. Pildid, asukoht, tüpograafia, värvid ja nupu tekst jäävad slaidil muudetavaks. Kampaania muutmine Kampaaniaribal muudab slaidi kohe.
- Uus: kampaania muutmise lehel on kast „Wonom Slider” linnukesega „Näita seda kampaaniat ka slaideris” ja malli valikuga. Linnuke loob seotud slaidi automaatselt (mallist kopeeritakse pildid, asukoht, tüpograafia, värvid), eemaldamine peidab slaidi, kampaania kustutamine kustutab slaidi. Üldine mall: Seaded → Kampaaniariba.
- Kampaaniariba koodi ei pea muutma; sidumine töötab ainult siis, kui Kampaaniariba on aktiivne.

## 1.5.6 - 2026-10-04

- Muudatus: slaidi nime saab muuta otse kaardi päises (avatud kaardil on nimi sisestusväli). Eraldi „Sisemine nimi” väli kadus.
- Parandus: slaidide lehe eelvaade tuleb nüüd REST-i kaudu (srcdoc), mitte esilehe päringuna; kiirendusplugina (FlyingPress) laisk pildilaadimine ja viivitatud JavaScript ei jäta eelvaates pilte enam näitamata.
- Uus: Seaded → Lehe vahemälu juhis, kuidas lisada „wonom-slider” kiirendusplugina Delay-JS väljajätmistesse, et slaider käivituks esilehel kohe.
- Parandus: WoodMart-teema laisk pildilaadimine (lazy.svg + data-src) on slaideri piltidel välja lülitatud – esimene pilt (LCP) laadib kohe ja eelvaade näitab pilte. Slaideri JS vahetab igaks juhuks ka teiste laiskade laadijate `data-src` ise sisse.
- Parandus: slaideri skript on märgitud kiirenduspluginatele „ära viivita / ära muuda” (data-no-delay jt).

## 1.5.5 - 2026-10-04

- Uus: Seaded → Navigeerimine: punktide asukoht (keskel / vasakul / paremal), eraldi valik mobiilile (sh „peidetud”) ja noolte peitmine mobiilis.

## 1.5.4 - 2026-10-04

- Parandus: kõrguse arvutus reageerib ainult laiuse muutusele ja väljaspool ResizeObserveri tagasikutset; kaob brauseri konsooli hoiatus „ResizeObserver loop”.

## 1.5.3 - 2026-10-04

- Parandus: mobiilis jäi automaatne vahetus pärast esimest puudutust seisma (puudutus tekitas „hiir on peal” pausi ja fookuse, mis ei lõppenud). Hover-paus töötab nüüd ainult hiirega seadmetel, puute- ja klõpsufookus esitust ei peata; ainult klaviatuurifookus peatab. Reduce Motion ei lülita enam autoplay’d välja.

## 1.5.2 - 2026-10-04

- Uus: slaidide nimekirja pisipilt on elav – näitab täpselt sama väljundit, mis esilehel (taust, pilt või kollaaž, tekstid, nupp), vähendatult. Uueneb salvestamisel.

## 1.5.1 - 2026-10-04

- Parandus: slaidide nimekiri näitas ainult tekstiga slaidi ja kollaažslaidi olekuna „Tühi”, kuigi need olid eetris. Nimekiri kasutab nüüd sama sisu-loogikat mis server. Pildita slaidil on pisipildi asemel tema taustavärv ja pealkiri.

## 1.5.0 - 2026-10-04

- Muudatus: „Tekstid ja nupp” on nüüd kahes veerus – vasakul sisu (silt, väike rida, pealkiri, tekst, nupud, alt-tekst), paremal sama rea kujundus ja tüpograafia (font, suurus arvutis/mobiilis, paksus, suurtähed, tähevahe, värvid, vahed, nupu nurgad). Eraldi Tüpograafia plokk kadus, nupu ja teksti värvid liikusid oma rea juurde.
- Uus: väikese rea (eyebrow) suurus arvutis ja mobiilis.

## 1.4.1 - 2026-10-04

- Parandus: slaideri külgede suhe võetakse esimeselt slaidilt, millel on pilt või kollaaž, mitte lihtsalt esimeselt slaidilt. Kui esimene slaid oli ainult tekstiga, jäi slaider mobiilis liiga madalaks ja tekst läks üle serva. Kui ühelgi slaidil pilti pole, on mobiilis suhe 4:3.

## 1.4.0 - 2026-10-04

- Uus: kollaažslaid – 2–4 pilti meediateegist kõrvuti, igal oma fookuspunkt; üleminek terav / sulandumine / hägu; piltide vahe; mobiilis kõik / kaks esimest / üks. Külgede suhe arvutatakse esimesest fotost. Komposiitpilti pole enam vaja Photoshopis teha.
- Uus: raam – pildi (või kollaaži) kaugus slaidi servast ja nurga raadius; raami ümber paistab slaidi taustavärv. Mobiilis on kaugus poole väiksem.

## 1.3.0 - 2026-10-04

- Muudatus: tüpograafia on ainult slaidi põhine. Üldseadete plokk „Tüpograafia” on eemaldatud; kõik samad valikud (fondid, suurused arvutile ja mobiilile, paksus, suurtähed, tähevahe, ridade vahe, vahe nupu ees) ja lisaks nupu nurgaraadius on slaidi plokis „Tüpograafia”. Tühi väli = vaikeväärtus, mis on kohatäitena näha.
- Muudatus: pealkirja HTML-tag (SEO) on nüüd Seaded → Paigutus all.

## 1.2.3 - 2026-10-04

- Muudatus: eelvaade on slaidide lehel vaikimisi avatud; „Peida eelvaade” jääb brauseris meelde. Eelvaate kõrgus järgib slaiderit, tühja ala alla ei jää.

## 1.2.2 - 2026-10-04

- Parandus: lava laadib veebifondi ka siis, kui slaidi font on alles valitud ja salvestamata.

## 1.2.1 - 2026-10-04

- Parandus: tühjad tõlke- ja tüpograafiaväljad jõudsid redaktorisse massiivina (`[]`), mistõttu uue slaidi esimene tõlge või tüpograafiamuudatus läks salvestamisel kaotsi. Nüüd alati objektid.

## 1.2.0 - 2026-10-04

- Uus: tüpograafia slaidi kaupa – pealkirja ja teksti font, suurused (arvuti/mobiil), paksus, suurtähed, tähevahe ning kaks vahet: ridade vahe (silt/väike rida/pealkiri/tekst) ja vahe nupu ees. Tühi väli = nagu üldseadetes. Lava uueneb kohe.
- Muudatus: tekstielementide vahed on nüüd CSS-muutujad (`--ws-gap`, `--ws-gap-btn`); mobiili pealkirja tähevahe järgib seadistatud tähevahet.

## 1.1.0 - 2026-10-04

- Uus: slaidi redaktoris on lava – slaid kuvatakse täpselt nii nagu esilehel (sama server-render), arvuti- ja mobiilivaates, tekstid otse pildi peal.
- Uus: tekstiploki vaba paigutus – lohista laval, nooleklahvid (1 %, Shift 5 %), laiuse pide; eraldi asukoht mobiilile. Ruudustik-joondus jääb kiirvalikuna alles.
- Uus: taustavärv slaidi kohta; slaid võib olla ka ilma pildita, ainult tekstiga.
- Uus: fondid – pealkirja ning teksti/nupu font eraldi (saidi vaikefont, kureeritud veebifondid Bunny Fontsist või oma CSS), pealkirja paksus, suurtähed ja tähevahe.
- Uus: lehe vahemälu tühjendamine Kampaaniariba eeskujul – FlyingPress, WP Rocket, LiteSpeed, W3TC, Super Cache, Fastest Cache, Cache Enabler, SiteGround, Breeze, Autoptimize, Elementor; Cloudflare API; käsitsi nupp; automaatselt salvestamisel, ajastatud hetkedel ja pärast plugina uuendust.

## 1.0.2 - 2026-10-04

- Parandus: mobiilis (Elementori veergsuunaline konteiner) ei andnud ainult külgede suhtest tulenev kõrgus vidinale mõõtu ja järgmine sektsioon joonistus bänneri peale. Slaideri rada saab nüüd JS-iga täpse pikslikõrguse (ResizeObserver).
- Parandus: seadete lehe nupp „Uuenda kohe” andis vigase lingi (&amp;), WordPress teatas aegunud viitest.

## 1.0.1 - 2026-10-04

- Parandus: teemad (nt WoodMart), mis kirjutavad kõigi nuppude stiilid üle, lükkasid nooled slaiderist välja ja tegid punktid kandiliseks. Noolte ja punktide stiilid on nüüd kaitstud.

## 1.0.0 - 2026-10-04

- Esimene versioon.
- Slaidikaardid lohistatava järjekorraga, lüliti, koopia, kustutamine.
- Arvuti- ja mobiilipilt, fookuspunkt klõpsuga, eraldi mobiili joondus/suurused/„ainult pilt“.
- Tekstid: väike rida, pealkiri, tekst, kuni kaks nuppu, silt, alt-tekst; keelevahelehed (Polylang/WPML või käsitsi loend); DeepL tõlkenupp.
- Ajakava: algus ja lõpp kalendrist, kiirvalikud, olekurida, kuukalendri vaade; vahemälu tühjendamine ajastatud hetkel.
- Seaded: esitus, navigeerimine, külgede suhted, murdepunkt, tüpograafia, pealkirja tag, oma CSS, eksport/import.
- Eelvaade arvuti/tahvli/mobiili laiuses otse redaktoris.
- Elementori vidin (töötab redaktoris), lühikood, Gutenbergi plokk, PHP-funktsioon.
- Automaatsed uuendused GitHub Releases'ist (avalik või privaatne hoidla), GitHub Action ZIP-i ehitamiseks.
- Eesti tõlge.
