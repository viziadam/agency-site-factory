# Agency Site Factory teljes keretrendszer-térkép

Ez a dokumentum a jelenlegi, fejlesztőbarát Agency Site Factory szerkezet gyakorlati térképe. A célja, hogy VS Code-ban gyorsan megtaláld, hol van az Agency Core, hol készülnek az új ügyfélprojektek, hol élnek a modulpluginek, hol vannak a theme komponensek, és egy új weboldal létrehozásakor pontosan milyen részek dolgoznak együtt.

Repo gyökér (ezt nyisd meg VS Code-ban, és ezt tekinti hivatalos forrásgyökérnek a Factory Manager és minden generáló script):

```text
agency-site-factory/
```

A dokumentumban minden aktív framework-forrásútvonal ehhez a repo gyökérhez képest van megadva. Régi, átmeneti `outputs/...` vagy gépspecifikus `C:\Users\...` workspace útvonalat a runtime nem használhat framework source rootként.

## 1. Nagy kép: miből áll a rendszer?

Az Agency Site Factory három fő rétegre van bontva.

### 1.1 Factory / site-generátor réteg

Ez nem az ügyfél WordPress site-ban futó plugin, hanem a helyi fejlesztői/agency eszközrendszer. Feladata:

- új ügyfélprojekt konfigurációjának létrehozása;
- LocalWP vagy DDEV WordPress `public` mappa validálása;
- Agency Theme, Agency Core és kiválasztott modulok bemásolása;
- meglévő Agency mappák backupolása;
- `wp-content/agency-project.json` manifest létrehozása/frissítése;
- opcionális WP-CLI aktiválás, blueprint import és projektbeállítások alkalmazása;
- dry-run, install/update és modulfrissítés UI-ból.

Fő útvonalak:

```text
agency-site-factory/tools/
agency-site-factory/tools/factory-manager/
agency-site-factory/tools/lib/site-factory.php
agency-site-factory/site-factory/projects/
```

### 1.2 Ügyfélsite alapréteg

Ez kerül be minden elkészített WordPress weboldalba:

```text
agency-site-factory/wp-content/plugins/agency-core
agency-site-factory/wp-content/themes/agency-theme
```

Az Agency Core felel az adatmodellért, admin beállításokért, blueprint importért, modulkezelésért, `/admin/` ügyfélportálért, schema kimenetért és közös szolgáltatásokért.

Az Agency Theme felel a frontend megjelenésért, template hierarchyért, design tokenekért, CSS-ért és a section komponensek PHP template partjaiért.

### 1.3 Opcionális feature modulok

Ezek önálló WordPress pluginek, amelyeket a Factory Manager vagy az Agency Kit Modules felület kapcsolhat be:

```text
agency-site-factory/wp-content/plugins/agency-module-booking
agency-site-factory/wp-content/plugins/agency-module-auth
agency-site-factory/wp-content/plugins/agency-module-legal
agency-site-factory/wp-content/plugins/agency-module-analytics
agency-site-factory/wp-content/plugins/agency-module-newsletter
agency-site-factory/wp-content/plugins/agency-module-webshop
```

Ezek minden ügyfélsite-ban újrahasználhatók. A Booking, Auth és Legal már a fejlesztőbarát, átláthatóbb szerkezet szerint van szétbontva. Az Analytics, Newsletter és Webshop is rendelkezik az egységes mappaszerkezettel, de részben még monolitikusabb, fokozatosan tovább bontható állapotban van.

## 2. Gyökérmappa: mi található a csomag tetején?

```text
agency-site-factory/
```

Fontos fájlok és mappák:

```text
agency-site-factory/README.md
```

Fő bevezető dokumentáció. Telepítés, Factory Manager, modulok, LocalWP workflow, Agency sections, blueprint importer és production modulok rövid áttekintése.

```text
agency-site-factory/CHANGELOG.md
```

Verziótörténet. Itt követhető, hogy az egyes kiadásokban milyen modul, admin, factory vagy theme változás történt.

```text
agency-site-factory/AUDIT.md
```

Audit jellegű megjegyzések és ellenőrzési pontok.

```text
agency-site-factory/composer.json
agency-site-factory/phpcs.xml.dist
```

Opcionális fejlesztői eszközök PHP lintinghez / WordPress Coding Standards ellenőrzéshez. A runtime működéshez nem kötelező Composer.

```text
agency-site-factory/start-factory-manager.bat
```

Windows helper a Factory Manager helyi indításához. A cél az, hogy ne kelljen kézzel PHP parancsokat írni; a manager böngészőből használható.

```text
agency-site-factory/.ddev/config.yaml
```

DDEV konfiguráció fejlesztői környezethez.

```text
agency-site-factory/.github/workflows/ci.yml
```

CI workflow helye. Itt futtatható/látható a jövőbeni automatikus lint és contract teszt folyamat.

## 3. Dokumentációs mappa

```text
agency-site-factory/docs/
```

Meglévő dokumentumok:

```text
agency-site-factory/docs/BOOKING-PORTAL.md
```

Booking portál és foglaláskezelő működésének leírása.

```text
agency-site-factory/docs/CLIENT-ADMIN.md
```

A különálló `/admin/` ügyfél-admin portál dokumentációja: belépés, session cookie, portál fülek, wp-admin beállítások.

```text
agency-site-factory/docs/CREATE-CLIENT-SITE.md
```

Új ügyfélsite létrehozása CLI fallbackkel és LocalWP/DDEV célmappával.

```text
agency-site-factory/docs/DEPLOYMENT-CHECKLIST.md
```

Production checklist: mi legyen beállítva élesítés előtt.

```text
agency-site-factory/docs/EXTENDING.md
```

Fejlesztői bővítési kézikönyv: új plugin, shortcode, Agency section, theme komponens, design variáns, migráció, email service használata.

```text
agency-site-factory/docs/FACTORY-MANAGER.md
```

Factory Manager használata: projektwizard, target public path, dry-run, install/update, modulfrissítés, manuális LocalWP lépések.

```text
agency-site-factory/docs/MODULE-MIGRATIONS.md
```

Modulmigrációk, adatmegőrzési szabályok, verziózott módosítások.

```text
agency-site-factory/docs/MODULE-SETUP.md
```

Modulok automatikus setupja, dependencyk, szükséges oldalak, ready/warning állapotok.

```text
agency-site-factory/docs/FRAMEWORK-STRUCTURE.md
```

Ez a dokumentum: teljes útvonaltérkép és működési magyarázat.

## 4. Factory Manager és projektgyártás

### 4.1 Factory Manager helye

```text
agency-site-factory/tools/factory-manager/
```

Ez a központi helyi manager UI. Nem WordPress plugin, hanem helyi admin/felület, amely az agency gépén fut. Az ügyfélsite-ban nem ebből lesz admin oldal.

Fájlok:

```text
agency-site-factory/tools/factory-manager/index.php
```

A Factory Manager webes belépési pontja. Feladata:

- localhost-only védelem;
- session és CSRF kezelés;
- projektlista renderelése;
- új projekt wizard renderelése;
- projekt szerkesztő formok kezelése;
- dry-run indítása;
- install/update indítása;
- modulfrissítés indítása;
- műveleti logok megjelenítése;
- státusz és hibaüzenetek kiírása.

```text
agency-site-factory/tools/factory-manager/manager-lib.php
```

Factory Manager helper függvények. Itt érdemes keresni azokat a manager oldali segédeket, amelyek:

- projektfájlokat olvasnak;
- manager metadata fájlokat kezelnek;
- operations/log bejegyzéseket mentenek;
- útvonalakat és configokat normalizálnak.

```text
agency-site-factory/tools/factory-manager/assets/style.css
```

A Factory Manager UI saját CSS-e. Ez nem kerül be az ügyfélsite frontendjébe.

### 4.2 Közös factory motor

```text
agency-site-factory/tools/lib/site-factory.php
```

Ez a factory működés közös magja. A CLI scriptek és a Factory Manager is erre támaszkodnak.

Fő felelősségek:

- project config beolvasása;
- config validálás;
- target WordPress public mappa validálás;
- engedélyezett modulok kiszámítása;
- manifest építés;
- könyvtárak másolása;
- meglévő célkönyvtárak backupolása;
- JSON írás;
- parancsok futtatása.

Fontos függvények:

```text
agency_factory_read_config()
agency_factory_validate_config()
agency_factory_validate_target()
agency_factory_enabled_modules()
agency_factory_build_manifest()
agency_factory_copy_directory()
agency_factory_backup_directory()
agency_factory_write_json()
agency_factory_run()
```

A factory verziója itt található:

```text
agency-site-factory/tools/lib/site-factory.php
```

Jelenlegi keretrendszer-verzió: `1.12.0`.

