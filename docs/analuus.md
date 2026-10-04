# Päiseslaideri pluginate analüüs ja Wonom Slideri lähtekoht

Koostatud 04.10.2026 Emma and Theo e-poe (WooCommerce + Elementor, ET/EN) päisebänneri jaoks.

## 1. Miks Slider Revolution tundub keeruline

Slider Revolution (SR6) on *animatsiooniredaktor*, mitte bännerihaldur. Selle tugevused ja nõrkused tulevad samast kohast:

| Omadus | Mida see praktikas tähendab |
|---|---|
| Kihipõhine ajajoon (layers + timeline) | Iga tekst, nupp ja pilt on eraldi kiht oma animatsiooni, kestuse ja viivitusega. Ühe bänneri vahetamiseks tuleb käia läbi 3–4 vaadet. |
| 250+ malli, 20+ lisamoodulit (add-on) | Võimas, aga iga moodul lisab JS-i. Tüüpiline SR-leht laeb 300–600 kB skripte ja stiile ka siis, kui kasutad ühte pilti ja üht nuppu. |
| Eraldi „Module“, „Slide“, „Layer“ seaded + globaalsed seaded | Sama asja (nt mobiili suurus) saab määrata neljas kohas; raske aru saada, kumb kehtib. |
| Responsiivsus läbi „breakpoint“-kihtide | Mobiilivaade tähendab iga kihi käsitsi ümberpaigutamist igale murdepunktile. |
| Litsents saidi kohta, uuendused läbi ThemePunchi serveri | Teema kaasa tulnud litsents ei anna sageli uuendusi; vanad versioonid on olnud turvaaukude allikas. |
| Ajastus | Olemas ainult lisamooduli või käsitsi avaldamise kaudu – kampaaniabänneri „alates–kuni“ loogikat sisse ehitatud ei ole. |
| Mitmekeelsus | Puudub. WPML/Polylangiga tuleb teha iga keele jaoks eraldi slaider ja vahetada shortcode'i. |

Elementori võrdlusartikkel (elementor.com/blog/best-wordpress-slider-plugins) ütleb sama: „steep learning curve“, „operates like professional animation software“, „can be heavy on performance“.

## 2. Alternatiivid turul

| Plugin | Tugevus | Nõrkus Emma and Theo kontekstis | Hind |
|---|---|---|---|
| **Smart Slider 3** | Mugav visuaalne redaktor, dünaamiline sisu, hea mobiilitugi | Ikkagi kihipõhine; tasuta versioon piiratud; ajastust ja keeli pole sisse ehitatud | Pro ~ 49–149 € |
| **MetaSlider** | Lihtne, kiire, 4 slaiderimootorit | Tekstikihid ja nupud ainult Pro-s; ajastamine ainult Pro „Schedule“; keeli pole | Pro ~ 39 €/a |
| **Soliloquy** | Ehitatud jõudlus ees; modulaarne | Aastatasu; Pro-funktsioonid vajavad eraldi lisasid (Schedule, Dynamic jne) | ~ 19–299 €/a |
| **LayerSlider** | Võimas animatsioon, parallax | Sama probleem mis SR-il: keerukus ja raskus | ~ 29 € (CodeCanyon) |
| **Depicter** | Moodne UI, AI-abilised | Uus toode, paljud funktsioonid pilveteenuse taga | tasuta/Pro |
| **Elementori enda Slides-vidin (Pro)** | Elementoris sees, lihtne | Ainult Elementoris; ajastust pole; keeled läbi WPML-i stringitõlke | Elementor Pro |
| **Teema (WoodMart) slaider** | Null lisapluginaid | Hallatakse Elementori/teema malli kaudu, ajastust ja mobiili eraldi pilti pole mugavalt | tasuta |

**Järeldus:** ükski valmisplugin ei ühenda kolme asja, mida vaja: (1) üks lihtne vorm slaidi kohta, (2) kampaaniabänneri ajakava „alates–kuni“, (3) ET/EN tekstid samas vormis. Kõigis tuleb osta Pro-pakett ja ikkagi midagi käsitsi kombineerida.

