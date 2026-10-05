# Changelog

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
