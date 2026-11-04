<?php

declare(strict_types=1);

use RomainGenin\MultiLangBundle\Controller\LocaleController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * Import in your app (config/routes/multilang.yaml):
 *
 *     multilang:
 *         resource: '@MultiLangBundle/config/routes.php'
 */
return static function (RoutingConfigurator $routes): void {
    // "/" => redirects to the visitor's preferred locale ("/fr/", "/ar/"...)
    $routes->add('multilang_root', '/')
        ->controller([LocaleController::class, 'root'])
        ->methods(['GET', 'HEAD']);

    // Fallback switch for pages that are not prefixed by {_locale}
    $routes->add('multilang_switch', '/_locale/{locale}')
        ->controller([LocaleController::class, 'switch'])
        ->requirements(['locale' => '[a-zA-Z]{2,3}([_-][a-zA-Z0-9]{2,8})?'])
        ->methods(['GET', 'HEAD']);
};
