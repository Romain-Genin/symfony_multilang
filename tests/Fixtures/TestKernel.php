<?php

declare(strict_types=1);

namespace RomainGenin\MultiLangBundle\Tests\Fixtures;

use RomainGenin\MultiLangBundle\MultiLangBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\UX\TwigComponent\TwigComponentBundle;

final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new TwigComponentBundle();
        yield new MultiLangBundle();
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/multilang-bundle-tests/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/multilang-bundle-tests/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'default_locale' => 'fr',
            'translator' => ['default_path' => __DIR__.'/translations', 'fallbacks' => ['en']],
        ]);
        $container->extension('twig', ['default_path' => __DIR__.'/templates']);
        $container->extension('twig_component', ['defaults' => [], 'anonymous_template_directory' => 'components/']);
        $container->extension('multi_lang', [
            'default_locale' => 'fr',
            'locales' => ['fr', 'en', 'ar', 'he', 'ja'],
        ]);

        $container->services()->set(PageController::class)->autowire()->tag('controller.service_arguments');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@MultiLangBundle/config/routes.php');

        $routes->add('app_home', '/{_locale}/')
            ->controller(PageController::class)
            ->requirements(['_locale' => '%multilang.locales_requirement%']);
        $routes->add('app_contact', '/{_locale}/contact')
            ->controller(PageController::class)
            ->requirements(['_locale' => '%multilang.locales_requirement%']);
        $routes->add('app_legacy', '/legacy')
            ->controller(PageController::class);
    }
}