## 3. Mida artiklid soovitavad jõudluse ja SEO kohta (ja mis Wonom Slideris on arvestatud)

| Soovitus | Lahendus Wonom Slideris |
|---|---|
| Piltide kaal on kõige olulisem; WebP/AVIF | Pildid tulevad WP meediateegist (`srcset` + WebP, kui sait seda genereerib); admin näitab soovitusliku suuruse |
| Lazy-load, aga mitte LCP-elemendile | Esimene slaid `loading=eager` + `fetchpriority=high`, ülejäänud `lazy`; järgmise slaidi pilt laaditakse ette enne üleminekut |
| Mitte mitu H1-te lehel | Pealkirja tag on seadistatav, vaikimisi H2; H1 lubatakse ainult esimesele slaidile |
| Maksimaalselt 3–5 slaidi, autoplay ettevaatlikult | Autoplay on sees vaikimisi 6 s, peatub hiirega, fookusega, ekraanilt väljas ja `prefers-reduced-motion` korral |
| Vähe JS-i | Front-end: ~9 kB JS + ~11 kB CSS (gzipituna u 5 kB), ilma jQueryta ega Swiperita; laetakse ainult lehel, kus slaider on |
| Vahemälu vs ajastus | Nähtavuse otsustab PHP; salvestamisel ja iga ajastatud alguse/lõpu hetkel tühjendatakse tuntud vahemälupluginate cache (WP Rocket, LiteSpeed, W3TC, SG, WPFC, Autoptimize) |

## 4. Wonom Slideri disainiotsused

1. **Üks slaider, mitu slaidi.** Pood vajab ühte päisebännerit. Kampaania on lihtsalt slaid, millel on ajakava ja märge „Kampaania“ – see ilmub ja kaob ise, tavabänner jääb alles.
2. **Üks ekraan, kaardid.** Iga slaid on kaart: pilt, nimi, olek (Eetris / Tulemas / Lõppenud / Väljas), ajakava kokkuvõte, lüliti. Kaardi avamine näitab kõiki välju ühes voos: pildid → tekstid → kujundus → mobiil → ajakava → täpsemalt.
3. **Mobiil eraldi, aga ainult kui vaja.** Eraldi mobiilipilt, külgede suhe, pealkirja/teksti suurus, külgvahe, joondus ja „ainult pilt“ – kõik on vaikimisi „sama mis arvutis“.
4. **Keeled samas vormis.** Polylang/WPML tuvastatakse automaatselt; muidu seadetes „et,en“. Tekstiväljadel on keelevahelehed, tühi väli võtab vaikekeele teksti. DeepL-võtmega nupp „Tõlgi eesti keelest“.
5. **Ajakava nagu Kampaaniaribal.** Sama kalendripopup (esmaspäev esimene, 24 h), kiirvalikud (Kohe, Homme, +7, +14, Kuu lõpp), olekurida ja eraldi kuukalendri vaade kõigi ajastatud slaididega.
6. **Elementor esmaklassiline.** Oma vidin kategoorias „Wonom“, töötab Elementori redaktoris (slaider initsialiseeritakse `frontend/element_ready` haagiga), min/max kõrgus responsiivsete kontrollidena. Lisaks shortcode, Gutenbergi plokk ja PHP-funktsioon.
7. **Uuendused GitHubist.** Sama muster mis Kampaaniaribal: väljalase GitHubis → WordPress näitab uuendust. Lisaks GitHub Action, mis ehitab ZIP-i automaatselt tag'i pushimisel.

## 5. Mida esimene versioon teadlikult ei tee

- Ei ole kihipõhist animatsiooniredaktorit (see ongi probleemi allikas).
- Ei ole video-tausta (lisatav hiljem: `<video>` sama `media`-ploki sisse).
- Ei ole dünaamilist sisu (toodete/kategooriate automaatne slaider) – vajadusel filter `wonom_slider_slides`.
- Ei ole A/B-testimist ega statistikat.
