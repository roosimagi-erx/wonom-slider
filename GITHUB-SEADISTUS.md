# GitHubi seadistus – automaatsed uuendused

Sama muster mis Wonom Kampaaniaribal. Konto: **roosimagi-erx**. Hoidla nimi: **wonom-slider**.
Väljalase käib nagu Wonom KKK-l: skript avaldab väljalaske koos ZIP-iga ise, käsitsi üleslaadimist ei ole.

> **Olek 04.10.2026:** hoidla on loodud (avalik), kood pushitud, release 1.0.0 avaldatud Actioni poolt, plugin paigaldatud testsaidile test.emmaandtheo.ee. Sammud 1–3 on tehtud; edaspidi vt „Edaspidine töövoog”.

## 1. Loo hoidla

1. <https://github.com/new> → **Repository name:** `wonom-slider` → Private või Public.
2. Ära lisa README-d, .gitignore'i ega litsentsi (failid on juba olemas).
3. **Create repository**.

## 2. Saada kood üles

PowerShell selles kaustas:

```powershell
git init -b main
git add -A
git commit -m "Wonom Slider 1.0.0"
git remote add origin https://github.com/roosimagi-erx/wonom-slider.git
git push -u origin main
```

## 3. Esimene väljalase

```powershell
.\build-release.ps1 -Version 1.0.0 -Tag
```

Skript teeb tag'i `v1.0.0` ja pushib. Umbes minuti pärast on hoidla **Releases** all väljalase `1.0.0`
koos failiga `wonom-slider.zip` (vaata **Actions** vahelehelt, kui midagi läks valesti).

Kui hoidla on **privaatne**, luba Actions'il release'e luua: Settings → Actions → General →
Workflow permissions → *Read and write permissions*.

## 4. Privaatse hoidla võti (jäta vahele, kui hoidla on avalik)

1. <https://github.com/settings/personal-access-tokens> → Generate new token (fine-grained).
2. Repository access: *Only select repositories* → `wonom-slider`.
3. Permissions → Repository permissions → **Contents: Read-only**.
4. Kopeeri võti kohe.

Poes: **Wonom Slider → Seaded → Automaatsed uuendused → Juurdepääsuvõti**, või turvalisemalt `wp-config.php`:

```php
define( 'WONOM_SLIDER_GITHUB_TOKEN', 'github_pat_…' );
```

## 5. Ühenda pood

Hoidla `roosimagi-erx/wonom-slider` on pluginasse sisse kirjutatud (`wonom-slider.php` konstandid), seega
avaliku hoidla puhul pole midagi seadistada. Teise hoidla kasutamiseks kirjuta see **Seaded → Automaatsed uuendused**.
Vajuta **Kontrolli uuendusi kohe** – olekurida peab näitama paigaldatud versiooni ja GitHubi viimast väljalaset.

## Edaspidine töövoog (sama mis Wonom KKK-l)

Claude teeb koodimuudatuse, kirjutab CHANGELOG.md-sse uue sektsiooni ja käivitab:

```powershell
.\build-release.ps1 -Version 1.8.0 -Release
```

Skript tõstab versiooninumbri, ehitab ZIP-i, teeb commiti, saadab selle GitHubi ja avaldab
väljalaske koos ZIP-iga (GitHubi käsurea tööriist `gh`). Väljalaske tekst on CHANGELOG-i sektsioon.
Seejärel kontrollib GitHub Action PHP süntaksit ja versiooni; kui midagi on valesti, muudab see
väljalaske mustandiks ja poed seda ei näe.

**Sinu osa:** WordPressis **Wonom Slider → Seaded → „Kontrolli uuendusi kohe“ → „Uuenda kohe“**.
Ilma nuputa näeb pood uuendust 6 tunni jooksul.

`gh` on selles arvutis paigaldatud ja sisse logitud kontoga roosimagi-erx. Kui see kunagi välja logib:

```powershell
gh auth login
```

Varuvariant ilma `gh`-ta: `.\build-release.ps1 -Version 1.8.0 -Tag` pushib ainult tagi ja Action
ehitab ning avaldab väljalaske ise umbes minutiga.

**Versiooninumber peab kattuma** plugina failis (`Version:` + `WONOM_SLIDER_VERSION`) ja tag'is (`v1.8.0`).
Skript hoiab failid paigas ja keeldub väiksemast või samast numbrist ning vales kaustas jooksutamisest.

**WordPress ei uuenda tagasi** – väiksem number ei tee midagi.

**Kaust on OneDrive'i all.** Kui git kurdab lukustatud failide üle, pane OneDrive hetkeks pausile.
