# Changelog

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