### 4.3 CLI fallback scriptek

A cél továbbra is az, hogy a felhasználó gombokkal dolgozzon a Factory Managerben, de a CLI fallback megmaradt.

```text
agency-site-factory/tools/create-client-site.php
```

Új ügyfélsite telepítő/frissítő script. Ugyanazokat az alapműveleteket végzi, mint a manager install/update gombja:

1. config beolvasása;
2. config validálás;
3. target WordPress public validálás;
4. Agency Theme másolás;
5. Agency Core másolás;
6. kiválasztott modulok másolása;
7. meglévő Agency mappák backupolása;
8. `wp-content/agency-project.json` manifest írása;
9. ha elérhető a WP-CLI, plugin/theme aktiválás, blueprint import és beállítások alkalmazása.

```text
agency-site-factory/tools/update-client-modules.php
```

Modulfrissítő script. Akkor hasznos, ha egy ügyfélsite-ban már telepítve van a framework, de az opcionális modulokat frissíteni kell.

### 4.4 Projektkonfigok helye

```text
agency-site-factory/site-factory/projects/
```

Itt vannak a manager által kezelt projektmappák.

Példa projekt:

```text
agency-site-factory/site-factory/projects/demo-beauty/
```

Fájlok:

```text
agency-site-factory/site-factory/projects/demo-beauty/config.json
```

Az ügyfélprojekt fő konfigurációja. Ebből dolgozik a factory. Tartalmazza például:

- project name;
- project slug;
- target WordPress public path;
- brand settings;
- színek;
- kontaktadatok;
- social URL-ek;
- blueprint slug;
- kiválasztott modulok.

```text
agency-site-factory/site-factory/projects/demo-beauty/manager.json
```

Factory Manager saját metadata. Például státusz, utolsó megnyitás/frissítés, manager oldali állapotok.

```text
agency-site-factory/site-factory/projects/demo-beauty/operations.json
```

Műveleti napló. Ide kerülnek a dry-run, install/update és module update futások eredményei.

### 4.5 Mi történik install/update közben?

Amikor a Factory Managerben megnyomod az install/update gombot, a logika lényegében ez:

1. A manager betölti a projekt `config.json` fájlját.
2. A `tools/lib/site-factory.php` validálja, hogy kötelező mezők megvannak-e.
3. A target path csak akkor elfogadott, ha valódi WordPress public mappa:
   - van `wp-admin`;
   - van `wp-content`;
   - WordPress-szerű könyvtárstruktúra látszik.
4. A factory backupolja az érintett régi Agency könyvtárakat, ha léteznek.
5. Bemásolja:
   - `wp-content/themes/agency-theme`;
   - `wp-content/plugins/agency-core`;
   - kiválasztott `agency-module-*` plugineket.
6. Létrehozza/frissíti:

```text
{target-public}\wp-content\agency-project.json
```

7. Ha WP-CLI elérhető és az adatbázis fut, akkor aktiválás/import/beállítás is megpróbálható.
8. Ha WP-CLI vagy adatbázis nem elérhető, a fájltelepítés akkor is megtörténhet, a log pedig pontos manuális lépéseket ad.

## 5. Ügyfélsite manifest

Minden generált/frissített ügyfélsite-ban ez a fájl rögzíti, hogy milyen Agency projekt van telepítve:

```text
{target-public}\wp-content\agency-project.json
```

Példa LocalWP cél esetén:

```text
C:\Users\Vizi\Local Sites\agency-test\app\public\wp-content\agency-project.json
```

Az Agency Core ezt olvassa a WordPress adminban:

```text
agency-site-factory/wp-content/plugins/agency-core/includes/project.php
```

Mit tartalmaz a manifest?

- project slug;
- framework version;
- blueprint;
- aktív modulok;
- site path;
- utolsó frissítés;
- státusz jellegű mezők.

Hol látható WordPress adminban?

```text
Agency Kit → Project
```

A menüt az Agency Core itt regisztrálja:

```text
agency-site-factory/wp-content/plugins/agency-core/includes/project.php
```

## 6. Agency Core plugin

### 6.1 Helye

```text
agency-site-factory/wp-content/plugins/agency-core
```

Ez minden ügyfélsite alap pluginje. A theme nem üzleti adatmodellt kezel; azt az Agency Core viszi.

### 6.2 Belépési pont

```text
agency-site-factory/wp-content/plugins/agency-core/agency-core.php
```

Feladata:

- plugin header;
- verzió és path konstansok;
- include fájlok betöltése;
- aktivációs hook;
- deaktivációs hook;
- textdomain betöltés.

Fontos konstansok:

```text
AGENCY_CORE_VERSION
AGENCY_CORE_SCHEMA_VERSION
AGENCY_CORE_FILE
AGENCY_CORE_PATH
```

### 6.3 Agency Core includes térkép

```text
agency-site-factory/wp-content/plugins/agency-core/includes/helpers.php
```

Általános helper függvények:

- beállítások lekérése;
- JSON fájl olvasás;
- `_agency_sections` validálás;
- section normalizálás;
- section tisztítás.

```text
agency-site-factory/wp-content/plugins/agency-core/includes/components.php
```

Core component registry és section normalizálás. Itt található:

- `agency_core_get_component_registry()`;
- régi section formátum kompatibilitása;
- component/variant validálás;
- mezőszintű sanitization;
- section migrációk.

Ez védi ki azt, hogy user input alapján tetszőleges PHP fájlt lehessen betölteni.

```text
agency-site-factory/wp-content/plugins/agency-core/includes/post-types.php
```

Egyedi tartalomtípusok regisztrációja. Itt keresd a service, testimonial, FAQ és hasonló CPT definíciókat.

```text
agency-site-factory/wp-content/plugins/agency-core/includes/taxonomies.php
```

Taxonómiák regisztrációja.

```text
agency-site-factory/wp-content/plugins/agency-core/includes/meta.php
```

Meta mezők:

- `_agency_sections`;
- service meta mezők;
- meta sanitization;
- metabox mentés.

```text
agency-site-factory/wp-content/plugins/agency-core/includes/settings.php
```

Agency Kit fő settings oldal:

```text
Agency Kit → Settings
Agency Kit → Import Blueprint
```

Itt vannak:

- márkaadatok;
- színek;
- kontaktadatok;
- schema alapadatok;
- admin menü regisztráció.

```text
agency-site-factory/wp-content/plugins/agency-core/includes/importer.php
```

Blueprint importer. Feladata:

- elérhető blueprintek listázása;
- admin import oldal renderelése;
- import POST kezelés;
- oldalak, szolgáltatások, FAQ, testimonial elemek upsertelése;
- primary menü létrehozása/frissítése;
- statikus front page és posts page beállítása;
- helyi médiafájlok importja;
- idempotens import slug alapján.

```text
agency-site-factory/wp-content/plugins/agency-core/includes/renderer.php
```

Plugin oldali render helper. Hordozható komponensrendereléshez és kártyalistákhoz használható.

```text
agency-site-factory/wp-content/plugins/agency-core/includes/preview.php
```

Gutenberg iframe preview REST endpoint. A szerkesztő középső vászna ezen keresztül kap PHP-renderelt előnézetet.

```text
agency-site-factory/wp-content/plugins/agency-core/includes/admin-editor.php
```

Gutenberg Agency sections panel és editor asset enqueue. Ehhez tartozik:

```text
agency-site-factory/wp-content/plugins/agency-core/assets/js/section-editor.js
```

```text
agency-site-factory/wp-content/plugins/agency-core/includes/schema.php
```

JSON-LD/schema kimenet. Feladata:

- SEO plugin detektálás;
- duplikált schema kimenet elkerülése;
- LocalBusiness / Article / Service / Offer / FAQPage jellegű graph építés;
- document title filter.

```text
agency-site-factory/wp-content/plugins/agency-core/includes/shortcodes.php
```

Core shortcode-ok:

```text
[agency_services_grid]
[agency_faq]
[agency_testimonials]
[agency_cta]
```

```text
agency-site-factory/wp-content/plugins/agency-core/includes/project.php
```

Project manifest kezelés:

- `wp-content/agency-project.json` útvonal meghatározása;
- manifest olvasás/írás;
- project config validálás;
- project config alkalmazás;
- `Agency Kit → Project` admin oldal.

```text
agency-site-factory/wp-content/plugins/agency-core/includes/modules.php
```

Modul registry és modulkapcsolás:

- elérhető modulok listája;
- manifest modulok szinkronizálása;
- modul állapot kapcsolása;
- `Agency Kit → Modules` oldal;
- module dependencyk kijelzése.

```text
agency-site-factory/wp-content/plugins/agency-core/includes/module-setup.php
```

Modul setup motor:

