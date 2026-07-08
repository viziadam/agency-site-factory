# Agency Site Factory 1.12

Az egységes ügyfél-admin a domain `/admin/` útvonalán érhető el. Ez önálló portálfelület: nem az aktív theme-ben, nem WordPress oldalsablonban és nem a weboldal belső profiloldalaként renderelődik. Kijelentkezett állapotban teljes képernyős bejelentkezést, belépés után moduláris irányítópultot mutat Booking, naptárbeállítás, oldalankénti látogatottság, interakciók, konverziók és alap weboldal-beállítások oldalakkal. A hozzáférés a wp-admin **Agency Kit → Client Admin** oldalán kezelhető, a portál pedig saját `agency_portal_session` access-token cookie-t használ.

Az 1.9 kiadásban elkészült négylépéses, reszponzív foglalási felület az 1.10-es `/admin/` portál Booking moduljából kezelhető: itt hagyhatók jóvá vagy utasíthatók el a foglalások, és itt állíthatók a munkanapok, nyitvatartás, zárt és rendkívüli napok.

Az alap ügyfél-admin hozzáférés és további portálfelhasználók az **Agency Kit → Client Admin** oldalon kezelhetők. Részletek: [docs/CLIENT-ADMIN.md](docs/CLIENT-ADMIN.md) és [docs/BOOKING-PORTAL.md](docs/BOOKING-PORTAL.md).

Fejlesztői bővítéshez és az új, egységes feature-plugin mappaszerkezethez lásd: [docs/EXTENDING.md](docs/EXTENDING.md). A teljes keretrendszer útvonaltérképe és működési magyarázata: [docs/FRAMEWORK-STRUCTURE.md](docs/FRAMEWORK-STRUCTURE.md).

Az 1.8 production modulkiadás valódi, adatbázis-alapú Booking availability motort, reszponzív slotválasztót, teljes Booking/Auth/Legal admin felületeket, egységes Email Service health ellenőrzést és részletes modul setup státuszt tartalmaz.

Booking aktiválásakor a rendszer kikényszeríti az Auth + Legal függőségeket, létrehozza a frontend oldalakat, beköti a kezdőlapi CTA-t/menüt és csak sikeres Email Service teszt után jelzi READY állapotúnak. Részletek: [docs/MODULE-SETUP.md](docs/MODULE-SETUP.md), [docs/DEPLOYMENT-CHECKLIST.md](docs/DEPLOYMENT-CHECKLIST.md).

Újrahasznosítható WordPress weboldalgyártó keretrendszer PHP 8+ és WordPress 6+ környezethez. A theme kezeli a megjelenést és a komponenseket; az Agency Core plugin kezeli az adatmodellt, a vizuális oldal-összeállítást, a site blueprintet, az importot, a SEO-sémát és az automatizálást.

Nincs szükség npm buildre, Composerre, Elementorra vagy külső CDN-re. A gyökérben található `composer.json` kizárólag opcionális fejlesztői WPCS-ellenőrzéshez kell; a WordPress futtatásához nem.

## Mit tartalmaz?

- Agency Theme klasszikus PHP template hierarchy-val és reszponzív design token rendszerrel.
- Vizuális **Agency sections** panel component- és variant-választóval.
- Iframe-alapú, PHP-renderelt Agency live preview a Gutenberg középső vásznán.
- Központi, engedélylistás component registry és backward-compatible section normalizálás.
- Öt egyedi tartalomtípus és három taxonómia.
- Manifest-alapú, több blueprintet kezelő, idempotens importer.
- Beauty Studio és Consulting Firm magyar demo blueprint.
- Helyi blueprint-képek biztonságos Média könyvtár importja.
- WP-CLI blueprint listázás és import.
- LocalBusiness, Article, Service, Offer és FAQPage JSON-LD.
- Automatikus Yoast, Rank Math, AIOSEO és SEOPress együttműködés.
- DDEV konfiguráció, CI, blueprint contract és idempotencia teszt.
- Fordítási katalógusok és akadálymentességi/performance auditlista.
- Validált client project config és Windows/LocalWP-kompatibilis site generator.
- Project manifest, Agency Kit Project/Modules admin és hat bővíthető feature-module plugin.
- Localhost Agency Factory Manager projektwizarddal, gombos dry-run/install/update folyamattal és műveleti naplóval.
- Verziófelismerős, kötelező dry-runos framework/module update és rollback útmutató.
- Közös module service: migráció, auditlog, rate limit és titkosított wp_mail/Brevo email provider.
- Production MVP Legal, Auth és Booking modul frontend és rendezett admin folyamattal.

