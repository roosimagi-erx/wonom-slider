# Wonom Slideri üleviimine live-poodi (emmaandtheo.ee)

Eesmärk: Slider Revolution asendada Wonom Slideriga ilma katkestuseta ja nii, et vahemälu käitumine on live-saidil eraldi tõestatud.

Põhimõte: plugina paigaldamine ja seadistamine ei muuda esilehte enne, kui Elementoris vahetatakse vidin. Kogu ettevalmistus tehakse „pimedalt” ja ainus nähtav hetk on üks Elementori avaldamine, mida saab ühe klõpsuga tagasi võtta.

## 0. Ettevalmistus (enne live-saiti puutumata)

1. **Testsait lõplikuks.** Vaata testsaidil slaidid ja seaded üle täpselt sellisena, nagu need peavad live’is olema (tekstid, pildid, asukohad, tüpograafia, punktide asukoht mobiilis). Kustuta näidisslaidid, mida live’i ei taha.
2. **Ekspordi JSON.** Testsait → Wonom Slider → Seaded → Andmed → *Ekspordi JSON*. Fail sisaldab slaide ja seadeid. Pildid on JSON-is meediateegi ID-dena; kuna testsait on live’i koopia, on sama ID live’is sama pilt. Kontroll: ava Meedia-teegis live’is pilt ja võrdle ID-d (URL-is `post=301067`). Kui mõni pilt on üles laetud alles testsaidile, lae see live’i meediateeki ja vali slaidil uuesti.
3. **Versioon.** GitHubis on viimane release (praegu 1.6.0). Live võtab sama ZIP-i: `https://github.com/roosimagi-erx/wonom-slider/releases/latest`. Automaatsed uuendused töötavad live’is samamoodi nagu testis.
4. **Varukoopia.** Enne 1. sammu tee live’is hostingu varukoopia (snapshot) või vähemalt andmebaasi eksport. Elementor hoiab lehe versioone ise (History → Revisions), aga täisvarukoopia on odav kindlustus.
5. **Aeg.** Tee vahetus vaiksel ajal (hommikul enne 9 või hilisõhtul), kui ostjaid on vähe. Kogu nähtav muudatus kestab sekundeid.

## 1. Paigaldus live’is (nähtamatu samm)

1. Pluginad → Lisa uus → Laadi üles → `wonom-slider.zip` (GitHub release) → Paigalda → Lülita sisse.
2. Kontroll: menüüsse tekib Wonom Slider, Pluginad-lehel on real „Kontrolli uuendusi” ja „GitHub”. Esileht ei muutu.
3. Wonom Slider → Seaded → Andmed → *Impordi JSON* (fail 0.2-st). Import asendab tühja loendi, nii et midagi ei kaota.
4. Slaidide lehel: igal kaardil peab olema elav pisipilt päris pildiga. Kui mõnel on tühi või vale pilt, ava slaid ja vali pilt live meediateegist.
5. Seaded üle: Navigeerimine (punktid mobiilis), Lehe vahemälu (automaatne tühjendus sees), Kampaaniariba mall, keeled (WPML tuvastatakse ise).
6. Eelvaade (slaidide lehel, vaikimisi avatud): vaata arvuti/tahvel/mobiil ja Eesti/English. See on päris live-render päris teemaga, ilma et keegi seda näeks.

## 2. Vahemälu test live’is (enne vidina vahetust)

Esileht on veel Slider Revolutioniga, seega saab vahemälu käitumist testida ohutult ühel eraldi lehel.

1. **Testleht.** Loo Elementoris uus leht „Slaideri test” (mustand või avaldatud, aga menüüs mitte), pane sinna vidin *Wonom Slider*, avalda. Ava leht teises brauseris või inkognito aknas (ilma sisselogimiseta – vahemälu kehtib ainult külalistele).
2. **Päise kontroll.** Ava sama leht inkognito aknas kaks korda; teisel korral peab olema vastuse päis `x-flying-press-cache: HIT` (brauseri DevTools → Network → dokument → Headers). Kui HIT-i ei tule, ei ole FlyingPress seda lehte vahemällu pannud ja test ei ole veel sisukas (vaata FlyingPressi väljajätmisi).
3. **Muudatuse test.** Wonom Slideris muuda ühe slaidi pealkirja ja salvesta. Laadi testleht inkognito aknas uuesti: uus pealkiri peab olema kohe näha. Kui on vana, siis automaatne tühjendus ei jõudnud FlyingPressini → Seaded → Lehe vahemälu → *Tühjenda vahemälu kohe* ja vaata, mis nimekirjas on („Tühjendatud: FlyingPress”). Kui FlyingPressi seal pole, anna mulle teada, siis vaatame klassinime.
4. **Cloudflare’i kontroll.** Samas Network-vaates vaata päist `cf-cache-status`. Kui see on `DYNAMIC` või `BYPASS`, ei hoia Cloudflare HTML-i ja midagi tegema ei pea. Kui see on `HIT`, lisa Seaded → Lehe vahemälu → Cloudflare Zone ID ja API võti (õigus Zone → Cache Purge) ja korda 3. sammu.
5. **Ajastuse test.** Lisa kampaaniaslaid, algus 5 minuti pärast, lõpp 15 minuti pärast, salvesta. Ära puutu midagi. 6 minuti pärast laadi testleht inkognito aknas: slaid peab olema ilmunud. 16 minuti pärast: kadunud. See tõestab, et WP-Cron käivitab tühjenduse ise. Kui ei ilmu: Seaded → Lehe vahemälu näitab, kas WP-Cron on välja lülitatud; siis peab serveri cron käivitama `wp-cron.php` (hosting) – sama vajadus on Kampaaniaribal, nii et see on tõenäoliselt juba paigas.
6. **Mobiil ja autoplay.** Ava testleht telefonis (mitte sisse logitud). Slaidid peavad vahetuma ise ilma puudutuseta. Kui ei vahetu, on kiirendusplugin JavaScripti viivitanud: FlyingPress → JavaScript → Delay → väljajätmistesse `wonom-slider` (Seaded → Lehe vahemälu all on kopeerimisnupp). Pärast seda FlyingPressis *Purge all*.
7. **Kupongiga kampaania.** Kui kasutad Kampaaniariba sidumist: ava testlehelt kampaania nupu link, ostukorvis peab kupong rakenduma (see on Kampaaniariba enda loogika, slider kasutab sama linki).
8. Kustuta testleht või jäta mustandiks hilisemateks testideks.

