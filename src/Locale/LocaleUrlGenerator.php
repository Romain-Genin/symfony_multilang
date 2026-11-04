<?php

declare(strict_types=1);

namespace RomainGenin\MultiLangBundle\Locale;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Builds the URL of the current page in another locale.
 *
 * - If the current route has a {_locale} parameter, the same route is generated with the new locale
 *   (query string preserved), e.g. /fr/contact?x=1 => /ar/contact?x=1.
 * - Otherwise (route without prefix, error page...), it falls back to the "multilang_switch" route,
 *   which stores the choice in a cookie and redirects back to the current page.
 */
final class LocaleUrlGenerator
{
    public const SWITCH_ROUTE = 'multilang_switch';

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack,
        private readonly LocaleRegistry $registry,
    ) {
    }

    public function generate(string $locale, int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH, ?Request $request = null): string
    {
        $this->registry->get($locale); // validates the locale

        $request ??= $this->requestStack->getMainRequest();

        if (null !== $request && $this->isLocalizedRoute($request)) {
            try {
                $url = $this->urlGenerator->generate(
                    $request->attributes->get('_route'),
                    array_merge($request->query->all(), $request->attributes->get('_route_params', []), ['_locale' => $locale]),
                    $referenceType,
                );

                // "_locale" ended up in the query string: the route path has no {_locale} placeholder
                if (!str_contains($url, '_locale=')) {
                    return $url;
                }
            } catch (RoutingException) {
                // fall back to the switch route below
            }
        }

        $parameters = ['locale' => $locale];
        if (null !== $request) {
            $parameters['redirect'] = $request->getRequestUri();
        }

        return $this->urlGenerator->generate(self::SWITCH_ROUTE, $parameters, $referenceType);
    }

    /**
     * Returns true if the current page can be generated in every locale (it has a {_locale} route parameter).
     */
    public function isLocalizedRoute(?Request $request = null): bool
    {
        $request ??= $this->requestStack->getMainRequest();
        if (null === $request) {
            return false;
        }

        $route = $request->attributes->get('_route');
        $params = $request->attributes->get('_route_params', []);

        return \is_string($route) && \is_array($params) && \array_key_exists('_locale', $params);
    }
}
