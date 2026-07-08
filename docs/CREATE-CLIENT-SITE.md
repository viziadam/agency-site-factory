# Új ügyfélsite létrehozása

Az ajánlott 1.8-as workflow az [`Agency Factory Manager`](FACTORY-MANAGER.md): a projektconfig, dry-run, telepítés, frissítés, modulkezelés és production setup böngészőből végezhető. Az alábbi parancssoros folyamat fallback és automatizálási lehetőség.

Az Agency Site Factory 1.8 egy már létrehozott WordPress site-ba telepíti a közös frameworköt. A generátor nem hoz létre LocalWP site-ot, adatbázist vagy admin felhasználót, és nem töröl ügyféltartalmat.

## Repo-root alapú működés

A CLI scripteket az `agency-site-factory/` repo gyökeréből futtasd:

```text
agency-site-factory/
```

A generátor a forrást mindig ebből a gyökérből oldja fel:

```text
agency-site-factory/wp-content/plugins/agency-core/
agency-site-factory/wp-content/themes/agency-theme/
agency-site-factory/wp-content/plugins/agency-module-*/
agency-site-factory/site-factory/projects/
```

Másolási irány példa:

```text
agency-site-factory/wp-content/plugins/agency-core
  → {target-public}/wp-content/plugins/agency-core

agency-site-factory/wp-content/themes/agency-theme
  → {target-public}/wp-content/themes/agency-theme

agency-site-factory/wp-content/plugins/agency-module-booking
  → {target-public}/wp-content/plugins/agency-module-booking
```

Régi, ideiglenes vagy gépspecifikus workspace útvonalat a script nem használ framework-forrásként. A repo gyökerét a központi `tools/lib/site-factory.php` helper számolja ki a saját helyzetéből.

## LocalWP CLI fallback workflow

1. LocalWP-ben hozz létre egy új site-ot, például `demo-beauty` néven, PHP 8.0 vagy újabb környezettel.
2. Jegyezd fel a WordPress public mappát, például:

   ```text
   C:\Users\Vizi\Local Sites\demo-beauty\app\public
   ```

3. Másold a `site-factory/projects/demo-beauty` mappát új projektnévre, majd módosítsd a `config.json` fájlt. Minden site-, brand-, blueprint- és module-mező kötelező; a modulértékek csak `true` vagy `false` értékek lehetnek.
4. Először mindig futtass dry-runt:

   ```powershell
   php tools/create-client-site.php --config=site-factory/projects/demo-beauty/config.json --target="C:\Users\Vizi\Local Sites\demo-beauty\app\public" --dry-run
   ```

5. Telepítés WP-CLI nélkül:

   ```powershell
   php tools/create-client-site.php --config=site-factory/projects/demo-beauty/config.json --target="C:\Users\Vizi\Local Sites\demo-beauty\app\public"
   ```

   A script bemásolja a theme-et, a core plugint és a kiválasztott modulokat, backupolja a lecserélt Agency mappákat, létrehozza az `agency-project.json` manifestet, majd kiírja a pontos admin lépéseket.

6. LocalWP WP-CLI automatizáláshoz add meg a Local által használt PHP-t, php.ini-t és `wp-cli.phar` fájlt:

   ```powershell
   php tools/create-client-site.php --config=site-factory/projects/demo-beauty/config.json --target="C:\Users\Vizi\Local Sites\demo-beauty\app\public" --php="C:\path\to\php.exe" --php-ini="C:\path\to\php.ini" --wp-cli="C:\path\to\wp-cli.phar"
   ```

   Ekkor a generátor aktiválja az Agency Core-t és Agency Theme-et, beállítja a permalinket, importálja a blueprintet, a configból alkalmazza az Agency Settings értékeit, majd aktiválja a kiválasztott modulokat.

7. Ellenőrizd a WordPress adminban:

   - **Agency Kit → Project**: manifest és kapcsolati állapot;
   - **Agency Kit → Modules**: telepített és aktív modulok;
   - **Agency Kit → Settings**: márka- és schema-adatok;
   - **Agency Kit → Import Blueprint**: választott recept;
   - **Oldalak**: importált oldalak és kezdőlap.

## Manifest és biztonság

A generátor a klienssite `wp-content/agency-project.json` fájljában tartja a projekt azonosítóját, framework-verzióját, blueprintjét, bekapcsolt moduljait és időbélyegeit. A teljes validált config a WP-CLI átadáshoz `wp-content/agency-project-config.json` néven kerül a site-ba.

A célmappa csak akkor fogadható el, ha létező WordPress public könyvtár. A forráscsomagok útvonalai belső allowlistből származnak. Felülírás előtt minden Agency framework- és modulmappa időbélyeges `.backup-*` másolatot kap. A script bejegyzést, oldalt, médiát vagy más ügyféladatot nem töröl.

## Modulok későbbi hozzáadása

```powershell
php tools/update-client-modules.php --target="C:\Users\Vizi\Local Sites\demo-beauty\app\public" --enable=booking,legal --disable=newsletter --dry-run
```

WP-CLI-vel:

```bash
wp agency module list
wp agency module enable booking
wp agency module disable booking
wp agency project show
wp agency project apply-config wp-content/agency-project-config.json
```

Az `apply-config` biztonsági okból csak a site `wp-content` mappáján belüli JSON-t olvas. Az admin module kapcsolók nonce- és jogosultság-ellenőrzést használnak. Auth, Booking és Legal production modul; Webshop, Newsletter és Analytics továbbra is külön fejleszthető skeleton csomag.

## DDEV

Az új projektconfig DDEV site-nál is használható, ha a WordPress public célmappa már létezik. A meglévő `ddev setup-agency` továbbra is a keretrendszer demo környezetének felépítésére szolgál. A teljes, configból induló DDEV site-létrehozás későbbi fejlesztési lépés; jelenleg indítsd el vagy telepítsd a WordPresst DDEV-ben, majd a public/docroot mappára futtasd a generátort.
