<?php

declare(strict_types=1);

namespace StudioAstra\MultiLangBundle\Locale;

use Symfony\Component\HttpFoundation\Request;

/**
 * Determines the preferred locale of a visitor: cookie > Accept-Language header > default locale.
 */
final class LocaleResolver
{
    public function __construct(
        private readonly LocaleRegistry $registry,
        private readonly string $cookieName,
        private readonly bool $detectBrowserLanguage = true,
    ) {
    }

    public function resolve(Request $request): string
    {
        return $this->fromCookie($request)
            ?? ($this->detectBrowserLanguage ? $this->fromAcceptLanguage($request) : null)
            ?? $this->registry->getDefaultLocale();
    }

    public function fromCookie(Request $request): ?string
    {
        $locale = $request->cookies->get($this->cookieName);

        return \is_string($locale) && $this->registry->has($locale) ? $locale : null;
    }

    public function fromAcceptLanguage(Request $request): ?string
    {
        // getLanguages() returns normalized, quality-sorted codes: ["pt_BR", "pt", "en"]
        foreach ($request->getLanguages() as $language) {
            if ($this->registry->has($language)) {
                return $language;
            }

            $primary = LanguageCatalog::primaryLanguage($language);
            if ($this->registry->has($primary)) {
                return $primary;
            }
        }

        return null;
    }

    public function getCookieName(): string
    {
        return $this->cookieName;
    }
}
