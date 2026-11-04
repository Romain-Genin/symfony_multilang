<?php

declare(strict_types=1);

namespace RomainGenin\MultiLangBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RomainGenin\MultiLangBundle\Controller\LocaleController;
use RomainGenin\MultiLangBundle\Locale\LocaleRegistry;
use RomainGenin\MultiLangBundle\Locale\LocaleResolver;
use Symfony\Component\HttpFoundation\Request;

final class LocaleResolverTest extends TestCase
{
    private LocaleResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new LocaleResolver(new LocaleRegistry(['fr', 'en', 'ar', 'pt'], 'fr'), 'lang');
    }

    public function testCookieWins(): void
    {
        $request = Request::create('/', cookies: ['lang' => 'ar'], server: ['HTTP_ACCEPT_LANGUAGE' => 'en']);
        self::assertSame('ar', $this->resolver->resolve($request));
    }

    public function testInvalidCookieIsIgnored(): void
    {
        $request = Request::create('/', cookies: ['lang' => 'xx'], server: ['HTTP_ACCEPT_LANGUAGE' => 'en']);
        self::assertSame('en', $this->resolver->resolve($request));
    }

    public function testAcceptLanguageMatchesPrimaryLanguage(): void
    {
        $request = Request::create('/', server: ['HTTP_ACCEPT_LANGUAGE' => 'de-DE,pt-BR;q=0.9,en;q=0.5']);
        self::assertSame('pt', $this->resolver->resolve($request));
    }

    public function testDefaultLocale(): void
    {
        $request = Request::create('/', server: ['HTTP_ACCEPT_LANGUAGE' => 'de-DE']);
        self::assertSame('fr', $this->resolver->resolve($request));
    }

    public function testBrowserDetectionCanBeDisabled(): void
    {
        $resolver = new LocaleResolver(new LocaleRegistry(['fr', 'en'], 'fr'), 'lang', false);
        $request = Request::create('/', server: ['HTTP_ACCEPT_LANGUAGE' => 'en']);
        self::assertSame('fr', $resolver->resolve($request));
    }

    public function testSafeRedirectPaths(): void
    {
        self::assertTrue(LocaleController::isSafeLocalPath('/fr/contact?x=1'));
        self::assertFalse(LocaleController::isSafeLocalPath('//evil.com'));
        self::assertFalse(LocaleController::isSafeLocalPath('https://evil.com'));
        self::assertFalse(LocaleController::isSafeLocalPath('/\\evil.com'));
        self::assertFalse(LocaleController::isSafeLocalPath("/foo\r\nbar"));
    }
}
