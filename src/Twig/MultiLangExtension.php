<?php

declare(strict_types=1);

namespace RomainGenin\MultiLangBundle\Twig;

use RomainGenin\MultiLangBundle\Locale\Language;
use RomainGenin\MultiLangBundle\Locale\LocaleRegistry;
use RomainGenin\MultiLangBundle\Locale\LocaleUrlGenerator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class MultiLangExtension extends AbstractExtension
{
    public function __construct(
        private readonly LocaleRegistry $registry,
        private readonly LocaleUrlGenerator $urlGenerator,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('multilang_locales', $this->getLocales(...)),
            new TwigFunction('multilang_current', $this->getCurrent(...)),
            new TwigFunction('multilang_dir', $this->getDirection(...)),
            new TwigFunction('multilang_is_rtl', $this->isRtl(...)),
            new TwigFunction('multilang_switch_url', $this->getSwitchUrl(...)),
            new TwigFunction('multilang_html_attributes', $this->getHtmlAttributes(...), ['is_safe' => ['html']]),
            new TwigFunction('multilang_alternate_links', $this->getAlternateLinks(...), ['is_safe' => ['html']]),
        ];
    }

    /**
     * @return array<string, Language>
     */
    public function getLocales(): array
    {
        return $this->registry->all();
    }

    public function getCurrentCode(): string
    {
        $locale = $this->requestStack->getCurrentRequest()?->getLocale();

        return null !== $locale && $this->registry->has($locale) ? $locale : $this->registry->getDefaultLocale();
    }

    public function getCurrent(): Language
    {
        return $this->registry->get($this->getCurrentCode());
    }

    public function getDirection(?string $locale = null): string
    {
        return $this->registry->getDirection($locale ?? $this->getCurrentCode());
    }

    public function isRtl(?string $locale = null): bool
    {
        return $this->registry->isRtl($locale ?? $this->getCurrentCode());
    }

    public function getSwitchUrl(string $locale, bool $absolute = false): string
    {
        return $this->urlGenerator->generate($locale, $absolute ? UrlGeneratorInterface::ABSOLUTE_URL : UrlGeneratorInterface::ABSOLUTE_PATH);
    }

    /**
     * <html {{ multilang_html_attributes() }}> => lang="ar" dir="rtl".
     */
    public function getHtmlAttributes(): string
    {
        $language = $this->getCurrent();

        return sprintf('lang="%s" dir="%s"', self::escape($language->getHtmlLang()), $language->direction);
    }

    /**
     * <link rel="alternate" hreflang="..."> tags for SEO, only on localized routes.
     */
    public function getAlternateLinks(): string
    {
        if (!$this->urlGenerator->isLocalizedRoute()) {
            return '';
        }

        $links = [];
        foreach ($this->registry->all() as $code => $language) {
            $links[] = sprintf('<link rel="alternate" hreflang="%s" href="%s">', self::escape($language->getHtmlLang()), self::escape($this->getSwitchUrl($code, true)));
        }

        $links[] = sprintf('<link rel="alternate" hreflang="x-default" href="%s">', self::escape($this->getSwitchUrl($this->registry->getDefaultLocale(), true)));

        return implode("\n", $links);
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }
}
