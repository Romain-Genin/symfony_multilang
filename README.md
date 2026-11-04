# MultiLangBundle

Bundle Symfony pour les sites multilingues :

- **Traductions prêtes à l'emploi** : 71 clés génériques (navigation, formulaires, auth, footer, cookies, erreurs…) dans **14 langues** : `fr en es de it pt nl ar he fa ru zh ja tr`
- **Composant Twig** `<twig:MultiLang:LocaleSwitcher />` pour changer de langue (dropdown, `<select>` ou liste de liens), en **Tailwind CSS + Alpine.js**, accessible au clavier
- **Gestion LTR / RTL** : `<html lang="ar" dir="rtl">`, détection automatique des langues RTL (ar, he, fa, ur…), composant écrit en utilitaires logiques (`start`/`end`, `ms`/`me`…) qui se met en miroir tout seul
- **URLs préfixées** (`/fr/contact`, `/ar/contact`) + **cookie** qui retient le choix + détection `Accept-Language` à la première visite
- Balises SEO `hreflang` générées automatiquement

Prérequis : PHP 8.2+, Symfony 6.4, 7.x ou 8.x, `symfony/ux-twig-component` 2.13+ ou 3.x (Symfony 8 et UX 3 demandent PHP 8.4).

---

## 1. Installation

```bash
composer require romain-genin/symfony-multilang-bundle
php bin/console multilang:install
```

Avec Symfony Flex, `composer require` active automatiquement le bundle dans `config/bundles.php`. La commande `multilang:install` fait ensuite le reste :

