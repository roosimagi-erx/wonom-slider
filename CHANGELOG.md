# Changelog

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