## Production modulok

- **Email Service:** Agency Kit → Email Service; `wp_mail` fallback vagy Brevo. Az API-kulcs titkosítva tárolódik és soha nem kerül logba.
- **Auth:** `[agency_auth_login]`, `[agency_auth_register]`, `[agency_auth_account]`; frontend regisztráció/login/logout/reset, lejáró email-verifikáció és customer wp-admin tiltás.
- **Booking:** `[agency_booking_form]`, `[agency_booking_customer_list]`, illetve `booking/form` és `booking/customer-list` Agency section; saját indexelt tábla, availability motor, slotnaptár, teljes státusz-workflow és email értesítések.
- **Legal:** szerkeszthető draft jogi oldalsablonok és közös registration/booking/contact/newsletter hozzájárulási szövegek.
- **Newsletter:** `[agency_newsletter]`; saját feliratkozói tábla, double opt-in megerősítés, leiratkozás és csak aktív hozzájárulások CSV-exportja.
- **Analytics:** validált GA4 konfiguráció, Do Not Track támogatás és csak a Legal cookie bannerben adott hozzájárulás után betöltődő mérés.

Migrációs és adatmegőrzési szabályok: [`docs/MODULE-MIGRATIONS.md`](docs/MODULE-MIGRATIONS.md).

## Agency Factory Manager – ajánlott használat

Windows alatt kattints duplán a gyökérben található `start-factory-manager.bat` fájlra. A böngészőben megnyílik:

```text
http://127.0.0.1:8765
```

A managerben formból hozhatsz létre projektet, megadhatod a márka- és targetadatokat, kiválaszthatod a blueprintet és modulokat, majd gombbal futtathatod a dry-runt, telepítést vagy modulfrissítést. Kézi JSON-szerkesztés és parancssor nem szükséges.

Részletes útmutató: [`docs/FACTORY-MANAGER.md`](docs/FACTORY-MANAGER.md).

## A leggyorsabb használat LocalWP-ben

1. Hozz létre vagy indíts el egy LocalWP site-ot PHP 8.0+ verzióval.
2. A csomag `wp-content/plugins/agency-core` mappáját másold a Local site `app/public/wp-content/plugins/` könyvtárába.
3. A `wp-content/themes/agency-theme` mappát másold az `app/public/wp-content/themes/` könyvtárába.
4. WordPress admin → **Bővítmények** → aktiváld az **Agency Core** plugint.
5. **Megjelenés → Sablonok** → aktiváld az **Agency Theme** sablont.
6. **Agency Kit → Import Blueprint** → válaszd a Beauty Studio vagy Consulting Firm receptet.
7. **Agency Kit → Settings** → cseréld le a demo márka-, kontakt-, cím- és schema-adatokat.

Külön ZIP-ek esetén ugyanez elvégezhető a WordPress admin „Új bővítmény/sablon feltöltése” funkciójával.

Az alábbi CLI workflow automatizálási fallbackként továbbra is használható:

```powershell
php tools/create-client-site.php --config=site-factory/projects/demo-beauty/config.json --target="C:\Users\Vizi\Local Sites\demo-beauty\app\public" --dry-run
```

A részletes LocalWP, WP-CLI, modulfrissítési és DDEV leírás: [`docs/CREATE-CLIENT-SITE.md`](docs/CREATE-CLIENT-SITE.md).

