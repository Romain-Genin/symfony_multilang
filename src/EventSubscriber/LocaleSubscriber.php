<?php

declare(strict_types=1);

namespace RomainGenin\MultiLangBundle\EventSubscriber;

use RomainGenin\MultiLangBundle\Locale\LocaleRegistry;
use RomainGenin\MultiLangBundle\Locale\LocaleResolver;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * - Routes WITH {_locale}: Symfony sets the locale; this subscriber remembers it in a cookie.
 * - Routes WITHOUT {_locale}: the locale is taken from the cookie, then the Accept-Language header.
 * - Adds a Content-Language header to every main response.
 */
final class LocaleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LocaleRegistry $registry,
        private readonly LocaleResolver $resolver,
        private readonly int $cookieLifetime = 31536000,
        private readonly string $cookieSameSite = Cookie::SAMESITE_LAX,
        private readonly ?bool $cookieSecure = null,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // after the RouterListener (32), before Symfony's LocaleListener (16) and LocaleAwareListener (15)
            KernelEvents::REQUEST => [['onKernelRequest', 20]],
            KernelEvents::RESPONSE => [['onKernelResponse', 0]],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        $routeLocale = $request->attributes->get('_locale');
        if (\is_string($routeLocale) && $this->registry->has($routeLocale)) {
            return;
        }

        $request->setLocale($this->resolver->resolve($request));
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        $locale = $request->getLocale();
        if (!$this->registry->has($locale)) {
            return;
        }

        if (!$response->headers->has('Content-Language')) {
            $response->headers->set('Content-Language', $this->registry->get($locale)->getHtmlLang());
        }

        // Only remember an explicit choice: a locale present in the URL.
        $routeLocale = $request->attributes->get('_locale');
        if (\is_string($routeLocale) && $this->registry->has($routeLocale) && $this->resolver->fromCookie($request) !== $routeLocale) {
            $this->setCookie($request, $response, $routeLocale);
        }
    }

    public function setCookie(Request $request, Response $response, string $locale): void
    {
        $response->headers->setCookie(Cookie::create(
            name: $this->resolver->getCookieName(),
            value: $locale,
            expire: time() + $this->cookieLifetime,
            path: '/',
            secure: $this->cookieSecure ?? $request->isSecure(),
            httpOnly: true,
            sameSite: $this->cookieSameSite,
        ));
    }
}
