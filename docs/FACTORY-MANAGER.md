# Agency Factory Manager

## Modul setup (1.8)

A projekt dashboard Auth, Booking és Legal kártyái megmutatják a dependencies/pages/shortcodes/display/email/admin/database health állapotot. Az **Apply module setup** biztonságosan létrehozza a hiányzó oldalakat és függőségeket. A **Repair module pages** kizárólag hiányzó kötelező shortcode-ot ad hozzá; a **Repair CTA/menu/footer** a modul látható site-kapcsolatait javítja.

Booking kijelölésekor a Manager Auth és Legal modult is kijelöli a szerveroldali validáció során. Auth vagy Legal nem kapcsolható ki aktív Booking mellett. A setup gombokhoz a LocalWP site-nak és adatbázisnak futnia kell.

Az Agency Factory Manager az Agency Site Factory 1.8 localhoston futó központi kezelőfelülete. Új ügyfélprojekt konfigurációja, framework telepítése, dry-runja, frissítése, modulkezelése és setup health ellenőrzése böngészőből végezhető. Nem hoz létre LocalWP site-ot vagy adatbázist: a cél WordPress site-nak előre léteznie kell.

## Hivatalos forrásgyökér

A Factory Manager mindig a repo gyökerét tekinti framework source rootnak:

```text
agency-site-factory/
```

Innen oldja fel a forráscsomagokat:

```text
agency-site-factory/wp-content/plugins/agency-core/
agency-site-factory/wp-content/themes/agency-theme/
agency-site-factory/wp-content/plugins/agency-module-*/
agency-site-factory/site-factory/projects/
agency-site-factory/tools/lib/site-factory.php
```

A gyökér nem hardcode-olt gépspecifikus útvonal: a `tools/lib/site-factory.php` saját helyzetéből számolja ki. Emiatt a repo átmozgatható vagy GitHubról másik gépre lehúzható anélkül, hogy régi `outputs/...` vagy `C:\Users\...` workspace útvonalakat vissza kellene állítani.

## Indítás

Windows alatt kattints duplán a csomag gyökerében található:

```text
start-factory-manager.bat
```

A launcher megkeresi a PATH-ban vagy a LocalWP services mappájában a PHP-t, elindítja a managert kizárólag a `127.0.0.1:8765` címen, és megnyitja:

```text
http://127.0.0.1:8765
```

A megnyitott konzolablakot hagyd futni a manager használata közben. Bezárásával a manager leáll. Másik gépről a felület nem érhető el.

Alternatív fejlesztői indítás:

```powershell
php -S 127.0.0.1:8765 -t tools/factory-manager
```

## Új projekt létrehozása

1. LocalWP-ben vagy DDEV-ben hozd létre és indítsd el az üres WordPress site-ot.
2. A managerben válaszd az **Új projekt** gombot.
3. Töltsd ki a projektnevet, slugot, WordPress public mappát, domaint és admin e-mailt.
4. Add meg a márkanevet, színeket, kontaktadatokat, booking URL-t és social URL-eket.
5. Válaszd ki a Beauty vagy Consulting blueprintet.
6. Jelöld be a kívánt Booking, Auth, Webshop, Newsletter, Analytics és Legal modulokat.
7. Kattints a **Projekt mentése és validálása** gombra.

A manager elkészíti a `site-factory/projects/{slug}/config.json` fájlt és külön `manager.json` fájlban tárolja a target útvonalat. Nem kell JSON-t kézzel szerkeszteni. A target csak akkor menthető, ha valós WordPress public mappa, benne `wp-admin`, `wp-includes`, `wp-content` és `wp-config.php`.

## Dry-run és install

A projekt dashboardon:

- **Dry-run indítása**: validál és részletesen kiírja a tervezett fájlműveleteket, de semmit nem módosít.
- **Install / update indítása**: időbélyeges backupot készít a meglévő Agency theme, core és kiválasztott modulmappákról, majd telepíti az új verziót és frissíti az `agency-project.json` manifestet.
- **Check updates**: összeveti a manifest telepített framework-verzióját a factory forrásverziójával.

Az install/update csak akkor indulhat el, ha előtte ugyanahhoz az aktuális forrásverzióhoz sikeres dry-run futott. Sikertelen frissítésnél a log felsorolja a rollbackhez használható backup mappákat.

LocalWP esetén a manager automatikusan keresi a Local `wp-cli.phar` fájlt és a target site saját `php.ini` konfigurációját. Ha mindkettő elérhető és a Local site fut, automatikusan aktiválja az Agency Core plugint és Agency Theme-et, beállítja a permalinket, importálja a blueprintet, alkalmazza a brand configot és aktiválja a modulokat.

Bekapcsolt WP-CLI automatizálásnál a manager minden író művelet előtt read-only WordPress/adatbázis preflightot futtat. Ha a Local site vagy MySQL nem elérhető, a folyamat még backup és fájlmásolás előtt leáll, így nem keletkezik félbemaradt telepítés.

Az install gomb mellett a **WP-CLI automatizálás** checkbox kikapcsolható. Ezt használd, ha a Local site éppen nem fut, vagy csak backupolt fájlfrissítést szeretnél. Ha WP-CLI nem érhető el vagy ki van kapcsolva, a fájltelepítés akkor is elkészül, a műveleti napló pedig pontos WordPress admin lépéseket ad.

## Modulok kezelése

A projekt dashboard **Modulkezelés** táblája külön jelzi:

- a configban kívánt modult;
- a targetben telepített pluginmappát;
- a manifest szerint aktív modult;
- az elérhető production vagy skeleton csomagot.

A checkboxok módosítása után a **Modulok telepítése / frissítése** gomb a meglévő biztonságos module updater scriptet futtatja. A külön WP-CLI checkbox bekapcsolásával aktiválja/deaktiválja is a modulokat; nélküle bemásolja a csomagokat, majd az ügyfélsite **Agency Kit → Modules** oldalán kapcsolhatók.

## Napló és manifest

Minden projekt utolsó húsz művelete megjelenik a dashboardon, és a projekt `operations.json` fájljában tárolódik. A napló tartalmazza:

- tervezett vagy végrehajtott fájlműveleteket;
- backup útvonalakat;
- telepített modulokat;
- WP-CLI lépéseket;
- exit code-ot és hibát;
- szükséges manuális lépéseket.

Jelszó-, token-, secret- és kulcsjellegű parancsparamétereket a logger maszkol. A dashboard beolvassa a target `wp-content/agency-project.json` manifestjét, és mutatja a projekt slugot, framework verziót, blueprintet, aktív modulokat és utolsó frissítést.

## Biztonsági modell

- a webalkalmazás csak loopback címen fogad kérést;
- minden író művelet session-alapú CSRF tokent ellenőriz;
- projektek csak szigorúan validált slug alatt érhetők el;
- config és target validálás minden művelet előtt megismétlődik;
- parancsok shell nélkül, argumentumtömbből indulnak;
- framework-felülírás előtt backup készül;
- a manager nem töröl bejegyzést, oldalt, médiát, adatbázist vagy más ügyféltartalmat;
- az ügyfélsite Agency Core adminműveletei továbbra is WordPress capability- és nonce-ellenőrzést használnak.

## Mi marad manuális?

- maga a LocalWP/DDEV site és adatbázis létrehozása;
- LocalWP site elindítása az automatikus WP-CLI lépések előtt;
- szükség esetén WordPress admin belépés;
- WP-CLI nélküli környezetben a telepített plugin/theme aktiválása és blueprint importja.

A CLI scriptek továbbra is elérhetők automatizálási és hibakeresési fallbackként.
