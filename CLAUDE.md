# Wonom Slider – juhised Claude'ile

Ühised Wonom pluginate reeglid: @../wonom-woocommerce-plugin-alus.md

## Väljalase (Claude teeb ise, kasutaja ainult uuendab WordPressis)

1. Tee koodimuudatus ja kirjuta `CHANGELOG.md`-sse uus sektsioon `## X.Y.Z - YYYY-MM-DD`
   poe omanikule arusaadavas eesti keeles (mis oli valesti, mis nüüd teisiti).
2. Käivita PowerShellis repo juurkaustas:

   ```powershell
   .\build-release.ps1 -Version X.Y.Z -Release
   ```

   Skript tõstab versiooni (`wonom-slider.php` päis + `WONOM_SLIDER_VERSION`, `readme.txt` Stable tag),
   ehitab `wonom-slider.zip`, teeb commiti, pushib `main`-i ja loob `gh release create`-ga väljalaske
   koos ZIP-iga. Märkmed tulevad CHANGELOG-i sektsioonist (või `-Notes "…"`).
3. GitHub Action (`.github/workflows/release.yml`) kontrollib tagi: versioon peab kattuma ja `php -l`
   peab läbima. Kui kontroll kukub, muudab Action väljalaske mustandiks ja poed seda ei näe.
   Vaata tulemust: `gh run list --repo roosimagi-erx/wonom-slider --limit 1`.
4. Ütle kasutajale: **Wonom Slider → Seaded → „Kontrolli uuendusi kohe“ → „Uuenda kohe“**
   (või Pluginad-lehel). Ilma nuputa näeb pood uuendust 6 tunni jooksul.

**Suurem või riskantsem muudatus – testsait enne poode:**

```powershell
.\build-release.ps1 -Version X.Y.Z -Release -Prerelease   # GitHubi eelväljalase: poed seda ei näe
# testsait: Pluginad → Lisa uus → Laadi üles → wonom-slider.zip → „Asenda praegune“, testi
.\build-release.ps1 -Version X.Y.Z -Promote              # tavaliseks väljalaskeks: poed näevad uuendust
```

Tagasipööramine testsaidil: lae alla eelmise väljalaske ZIP
(`https://github.com/roosimagi-erx/wonom-slider/releases/download/vA.B.C/wonom-slider.zip`) ja
paigalda see samamoodi „Asenda praegune“ valikuga. Andmed (`wonom_slider_*` optionid) jäävad alles.

Tõlked: muuda ainult `languages/wonom-slider-et.l10n.php`; `.po`, `.mo` ja admin-JS-i JSON genereerib
`tools/build-translations.ps1` (käivitub väljalaske skriptist). Admin-liidese tekstid on `assets/admin/i18n.js`-is.

Skript keeldub: vales kaustas, väiksema või sama versiooniga (`-Force` ehitab sama versiooni uuesti),
tühja CHANGELOG-i sektsiooniga, olemasoleva tagi või väljalaskega, mitte-`main` harul.
`gh` on paigaldatud (`C:\Program Files\GitHub CLI\gh.exe`) ja sisse logitud kontoga `roosimagi-erx`.

## Töökord

- **Kõigepealt testsait** test.emmaandtheo.ee. Live emmaandtheo.ee uuendab kasutaja ise või ainult tema loal.
- Väljalase on avalik (hoidla on avalik) ja live-admin pakub uuendust kohe, seega ära avalda poolikut tööd.
- Kohalikult pole PHP-d ega Node'i: enne väljalaset kontrolli sulgude tasakaalu (awk) ja vajadusel
  admin-JS-i testraamistikus (scratchpad `harness/serve.ps1`, port 8765).
- Kaust on OneDrive'i all. Kui git kurdab lukustatud failide üle, pane OneDrive pausile.
