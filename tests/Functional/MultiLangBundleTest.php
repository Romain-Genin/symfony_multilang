<?php

declare(strict_types=1);

namespace RomainGenin\MultiLangBundle\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Filesystem\Filesystem;

final class MultiLangBundleTest extends WebTestCase
{
    public static function setUpBeforeClass(): void
    {
        (new Filesystem())->remove(sys_get_temp_dir().'/multilang-bundle-tests');
    }

    public function testRootRedirectsToBrowserLanguage(): void
    {
        $client = self::createClient();
        $client->request('GET', '/', server: ['HTTP_ACCEPT_LANGUAGE' => 'ar-EG,ar;q=0.9']);

        self::assertResponseRedirects('/ar/');
    }

    public function testRootRedirectsToCookieLanguage(): void
    {
        $client = self::createClient();
        $client->getCookieJar()->set(new Cookie('multilang_locale', 'ja'));
        $client->request('GET', '/', server: ['HTTP_ACCEPT_LANGUAGE' => 'en']);

        self::assertResponseRedirects('/ja/');
    }

    public function testRootFallsBackToDefaultLocale(): void
    {
        $client = self::createClient();
        $client->request('GET', '/', server: ['HTTP_ACCEPT_LANGUAGE' => 'de']);

        self::assertResponseRedirects('/fr/');
    }

    public function testLtrPage(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/fr/contact?ref=x');

        self::assertResponseIsSuccessful();
        self::assertSame('fr', $crawler->filter('html')->attr('lang'));
        self::assertSame('ltr', $crawler->filter('html')->attr('dir'));
        self::assertSelectorTextSame('#title', 'Accueil');
        self::assertResponseHeaderSame('Content-Language', 'fr');
        self::assertResponseHasCookie('multilang_locale');
        self::assertSame('fr', $client->getResponse()->headers->getCookies()[0]->getValue());

        // dropdown: current locale + links to the same page in every locale (query string kept)
        $links = $crawler->filter('#dropdown [role=menuitem]');
        self::assertCount(5, $links);
        self::assertSame('/ar/contact?ref=x', $crawler->filter('#dropdown a[hreflang=ar]')->attr('href'));
        self::assertSame('true', $crawler->filter('#dropdown a[hreflang=fr]')->attr('aria-current'));
        self::assertStringContainsString('🇫🇷', $crawler->filter('#dropdown button')->text());
        self::assertStringContainsString('Changer de langue', $crawler->filter('#dropdown [role=menu]')->attr('aria-label'));

        // select + list variants
        self::assertSame('/en/contact?ref=x', $crawler->filter('#select-wrapper option')->eq(1)->attr('value'));
        self::assertNotNull($crawler->filter('#select-wrapper option')->eq(0)->attr('selected'));
        self::assertSame('JA', $crawler->filter('#list a[hreflang=ja]')->text());

        // hreflang SEO links
        self::assertSame('http://localhost/he/contact?ref=x', $crawler->filter('link[hreflang=he]')->attr('href'));
        self::assertSame('http://localhost/fr/contact?ref=x', $crawler->filter('link[hreflang=x-default]')->attr('href'));
    }

    public function testCustomisation(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/fr/');

        // app template extending '@!MultiLang/...' and overriding one block
        self::assertCount(5, $crawler->filter('#list .custom-marker'));
        self::assertSame('JA', $crawler->filter('#list a[hreflang=ja]')->text());

        // class props replace the default classes
        self::assertSame('my-btn', $crawler->filter('#custom button')->attr('class'));
        self::assertSame('my-active', $crawler->filter('#custom a[aria-current]')->attr('class'));
        self::assertStringContainsString('var(--multilang-text', $crawler->filter('#custom a[hreflang=en]')->attr('class'));
    }

    public function testRtlPage(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/ar/');

        self::assertResponseIsSuccessful();
        self::assertSame('ar', $crawler->filter('html')->attr('lang'));
        self::assertSame('rtl', $crawler->filter('html')->attr('dir'));
        self::assertSelectorTextSame('#dir', 'rtl');
        self::assertSelectorTextSame('#title', 'الرئيسية');
        self::assertStringContainsString('تغيير اللغة', $crawler->filter('#dropdown [role=menu]')->attr('aria-label'));
        self::assertSame('he', $crawler->filter('#dropdown a[hreflang=he] bdi')->attr('lang'));
        self::assertSame('العربية', $crawler->filter('#dropdown button bdi')->text());
        self::assertStringContainsString('end-0', $crawler->filter('#dropdown [role=menu]')->attr('class'));
    }

    public function testUnknownLocaleIs404(): void
    {
        $client = self::createClient();
        $client->request('GET', '/de/');

        self::assertResponseStatusCodeSame(404);
    }

    public function testNonLocalizedPageUsesCookieAndSwitchRoute(): void
    {
        $client = self::createClient();
        $client->getCookieJar()->set(new Cookie('multilang_locale', 'he'));
        $crawler = $client->request('GET', '/legacy?a=1');

        self::assertResponseIsSuccessful();
        self::assertSame('he', $crawler->filter('html')->attr('lang'));
        self::assertSame('rtl', $crawler->filter('html')->attr('dir'));
        self::assertCount(0, $crawler->filter('link[rel=alternate]'));

        $href = $crawler->filter('#dropdown a[hreflang=en]')->attr('href');
        self::assertSame('/_locale/en?redirect=/legacy?a%3D1', $href);

        $client->request('GET', $href);
        self::assertResponseRedirects('/legacy?a=1');
        self::assertSame('en', $client->getCookieJar()->get('multilang_locale')->getValue());

        $crawler = $client->followRedirect();
        self::assertSame('ltr', $crawler->filter('html')->attr('dir'));
        self::assertSelectorTextSame('#title', 'Welcome home');
    }

    public function testSwitchRoutePreventsOpenRedirect(): void
    {
        $client = self::createClient();
        $client->request('GET', '/_locale/ja?redirect=//evil.example');

        self::assertResponseRedirects('/ja/');
    }

    public function testSwitchRouteRejectsDisabledLocale(): void
    {
        $client = self::createClient();
        $client->request('GET', '/_locale/de');

        self::assertResponseStatusCodeSame(404);
    }

    public function testAppTranslationsOverrideBundleOnes(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/en/');

        self::assertSelectorTextSame('#title', 'Welcome home');
    }
}