## Egy új weboldal gyártási folyamata

### 1. Induló recept kiválasztása

Az **Agency Kit → Import Blueprint** oldalon válassz iparági receptet. Az importer:

- létrehozza vagy frissíti az oldalakat;
- beállítja a statikus kezdőlapot és blogoldalt;
- létrehozza a primary menüt;
- importálja a szolgáltatásokat, GYIK-elemeket és véleményeket;
- elmenti az oldal komponenslistáit;
- helyi blueprint-képek esetén létrehozza a médiaelemeket és kiemelt képeket.

Az import slug alapján idempotens. Ugyanaz a blueprint többször is futtatható.

### 2. Márka és design beállítása

Az **Agency Kit → Settings** oldalon állítsd be:

- márkanevet, színeket és kontaktadatokat;
- strukturált címet, koordinátát és nyitvatartást;
- social URL-eket, pénznemet és ársávot;
- LocalBusiness típust és schema módot.

A primary, secondary és background szín futásidőben felülírja a theme tokenjeit. További tokenek a `agency-theme/assets/css/tokens.css` fájlban módosíthatók.

### 3. Oldalak moduláris összeállítása

Nyiss meg egy oldalt szerkesztésre. A jobb oldali dokumentumbeállítások között megjelenik az **Agency sections** panel.

Itt:

- új section adható hozzá;
- kiválasztható a component típusa és annak variantja;
- szerkeszthetők a komponens mezői;
- módosítható a sectionök sorrendje;
- meglévő section componentje vagy variantja cserélhető;
- eltávolítható egy section.

Mentéskor a plugin validálja a mezőket és verziózott JSON-ként tárolja az `_agency_sections` metában. Ha nincs section, a hagyományos WordPress blokk-tartalom jelenik meg.

Az Agency sections használatakor a Gutenberg középső vásznán izolált iframe live preview jelenik meg. Ez nem egy külön JavaScriptben újraírt komponensnézet: a szerver ugyanazt az `agency_theme_render_sections()` függvényt, registryt, PHP template partokat, header/footer template-et, frontend CSS-t és design tokeneket használja, mint a publikus oldal.

A panel módosításai 400 ms késleltetéssel frissítik a preview-t. Ez nem menti automatikusan az oldalt. A publikus frontend csak a Gutenberg **Mentés/Frissítés** gomb használatakor változik. Újratöltéskor mindig az utoljára mentett `_agency_sections` meta töltődik vissza.

Az új section formátum:

```json
{
  "component": "hero",
  "variant": "split",
  "data": {
    "title": "Oldalcím"
  }
}
```

A régi `"component": "hero/hero-split"` formátum továbbra is olvasható, és migrációkor adatvesztés nélkül az új alakra normalizálódik.

Elérhető variánsok:

- Hero: `split`, `centered`, `image-card`, `minimal`
- Services: `grid`, `cards`, `icon-list`, `featured`
- CTA: `simple`, `boxed`, `full-width`, `booking`
- Header: `default`, `centered`, `compact`, `split`
- FAQ: `accordion`
- Contact: `section`
- Blog: `grid`

A header variáns az **Agency Kit → Settings → Header variant** mezőben állítható.

## Live preview architektúra

A Gutenberg kliens a bejelentkezett szerkesztő aktuális, még nem mentett sectionállapotát küldi erre a REST endpointra:

```text
POST /wp-json/agency/v1/preview-sections
```

Payload:

```json
{
  "post_id": 123,
  "sections": []
}
```

Az endpoint:

1. ellenőrzi a WordPress REST nonce-ot;
2. ellenőrzi az `edit_post` jogosultságot;
3. registry alapján validálja és normalizálja a component/variant értékeket;
4. ugyanazzal a PHP pipeline-nal rendereli a headert, sectionöket és footert;
5. teljes iframe HTML-dokumentumot ad vissza a frontend assetekkel.