- modulokhoz szükséges oldalak létrehozása;
- Booking CTA bekötése;
- primary menü frissítése;
- display repair;
- setup státusz;
- inactive module placeholder komponensek.

```text
agency-site-factory/wp-content/plugins/agency-core/includes/module-services.php
```

Közös modul szolgáltatások:

- modul opciók kezelése;
- verziózott migráció;
- audit log;
- rate limit;
- secret encryption;
- Email Service;
- Email Service admin oldal;
- Activity Log admin oldal;
- biztonságos REST route helper.

Admin oldalak innen:

```text
Agency Kit → Email Service
Agency Kit → Activity Log
```

```text
agency-site-factory/wp-content/plugins/agency-core/includes/client-admin.php
```

Különálló ügyfél-admin portál a domain `/admin/` útvonalán. Ez nagyon fontos: nem WordPress wp-admin, nem theme oldalsablon, nem frontend profiloldal.

Feladata:

- `/admin/` request felismerése;
- teljes portáldokumentum renderelése;
- login oldal renderelése;
- saját `agency_portal_session` cookie;
- szerveroldali transient session;
- portál felhasználók kezelése;
- default portál user létrehozása;
- portál action dispatcher;
- portál tab registry;
- wp-admin Client Admin beállítóoldal;
- első féltől származó event tracking tárolása;
- wp-admin redirect portál manager usereknek.

Kapcsolódó assetek:

```text
agency-site-factory/wp-content/plugins/agency-core/assets/client-admin.css
agency-site-factory/wp-content/plugins/agency-core/assets/client-admin-override.css
agency-site-factory/wp-content/plugins/agency-core/assets/first-party-analytics.js
```

```text
agency-site-factory/wp-content/plugins/agency-core/includes/cli.php
```

WP-CLI parancsok helye, például blueprint listázás/import.

### 6.4 Agency Core assetek

```text
agency-site-factory/wp-content/plugins/agency-core/assets/css/admin.css
```

Agency Kit wp-admin oldalak stílusa.

```text
agency-site-factory/wp-content/plugins/agency-core/assets/css/preview.css
```

Editor preview kapcsolódó stílus.

```text
agency-site-factory/wp-content/plugins/agency-core/assets/js/section-editor.js
```

Gutenberg Agency sections panel JavaScript:

- section lista;
- component választás;
- variant választás;
- mezőszerkesztés;
- sorrendezés;
- törlés;
- preview frissítés.

```text
agency-site-factory/wp-content/plugins/agency-core/assets/first-party-analytics.js
```

Saját, első féltől származó interakciómérés. Például booking gomb kattintás, oldal interakciók, konverziós események alapja.

## 7. Blueprint rendszer

### 7.1 Blueprint mappák

```text
agency-site-factory/wp-content/plugins/agency-core/blueprints/beauty
agency-site-factory/wp-content/plugins/agency-core/blueprints/consulting
```

Mindegyik blueprint azonos elv szerint épül fel.

Beauty:

```text
agency-site-factory/wp-content/plugins/agency-core/blueprints/beauty/config.json
agency-site-factory/wp-content/plugins/agency-core/blueprints/beauty/pages.json
agency-site-factory/wp-content/plugins/agency-core/blueprints/beauty/services.json
agency-site-factory/wp-content/plugins/agency-core/blueprints/beauty/faq.json
agency-site-factory/wp-content/plugins/agency-core/blueprints/beauty/testimonials.json
agency-site-factory/wp-content/plugins/agency-core/blueprints/beauty/manifest.json
```

Consulting:

```text
agency-site-factory/wp-content/plugins/agency-core/blueprints/consulting/config.json
agency-site-factory/wp-content/plugins/agency-core/blueprints/consulting/pages.json
agency-site-factory/wp-content/plugins/agency-core/blueprints/consulting/services.json
agency-site-factory/wp-content/plugins/agency-core/blueprints/consulting/faq.json
agency-site-factory/wp-content/plugins/agency-core/blueprints/consulting/testimonials.json
agency-site-factory/wp-content/plugins/agency-core/blueprints/consulting/manifest.json
```

### 7.2 Mit csinál a blueprint import?

Az importer:

- létrehozza/frissíti a szükséges oldalakat;
- `_agency_sections` meta alapján felépíti a moduláris oldalstruktúrát;
- létrehozza/frissíti a szolgáltatásokat;
- létrehozza/frissíti a FAQ elemeket;
- létrehozza/frissíti a testimonial elemeket;
- beállítja a kezdőlapot statikus front page-ként;
- beállítja a Blog oldalt posts page-ként;
- létrehozza/frissíti az Agency Primary menüt.

Idempotencia: ugyanazt a blueprintet többször futtatva slug alapján frissít, nem duplikál.

### 7.3 Section formátum

Új formátum:

```json
{
  "component": "hero",
  "variant": "split",
  "data": {
    "title": "Bemutatkozó cím"
  }
}
```

Régi formátum továbbra is kezelt:

```json
{
  "component": "hero/hero-split",
  "data": {
    "title": "Bemutatkozó cím"
  }
}
```

A normalizálás helye:

```text
agency-site-factory/wp-content/plugins/agency-core/includes/components.php
agency-site-factory/wp-content/plugins/agency-core/includes/helpers.php
```

A tényleges theme template kiválasztás helye:

```text
agency-site-factory/wp-content/themes/agency-theme/inc/components.php
```

## 8. Agency Theme

### 8.1 Helye

```text
agency-site-factory/wp-content/themes/agency-theme
```

Ez a klasszikus WordPress theme. Nem tartalmaz üzleti adatmodellt. Megjelenít, CSS-t ad, template partokat renderel.

### 8.2 Theme fő fájlok

```text
agency-site-factory/wp-content/themes/agency-theme/functions.php
```

Theme bootstrap:

- theme setup;
- menu helyek;
- asset enqueue;
- Agency Theme include-ok betöltése.

```text
agency-site-factory/wp-content/themes/agency-theme/style.css
```

WordPress theme header és alap theme metadata.

```text
agency-site-factory/wp-content/themes/agency-theme/front-page.php
```

Kezdőlap template. Ha van `_agency_sections`, azokat rendereli.

```text
agency-site-factory/wp-content/themes/agency-theme/page.php
```

Általános oldal template. Agency sections esetén komponenseket renderel, különben WordPress content.

```text
agency-site-factory/wp-content/themes/agency-theme/index.php
agency-site-factory/wp-content/themes/agency-theme/single.php
agency-site-factory/wp-content/themes/agency-theme/archive.php
agency-site-factory/wp-content/themes/agency-theme/404.php
```

Standard WordPress template hierarchy fájlok.

```text
agency-site-factory/wp-content/themes/agency-theme/header.php
agency-site-factory/wp-content/themes/agency-theme/footer.php
```

Globális header/footer wrapper.

### 8.3 Theme component registry

```text
agency-site-factory/wp-content/themes/agency-theme/inc/components.php
```

Ez köti össze a `component + variant` adatmodellt a PHP template partokkal.

Feladata:

- theme oldali component registry;
- engedélyezett komponensek és variánsok;
- template path meghatározása;
- section renderelés;
- backward compatibility;
- `template-parts/{component}/{component}-{variant}.php` betöltése.

Példa:

```json
{
  "component": "hero",
  "variant": "split"
}
```

Ebből ez lesz:

```text
agency-site-factory/wp-content/themes/agency-theme/template-parts/hero/hero-split.php
```

### 8.4 Theme CSS

```text
agency-site-factory/wp-content/themes/agency-theme/assets/css/tokens.css
```

Design tokenek:

- `--agency-color-primary`;
- `--agency-color-secondary`;
- `--agency-color-background`;
- `--agency-color-text`;
- `--agency-font-heading`;
- további globális változók.

Az Agency Kit Settingsben megadott fő színek runtime felülírhatják ezeket.

```text
agency-site-factory/wp-content/themes/agency-theme/assets/css/base.css
```

Alap HTML/body/tipográfia.

```text
agency-site-factory/wp-content/themes/agency-theme/assets/css/layout.css
```

Oldal shell, grid, fő layout szabályok.

```text
agency-site-factory/wp-content/themes/agency-theme/assets/css/components.css
```

Frontend komponensek és variánsok CSS-e.

```text
agency-site-factory/wp-content/themes/agency-theme/assets/css/utilities.css
```

Kisebb utility classok.

```text
agency-site-factory/wp-content/themes/agency-theme/assets/css/editor.css
```

Editorhoz kapcsolódó theme CSS.

### 8.5 Theme JavaScript

```text
agency-site-factory/wp-content/themes/agency-theme/assets/js/main.js
```

Minimális frontend JavaScript. Ide tartozhatnak theme-szintű interakciók.

### 8.6 Template partok

Layout:

