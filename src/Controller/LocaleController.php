<?php

declare(strict_types=1);

namespace StudioAstra\MultiLangBundle\Controller;

use StudioAstra\MultiLangBundle\EventSubscriber\LocaleSubscriber;
use StudioAstra\MultiLangBundle\Locale\LocaleRegistry;
use StudioAstra\MultiLangBundle\Locale\LocaleResolver;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class LocaleController
{
    public function __construct(
        private readonly LocaleRegistry $registry,
        private readonly LocaleResolver $resolver,
        private readonly LocaleSubscriber $subscriber,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $homeRoute,
    ) {
    }

    /**
     * "/" => "/{preferred locale}/" (cookie > Accept-Language > default).
     */
    public function root(Request $request): Response
    {
        $url = $this->urlGenerator->generate($this->homeRoute, ['_locale' => $this->resolver->resolve($request)]);
        if ('' !== ($query = (string) $request->getQueryString())) {
            $url .= (str_contains($url, '?') ? '&' : '?').$query;
        }

        $response = new RedirectResponse($url, Response::HTTP_FOUND);
        $response->setVary(['Accept-Language', 'Cookie']);

        return $response;
    }

    /**
     * Stores the locale in a cookie and redirects back. Used for pages without a {_locale} prefix.
     */
    public function switch(Request $request, string $locale): Response
    {
        if (!$this->registry->has($locale)) {
            throw new NotFoundHttpException(sprintf('Locale "%s" is not enabled.', $locale));
        }

        $redirect = $request->query->get('redirect');
        if (!\is_string($redirect) || !self::isSafeLocalPath($redirect)) {
            $redirect = $this->urlGenerator->generate($this->homeRoute, ['_locale' => $locale]);
        }

        $response = new RedirectResponse($redirect);
        $this->subscriber->setCookie($request, $response, $locale);

        return $response;
    }

    /**
     * Prevents open redirects: only same-site absolute paths ("/foo") are accepted.
     */
    public static function isSafeLocalPath(string $path): bool
    {
        return str_starts_with($path, '/')
            && !str_starts_with($path, '//')
            && !str_contains($path, '\\')
            && 1 !== preg_match('/[\x00-\x1F\x7F]/', $path);
    }
}
