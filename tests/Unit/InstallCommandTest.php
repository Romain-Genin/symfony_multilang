<?php

declare(strict_types=1);

namespace StudioAstra\MultiLangBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use StudioAstra\MultiLangBundle\Command\InstallCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

final class InstallCommandTest extends TestCase
{
    private const FLEX_ROUTES = <<<'YAML'
        controllers:
            resource:
                path: ../src/Controller/
                namespace: App\Controller
            type: attribute

        other:
            path: /health
            controller: App\Controller\HealthController

        YAML;

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/multilang-install-'.bin2hex(random_bytes(4));
        (new Filesystem())->dumpFile($this->dir.'/config/routes.yaml', self::FLEX_ROUTES);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->dir);
    }

    private function install(array $input = []): CommandTester
    {
        $tester = new CommandTester(new InstallCommand($this->dir, \dirname(__DIR__, 2)));
        $tester->setInputs(['yes']);
        $tester->execute($input);

        return $tester;
    }

    public function testInstall(): void
    {
        $tester = $this->install(['--locales' => 'en,fr,ar', '--template' => true]);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        $config = Yaml::parseFile($this->dir.'/config/packages/multi_lang.yaml');
        self::assertSame(['en', 'fr', 'ar'], $config['multi_lang']['locales']);
        self::assertSame('en', $config['multi_lang']['default_locale']);

        $routes = Yaml::parseFile($this->dir.'/config/routes/multilang.yaml');
        self::assertSame('@MultiLangBundle/config/routes.php', $routes['multilang']['resource']);

        $appRoutes = Yaml::parseFile($this->dir.'/config/routes.yaml');
        self::assertSame('/{_locale}', $appRoutes['controllers']['prefix']);
        self::assertSame('%multilang.locales_requirement%', $appRoutes['controllers']['requirements']['_locale']);
        self::assertSame('attribute', $appRoutes['controllers']['type']);
        self::assertArrayNotHasKey('prefix', $appRoutes['other']);

        $template = (string) file_get_contents($this->dir.'/templates/bundles/MultiLangBundle/components/LocaleSwitcher.html.twig');
        self::assertStringContainsString("{% extends '@!MultiLang/components/LocaleSwitcher.html.twig' %}", $template);
    }

    public function testIsIdempotentAndKeepsExistingFiles(): void
    {
        $this->install(['--locales' => 'fr,en']);
        file_put_contents($this->dir.'/config/packages/multi_lang.yaml', '# mine');

        $tester = $this->install();
        self::assertSame('# mine', file_get_contents($this->dir.'/config/packages/multi_lang.yaml'));
        self::assertStringContainsString('already exists', $tester->getDisplay());
        self::assertSame(1, substr_count((string) file_get_contents($this->dir.'/config/routes.yaml'), 'prefix'));
    }

    public function testInvalidDefaultLocale(): void
    {
        $tester = $this->install(['--locales' => 'fr,en', '--default' => 'de']);
        self::assertSame(Command::INVALID, $tester->getStatusCode());
    }
}