```text
agency-site-factory/wp-content/themes/agency-theme/template-parts/layout/site-header.php
agency-site-factory/wp-content/themes/agency-theme/template-parts/layout/site-footer.php
agency-site-factory/wp-content/themes/agency-theme/template-parts/layout/breadcrumbs.php
```

Header variánsok:

```text
agency-site-factory/wp-content/themes/agency-theme/template-parts/header/header-default.php
agency-site-factory/wp-content/themes/agency-theme/template-parts/header/header-centered.php
agency-site-factory/wp-content/themes/agency-theme/template-parts/header/header-compact.php
agency-site-factory/wp-content/themes/agency-theme/template-parts/header/header-split.php
```

Hero variánsok:

```text
agency-site-factory/wp-content/themes/agency-theme/template-parts/hero/hero-split.php
agency-site-factory/wp-content/themes/agency-theme/template-parts/hero/hero-centered.php
agency-site-factory/wp-content/themes/agency-theme/template-parts/hero/hero-image-card.php
agency-site-factory/wp-content/themes/agency-theme/template-parts/hero/hero-minimal.php
```

Services variánsok:

```text
agency-site-factory/wp-content/themes/agency-theme/template-parts/services/services-grid.php
agency-site-factory/wp-content/themes/agency-theme/template-parts/services/services-cards.php
agency-site-factory/wp-content/themes/agency-theme/template-parts/services/services-icon-list.php
agency-site-factory/wp-content/themes/agency-theme/template-parts/services/services-featured.php
agency-site-factory/wp-content/themes/agency-theme/template-parts/services/service-card.php
```

CTA variánsok:

```text
agency-site-factory/wp-content/themes/agency-theme/template-parts/cta/cta-simple.php
agency-site-factory/wp-content/themes/agency-theme/template-parts/cta/cta-boxed.php
agency-site-factory/wp-content/themes/agency-theme/template-parts/cta/cta-full-width.php
agency-site-factory/wp-content/themes/agency-theme/template-parts/cta/cta-booking.php
```

FAQ:

```text
agency-site-factory/wp-content/themes/agency-theme/template-parts/faq/faq-accordion.php
```

Contact:

```text
agency-site-factory/wp-content/themes/agency-theme/template-parts/contact/contact-section.php
```

Blog:

```text
agency-site-factory/wp-content/themes/agency-theme/template-parts/blog/blog-grid.php
agency-site-factory/wp-content/themes/agency-theme/template-parts/blog/related-posts.php
```

## 9. Egységes feature plugin szerkezet

Az új fejlesztőbarát célstruktúra minden modulnál ez:

```text
agency-module-{slug}/
  agency-module-{slug}.php
  README.md
  bootstrap/
    plugin.php
  includes/
    services/
    repositories/
    migrations/
    admin/
    frontend/
    rest/
  templates/
    frontend/
    admin/
  assets/
    css/
    js/
```

Mit jelent ez a gyakorlatban?

```text
agency-module-{slug}.php
```

WordPress plugin belépési pont. Plugin header, minimális konstansok/betöltés. A cél az, hogy itt ne legyen üzleti logika.

```text
bootstrap/plugin.php
```

Modul bootstrap. Itt történik:

- verzió konstansok;
- path/url konstansok;
- include fájlok betöltése;
- hookok regisztrálása;
- activation hook összekötése.

```text
includes/services/
```

Üzleti szolgáltatások, beállítások, helper logika. Például email token, verification, component registry, page generator.

```text
includes/repositories/
```

Adatbázis- és adatlekérési réteg. Például Booking táblák, availability lekérdezések, settings read/write.

```text
includes/migrations/
```

Verziózott adatbázis vagy option migrációk helye. Jelenleg több modulnál még üres vagy előkészített.

```text
includes/admin/
```

Wp-admin oldalak, admin POST actionök, admin menük.

```text
includes/frontend/
```

Frontend shortcode-ok, frontend handlerek, customer-facing renderelés, portálhoz kapcsolódó frontend kimenetek.

```text
includes/rest/
```

REST endpointok helye. Jelenleg néhány modulnál a REST logika még frontend fájlban van, de az új célstruktúra szerint ide bontható tovább.

```text
templates/frontend/
templates/admin/
```

Template fájlok helye. Ha egy modul renderelése nagyobb lesz, ide érdemes kitenni a markupot a PHP logika mellől.

```text
assets/css/
assets/js/
```

Modul saját frontend/admin CSS és JavaScript.

## 10. Booking modul

### 10.1 Helye

```text
agency-site-factory/wp-content/plugins/agency-module-booking
```

Ez a foglalási rendszer pluginje.

### 10.2 Belépési pont és bootstrap

```text
agency-site-factory/wp-content/plugins/agency-module-booking/agency-module-booking.php
```

Plugin header és bootstrap betöltés.

```text
agency-site-factory/wp-content/plugins/agency-module-booking/bootstrap/plugin.php
```

Booking modul loader. Fontos konstansok:

```text
AGENCY_BOOKING_VERSION
AGENCY_BOOKING_DB_VERSION
AGENCY_BOOKING_FILE
AGENCY_BOOKING_PATH
```

### 10.3 Booking adat- és availability réteg

```text
agency-site-factory/wp-content/plugins/agency-module-booking/includes/repositories/bookings.php
```

Ez a Booking legfontosabb backend fájlja. Itt található:

- booking tábla neve;
- default booking settings;
- settings lekérés;
- adatbázis migráció/dbDelta;
- booking manager role/capability;
- szolgáltatás időtartam és ár meta;
- service metabox;
- setup apply hook;
- nyitvatartás és zárt napok értelmezése;
- blocked interval számítás;
- elérhető slotok számítása;
- slot foglalhatóság ellenőrzése;
- booking email tokenek;
- booking email renderelés;
- booking email küldés.

Fontos függvények:

```text
agency_booking_table()
agency_booking_defaults()
agency_booking_settings()
agency_booking_migrate()
agency_booking_activate()
agency_booking_get_available_slots()
agency_booking_is_slot_available()
agency_booking_send_email()
```

### 10.4 Booking frontend

```text
agency-site-factory/wp-content/plugins/agency-module-booking/includes/frontend/booking-shortcodes.php
```

Frontend booking wizard és shortcode-ok.

Shortcode-ok:

```text
[agency_booking]
[agency_booking_form]
[agency_booking_list]
[agency_booking_customer_list]
```

REST endpointok ebben a fájlban:

```text
/wp-json/agency/v1/booking-slots
/wp-json/agency/v1/booking-calendar
```

Feladatok:

- négy lépéses booking UI renderelése;
- szolgáltatásválasztás;
- dátumválasztás;
- időpontválasztás;
- adatok megadása;
- customer booking lista;
- guest verification;
- booking submit kezelés.

### 10.5 Booking wp-admin

```text
agency-site-factory/wp-content/plugins/agency-module-booking/includes/admin/bookings-admin.php
```

Wp-admin Booking oldalak:

```text
Agency Kit → Booking
Agency Kit → Booking Settings
```

Feladatok:

- booking statisztikák;
- booking lista;
- status change: approve/reject/cancel/complete;
- settings page;
- calendar settings mentés;
- booking manager létrehozás;
- CSV export;
- portál action hookok kezelése.

Portál action hookok:

```text
agency_client_portal_action_agency_booking_status
agency_client_portal_action_agency_booking_save_settings
```

### 10.6 Booking külön `/admin/` portál integráció

```text
agency-site-factory/wp-content/plugins/agency-module-booking/includes/frontend/client-portal.php
```

Ez kapcsolja be a Booking modult az ügyfél-admin portálba.

Feladata:

- `/admin/` portál Booking tabok hozzáadása;
- booking lista renderelése;
- approve/reject gombok renderelése;
- naptárbeállítás form renderelése;
- legacy booking manager shortcode-ok kompatibilitása;
- régi manager oldalak átirányítása az új `/admin/` portálra.

Portál tab filter:

```text
agency_client_portal_tabs
```

Legacy shortcode-ok:

```text
[agency_booking_manager_login]
[agency_booking_manager_portal]
```

### 10.7 Booking Agency section registry

```text
agency-site-factory/wp-content/plugins/agency-module-booking/includes/services/component-registry.php
```

Ez teszi elérhetővé a Booking komponenseket az Agency sections rendszerben.

Komponensek:

```text
booking/form
booking/customer-list
```

### 10.8 Booking compatibility wrapper fájlok

Ezek backward compatibility miatt maradtak:

```text
agency-site-factory/wp-content/plugins/agency-module-booking/includes/data.php
agency-site-factory/wp-content/plugins/agency-module-booking/includes/frontend.php
agency-site-factory/wp-content/plugins/agency-module-booking/includes/admin.php
agency-site-factory/wp-content/plugins/agency-module-booking/includes/portal.php
agency-site-factory/wp-content/plugins/agency-module-booking/includes/integrations.php
```