Az iframe elkülöníti a frontend CSS-t a Gutenberg admin CSS-től. A header preview az Agency Kitben mentett aktuális header variantot használja.

Hiba esetén az iframe érthető állapotot mutat, a böngésző konzolban pedig `Agency live preview:` prefixű hiba található. Szerveroldalon ellenőrizd a `wp-content/debug.log` fájlt, a REST válasz HTTP státuszát és az endpoint JSON `code`/`message` mezőit. A preview-hiba nem írja felül a panel állapotát.

### 4. Üzleti tartalom feltöltése

A WordPress admin bal menüjében külön kezelhető:

- Services;
- FAQs;
- Testimonials;
- Portfolio;
- Price items.

A szolgáltatásnál ár és pénznem is megadható. Ezek a Service/Offer schema részévé válnak.

### 5. Végső WordPress-beállítások

- **Megjelenés → Testreszabás/Site Identity**: logó.
- **Megjelenés → Menük**: primary és footer navigáció.
- **Bejegyzések**: blogtartalom és kiemelt képek.
- **Beállítások → Közvetlen hivatkozások**: egyszer mentsd újra, ha új CPT-t adtál a kódhoz.

## Elérés és fontos admin útvonalak

- Keretrendszer beállításai: `/wp-admin/admin.php?page=agency-core-settings`
- Blueprint importer: `/wp-admin/admin.php?page=agency-core-import`
- Oldalak: `/wp-admin/edit.php?post_type=page`
- Szolgáltatások: `/wp-admin/edit.php?post_type=agency_service`
- GYIK: `/wp-admin/edit.php?post_type=agency_faq`
- Vélemények: `/wp-admin/edit.php?post_type=agency_testimonial`

Az oldal publikus címe a LocalWP-ben a site neve mellett látható **Open site** gombbal érhető el.

## DDEV indítás

A teljes forráscsomag gyökerében:

```bash
ddev start
ddev setup-agency
```

A parancs letölti a WordPress core-t, létrehozza a helyi konfigurációt, telepíti a site-ot, aktiválja a theme-et és plugint, importálja a Beauty blueprintet, majd megnyitja az admint.

Helyi demo belépés: `admin` / `agency-local-only`. Ezt kizárólag lokális fejlesztéshez használd.

## WP-CLI

```bash
wp agency blueprint list
wp agency blueprint import beauty
wp agency blueprint import consulting
wp agency project show
wp agency project apply-config wp-content/agency-project-config.json
wp agency module list
wp agency module enable booking
wp agency module disable booking
```

Idempotencia ellenőrzése működő WordPress telepítésben:

```bash
bash tests/wp-cli-idempotency.sh beauty
```

## Új komponens hozzáadása

1. Hozd létre a theme template partot, például `template-parts/team/team-grid.php`.
2. Kizárólag az `$args['data']` tömbből olvass, és escape-eld a kimenetet.
3. Add a `team` componentet és `grid` variantot az `agency_theme_get_component_registry()` központi registryhez.
4. Add meg a mezősémát, default adatot, editor labelt és a `template-parts/team/team-grid` template útvonalat.
5. Add a CSS-t a theme `assets/css/components.css` fájljához.
6. Emeld a komponens verzióját, ha később visszafelé nem kompatibilis adatváltozás történik.

A mezőséma támogatott típusai: `text`, `textarea`, `url`, `image_url`, `image_id`, `select`, `checkbox`, `number` és alapszintű JSON `repeater`. A repeater vizuális sorépítője későbbi TODO; jelenleg érvényes JSON tömbként szerkeszthető.

Új variant hozzáadásához elég ugyanazon component `variants` registry eleméhez új labelt és allow-listelt template útvonalat adni, létrehozni a PHP template partot, majd hozzáadni a CSS-t. Az editor és a live preview nem igényel külön JavaScript komponensregisztrációt.

Blueprint példa:

```json
{
  "component": "team",
  "variant": "grid",
  "data": {
    "title": "Csapat"
  }
}
```

## Új blueprint hozzáadása