- crée `config/packages/multi_lang.yaml` (vos langues : `--locales=fr,en,ar`) ;
- crée `config/routes/multilang.yaml` (redirection de `/` et route de changement de langue) ;
- ajoute le préfixe `/{_locale}` aux routes de vos contrôleurs dans `config/routes.yaml` ;
- avec `--template`, crée un template de surcharge prêt à personnaliser (voir [§ 6](#6-personnalisation)).

Elle ne touche jamais un fichier existant, sauf avec `--force`.

```bash
php bin/console multilang:install --locales=fr,en,ar --default=fr --template
```

> **Avec le starter** [`romain-genin/symfony-starter`](https://packagist.org/packages/romain-genin/symfony-starter),
> ne lancez pas `multilang:install` : le starter fournit déjà sa configuration (voir son README, « Modules optionnels »).

> **Montée de version 1.x → 2.0** : le namespace PHP devient `RomainGenin\MultiLangBundle`, voir le [CHANGELOG](CHANGELOG.md).

## 2. Configuration

```yaml
# config/packages/multi_lang.yaml
multi_lang:
    default_locale: fr
    locales: [fr, en, es, de, it, pt, nl, ar, he, fa, ru, zh, ja, tr]   # ordre d'affichage
    home_route: app_home            # route localisée utilisée par la redirection de "/"
    detect_browser_language: true   # Accept-Language à la 1re visite
    load_default_translations: true # charge les 71 clés génériques
    # rtl_locales: [ar, he, fa, ur, ...]  # déjà rempli par défaut
    cookie:
        name: multilang_locale
        lifetime: 31536000          # 1 an
        samesite: lax
        secure: ~                   # auto (HTTPS)
    locale_overrides:               # optionnel
        en: { flag: '🇺🇸' }
        pt: { name: 'Português (Portugal)' }
```

Alignez la locale par défaut de Symfony :

```yaml
# config/packages/translation.yaml
framework:
    default_locale: fr
    translator:
        default_path: '%kernel.project_dir%/translations'
        fallbacks: [fr]
```

## 3. Routes

```yaml
# config/routes.yaml
multilang:
    resource: '@MultiLangBundle/config/routes.php'   # "/" et "/_locale/{locale}"

controllers:
    resource:
        path: ../src/Controller/
        namespace: App\Controller
    type: attribute
    prefix: /{_locale}
    requirements:
        _locale: '%multilang.locales_requirement%'    # "fr|en|es|..."
```

```php
#[Route('/', name: 'app_home')]            // => /fr/, /ar/, ...
#[Route('/contact', name: 'app_contact')]  // => /fr/contact, /ar/contact, ...
```

| URL | Comportement |
|---|---|
| `/` | redirige vers `/{langue préférée}/` (cookie > `Accept-Language` > défaut) |
| `/ar/contact` | page en arabe, `dir="rtl"`, cookie mis à jour |
| `/de/...` (langue non activée) | 404 |
| page **sans** `{_locale}` | langue tirée du cookie / du navigateur ; le sélecteur passe par `/_locale/{code}?redirect=…` (protégé contre l'open redirect) |

## 4. Tailwind CSS & Alpine.js

**Tailwind v4** : ajoutez les templates du bundle aux sources, dans `assets/styles/app.css` :

```css
@import "tailwindcss";
@source "../../vendor/romain-genin/symfony-multilang-bundle/templates";

/* optionnel : thème (mode sombre + variables documentées) */
@import "../../vendor/romain-genin/symfony-multilang-bundle/assets/multilang.css";
```

Si vous n'importez pas `multilang.css`, ajoutez au moins `[x-cloak] { display: none !important; }`.

**Tailwind v3** : dans `tailwind.config.js`, ajoutez :

```js
content: [
    './assets/**/*.js',
    './templates/**/*.html.twig',
    './vendor/romain-genin/symfony-multilang-bundle/templates/**/*.html.twig',
],
```

**Alpine.js** (AssetMapper) :

```bash
php bin/console importmap:require alpinejs
```

```js
// assets/app.js
import Alpine from 'alpinejs';
window.Alpine = Alpine;
Alpine.start();
```

Les variantes `dropdown` et `select` ont besoin d'Alpine. La variante `list` fonctionne sans JavaScript.

## 5. Utilisation dans Twig

### Layout

```twig
{# templates/base.html.twig #}
<!DOCTYPE html>
<html {{ multilang_html_attributes() }}>   {# => lang="ar" dir="rtl" #}
<head>
    <meta charset="UTF-8">
    <title>{% block title %}{{ 'nav.home'|trans }}{% endblock %}</title>
    {{ multilang_alternate_links() }}       {# <link rel="alternate" hreflang="..."> #}
    {% block stylesheets %}{% endblock %}
    {% block javascripts %}{% block importmap %}{{ importmap('app') }}{% endblock %}{% endblock %}
</head>
<body class="min-h-screen bg-white text-gray-900">
    <a href="#main" class="sr-only focus:not-sr-only">{{ 'nav.skip_to_content'|trans }}</a>

    <header class="flex items-center justify-between gap-4 border-b px-6 py-4">
        <a href="{{ path('app_home') }}" class="font-bold">Logo</a>
        <nav class="flex gap-6">
            <a href="{{ path('app_home') }}">{{ 'nav.home'|trans }}</a>
            <a href="{{ path('app_contact') }}">{{ 'nav.contact'|trans }}</a>
        </nav>
        <twig:MultiLang:LocaleSwitcher showFlags />
    </header>

    <main id="main">{% block body %}{% endblock %}</main>

    <footer class="flex flex-wrap items-center justify-between gap-4 border-t px-6 py-6 text-sm">
        <p>{{ 'footer.copyright'|trans({'%year%': 'now'|date('Y'), '%company%': 'Mon entreprise'}) }}</p>
        <twig:MultiLang:LocaleSwitcher position="top" align="start" />
        {# ou : <twig:MultiLang:LocaleSwitcher variant="list" display="code" /> #}
    </footer>
</body>
</html>
```

`path('app_contact')` n'a pas besoin du paramètre `_locale` : Symfony reprend la locale courante.

### Props du composant

| Prop | Valeurs | Défaut | |
|---|---|---|---|
| `variant` | `dropdown`, `select`, `list` | `dropdown` | menu Alpine / `<select>` natif / liens en ligne |
| `display` | `native`, `english`, `code`, `native_code` | `native` | `Français` / `French` / `FR` / `Français (FR)` |
| `showFlags` | booléen | `false` | affiche le drapeau emoji |
| `position` | `bottom`, `top` | `bottom` | `top` pour un footer en bas de page |
| `align` | `end`, `start` | `end` | alignement logique : s'inverse en RTL |
| `only` | liste de codes | toutes | `:only="['fr', 'en']"` |
| `class`, `id`, … | | | attributs HTML fusionnés sur l'élément racine |
| `buttonClass`, `menuClass`, `itemClass`, `itemActiveClass`, `selectClass`, `linkClass`, `linkActiveClass` | classes | | **remplacent** les classes par défaut de cet élément (voir § 6) |

```twig
<twig:MultiLang:LocaleSwitcher />
<twig:MultiLang:LocaleSwitcher variant="select" class="w-48" />
<twig:MultiLang:LocaleSwitcher variant="list" display="code" />
<twig:MultiLang:LocaleSwitcher display="native_code" showFlags position="top" />
```

Le dropdown gère `↑`/`↓`, `Home`/`End`, `Échap`, le clic à l'extérieur et la sortie du focus, et expose `aria-expanded` et `aria-current`. Chaque nom de langue est isolé dans un `<bdi lang="…">`, ce qui affiche correctement « العربية » dans une page LTR et « Français » dans une page RTL.


### Fonctions Twig

| Fonction | Résultat |
|---|---|
| `multilang_html_attributes()` | `lang="pt-BR" dir="ltr"` |
| `multilang_dir(locale = courante)` | `ltr` / `rtl` |
| `multilang_is_rtl(locale = courante)` | `true` / `false` |
| `multilang_current()` | `Language` (`code`, `nativeName`, `englishName`, `direction`, `flag`, `htmlLang`) |
| `multilang_locales()` | toutes les `Language` activées |
| `multilang_switch_url(locale, absolute = false)` | URL de la page courante dans `locale` |
| `multilang_alternate_links()` | balises `<link rel="alternate" hreflang>` + `x-default` |

### RTL avec Tailwind

Préférez les utilitaires logiques, qui suivent `dir` automatiquement : `ms-*`/`me-*`, `ps-*`/`pe-*`, `start-*`/`end-*`, `text-start`/`text-end`, `rounded-s-*`, `border-e`… Pour les cas particuliers, utilisez les variantes `rtl:` / `ltr:` :

```twig
<svg class="rtl:-scale-x-100">…flèche « suivant »…</svg>
```

## 6. Personnalisation

Trois niveaux, du plus léger au plus complet. Aucun ne modifie le dossier `vendor/` : vous gardez les mises à jour du bundle.

### Niveau 1 : couleurs et arrondis (variables CSS)

Toutes les couleurs du composant passent par des variables CSS, avec des valeurs par défaut. Il suffit de les redéfinir dans votre CSS :

```css
/* assets/styles/app.css */
:root {
    --multilang-accent: #e11d48;      /* langue active, anneau de focus */
    --multilang-accent-soft: #fff1f2; /* fond de la langue active */
    --multilang-bg: #ffffff;          /* bouton, menu, select */
    --multilang-bg-hover: #f3f4f6;
    --multilang-border: #e5e7eb;
    --multilang-text: #111827;
    --multilang-muted: #6b7280;       /* icônes, liens inactifs */
    --multilang-radius: 9999px;       /* bouton et select en « pilule » */
    --multilang-menu-radius: 0.75rem; /* menu déroulant */
}
.dark { --multilang-bg: #111827; /* ... */ }
```

En important `assets/multilang.css`, vous obtenez en plus un thème sombre, activé par la classe `dark` ou par `data-theme="dark"` sur `<html>`.

### Niveau 2 : classes d'un élément (props)

Pour changer complètement le style d'un élément, passez vos propres classes Tailwind. Elles **remplacent** celles par défaut, donc pas de conflit entre classes :

```twig
<twig:MultiLang:LocaleSwitcher
    buttonClass="inline-flex items-center gap-2 rounded-full bg-black px-4 py-2 text-white"
    itemActiveClass="flex w-full items-center gap-2 px-4 py-2 font-bold text-rose-600"
/>
```

Les classes de positionnement du menu (`absolute`, `top-full`, `end-0`…) sont toujours conservées.

### Niveau 3 : HTML (surcharge de blocs Twig)

```bash
php bin/console multilang:install --template
```

Cette commande crée `templates/bundles/MultiLangBundle/components/LocaleSwitcher.html.twig`, qui **étend** le template du bundle. Vous ne redéfinissez que les blocs voulus, et le reste continue de suivre les mises à jour du bundle :

```twig
{% extends '@!MultiLang/components/LocaleSwitcher.html.twig' %}

{# remplace le globe par le code de la langue #}
{% block multilang_button_icon %}
    <span class="text-xs font-bold uppercase">{{ this.current.code }}</span>
{% endblock %}

{# ajoute le nom anglais sous chaque langue #}
{% block multilang_item %}
    {{ parent() }}
    <span class="text-xs opacity-60">{{ option.language.englishName }}</span>
{% endblock %}
```

| Bloc | Contenu |
|---|---|
| `multilang_dropdown` | tout le dropdown |
| `multilang_button` | contenu du bouton : `multilang_button_icon`, drapeau, libellé, `multilang_button_chevron` |
| `multilang_item` | contenu d'une entrée du menu (`option.language`, `option.url`, `option.current`, `option.label`) |
| `multilang_select` / `multilang_select_option` | variante `select` / texte d'une `<option>` |
| `multilang_list` / `multilang_list_item` | variante `list` / contenu d'un lien |

Pour tout réécrire, ne mettez pas de `{% extends %}` : votre fichier remplace alors entièrement celui du bundle. Vous pouvez aussi écrire votre propre composant avec les [fonctions Twig](#fonctions-twig).

### Traductions et langues

- **Modifier une traduction** : redéfinissez la clé dans `translations/messages.{locale}.yaml` de votre application (prioritaire sur le bundle). Les libellés du sélecteur sont dans le domaine `multilang`.
- **Nom, drapeau ou sens d'une langue** : `multi_lang.locale_overrides` (voir § 2).

## 7. Traductions fournies

Domaine `messages` (dans `resources/translations/`, chargé si `load_default_translations: true`) :

| Groupe | Clés |
|---|---|
| `nav.*` | `home about services products blog contact faq menu open_menu close_menu skip_to_content search` |
| `search.*` | `placeholder no_results results_for` (`%query%`) |
| `action.*` | `submit send cancel save delete edit confirm back next previous close read_more learn_more see_all download subscribe` |
| `auth.*` | `login logout register my_account forgot_password remember_me` |
| `form.*` | `name first_name last_name email phone subject message password required invalid_email success error` |
| `footer.*` | `legal_notice privacy_policy terms cookie_policy sitemap copyright (%year%, %company%) follow_us newsletter_title newsletter_text` |
| `cookies.*` | `message accept reject customize` |
| `error.*` | `404_title 404_text 500_title 500_text back_home` |
| `common.*` | `loading language yes no` |

Domaine `multilang` (libellés du sélecteur) : `switcher.label`, `switcher.current`.

**Surcharger une valeur ou ajouter vos clés** : écrivez-les dans `translations/messages.{locale}.yaml` de votre application. Ces fichiers sont chargés après ceux du bundle et sont donc prioritaires.

```yaml
# translations/messages.fr.yaml
nav:
    about: 'Qui sommes-nous ?'
```

**Ajouter une langue** : ajoutez son code à `multi_lang.locales` (par exemple `pl` ou `pt_BR`) et créez `translations/messages.pl.yaml`. Le nom natif, le drapeau et le sens d'écriture viennent du catalogue intégré, qui couvre une trentaine de langues ; sinon, renseignez-les avec `locale_overrides`.

## 8. Tests

```bash
composer install
vendor/bin/phpunit
```

La suite vérifie notamment que les 14 fichiers de chaque domaine ont exactement les mêmes clés et les mêmes placeholders que la référence française.

## Licence

MIT