Régi client site-ok és régi include útvonalak ne törjenek el.

### 10.9 Booking assetek

Új szerkezet szerinti assetek:

```text
agency-site-factory/wp-content/plugins/agency-module-booking/assets/css/booking.css
agency-site-factory/wp-content/plugins/agency-module-booking/assets/css/admin.css
agency-site-factory/wp-content/plugins/agency-module-booking/assets/js/booking.js
```

Legacy kompatibilitási assetek:

```text
agency-site-factory/wp-content/plugins/agency-module-booking/assets/booking.css
agency-site-factory/wp-content/plugins/agency-module-booking/assets/admin.css
agency-site-factory/wp-content/plugins/agency-module-booking/assets/booking.js
```

## 11. Auth modul

### 11.1 Helye

```text
agency-site-factory/wp-content/plugins/agency-module-auth
```

Feladata:

- frontend regisztráció;
- frontend login;
- logout;
- jelszó reset;
- email verification;
- customer account oldal;
- customer wp-admin tiltás;
- Auth komponensek Agency sectionsben.

### 11.2 Belépési pont és bootstrap

```text
agency-site-factory/wp-content/plugins/agency-module-auth/agency-module-auth.php
agency-site-factory/wp-content/plugins/agency-module-auth/bootstrap/plugin.php
```

Fontos konstansok:

```text
AGENCY_AUTH_VERSION
AGENCY_AUTH_FILE
AGENCY_AUTH_PATH
```

### 11.3 Auth services

```text
agency-site-factory/wp-content/plugins/agency-module-auth/includes/services/settings.php
```

Auth default beállítások és settings lekérés.

```text
agency-site-factory/wp-content/plugins/agency-module-auth/includes/services/verification.php
```

Email verification:

- user verified állapot;
- verification email küldés;
- verification request feldolgozás.

```text
agency-site-factory/wp-content/plugins/agency-module-auth/includes/services/roles.php
```

Customer role és activation/setup logika.

```text
agency-site-factory/wp-content/plugins/agency-module-auth/includes/services/component-registry.php
```

Auth komponensek regisztrációja az Agency sections rendszerhez.

### 11.4 Auth frontend

```text
agency-site-factory/wp-content/plugins/agency-module-auth/includes/frontend/shortcodes.php
```

Shortcode-ok:

```text
[agency_auth]
[agency_auth_login]
[agency_auth_register]
[agency_account]
[agency_auth_account]
```

Feladatok:

- login/register form renderelése;
- account oldal renderelése;
- admin guard;
- menüelemek módosítása.

```text
agency-site-factory/wp-content/plugins/agency-module-auth/includes/frontend/handlers.php
```

POST actionök:

- register;
- login;
- reset;
- verification resend.

### 11.5 Auth admin

```text
agency-site-factory/wp-content/plugins/agency-module-auth/includes/admin/menu.php
agency-site-factory/wp-content/plugins/agency-module-auth/includes/admin/page.php
agency-site-factory/wp-content/plugins/agency-module-auth/includes/admin/actions.php
```

Admin oldal:

```text
Agency Kit → Auth
```

Itt kezelhetők az Auth beállítások és user műveletek.

### 11.6 Auth assetek

```text
agency-site-factory/wp-content/plugins/agency-module-auth/assets/css/auth.css
agency-site-factory/wp-content/plugins/agency-module-auth/assets/auth.css
```

Az első az új szerkezet szerinti asset, a második legacy kompatibilitás miatt maradt.

## 12. Legal modul

### 12.1 Helye

```text
agency-site-factory/wp-content/plugins/agency-module-legal
```

Feladata:

- jogi oldalak létrehozása;
- adatkezelési/cookie/impresszum sablonok;
- hozzájárulási szövegek;
- cookie banner;
- footer legal linkek;
- Legal komponensek Agency sectionsben.

### 12.2 Belépési pont és bootstrap

```text
agency-site-factory/wp-content/plugins/agency-module-legal/agency-module-legal.php
agency-site-factory/wp-content/plugins/agency-module-legal/bootstrap/plugin.php
```

Fontos konstansok:

```text
AGENCY_LEGAL_VERSION
AGENCY_LEGAL_FILE
AGENCY_LEGAL_PATH
```

### 12.3 Legal services

```text
agency-site-factory/wp-content/plugins/agency-module-legal/includes/services/settings.php
```

Legal default settings, checkbox szövegek, template értékek.

```text
agency-site-factory/wp-content/plugins/agency-module-legal/includes/services/page-generator.php
```

Jogi oldalak generálása:

- privacy;
- cookie;
- imprint;
- egyéb setup page-ek.

```text
agency-site-factory/wp-content/plugins/agency-module-legal/includes/services/component-registry.php
```

Legal komponensek Agency sections registrybe.

```text
agency-site-factory/wp-content/plugins/agency-module-legal/includes/services/activation.php
```

Aktivációs/setup logika.

### 12.4 Legal frontend

```text
agency-site-factory/wp-content/plugins/agency-module-legal/includes/frontend/legal-links.php
```

Shortcode:

```text
[agency_legal_links]
```

Footer legal linkek renderelése.

```text
agency-site-factory/wp-content/plugins/agency-module-legal/includes/frontend/cookie-banner.php
```

Cookie banner renderelése és consent kapcsolódás.

### 12.5 Legal admin

```text
agency-site-factory/wp-content/plugins/agency-module-legal/includes/admin/menu.php
agency-site-factory/wp-content/plugins/agency-module-legal/includes/admin/page.php
agency-site-factory/wp-content/plugins/agency-module-legal/includes/admin/actions.php
```

Admin oldal:

```text
Agency Kit → Legal
```

### 12.6 Legal assetek

```text
agency-site-factory/wp-content/plugins/agency-module-legal/assets/css/legal.css
agency-site-factory/wp-content/plugins/agency-module-legal/assets/js/legal.js
agency-site-factory/wp-content/plugins/agency-module-legal/assets/legal.css
agency-site-factory/wp-content/plugins/agency-module-legal/assets/legal.js
```

Az `assets/css` és `assets/js` az új szerkezet, a gyökér assetek legacy kompatibilitást szolgálnak.

## 13. Analytics modul

### 13.1 Helye

```text
agency-site-factory/wp-content/plugins/agency-module-analytics
```

Feladata:

- analytics beállítások;
- GA4 jellegű konfiguráció;
- Do Not Track figyelembevétele;
- Legal cookie consenthez kötött mérés;
- `/admin/` portál statisztikai oldal;
- oldalankénti látogatottság;
- interakciók és konverziók megjelenítése.

### 13.2 Fő fájlok

```text
agency-site-factory/wp-content/plugins/agency-module-analytics/agency-module-analytics.php
```

Jelenleg a modul fő logikája többnyire ebben a fájlban van.

Admin oldal:

```text
Agency Kit → Analytics
```

Portál integráció:

```text
agency_client_portal_tabs
agency_client_portal_action_agency_analytics_save
```

```text
agency-site-factory/wp-content/plugins/agency-module-analytics/analytics.js
```

Frontend analytics JavaScript.

```text
agency-site-factory/wp-content/plugins/agency-module-analytics/assets/
agency-site-factory/wp-content/plugins/agency-module-analytics/bootstrap/
agency-site-factory/wp-content/plugins/agency-module-analytics/includes/
agency-site-factory/wp-content/plugins/agency-module-analytics/templates/
```

Előkészített fejlesztőbarát struktúra. A következő nagyobb refaktorban az `agency-module-analytics.php` bontható tovább ezekbe.

## 14. Newsletter modul

### 14.1 Helye

```text
agency-site-factory/wp-content/plugins/agency-module-newsletter
```

Feladata:

- newsletter form;
- double opt-in;
- unsubscribe;
- feliratkozói tábla;
- consent alapú export.

### 14.2 Fő fájlok

```text
agency-site-factory/wp-content/plugins/agency-module-newsletter/agency-module-newsletter.php
```

Jelenlegi fő modul logika.

Shortcode:

```text
[agency_newsletter]
```

Admin oldal:

```text
Agency Kit → Newsletter
```

```text
agency-site-factory/wp-content/plugins/agency-module-newsletter/newsletter.css
```

Frontend CSS.

Előkészített mappák:

```text
agency-site-factory/wp-content/plugins/agency-module-newsletter/assets/
agency-site-factory/wp-content/plugins/agency-module-newsletter/bootstrap/
agency-site-factory/wp-content/plugins/agency-module-newsletter/includes/
agency-site-factory/wp-content/plugins/agency-module-newsletter/templates/
```

## 15. Webshop modul

