<?php

declare(strict_types=1);

namespace RomainGenin\MultiLangBundle\Locale;

/**
 * Holds the locales enabled on the site, with their metadata (name, direction, flag).
 */
final class LocaleRegistry
{
    /** @var array<string, Language> */
    private array $languages = [];

    /**
     * @param list<string>                                                            $locales
     * @param list<string>                                                            $rtlLocales
     * @param array<string, array{name?: ?string, english_name?: ?string, flag?: ?string, direction?: ?string}> $overrides
     */
    public function __construct(
        array $locales,
        private readonly string $defaultLocale,
        array $rtlLocales = LanguageCatalog::RTL_LANGUAGES,
        array $overrides = [],
    ) {
        if ([] === $locales) {
            throw new \InvalidArgumentException('At least one locale must be enabled.');
        }

        if (!\in_array($defaultLocale, $locales, true)) {
            throw new \InvalidArgumentException(sprintf('The default locale "%s" must be part of the enabled locales (%s).', $defaultLocale, implode(', ', $locales)));
        }

        foreach ($locales as $code) {
            $known = LanguageCatalog::get($code);
            $override = $overrides[$code] ?? [];

            $isRtl = \in_array($code, $rtlLocales, true)
                || \in_array(LanguageCatalog::primaryLanguage($code), $rtlLocales, true);

            $direction = $override['direction'] ?? ($isRtl ? Language::RTL : Language::LTR);
            if (!\in_array($direction, [Language::LTR, Language::RTL], true)) {
                throw new \InvalidArgumentException(sprintf('Invalid direction "%s" for locale "%s" (expected "ltr" or "rtl").', $direction, $code));
            }

            $this->languages[$code] = new Language(
                code: $code,
                nativeName: $override['name'] ?? $known['native'] ?? $code,
                englishName: $override['english_name'] ?? $known['english'] ?? $code,
                direction: $direction,
                flag: \array_key_exists('flag', $override) ? $override['flag'] : ($known['flag'] ?? null),
            );
        }
    }

    /**
     * @return array<string, Language>
     */
    public function all(): array
    {
        return $this->languages;
    }

    /**
     * @return list<string>
     */
    public function getCodes(): array
    {
        return array_keys($this->languages);
    }

    public function has(string $code): bool
    {
        return isset($this->languages[$code]);
    }

    public function get(string $code): Language
    {
        return $this->languages[$code] ?? throw new \InvalidArgumentException(sprintf('Locale "%s" is not enabled. Enabled locales: %s.', $code, implode(', ', $this->getCodes())));
    }

    public function getDefaultLocale(): string
    {
        return $this->defaultLocale;
    }

    public function getDefault(): Language
    {
        return $this->languages[$this->defaultLocale];
    }

    public function isRtl(string $code): bool
    {
        return $this->has($code) && $this->languages[$code]->isRtl();
    }

    public function getDirection(string $code): string
    {
        return $this->isRtl($code) ? Language::RTL : Language::LTR;
    }

    /**
     * Regex usable as route requirement for {_locale}, e.g. "fr|en|ar".
     */
    public function getRouteRequirement(): string
    {
        return implode('|', array_map('preg_quote', $this->getCodes()));
    }
}
