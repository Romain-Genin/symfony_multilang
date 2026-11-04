<?php

declare(strict_types=1);

namespace StudioAstra\MultiLangBundle;

use StudioAstra\MultiLangBundle\Controller\LocaleController;
use StudioAstra\MultiLangBundle\EventSubscriber\LocaleSubscriber;
use StudioAstra\MultiLangBundle\Locale\LanguageCatalog;
use StudioAstra\MultiLangBundle\Locale\LocaleRegistry;
use StudioAstra\MultiLangBundle\Locale\LocaleResolver;
use StudioAstra\MultiLangBundle\Locale\LocaleUrlGenerator;
use StudioAstra\MultiLangBundle\Twig\Components\LocaleSwitcher;
use StudioAstra\MultiLangBundle\Twig\MultiLangExtension;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

final class MultiLangBundle extends AbstractBundle
{
    public const DEFAULT_LOCALES = ['fr', 'en', 'es', 'de', 'it', 'pt', 'nl', 'ar', 'he', 'fa', 'ru', 'zh', 'ja', 'tr'];

    protected string $extensionAlias = 'multi_lang';

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('default_locale')
                    ->info('Locale used when nothing else matches. Must be part of "locales".')
                    ->defaultValue('fr')
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('locales')
                    ->info('Enabled locales, in the order they are displayed in the switcher.')
                    ->scalarPrototype()->cannotBeEmpty()->end()
                    ->defaultValue(self::DEFAULT_LOCALES)
                    ->requiresAtLeastOneElement()
                ->end()
                ->arrayNode('rtl_locales')
                    ->info('Locales written right-to-left (matched on the exact code or the primary language).')
                    ->scalarPrototype()->end()
                    ->defaultValue(LanguageCatalog::RTL_LANGUAGES)
                ->end()
                ->booleanNode('detect_browser_language')
                    ->info('Use the Accept-Language header when the visitor has no cookie yet.')
                    ->defaultTrue()
                ->end()
                ->booleanNode('load_default_translations')
                    ->info('Load the generic "messages" translations shipped with the bundle (your app translations always win).')
                    ->defaultTrue()
                ->end()
                ->scalarNode('home_route')
                    ->info('Localized route ({_locale} parameter) used by the "/" redirect and as fallback after a switch.')
                    ->defaultValue('app_home')
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('cookie')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('name')->defaultValue('multilang_locale')->cannotBeEmpty()->end()
                        ->integerNode('lifetime')->info('In seconds (default: 1 year).')->defaultValue(31536000)->min(0)->end()
                        ->enumNode('samesite')->values([Cookie::SAMESITE_LAX, Cookie::SAMESITE_STRICT, Cookie::SAMESITE_NONE])->defaultValue(Cookie::SAMESITE_LAX)->end()
                        ->booleanNode('secure')->info('null = automatic (secure on HTTPS requests).')->defaultNull()->end()
                    ->end()
                ->end()
                ->arrayNode('locale_overrides')
                    ->info('Override the built-in metadata of a locale, e.g. {pt_BR: {name: "Português", flag: "🇧🇷"}}.')
                    ->useAttributeAsKey('code')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('name')->info('Native name shown in the switcher.')->end()
                            ->scalarNode('english_name')->end()
                            ->scalarNode('flag')->info('Emoji or any short text; null to hide.')->end()
                            ->enumNode('direction')->values(['ltr', 'rtl'])->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->validate()
                ->ifTrue(static fn (array $c): bool => !\in_array($c['default_locale'], $c['locales'], true))
                ->then(static function (array $c): never {
                    throw new \InvalidArgumentException(sprintf('multi_lang.default_locale "%s" must be one of multi_lang.locales (%s).', $c['default_locale'], implode(', ', $c['locales'])));
                })
            ->end();
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // Read the user config to prepend the generic translations path only when wanted.
        $configs = $builder->getExtensionConfig($this->extensionAlias);
        $load = true;
        foreach ($configs as $config) {
            if (\array_key_exists('load_default_translations', $config)) {
                $load = (bool) $config['load_default_translations'];
            }
        }

        if ($load) {
            // Registered as an extra translator path: the app's own translations/ directory is loaded after it and wins.
            $builder->prependExtensionConfig('framework', [
                'translator' => ['paths' => [$this->getPath().'/resources/translations']],
            ]);
        }
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $locales = array_values(array_unique($config['locales']));

        $container->parameters()
            ->set('multilang.default_locale', $config['default_locale'])
            ->set('multilang.locales', $locales)
            ->set('multilang.rtl_locales', $config['rtl_locales'])
            // usable as route requirement: requirements: { _locale: '%multilang.locales_requirement%' }
            ->set('multilang.locales_requirement', implode('|', array_map('preg_quote', $locales)));

        $services = $container->services();

        $services->set(LocaleRegistry::class)
            ->args([
                param('multilang.locales'),
                param('multilang.default_locale'),
                param('multilang.rtl_locales'),
                $config['locale_overrides'],
            ]);

        $services->set(LocaleResolver::class)
            ->args([
                service(LocaleRegistry::class),
                $config['cookie']['name'],
                $config['detect_browser_language'],
            ]);

        $services->set(LocaleUrlGenerator::class)
            ->args([
                service('router'),
                service('request_stack'),
                service(LocaleRegistry::class),
            ]);

        $services->set(LocaleSubscriber::class)
            ->args([
                service(LocaleRegistry::class),
                service(LocaleResolver::class),
                $config['cookie']['lifetime'],
                $config['cookie']['samesite'],
                $config['cookie']['secure'],
            ])
            ->tag('kernel.event_subscriber');

        $services->set(LocaleController::class)
            ->args([
                service(LocaleRegistry::class),
                service(LocaleResolver::class),
                service(LocaleSubscriber::class),
                service('router'),
                $config['home_route'],
            ])
            ->public()
            ->tag('controller.service_arguments');

        $services->set(MultiLangExtension::class)
            ->args([
                service(LocaleRegistry::class),
                service(LocaleUrlGenerator::class),
                service('request_stack'),
            ])
            ->tag('twig.extension');

        $services->set(LocaleSwitcher::class)
            ->args([
                service(LocaleRegistry::class),
                service(LocaleUrlGenerator::class),
                service('request_stack'),
            ])
            ->autoconfigure() // registers the #[AsTwigComponent] attribute
            ->share(false);
    }
}