### 15.1 Helye

```text
agency-site-factory/wp-content/plugins/agency-module-webshop
```

Jelenleg skeleton/placeholder modul. Célja, hogy később webshop funkciót lehessen egységes modulstruktúrában hozzáadni.

Fő fájl:

```text
agency-site-factory/wp-content/plugins/agency-module-webshop/agency-module-webshop.php
```

Admin oldal:

```text
Agency Kit → Webshop
```

Előkészített szerkezet:

```text
agency-site-factory/wp-content/plugins/agency-module-webshop/assets/
agency-site-factory/wp-content/plugins/agency-module-webshop/bootstrap/
agency-site-factory/wp-content/plugins/agency-module-webshop/includes/
agency-site-factory/wp-content/plugins/agency-module-webshop/templates/
```

## 16. Különálló ügyfél-admin: `/admin/`

### 16.1 Hol van?

A különálló ügyfél-admin magja:

```text
agency-site-factory/wp-content/plugins/agency-core/includes/client-admin.php
```

CSS:

```text
agency-site-factory/wp-content/plugins/agency-core/assets/client-admin.css
agency-site-factory/wp-content/plugins/agency-core/assets/client-admin-override.css
```

Interakciómérés:

```text
agency-site-factory/wp-content/plugins/agency-core/assets/first-party-analytics.js
```

Booking portál oldalak:

```text
agency-site-factory/wp-content/plugins/agency-module-booking/includes/frontend/client-portal.php
```

Analytics portál oldal:

```text
agency-site-factory/wp-content/plugins/agency-module-analytics/agency-module-analytics.php
```

### 16.2 Miben más, mint a wp-admin?

A `/admin/` nem a WordPress admin.

Nem ez:

```text
/wp-admin/
```

Hanem ez:

```text
/admin/
```

A portál:

- teljesen külön HTML dokumentumként renderelődik;
- nem az aktív theme `page.php` vagy `front-page.php` fájlján át fut;
- nem a weboldal belső profiloldala;
- saját bejelentkező képernyője van;
- saját `agency_portal_session` cookie-t használ;
- szerveroldali transient sessiont használ;
- moduláris tab rendszerrel bővíthető.

### 16.3 Belépés és session

Cookie név:

```text
agency_portal_session
```

Fontos függvények:

```text
agency_core_client_admin_cookie_name()
agency_core_client_admin_set_cookie()
agency_core_client_admin_clear_cookie()
agency_core_client_admin_create_session()
agency_core_client_admin_destroy_session()
agency_core_client_admin_bootstrap_session()
```

A portál action URL-eket ezzel kell képezni:

```text
agency_core_client_admin_action_url()
```

Ez azért fontos, mert így a modulok nem kézzel raknak össze admin URL-t, hanem ugyanazt a portál action mechanizmust használják.

### 16.4 Portál bővítése modulból

Új portál oldal hozzáadása filterrel:

```text
agency_client_portal_tabs
```

Portál action hozzáadása action hookkal:

```text
agency_client_portal_action_{action_name}
```

Példák:

```text
agency_client_portal_action_agency_booking_status
agency_client_portal_action_agency_booking_save_settings
agency_client_portal_action_agency_analytics_save
```

### 16.5 Wp-admin Client Admin beállítás

WordPress adminban:

```text
Agency Kit → Client Admin
```

Regisztrálás helye:

```text
agency-site-factory/wp-content/plugins/agency-core/includes/client-admin.php
```

Itt kezelhetők a portál hozzáférések. A cél az, hogy az ügyfél ne a teljes WordPress wp-adminba menjen napi használatra, hanem a szebb, célzott `/admin/` felületre.

## 17. Agency sections működés

### 17.1 Hol tárolódik?

Az oldalak section listája WordPress post meta mezőben:

```text
_agency_sections
```

Regisztráció és sanitization:

```text
agency-site-factory/wp-content/plugins/agency-core/includes/meta.php
```

Normalizálás:

```text
agency-site-factory/wp-content/plugins/agency-core/includes/components.php
agency-site-factory/wp-content/plugins/agency-core/includes/helpers.php
```

### 17.2 Hol szerkeszthető?

WordPress Gutenberg editor jobb oldali panel:

```text
Agency sections
```

JS:

```text
agency-site-factory/wp-content/plugins/agency-core/assets/js/section-editor.js
```

PHP enqueue:

```text
agency-site-factory/wp-content/plugins/agency-core/includes/admin-editor.php
```

### 17.3 Hol renderelődik?

Theme oldalon:

```text
agency-site-factory/wp-content/themes/agency-theme/inc/components.php
```

Template-ek:

```text
agency-site-factory/wp-content/themes/agency-theme/template-parts/
```

Oldal template-ek:

```text
agency-site-factory/wp-content/themes/agency-theme/front-page.php
agency-site-factory/wp-content/themes/agency-theme/page.php
```

### 17.4 Fontos biztonsági elv

A section adatokban érkező `component` és `variant` nem jelenthet szabad fájlútvonalat.

Helyes működés:

1. Core normalizálja és validálja a sectiont.
2. Theme registry megnézi, hogy a component/variant engedélyezett-e.
3. Csak registryben szereplő template path tölthető be.

## 18. Shortcode térkép

Core:

```text
[agency_services_grid]
[agency_faq]
[agency_testimonials]
[agency_cta]
```

Core shortcode fájl:

```text
agency-site-factory/wp-content/plugins/agency-core/includes/shortcodes.php
```

Booking:

```text
[agency_booking]
[agency_booking_form]
[agency_booking_list]
[agency_booking_customer_list]
[agency_booking_manager_login]
[agency_booking_manager_portal]
```

Booking shortcode fájlok:

```text
agency-site-factory/wp-content/plugins/agency-module-booking/includes/frontend/booking-shortcodes.php
agency-site-factory/wp-content/plugins/agency-module-booking/includes/frontend/client-portal.php
```

Auth:

```text
[agency_auth]
[agency_auth_login]
[agency_auth_register]
[agency_account]
[agency_auth_account]
```

Auth shortcode fájl:

```text
agency-site-factory/wp-content/plugins/agency-module-auth/includes/frontend/shortcodes.php
```

Legal:

```text
[agency_legal_links]
```

Legal shortcode fájl:

```text
agency-site-factory/wp-content/plugins/agency-module-legal/includes/frontend/legal-links.php
```

Newsletter:

```text
[agency_newsletter]
```

Newsletter shortcode fájl:

```text
agency-site-factory/wp-content/plugins/agency-module-newsletter/agency-module-newsletter.php
```

Client Admin legacy shortcode:

```text
[agency_client_admin]
```

Helye:

```text
agency-site-factory/wp-content/plugins/agency-core/includes/client-admin.php
```

A fő, ajánlott ügyfél-admin viszont a `/admin/` URL.

## 19. REST endpointok

Core preview:

```text
agency-site-factory/wp-content/plugins/agency-core/includes/preview.php
```

Feladata: Gutenberg Agency preview HTML generálása.

Core event/analytics:

```text
agency-site-factory/wp-content/plugins/agency-core/includes/client-admin.php
```

Feladata: first-party event tracking fogadása.

Core secure REST helper:

```text
agency-site-factory/wp-content/plugins/agency-core/includes/module-services.php
```

Függvény:

```text
agency_core_register_secure_rest_route()
```

Booking availability/calendar:

```text
agency-site-factory/wp-content/plugins/agency-module-booking/includes/frontend/booking-shortcodes.php
```

Endpointok:

```text
/wp-json/agency/v1/booking-slots
/wp-json/agency/v1/booking-calendar
```

## 20. Admin menü térkép

Fő menü:

```text
Agency Kit
```

Regisztráció:

```text
agency-site-factory/wp-content/plugins/agency-core/includes/settings.php
```

Core oldalak:

```text
Agency Kit → Settings
Agency Kit → Import Blueprint
Agency Kit → Project
Agency Kit → Modules
Agency Kit → Email Service
Agency Kit → Activity Log
Agency Kit → Client Admin
```

Helyek:

```text
agency-site-factory/wp-content/plugins/agency-core/includes/settings.php
agency-site-factory/wp-content/plugins/agency-core/includes/project.php
agency-site-factory/wp-content/plugins/agency-core/includes/modules.php
agency-site-factory/wp-content/plugins/agency-core/includes/module-services.php
agency-site-factory/wp-content/plugins/agency-core/includes/client-admin.php
```

Modul oldalak:

```text
Agency Kit → Booking
Agency Kit → Booking Settings
Agency Kit → Auth
Agency Kit → Legal
Agency Kit → Analytics
Agency Kit → Newsletter
Agency Kit → Webshop
```

Helyek:

