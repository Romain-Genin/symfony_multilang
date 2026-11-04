<?php

declare(strict_types=1);

namespace StudioAstra\MultiLangBundle\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use StudioAstra\MultiLangBundle\MultiLangBundle;
use Symfony\Component\Translation\Loader\YamlFileLoader;

/**
 * Every shipped language must define exactly the same keys and placeholders as the French reference.
 */
final class TranslationFilesTest extends TestCase
{
    public static function provideFiles(): iterable
    {
        $root = \dirname(__DIR__, 2);
        foreach (['messages' => $root.'/resources/translations', 'multilang' => $root.'/translations'] as $domain => $dir) {
            foreach (MultiLangBundle::DEFAULT_LOCALES as $locale) {
                yield "$domain.$locale" => [$dir, $domain, $locale];
            }
        }
    }

    #[DataProvider('provideFiles')]
    public function testSameKeysAndPlaceholdersAsReference(string $dir, string $domain, string $locale): void
    {
        $loader = new YamlFileLoader();
        $reference = $loader->load("$dir/$domain.fr.yaml", 'fr', $domain)->all($domain);
        $file = "$dir/$domain.$locale.yaml";

        self::assertFileExists($file);
        $messages = $loader->load($file, $locale, $domain)->all($domain);

        self::assertSame(array_keys($reference), array_keys($messages), "Keys differ in $file");

        foreach ($reference as $key => $value) {
            self::assertNotSame('', trim($messages[$key]), "Empty value for $key in $file");
            preg_match_all('/%\w+%/', $value, $expected);
            preg_match_all('/%\w+%/', $messages[$key], $actual);
            sort($expected[0]);
            sort($actual[0]);
            self::assertSame($expected[0], $actual[0], "Placeholders differ for $key in $file");
        }
    }
}
