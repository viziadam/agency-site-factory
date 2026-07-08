# Agency Core 1.8

Az 1.8-as Core központi module setup/health orchestrationt, titkosított Email Service beállítást, wp_mail/Brevo providert, activity logot és Factory Manager-kompatibilis manifestet tartalmaz.

Az Agency Site Factory hordozható üzleti és tartalmi rétege. A plugin nem tartalmaz frontend layout CSS-t.

## Adatmodell

CPT-k: `agency_service`, `agency_faq`, `agency_testimonial`, `agency_portfolio`, `agency_price_item`.

Taxonómiák: `agency_service_category`, `agency_location`, `agency_industry`.

Az oldalak moduláris definíciója az `_agency_sections` post metában, JSON formában él.

## Settings

Az **Agency Kit → Settings** oldalon az `agency_core_settings` option tárolja a márka-, szín-, kontakt-, social- és alap SEO-adatokat. Lekérés:

```php
agency_core_get_setting( 'brand_name', 'Agency Starter' );
```

## Vizuális oldal-összeállító

Az oldalak Gutenberg dokumentumbeállításai között az **Agency sections** panel a theme központi component registryjéből épül fel. Component és variant választható, a sectionök átnevezhetők az adataikkal, mozgathatók és törölhetők. A mentett meta REST-en keresztül frissül, szerveroldalon újra validálódik, és minden section komponensverziót kap.

A középső Gutenberg vászon iframe live preview-t mutat. A panel 400 ms debounce után a `/agency/v1/preview-sections` REST endpointot hívja; az endpoint ugyanazt a PHP theme render pipeline-t és frontend asseteket használja, mint a publikus oldal. A preview nem ment automatikusan.

Az új adatmodell `component`, `variant`, `data`. A plugin a régi `hero/hero-split` formátumot is normalizálja.

Az endpoint cookie-authenticated WordPress REST nonce-ot, `edit_post` jogosultságot és registry-validációt követel. Ismeretlen component/variant nem renderelhet fájlt.

Debug: a REST válasz `code` és `message` mezője, a böngésző `Agency live preview:` konzolbejegyzése és a WordPress debug log adja a diagnosztikai láncot.

## Importer

Az **Agency Kit → Import Blueprint** oldal a `blueprints/*/manifest.json` fájlokból automatikusan katalógust készít. Az importer slug alapján frissít vagy létrehoz, ezért újrafuttatható. Helyi képfájlt is át tud emelni a WordPress Média könyvtárba.

## Schema

A `schema.php` LocalBusiness/Organization, Article, Service/Offer és FAQPage JSON-LD-t ad. Ismert SEO plugin mellett Auto módban nem ad második schema graphot.

## WP-CLI

```bash
wp agency blueprint list
wp agency blueprint import beauty
```

## Shortcode-ok

- `[agency_services_grid limit="6"]`
- `[agency_faq limit="8"]`
- `[agency_testimonials limit="3"]`
- `[agency_cta title="..." text="..." button_label="..." button_url="..."]`

Az Agency Theme aktív állapotában a shortcode-ok annak template partjait használják; más theme esetén biztonságos HTML fallbacket adnak.

## Új CPT

Adj új konfigurációs sort az `includes/post-types.php` tömbjéhez, majd szükség szerint rendelj hozzá taxonómiát az `includes/taxonomies.php` fájlban. Ezután egyszer mentsd újra a permalinkeket.