## 3. Vahetus Elementoris (ainus nähtav hetk)

1. Esileht → *Muuda Elementoriga*. Tee kõigepealt **inglise originaalleht** (WPML: see, mida muutes hoiatust ei tule), siis eestikeelne (WPML näitab hoiatust „Edit anyway”, vali see – nii tegime testis).
2. Lohista vidin *Wonom Slider* (kategooria Wonom) Slider Revolutioni vidina kohale samasse konteinerisse. Kustuta SR-vidin (parem klõps → Delete). Ära kustuta konteinerit.
3. Kontrolli redaktoris, et slaider renderdub, ja vajuta *Uuenda*. Avaldamine kestab sekundi, külastaja näeb kas vana või uut lehte, mitte katkist.
4. Kohe pärast avaldamist: inkognito aknas esileht ET ja EN, arvutis ja telefonis. Vahemälu peaks Elementori avaldamine ise tühjendama; kui näed vana slaiderit, Wonom Slider → *Tühjenda vahemälu kohe*.
5. Jäta Slider Revolution esialgu **aktiivseks** (tema andmed ja shortcode jäävad alles). Tagasivõtt on siis kahe võimalusega: Elementor → History → eelmine versioon → Uuenda (10 sekundit), või lihtsalt SR-vidin tagasi lohistada.

## 4. Pärast vahetust (sama päev)

- Kontrolli esilehte ET/EN, arvutis ja telefonis; konsoolis ei tohi olla vigu (DevTools → Console).
- PageSpeed Insights esilehele: LCP peab olema parem või sama kui varem (esimene pilt laetakse kohe, SR-i 300–600 kB skripte enam pole).
- Wonom Slider → Kalender: ajastatud slaidid on õigete aegadega.
- Kampaaniariba: pane jooksval kampaanial linnuke „Näita seda kampaaniat ka slaideris”, kui tahad.

## 5. Nädal hiljem

- Kui kõik on korras: Pluginad → Slider Revolution → Lülita välja (andmed jäävad). Veel paar nädalat hiljem võib kustutada.
- Kui ilmneb midagi, mida ei saa kiiresti parandada: Elementor → History → versioon enne vahetust → Uuenda. SR on aktiivne ja töötab kohe.

## Kontrollnimekiri (võta vahetuse ajaks lahti)

- [ ] Testsait lõplik, JSON eksporditud
- [ ] Live varukoopia tehtud
- [ ] Plugin live’is paigaldatud ja aktiivne, „Kontrolli uuendusi” töötab
- [ ] JSON imporditud, kõigil slaididel õiged pildid
- [ ] Seaded üle vaadatud, automaatne tühjendus sees
- [ ] Testleht: FlyingPress HIT; muudatus nähtav kohe; Cloudflare kontrollitud; ajastatud slaid ilmus ja kadus ise; mobiilis autoplay töötab
- [ ] Elementor: EN leht vahetatud ja avaldatud, kontrollitud
- [ ] Elementor: ET leht vahetatud ja avaldatud, kontrollitud
- [ ] Inkognito kontroll ET/EN, arvuti/telefon
- [ ] PageSpeed enne/pärast kirjas
- [ ] SR jääb aktiivseks nädalaks, siis välja

## Seis 05.10.2026

Sammud 0–3 on tehtud: plugin 1.6.4 live'is, slaidid imporditud, vahemälu ja ajastus live'is testitud (FlyingPress ja Cloudflare tühjenevad salvestamisel ja ajastatud hetkedel), Elementoris vahetatud SR-vidin Wonom Slideri vastu ET ja EN esilehel. Slider Revolution on veel aktiivne (tagasivõtt: Elementor → History). Järgmised: PageSpeed enne/pärast, nädala pärast SR välja.
