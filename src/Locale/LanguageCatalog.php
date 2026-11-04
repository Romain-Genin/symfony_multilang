<?php

declare(strict_types=1);

namespace StudioAstra\MultiLangBundle\Locale;

/**
 * Built-in metadata for common languages (native name, English name, writing direction, default flag).
 *
 * Any value can be overridden through the bundle configuration (multi_lang.locale_overrides).
 */
final class LanguageCatalog
{
    /**
     * Languages written right-to-left. Used as default for multi_lang.rtl_locales.
     */
    public const RTL_LANGUAGES = ['ar', 'arc', 'ckb', 'dv', 'fa', 'he', 'ks', 'ku', 'ps', 'sd', 'ug', 'ur', 'yi'];

    /**
     * @var array<string, array{native: string, english: string, flag: string}>
     */
    private const LANGUAGES = [
        'ar' => ['native' => 'العربية', 'english' => 'Arabic', 'flag' => '🇸🇦'],
        'bg' => ['native' => 'Български', 'english' => 'Bulgarian', 'flag' => '🇧🇬'],
        'ca' => ['native' => 'Català', 'english' => 'Catalan', 'flag' => '🇦🇩'],
        'cs' => ['native' => 'Čeština', 'english' => 'Czech', 'flag' => '🇨🇿'],
        'da' => ['native' => 'Dansk', 'english' => 'Danish', 'flag' => '🇩🇰'],
        'de' => ['native' => 'Deutsch', 'english' => 'German', 'flag' => '🇩🇪'],
        'el' => ['native' => 'Ελληνικά', 'english' => 'Greek', 'flag' => '🇬🇷'],
        'en' => ['native' => 'English', 'english' => 'English', 'flag' => '🇬🇧'],
        'es' => ['native' => 'Español', 'english' => 'Spanish', 'flag' => '🇪🇸'],
        'fa' => ['native' => 'فارسی', 'english' => 'Persian', 'flag' => '🇮🇷'],
        'fi' => ['native' => 'Suomi', 'english' => 'Finnish', 'flag' => '🇫🇮'],
        'fr' => ['native' => 'Français', 'english' => 'French', 'flag' => '🇫🇷'],
        'he' => ['native' => 'עברית', 'english' => 'Hebrew', 'flag' => '🇮🇱'],
        'hi' => ['native' => 'हिन्दी', 'english' => 'Hindi', 'flag' => '🇮🇳'],
        'hu' => ['native' => 'Magyar', 'english' => 'Hungarian', 'flag' => '🇭🇺'],
        'id' => ['native' => 'Bahasa Indonesia', 'english' => 'Indonesian', 'flag' => '🇮🇩'],
        'it' => ['native' => 'Italiano', 'english' => 'Italian', 'flag' => '🇮🇹'],
        'ja' => ['native' => '日本語', 'english' => 'Japanese', 'flag' => '🇯🇵'],
        'ko' => ['native' => '한국어', 'english' => 'Korean', 'flag' => '🇰🇷'],
        'nl' => ['native' => 'Nederlands', 'english' => 'Dutch', 'flag' => '🇳🇱'],
        'no' => ['native' => 'Norsk', 'english' => 'Norwegian', 'flag' => '🇳🇴'],
        'pl' => ['native' => 'Polski', 'english' => 'Polish', 'flag' => '🇵🇱'],
        'pt' => ['native' => 'Português', 'english' => 'Portuguese', 'flag' => '🇵🇹'],
        'pt_BR' => ['native' => 'Português (Brasil)', 'english' => 'Portuguese (Brazil)', 'flag' => '🇧🇷'],
        'ro' => ['native' => 'Română', 'english' => 'Romanian', 'flag' => '🇷🇴'],
        'ru' => ['native' => 'Русский', 'english' => 'Russian', 'flag' => '🇷🇺'],
        'sv' => ['native' => 'Svenska', 'english' => 'Swedish', 'flag' => '🇸🇪'],
        'th' => ['native' => 'ไทย', 'english' => 'Thai', 'flag' => '🇹🇭'],
        'tr' => ['native' => 'Türkçe', 'english' => 'Turkish', 'flag' => '🇹🇷'],
        'uk' => ['native' => 'Українська', 'english' => 'Ukrainian', 'flag' => '🇺🇦'],
        'ur' => ['native' => 'اردو', 'english' => 'Urdu', 'flag' => '🇵🇰'],
        'vi' => ['native' => 'Tiếng Việt', 'english' => 'Vietnamese', 'flag' => '🇻🇳'],
        'zh' => ['native' => '中文', 'english' => 'Chinese', 'flag' => '🇨🇳'],
        'zh_TW' => ['native' => '繁體中文', 'english' => 'Chinese (Traditional)', 'flag' => '🇹🇼'],
    ];

    /**
     * @return array{native: string, english: string, flag: string}|null
     */
    public static function get(string $code): ?array
    {
        return self::LANGUAGES[$code]
            ?? self::LANGUAGES[self::primaryLanguage($code)]
            ?? null;
    }

    public static function primaryLanguage(string $code): string
    {
        return strtolower(preg_split('/[_-]/', $code)[0]);
    }
}
