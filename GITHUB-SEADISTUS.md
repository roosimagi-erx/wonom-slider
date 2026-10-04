# GitHubi seadistus – automaatsed uuendused

Sama muster mis Wonom Kampaaniaribal. Konto: **roosimagi-erx**. Hoidla nimi: **wonom-slider**.
Erinevus Kampaaniaribast: siin ehitab GitHub Action ZIP-i ise, käsitsi üleslaadimist ei ole.

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

## Edaspidine töövoog

1. Tee koodimuudatus, kirjuta CHANGELOG.md-sse uus sektsioon `## 1.1.0 - kuupäev`.
2. `.\build-release.ps1 -Version 1.1.0 -Tag`
3. Action avaldab release'i; poed näevad uuendust 6 tunni jooksul või kohe nupuga.

**Versiooninumber peab kattuma** plugina failis (`Version:` + `WONOM_SLIDER_VERSION`) ja tag'is (`v1.1.0`).
Action keeldub ehitamast, kui ei kattu. Skript hoiab failid paigas.

**WordPress ei uuenda tagasi** – väiksem number ei tee midagi.

**Kaust on OneDrive'i all.** Kui git kurdab lukustatud failide üle, pane OneDrive hetkeks pausile.