```text
agency-site-factory/wp-content/plugins/agency-module-booking/includes/admin/bookings-admin.php
agency-site-factory/wp-content/plugins/agency-module-auth/includes/admin/menu.php
agency-site-factory/wp-content/plugins/agency-module-legal/includes/admin/menu.php
agency-site-factory/wp-content/plugins/agency-module-analytics/agency-module-analytics.php
agency-site-factory/wp-content/plugins/agency-module-newsletter/agency-module-newsletter.php
agency-site-factory/wp-content/plugins/agency-module-webshop/agency-module-webshop.php
```

## 21. Email Service

### 21.1 Hol van?

```text
agency-site-factory/wp-content/plugins/agency-core/includes/module-services.php
```

### 21.2 Mire való?

Központi emailküldő szolgáltatás, amelyet a modulok használhatnak. Például:

- Auth verification email;
- Booking confirmation email;
- Booking approve/reject email;
- Newsletter opt-in email.

### 21.3 Fontos függvények

```text
agency_core_email_settings()
agency_core_email_send()
agency_core_update_email_health()
agency_core_capture_wp_mail_error()
agency_core_encrypt_secret()
agency_core_decrypt_secret()
```

### 21.4 Admin felület

```text
Agency Kit → Email Service
```

Itt állítható a provider és tesztelhető a küldés. A secret/API kulcs titkosítva tárolódik.

## 22. Audit log, rate limit, migrációk

Közös szolgáltatások helye:

```text
agency-site-factory/wp-content/plugins/agency-core/includes/module-services.php
```

Fő függvények:

```text
agency_core_module_migrate()
agency_core_audit_log()
agency_core_rate_limit()
```

Activity Log admin:

```text
Agency Kit → Activity Log
```

Modulspecifikus migrációk helye az új struktúrában:

```text
agency-site-factory/wp-content/plugins/agency-module-booking/includes/migrations/
agency-site-factory/wp-content/plugins/agency-module-auth/includes/migrations/
agency-site-factory/wp-content/plugins/agency-module-legal/includes/migrations/
agency-site-factory/wp-content/plugins/agency-module-analytics/includes/migrations/
agency-site-factory/wp-content/plugins/agency-module-newsletter/includes/migrations/
agency-site-factory/wp-content/plugins/agency-module-webshop/includes/migrations/
```

Ha új adatbázis-verziós módosítást adsz hozzá, a cél:

1. modulverzió növelése;
2. migrációs callback írása;
3. `agency_core_module_migrate()` használata;
4. idempotens futás;
5. uninstallkor ügyféltartalom nem törlődik.

## 23. Tesztek és validáció

Tesztek helye:

```text
agency-site-factory/tests/
```

Statikus/contract tesztek:

```text
agency-site-factory/tests/blueprint-contract.php
agency-site-factory/tests/factory-manager-contract.php
agency-site-factory/tests/module-setup-contract.php
agency-site-factory/tests/module-structure-contract.php
agency-site-factory/tests/production-modules-contract.php
agency-site-factory/tests/project-factory-contract.php
```

WordPress integrációs jellegű tesztek:

```text
agency-site-factory/tests/wp-booking-availability.php
agency-site-factory/tests/wp-module-setup-integration.php
agency-site-factory/tests/wp-preview-integration.php
agency-site-factory/tests/wp-production-modules-integration.php
agency-site-factory/tests/wp-production-workflow.php
agency-site-factory/tests/wp-cli-idempotency.sh
```

Mit ellenőriznek?

- blueprint szerződés;
- Factory Manager szerződés;
- modulstruktúra;
- module setup;
- project factory;
- Booking availability;
- preview;
- production workflow;
- WP-CLI idempotencia.

## 24. Tipikus fejlesztői feladatok: hova nyúlj?

### 24.1 Új ügyfélsite gyártási flow módosítása

Ha az install/update/dry-run viselkedést módosítanád:

```text
agency-site-factory/tools/lib/site-factory.php
agency-site-factory/tools/create-client-site.php
agency-site-factory/tools/update-client-modules.php
agency-site-factory/tools/factory-manager/index.php
agency-site-factory/tools/factory-manager/manager-lib.php
```

Ha a manager UI külsejét módosítanád:

```text
agency-site-factory/tools/factory-manager/assets/style.css
```

### 24.2 Új projektmezőt adnál a wizardhoz

Érintett helyek:

```text
agency-site-factory/tools/factory-manager/index.php
agency-site-factory/tools/factory-manager/manager-lib.php
agency-site-factory/tools/lib/site-factory.php
agency-site-factory/wp-content/plugins/agency-core/includes/project.php
```

Ha az ügyfélsite-ban is alkalmazni kell a mezőt, akkor:

```text
agency-site-factory/wp-content/plugins/agency-core/includes/settings.php
```

vagy modulfüggően:

```text
agency-site-factory/wp-content/plugins/agency-module-{slug}/includes/services/settings.php
```

### 24.3 Új Agency sectiont adnál hozzá

Core registry/validálás:

```text
agency-site-factory/wp-content/plugins/agency-core/includes/components.php
```

Theme registry/render:

```text
agency-site-factory/wp-content/themes/agency-theme/inc/components.php
```

Template:

```text
agency-site-factory/wp-content/themes/agency-theme/template-parts/{component}/{component}-{variant}.php
```

CSS:

```text
agency-site-factory/wp-content/themes/agency-theme/assets/css/components.css
```

Blueprint használat:

```text
agency-site-factory/wp-content/plugins/agency-core/blueprints/{blueprint}/pages.json
```

### 24.4 Új design variánst adnál egy meglévő komponenshez

Példa: új hero variáns.

1. Új template:

```text
agency-site-factory/wp-content/themes/agency-theme/template-parts/hero/hero-{variant}.php
```

2. Registry:

```text
agency-site-factory/wp-content/themes/agency-theme/inc/components.php
agency-site-factory/wp-content/plugins/agency-core/includes/components.php
```

3. CSS:

```text
agency-site-factory/wp-content/themes/agency-theme/assets/css/components.css
```

4. Blueprint:

```text
agency-site-factory/wp-content/plugins/agency-core/blueprints/{blueprint}/pages.json
```

### 24.5 Új shortcode-ot adnál egy modulhoz

Modul frontend fájl:

```text
agency-site-factory/wp-content/plugins/agency-module-{slug}/includes/frontend/
```

Ha Booking:

```text
agency-site-factory/wp-content/plugins/agency-module-booking/includes/frontend/booking-shortcodes.php
```

Ha Auth:

```text
agency-site-factory/wp-content/plugins/agency-module-auth/includes/frontend/shortcodes.php
```

Ha Legal:

```text
agency-site-factory/wp-content/plugins/agency-module-legal/includes/frontend/legal-links.php
```

Ha új modul, akkor az új shortcode-ot a modul `bootstrap/plugin.php` fájljából töltsd be.

### 24.6 Új `/admin/` portál oldalt adnál

Core portál API:

```text
agency-site-factory/wp-content/plugins/agency-core/includes/client-admin.php
```

Modul oldali implementáció:

```text
agency-site-factory/wp-content/plugins/agency-module-{slug}/includes/frontend/
```

Példa Booking:

```text
agency-site-factory/wp-content/plugins/agency-module-booking/includes/frontend/client-portal.php
```

Használandó hook:

```text
agency_client_portal_tabs
```

Action mentéshez:

```text
agency_client_portal_action_{action_name}
```

### 24.7 Új adatbázis táblát adnál

Repository:

```text
agency-site-factory/wp-content/plugins/agency-module-{slug}/includes/repositories/
```

Migráció:

```text
agency-site-factory/wp-content/plugins/agency-module-{slug}/includes/migrations/
```

Közös migráció helper:

```text
agency-site-factory/wp-content/plugins/agency-core/includes/module-services.php
```

### 24.8 Új emailt küldenél

Közös service:

```text
agency-site-factory/wp-content/plugins/agency-core/includes/module-services.php
```

Hívandó függvény:

```text
agency_core_email_send()
```

Modul oldali email template/logika helye:

```text
agency-site-factory/wp-content/plugins/agency-module-{slug}/includes/services/
```

Booking példa:

```text
agency-site-factory/wp-content/plugins/agency-module-booking/includes/repositories/bookings.php
```

### 24.9 Új blueprintet adnál

Másold valamelyik meglévőt:

```text
agency-site-factory/wp-content/plugins/agency-core/blueprints/beauty
```

Új mappa:

```text
agency-site-factory/wp-content/plugins/agency-core/blueprints/{new-blueprint}
```

Kötelező fájlok:

```text
config.json
pages.json
services.json
faq.json
testimonials.json
manifest.json
```

Importer:

```text
agency-site-factory/wp-content/plugins/agency-core/includes/importer.php
```

