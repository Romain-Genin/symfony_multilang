# Changelog

Le projet respecte le [versionnage sémantique](https://semver.org/lang/fr/).

## [2.0.0] - 2026-11-04

### Modifié (rupture)

- Namespace PHP renommé `StudioAstra\MultiLangBundle` → `RomainGenin\MultiLangBundle` : mettez à jour `config/bundles.php`
  et vos `use`.
- Toutes les références au paquet utilisent son nom Composer `romain-genin/symfony-multilang-bundle` (chemins `vendor/…` compris).

### Ajouté

- Compatibilité Symfony 8 et Symfony UX 3 (`symfony/ux-twig-component` ^3.0).
- CI GitHub Actions : PHP 8.2 → 8.5, Symfony 6.4 → 8.x.


## [1.0.0] - 2026-11-04

- Première version.
