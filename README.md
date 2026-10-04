# Wonom Slider

Kerge ja lihtsalt hallatav päiseslaider WooCommerce'i / WordPressi esilehele.
Mitu slaidi, ajastatud kampaaniabännerid, eraldi mobiilipildid ja -seaded, ET/EN tekstid samas vormis,
Elementori vidin, automaatsed uuendused GitHubist.

> Kood: [github.com/roosimagi-erx/wonom-slider](https://github.com/roosimagi-erx/wonom-slider) ·
> Analüüs ja disainiotsused: [docs/analuus.md](docs/analuus.md) ·
> GitHubi ja uuenduste seadistus: [GITHUB-SEADISTUS.md](GITHUB-SEADISTUS.md)

## Paigaldus

1. Laadi alla `wonom-slider.zip` (GitHub → Releases) või ehita see `.\build-release.ps1 -Version 1.0.0`.
2. WordPress → Pluginad → Lisa uus → Laadi plugin üles → aktiveeri.
3. Menüüsse ilmub **Wonom Slider**.

## Slaideri lehele panemine

| Kus | Kuidas |
|---|---|
| Elementor | Vidin **Wonom Slider** (kategooria *Wonom*). Lohista päise sektsiooni, sea sektsioon *full width*. |
| Lühikood | `[wonom_slider]` |
| Plokiredaktor | Plokk **Wonom Slider** |
| Teema PHP | `<?php if ( function_exists( 'wonom_slider' ) ) { wonom_slider(); } ?>` |

Slider Revolutioni asendamiseks: eemalda Elementoris senine SR-vidin / lühikood `[rev_slider …]` ja pane asemele Wonom Slideri vidin. Kui pilt on ilus, deaktiveeri Slider Revolution.

## Igapäevane kasutus

**Tavabänner:** Wonom Slider → *Lisa slaid* → vali arvutivaate pilt → (soovi korral mobiilipilt, pealkiri, nupp) → *Salvesta*.

**Kampaaniabänner:** *Lisa kampaaniaslaid*. See pannakse järjekorras esimeseks ja saab vaikimisi 14-päevase ajakava.
Ajakava plokis määra täpne algus ja lõpp kalendrist või kiirvalikutega (Kohe, Homme, +7 päeva, Kuu lõpp).
Slaid ilmub ja kaob automaatselt; tavabänner jääb kogu aeg alles. Vaade *Kalender* näitab kõiki ajastatud slaide kuu peal.

**Inglise tekstid:** slaidi *Tekstid ja nupp* plokis on keelevahelehed (ET / EN). Tühi väli võtab eesti teksti.
Kui seadetes on DeepL API võti, täidab nupp *Tõlgi keelest Eesti* ingliskeelsed väljad automaatselt (loe üle!).
Keeled tuvastatakse Polylangist/WPML-ist; ilma nendeta kirjuta seadetes `et,en`.

**Mobiil:** eraldi mobiilipilt (nt püstine 800×1000), külgede suhe, pealkirja/teksti suurus, külgvahe, joondus
ja „ainult pilt“ – kõik seaded on vaikimisi „sama mis arvutis“. Fookuspunkt määratakse pildil klõpsates.

**Lava ja tekstiploki asukoht:** iga avatud slaidi kohal on lava, kus slaid kuvatakse täpselt nagu esilehel (arvuti või mobiil). Tekstiplokki saab laval lohistada, nooleklahvidega nihutada (1 %, Shift 5 %) ja sinisest pidemest laiust muuta. Kujundus-plokis saab valida „Ruudustik” (joondusnupud) või „Vaba” (täpne X/Y/laius protsentides). Mobiilile saab anda eraldi asukoha.

**Taustavärv:** slaidi taustavärv on näha pildi taga ja üksinda, kui pilti polegi – ainult tekstiga slaid.

**Fondid:** Seaded → Tüpograafia annab vaikimisi stiili kogu slaiderile: pealkirja ning teksti/nupu font eraldi (saidi vaikefont, kureeritud veebifondid Bunny Fontsist või oma CSS-väärtus), pealkirja paksus, suurtähed, tähevahe. Iga slaidi plokis **Tüpograafia (see slaid)** saab need slaidi kaupa üle kirjutada ning lisaks muuta ridade vahet ja vahet nupu ees; tühi väli = nagu seadetes.

**Vahemälu:** Seaded → Lehe vahemälu: tühjendatakse automaatselt salvestamisel, ajastatud alguse/lõpu hetkel ja pärast plugina uuendust (FlyingPress, WP Rocket, LiteSpeed, W3TC jt; Cloudflare API võtmega). Nupp „Tühjenda vahemälu kohe”.

**Eelvaade:** nupp *Eelvaade* avab salvestatud slaideri arvuti/tahvli/mobiili laiuses otse redaktoris.

## Seaded

- *Esitus*: automaatne vahetus, aeg, kiirus, hajumine/libisemine, kordus, Ken Burns.
- *Paigutus*: külgede suhe arvutis ja mobiilis (vaikimisi esimese pildi järgi), murdepunkt, maksimaalne laius.
- *Tüpograafia*: pealkirja ja teksti suurused eraldi arvutile ja mobiilile, font, nupu nurgad, pealkirja tag (vaikimisi H2 – mitu H1-te kahjustab SEO-d).
- *Keeled ja tõlkimine*, *Automaatsed uuendused*, *Oma CSS*, *Andmed* (eksport/import JSON, kustutamine eemaldamisel).

## Arendajale

```
wonom-slider.php                 – bootstrap, konstandid (GitHubi omanik/hoidla)
includes/class-wonom-slider-data.php      – andmemudel, sanitiseerimine, ajakava, keeled
includes/class-wonom-slider-frontend.php  – HTML, lühikood, plokk, eelvaade
includes/class-wonom-slider-admin.php     – admin-leht, tõlgitavad stringid
includes/class-wonom-slider-rest.php      – REST (/wonom-slider/v1/…)
includes/class-wonom-slider-updater.php   – GitHub Releases uuendaja
includes/class-wonom-slider-elementor*.php – Elementori vidin
assets/admin/admin.js|css        – redaktor (vanilla JS + jQuery UI sortable)
assets/public/slider.js|css      – front-end (ilma sõltuvusteta)
languages/wonom-slider-et.l10n.php – eesti tõlge (WP 6.5+ PHP-formaat)
```

Haagid: `wonom_slider_slides` (filter: slaidid enne renderdamist), `wonom_slider_html`, `wonom_slider_languages`,
`wonom_slider_current_language`, `wonom_slider_translate` (oma tõlketeenus), `wonom_slider_flush_caches`, `wonom_slider_saved`.

JS-sündmus: `wonom-slider:change` (`detail.index`, `detail.slide`). `window.WonomSlider.init(scope)` initsialiseerib dünaamiliselt lisatud slaiderid.

Andmed on kahes `wp_options` kirjes: `wonom_slider_slides` (massiiv) ja `wonom_slider_settings`.

### Uue versiooni avaldamine

```powershell
.\build-release.ps1 -Version 1.1.0 -Tag
```

Skript tõstab versiooni, ehitab ZIP-i, teeb commiti ja tag'i `v1.1.0` ning pushib. GitHub Action
(`.github/workflows/release.yml`) kontrollib PHP süntaksit, ehitab `wonom-slider.zip` ja avaldab Release'i
CHANGELOG.md vastava sektsiooniga. Poed näevad uuendust 6 tunni jooksul või kohe nupuga *Kontrolli uuendusi kohe*.

## Litsents

GPL-2.0-or-later. © Wonom Digital OÜ.
