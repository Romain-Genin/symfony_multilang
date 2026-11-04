<?php

declare(strict_types=1);

namespace RomainGenin\MultiLangBundle\Twig\Components;

use RomainGenin\MultiLangBundle\Locale\Language;
use RomainGenin\MultiLangBundle\Locale\LocaleRegistry;
use RomainGenin\MultiLangBundle\Locale\LocaleUrlGenerator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PostMount;

/**
 * Language switcher (Tailwind CSS + Alpine.js).
 *
 *     <twig:MultiLang:LocaleSwitcher />
 *     <twig:MultiLang:LocaleSwitcher variant="select" />
 *     <twig:MultiLang:LocaleSwitcher variant="list" display="code" />
 *     <twig:MultiLang:LocaleSwitcher position="top" align="start" showFlags />
 */
#[AsTwigComponent(name: 'MultiLang:LocaleSwitcher', template: '@MultiLang/components/LocaleSwitcher.html.twig')]
final class LocaleSwitcher
{
    public const VARIANTS = ['dropdown', 'select', 'list'];
    public const DISPLAYS = ['native', 'english', 'code', 'native_code'];

    /** dropdown (Alpine menu) | select (native <select>) | list (inline links) */
    public string $variant = 'dropdown';

    /** native ("Français") | english ("French") | code ("FR") | native_code ("Français (FR)") */
    public string $display = 'native';

    public bool $showFlags = false;

    /** Dropdown opening direction: bottom (header) | top (footer) */
    public string $position = 'bottom';

    /** Dropdown horizontal alignment, logical (RTL aware): end (default, switcher at the end of a header) | start */
    public string $align = 'end';

    /** Only show these locales (defaults to all enabled locales) */
    public ?array $only = null;

    /*
     * Class overrides: when set, they REPLACE the default classes of the element.
     * Colors can also be changed globally with the --multilang-* CSS variables.
     */
    public ?string $buttonClass = null;     // dropdown button
    public ?string $menuClass = null;       // dropdown panel (positioning classes are always kept)
    public ?string $itemClass = null;       // dropdown item
    public ?string $itemActiveClass = null; // dropdown current item
    public ?string $selectClass = null;     // <select>
    public ?string $linkClass = null;       // list link
    public ?string $linkActiveClass = null; // list current link

    public function __construct(
        private readonly LocaleRegistry $registry,
        private readonly LocaleUrlGenerator $urlGenerator,
        private readonly RequestStack $requestStack,
    ) {
    }

    #[PostMount]
    public function validate(): void
    {
        foreach (['variant' => self::VARIANTS, 'display' => self::DISPLAYS, 'position' => ['bottom', 'top'], 'align' => ['start', 'end']] as $prop => $allowed) {
            if (!\in_array($this->{$prop}, $allowed, true)) {
                throw new \InvalidArgumentException(sprintf('Invalid value "%s" for prop "%s" of MultiLang:LocaleSwitcher. Allowed: %s.', $this->{$prop}, $prop, implode(', ', $allowed)));
            }
        }
    }

    public function getCurrent(): Language
    {
        $locale = $this->requestStack->getCurrentRequest()?->getLocale();

        return null !== $locale && $this->registry->has($locale) ? $this->registry->get($locale) : $this->registry->getDefault();
    }

    /**
     * @return list<array{language: Language, url: string, current: bool, label: string}>
     */
    public function getOptions(): array
    {
        $current = $this->getCurrent()->code;
        $options = [];

        foreach ($this->registry->all() as $code => $language) {
            if (null !== $this->only && !\in_array($code, $this->only, true) && $code !== $current) {
                continue;
            }

            $options[] = [
                'language' => $language,
                'url' => $this->urlGenerator->generate($code),
                'current' => $code === $current,
                'label' => $this->label($language),
            ];
        }

        return $options;
    }

    public function label(Language $language): string
    {
        $code = strtoupper(str_replace('_', '-', $language->code));

        return match ($this->display) {
            'english' => $language->englishName,
            'code' => $code,
            'native_code' => sprintf('%s (%s)', $language->nativeName, $code),
            default => $language->nativeName,
        };
    }
}
