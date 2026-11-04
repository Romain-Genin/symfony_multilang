<?php

declare(strict_types=1);

namespace RomainGenin\MultiLangBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RomainGenin\MultiLangBundle\Locale\LocaleRegistry;

final class LocaleRegistryTest extends TestCase
{
    public function testMetadataAndDirection(): void
    {
        $registry = new LocaleRegistry(['fr', 'ar', 'he', 'fa', 'pt_BR'], 'fr');

        self::assertSame(['fr', 'ar', 'he', 'fa', 'pt_BR'], $registry->getCodes());
        self::assertSame('Français', $registry->get('fr')->nativeName);
        self::assertSame('العربية', $registry->get('ar')->nativeName);
        self::assertSame('ltr', $registry->getDirection('fr'));
        self::assertSame('rtl', $registry->getDirection('ar'));
        self::assertTrue($registry->isRtl('he'));
        self::assertTrue($registry->isRtl('fa'));
        self::assertSame('pt-BR', $registry->get('pt_BR')->getHtmlLang());
        self::assertSame('fr|ar|he|fa|pt_BR', $registry->getRouteRequirement());
    }

    public function testRegionalRtlLocaleMatchesPrimaryLanguage(): void
    {
        $registry = new LocaleRegistry(['en', 'ar_MA'], 'en');

        self::assertTrue($registry->isRtl('ar_MA'));
        self::assertSame('العربية', $registry->get('ar_MA')->nativeName);
    }

    public function testOverrides(): void
    {
        $registry = new LocaleRegistry(['fr', 'en', 'xx'], 'fr', ['ar'], [
            'en' => ['flag' => '🇺🇸', 'name' => 'English (US)'],
            'fr' => ['flag' => null],
            'xx' => ['direction' => 'rtl', 'name' => 'Custom'],
        ]);

        self::assertSame('🇺🇸', $registry->get('en')->flag);
        self::assertSame('English (US)', $registry->get('en')->nativeName);
        self::assertNull($registry->get('fr')->flag);
        self::assertTrue($registry->isRtl('xx'));
        self::assertSame('Custom', $registry->get('xx')->nativeName);
    }

    public function testDefaultLocaleMustBeEnabled(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new LocaleRegistry(['fr', 'en'], 'de');
    }

    public function testUnknownLocale(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new LocaleRegistry(['fr'], 'fr'))->get('de');
    }
}