1. Másold a `blueprints/consulting/` könyvtárat egy új, kisbetűs slugra.
2. A `manifest.json` `slug` értéke egyezzen a mappanévvel.
3. A manifest `files` és `content_types` térképe mondja meg, melyik JSON melyik CPT-be kerül.
4. Állítsd be a `front_page`, `posts_page` és `menu_name` értékeket.
5. Futtasd: `php tests/blueprint-contract.php`.
6. Importáld kétszer, majd futtasd az idempotencia tesztet.

Helyi képhez tegyél JPG, PNG vagy WebP fájlt a blueprint például `assets/` mappájába, majd az elem JSON-jában:

```json
{
  "title": "Szolgáltatás",
  "slug": "szolgaltatas",
  "featured_image": "assets/service.webp"
}
```

A fájlútvonal allow-listelt blueprint könyvtáron belül marad; külső URL-t az importer nem tölt le.

## Új CPT hozzáadása

Adj új sort az `includes/post-types.php` deklaratív tömbjéhez, majd szükség esetén rendeld taxonómiához az `includes/taxonomies.php` fájlban. Ha blueprintből is importálnád, add hozzá a manifest `content_types` térképéhez.

## SEO és schema

Az Agency Core saját JSON-LD-je **Auto** módban kikapcsol, ha Yoast SEO, Rank Math, All in One SEO vagy SEOPress aktív. A Settings oldalon ez felülbírálható vagy teljesen letiltható.

Támogatott saját sémák: LocalBusiness/Organization, Article, Service, Offer és FAQPage.

## Minőségbiztosítás

```bash
php tests/blueprint-contract.php
php tests/project-factory-contract.php
find . -name '*.php' -print0 | xargs -0 -n1 php -l
wp eval-file tests/wp-preview-integration.php
composer install
vendor/bin/phpcs
```

A Composer parancsok csak opcionális fejlesztői ellenőrzések. A GitHub Actions PHP 8.0, 8.2 és 8.3 alatt futtat szintaxist és blueprint contractot; a WPCS jelenleg advisory job.

Részletes manuális release checklist: `AUDIT.md`.

## Theme és plugin felelőssége

Theme: template hierarchy, site shell, template partok, reszponzív CSS, design tokenek és minimális frontend JavaScript.

Plugin: CPT-k, taxonómiák, meta, komponens-séma, editor, Options API, importer, médiaimport, schema, shortcode-ok, migrációk és WP-CLI.

Üzleti adatmodellt ne helyezz a theme-be; layout CSS-t ne helyezz a plugin frontendjére.

## Elkészült következő ticketek

- Vizuális, validált Gutenberg dokumentumpanel.
- Component/variant választó és középső editor-placeholder.
- PHP-renderelt, iframe-alapú valós idejű editor preview.
- 4 hero, 4 services, 4 CTA és 4 header variáns.
- Blueprint manifest és több blueprintet kezelő importer.
- WP-CLI import és automatizált contract/idempotencia teszt.
- SEO plugin felismerés és duplikált schema megelőzés.
- Részletes cím, ár, Offer, geo és nyitvatartás schema.
- Komponens-séma és verziózott adatmigráció.
- Biztonságos helyi képimport és média-hozzárendelés.
- Fordítási katalógusok.
- DDEV és több PHP-verziós CI.
- Akadálymentességi/performance fejlesztések és auditlista.

## Érdemes következő körben

- Drag-and-drop section rendezés és média-választó a Gutenberg panelen.
- FAQ, Testimonials, Contact, Blog és általános Cards további vizuális variánsai.
- Blueprint export az aktuális WordPress site-ból.
- Valódi WordPress PHPUnit integrációs környezet.
- Playwright vizuális baseline-ok egy rögzített böngészőmátrixhoz.
- Részletes Portfolio és Price Item frontend komponensek.
- Nyitvatartás strukturált admin UI-ja.
- Többnyelvű blueprint változatok Polylang/WPML adapterrel.
