# Agency Theme 1.3

Klasszikus, PHP-alapú WordPress theme az Agency Site Factory vizuális rétegéhez. Nincs buildlépés, külső CDN vagy Elementor-függés.

## Template hierarchy

- `front-page.php`: `_agency_sections` meta alapján komponenseket renderel, meta nélkül demo elrendezést ad.
- `page.php`: moduláris sectionök, vagy hagyományos `the_content()`.
- `single.php`: cikk, kiemelt kép és kapcsolódó bejegyzések.
- `archive.php`: reszponzív kártyarács.
- `404.php`: egyszerű visszatérési CTA.
- `header.php` és `footer.php`: a `template-parts/layout/` shell komponenseit tölti.

## Komponens renderelése

Minden komponens a `$args['data']` tömbből olvas. Példa:

```php
get_template_part(
    'template-parts/hero/hero-centered',
    null,
    array( 'data' => array( 'title' => 'Oldalcím' ) )
);
```

Oldalszinten az `_agency_sections` meta verziózott `component + variant` JSON-t tárol. Az Agency Core Gutenberg dokumentumpanelje ezt vizuálisan szerkeszti. Az `agency_theme_render_sections()` kizárólag az `agency_theme_get_component_registry()` engedélylistájában szereplő template partokat tölti be.

A Gutenberg iframe preview ugyanazt az `agency_theme_render_sections( $sections, 'editor_preview' )` függvényt, template partokat és a `agency_theme_get_frontend_styles()` / `agency_theme_get_frontend_scripts()` assetlistát használja. A `context` csak admin wrapperhez használható; a komponensek alapvető HTML-je nem térhet el a frontendtől.

Legacy példa: `hero/hero-split`. Új megfelelője: `component: hero`, `variant: split`. Mindkettő renderelhető.

Új komponenshez:

1. hozd létre a fájlt a `template-parts/{component}/{component}-{variant}.php` útvonalon;
2. kizárólag `$args['data']` adatot használj és escape-eld a kimenetet;
3. add a componentet és variantot az `inc/components.php` registryhez;
4. add a stílusát a `assets/css/components.css` fájlhoz.

Új variantnál nem kell editor JavaScriptet módosítani: a registry mezősémája és variant template útvonala automatikusan megjelenik a panelben és a PHP preview-ban.

## Design tokenek

Az alapértékek az `assets/css/tokens.css` fájlban vannak. Az aktív Agency Core plugin három színt a Settings értékeiből felülír. A layout, komponens és utility CSS ezeket a változókat használja. A theme látható fókuszállapotot és csökkentett mozgás beállítást is biztosít.
