<?php

declare(strict_types=1);

namespace RomainGenin\MultiLangBundle\Locale;

/**
 * Immutable description of an enabled locale.
 */
final class Language
{
    public const LTR = 'ltr';
    public const RTL = 'rtl';

    public function __construct(
        public readonly string $code,
        public readonly string $nativeName,
        public readonly string $englishName,
        public readonly string $direction,
        public readonly ?string $flag = null,
    ) {
    }

    public function isRtl(): bool
    {
        return self::RTL === $this->direction;
    }

    /**
     * BCP 47 tag, suitable for the HTML lang / hreflang attributes ("pt_BR" => "pt-BR").
     */
    public function getHtmlLang(): string
    {
        return str_replace('_', '-', $this->code);
    }
}