Fontos: fájlútvonal ne származzon közvetlen user inputból. Csak allowlist/manifest alapján.

## 25. Régi ügyfélsite kompatibilitás

A rendszer több ponton backward-compatible.

### 25.1 Régi section formátum

Régi:

```json
{
  "component": "hero/hero-split",
  "data": {}
}
```

Új:

```json
{
  "component": "hero",
  "variant": "split",
  "data": {}
}
```

A régi formátumot a Core normalizálja.

### 25.2 Régi Booking include útvonalak

Compatibility wrapper fájlok:

```text
agency-site-factory/wp-content/plugins/agency-module-booking/includes/data.php
agency-site-factory/wp-content/plugins/agency-module-booking/includes/frontend.php
agency-site-factory/wp-content/plugins/agency-module-booking/includes/admin.php
agency-site-factory/wp-content/plugins/agency-module-booking/includes/portal.php
agency-site-factory/wp-content/plugins/agency-module-booking/includes/integrations.php
```

### 25.3 Régi shortcode-ok

Több régi shortcode megmaradt aliasnak. Például:

```text
[agency_booking]
[agency_booking_form]
[agency_booking_list]
[agency_booking_customer_list]
```

és Auth oldalon:

```text
[agency_account]
[agency_auth_account]
```

### 25.4 Factory Manager update

A Factory Manager nem töröl ügyféltartalmat. Az Agency könyvtárak felülírása előtt backup készül. A tartalmi adatokat WordPress adatbázisban és `wp-content/agency-project.json` manifestben kezeli.

## 26. Egy új weboldal gyártásának teljes útja

### 26.1 LocalWP site manuális létrehozása

Első körben a LocalWP site még manuálisan jön létre. Példa public path:

```text
C:\Users\Vizi\Local Sites\agency-test\app\public
```

### 26.2 Factory Manager indítása

Windows helper:

```text
agency-site-factory/start-factory-manager.bat
```

Tipikus URL:

```text
http://127.0.0.1:8765
```

### 26.3 Projekt létrehozása wizarddal

A manager wizard kitölti:

- project name;
- project slug;
- target public path;
- brand name;
- primary/secondary/background szín;
- phone;
- email;
- address;
- booking URL;
- social URL-ek;
- blueprint;
- modulok.

Ebből létrejön:

```text
agency-site-factory/site-factory/projects/{project-slug}/config.json
agency-site-factory/site-factory/projects/{project-slug}/manager.json
agency-site-factory/site-factory/projects/{project-slug}/operations.json
```

### 26.4 Dry-run

Dry-run esetén a factory nem ír a targetbe, csak megmondja:

- mit másolna;
- hova másolná;
- milyen backup várható;
- milyen modulok mennének;
- milyen WP-CLI lépéseket próbálna.

### 26.5 Install/update

Install/update esetén:

- Agency Theme bekerül a target `wp-content/themes/agency-theme` alá;
- Agency Core bekerül a target `wp-content/plugins/agency-core` alá;
- kiválasztott modulok bekerülnek a target `wp-content/plugins/agency-module-*` alá;
- manifest íródik;
- WP-CLI esetén aktiválás/import/beállítás is lefuthat.

### 26.6 Ügyfélsite WordPress admin

Az ügyfélsite wp-admin felülete:

```text
{site-url}/wp-admin/
```

Itt:

- Agency Core aktiválás;
- Agency Theme aktiválás;
- Agency Kit settings;
- blueprint import;
- module setup;
- Client Admin hozzáférés beállítása.

### 26.7 Ügyfél napi admin portál

Ügyfélbarát admin:

```text
{site-url}/admin/
```

Itt:

- áttekintés;
- booking kezelés;
- booking calendar settings;
- látogatottság;
- interakciók;
- konverziók;
- alap site beállítások.

## 27. Mit ne keverj össze?

### Factory Manager

Helye:

```text
agency-site-factory/tools/factory-manager/
```

Ez az agency/developer helyi eszköze új projektek létrehozására/frissítésére.

### WordPress wp-admin

URL:

```text
/wp-admin/
```

Ez a teljes WordPress admin. Itt van az Agency Kit.

### Ügyfél-admin portál

URL:

```text
/admin/
```

Ez az ügyfélnek szánt, szép, különálló, moduláris admin felület.

### Frontend weboldal

URL:

```text
/
```

Ez a publikus weboldal, amelyet az Agency Theme és az Agency sections renderel.

## 28. Rövid útvonalkalauz

Ha ezt keresed | Itt találod
--- | ---
Új projekt létrehozása | `agency-site-factory/tools/factory-manager/`
Factory core másolási logika | `agency-site-factory/tools/lib/site-factory.php`
CLI install fallback | `agency-site-factory/tools/create-client-site.php`
CLI module update fallback | `agency-site-factory/tools/update-client-modules.php`
Projekt configok | `agency-site-factory/site-factory/projects/`
Agency Core | `agency-site-factory/wp-content/plugins/agency-core/`
Agency Theme | `agency-site-factory/wp-content/themes/agency-theme/`
Theme komponensek | `agency-site-factory/wp-content/themes/agency-theme/template-parts/`
Theme registry | `agency-site-factory/wp-content/themes/agency-theme/inc/components.php`
Core component registry | `agency-site-factory/wp-content/plugins/agency-core/includes/components.php`
Gutenberg section editor | `agency-site-factory/wp-content/plugins/agency-core/assets/js/section-editor.js`
Blueprint importer | `agency-site-factory/wp-content/plugins/agency-core/includes/importer.php`
Blueprint JSON-ok | `agency-site-factory/wp-content/plugins/agency-core/blueprints/`
Külön `/admin/` portál | `agency-site-factory/wp-content/plugins/agency-core/includes/client-admin.php`
Booking modul | `agency-site-factory/wp-content/plugins/agency-module-booking/`
Booking adat/availability | `agency-site-factory/wp-content/plugins/agency-module-booking/includes/repositories/bookings.php`
Booking frontend wizard | `agency-site-factory/wp-content/plugins/agency-module-booking/includes/frontend/booking-shortcodes.php`
Booking portál | `agency-site-factory/wp-content/plugins/agency-module-booking/includes/frontend/client-portal.php`
Auth modul | `agency-site-factory/wp-content/plugins/agency-module-auth/`
Auth frontend | `agency-site-factory/wp-content/plugins/agency-module-auth/includes/frontend/`
Legal modul | `agency-site-factory/wp-content/plugins/agency-module-legal/`
Analytics modul | `agency-site-factory/wp-content/plugins/agency-module-analytics/`
Newsletter modul | `agency-site-factory/wp-content/plugins/agency-module-newsletter/`
Webshop skeleton | `agency-site-factory/wp-content/plugins/agency-module-webshop/`
Email Service | `agency-site-factory/wp-content/plugins/agency-core/includes/module-services.php`
Tesztek | `agency-site-factory/tests/`

## 29. Javasolt VS Code munkamódszer

Nyisd meg workspace-ként:

```text
agency-site-factory/
```

Így a fő gyökérben azonnal látod:

```text
docs/
site-factory/
tests/
tools/
wp-content/
```

Ha factory/projektgenerálás a feladat:

```text
tools/
site-factory/
```

Ha WordPress admin/core logika:

```text
wp-content/plugins/agency-core/
```

Ha frontend design:

```text
wp-content/themes/agency-theme/
```

Ha Booking/Auth/Legal/Analytics/Newsletter/Webshop:

```text
wp-content/plugins/agency-module-*/
```

Ha dokumentáció:

```text
docs/
README.md
CHANGELOG.md
```

## 30. Fejlesztési alapelv

A jelenlegi architektúra lényege:

- a Factory Manager gyárt és frissít;
- az Agency Core adatmodellt, admin logikát, importot, portált és modulkezelést ad;
- az Agency Theme csak megjelenít;
- a feature modulok önálló pluginek;
- az ügyfél napi adminja külön `/admin/` portálon él;
- a WordPress wp-admin továbbra is megmarad tulajdonosi/technikai adminnak;
- a régi shortcode-ok, section formátumok és include útvonalak lehetőség szerint nem törnek el;
- ügyféltartalmat update nem törölhet.

Ezért ha új funkciót építesz, először azt döntsd el, melyik réteghez tartozik:

```text
Projektgyártás?       → tools/ vagy site-factory/
Adatmodell/admin?     → agency-core/
Frontend design?      → agency-theme/
Üzleti feature?       → agency-module-{slug}/
Ügyfél napi admin?    → agency-core client-admin + modul portal tab
```

Ha ezt a szétválasztást tartod, a keretrendszer hosszú távon bővíthető marad, és egy új ügyfélsite nem fog összekeveredni sem a factory eszközzel, sem a WordPress teljes adminjával.
