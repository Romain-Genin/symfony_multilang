<?php

declare(strict_types=1);

namespace RomainGenin\MultiLangBundle\Command;

use RomainGenin\MultiLangBundle\Locale\LanguageCatalog;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Sets the bundle up in an application: config files, locale prefix on the routes,
 * and optionally an override template for the language switcher.
 */
#[AsCommand(name: 'multilang:install', description: 'Install MultiLangBundle in your application (config, routes, optional template override)')]
final class InstallCommand extends Command
{
    private const TEMPLATE_PATH = 'templates/bundles/MultiLangBundle/components/LocaleSwitcher.html.twig';

    private readonly Filesystem $filesystem;

    public function __construct(
        private readonly string $projectDir,
        private readonly string $bundleDir,
    ) {
        parent::__construct();
        $this->filesystem = new Filesystem();
    }

    protected function configure(): void
    {
        $this
            ->addOption('locales', null, InputOption::VALUE_REQUIRED, 'Comma separated enabled locales, e.g. "fr,en,ar"')
            ->addOption('default', null, InputOption::VALUE_REQUIRED, 'Default locale (defaults to the first of --locales)')
            ->addOption('template', null, InputOption::VALUE_NONE, 'Create '.self::TEMPLATE_PATH.' to customise the switcher')
            ->addOption('no-routes-prefix', null, InputOption::VALUE_NONE, 'Do not add the {_locale} prefix to config/routes.yaml')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing files')
            ->setHelp(<<<'HELP'
                Creates <info>config/packages/multi_lang.yaml</info> and <info>config/routes/multilang.yaml</info>,
                adds the <info>/{_locale}</info> prefix to the "controllers" import of <info>config/routes.yaml</info>.

                    <info>php %command.full_name%</info>
                    <info>php %command.full_name% --locales=fr,en,ar --template</info>
                HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('MultiLangBundle installation');
        $force = (bool) $input->getOption('force');

        $locales = null;
        if (null !== $input->getOption('locales')) {
            $locales = array_values(array_filter(array_map('trim', explode(',', (string) $input->getOption('locales')))));
            if ([] === $locales) {
                $io->error('--locales must contain at least one locale.');

                return Command::INVALID;
            }
        }
        $default = $input->getOption('default') ?? $locales[0] ?? null;
        if (null !== $locales && !\in_array($default, $locales, true)) {
            $io->error(sprintf('The default locale "%s" must be part of --locales.', $default));

            return Command::INVALID;
        }

        // 1. config/packages/multi_lang.yaml
        $config = (string) file_get_contents($this->bundleDir.'/recipe/1.0/config/packages/multi_lang.yaml');
        if (null !== $locales) {
            $config = preg_replace('/^(\s+locales:).*$/m', '$1 ['.implode(', ', $locales).']', $config);
        }
        if (null !== $default) {
            $config = preg_replace('/^(\s+default_locale:).*$/m', '$1 '.$default, $config);
        }
        $this->write($io, 'config/packages/multi_lang.yaml', $config, $force);

        // 2. config/routes/multilang.yaml
        $this->write($io, 'config/routes/multilang.yaml', (string) file_get_contents($this->bundleDir.'/recipe/1.0/config/routes/multilang.yaml'), $force);

        // 3. {_locale} prefix on the app controllers
        if (!$input->getOption('no-routes-prefix')) {
            $this->addRoutesPrefix($io);
        }

        // 4. Optional override template
        if ($input->getOption('template')) {
            $this->write($io, self::TEMPLATE_PATH, self::overrideTemplate(), $force);
        }

        $unknown = array_filter($locales ?? [], static fn (string $l): bool => null === LanguageCatalog::get($l));
        if ([] !== $unknown) {
            $io->warning(sprintf('No built-in metadata for: %s. Set their name/flag/direction in multi_lang.locale_overrides and add translations/messages.{locale}.yaml.', implode(', ', $unknown)));
        }

        $io->section('Next steps');
        $io->listing([
            'Tailwind v4 - in <info>assets/styles/app.css</info>:'
                ."\n  <comment>@source \"../../vendor/romain-genin/symfony-multilang-bundle/templates\";</comment>"
                ."\n  <comment>@import \"../../vendor/romain-genin/symfony-multilang-bundle/assets/multilang.css\";</comment>"
                ."\n  (Tailwind v3: add the templates folder to \"content\" in tailwind.config.js)",
            'Alpine.js: <info>php bin/console importmap:require alpinejs</info>, then <info>Alpine.start()</info> in assets/app.js',
            'Layout: <info><html {{ multilang_html_attributes() }}></info> and <info><twig:MultiLang:LocaleSwitcher /></info> in your header/footer',
            'Make sure your home route is named <info>app_home</info> (or change multi_lang.home_route)',
        ]);
        $io->success('MultiLangBundle is installed. Run "php bin/console cache:clear".');

        return Command::SUCCESS;
    }

    private function write(SymfonyStyle $io, string $relativePath, string $content, bool $force): void
    {
        $path = $this->projectDir.'/'.$relativePath;
        if (!$force && $this->filesystem->exists($path)) {
            $io->text(sprintf(' <comment>skipped</comment>  %s (already exists, use --force to overwrite)', $relativePath));

            return;
        }

        $this->filesystem->dumpFile($path, $content);
        $io->text(sprintf(' <info>created</info>  %s', $relativePath));
    }

    private function addRoutesPrefix(SymfonyStyle $io): void
    {
        $relativePath = 'config/routes.yaml';
        $path = $this->projectDir.'/'.$relativePath;
        $manual = 'Add it manually to your controllers import: prefix: /{_locale} + requirements: { _locale: \'%multilang.locales_requirement%\' }';

        if (!$this->filesystem->exists($path)) {
            $io->text(sprintf(' <comment>skipped</comment>  %s not found. %s', $relativePath, $manual));

            return;
        }

        $yaml = (string) file_get_contents($path);
        if (str_contains($yaml, '{_locale}')) {
            $io->text(sprintf(' <comment>skipped</comment>  %s (already contains {_locale})', $relativePath));

            return;
        }

        // The "controllers:" import generated by Symfony Flex
        if (1 !== preg_match('/^controllers:[ \t]*\n((?:[ \t]+\S.*(?:\n|$))+)/m', $yaml, $match, \PREG_OFFSET_CAPTURE)) {
            $io->text(sprintf(' <comment>skipped</comment>  no "controllers:" import found in %s. %s', $relativePath, $manual));

            return;
        }

        if (!$io->confirm(sprintf('Add the /{_locale} prefix to the "controllers" routes of %s?', $relativePath), true)) {
            $io->text(sprintf(' <comment>skipped</comment>  %s. %s', $relativePath, $manual));

            return;
        }

        [$block, $offset] = $match[1];
        preg_match('/^([ \t]+)/', $block, $indent);
        $i = $indent[1];
        $addition = rtrim($block, "\n")."\n"
            .$i."prefix: /{_locale}\n"
            .$i."requirements:\n"
            .$i.$i."_locale: '%multilang.locales_requirement%'\n";

        $yaml = substr($yaml, 0, $offset).$addition.substr($yaml, $offset + \strlen($block));
        $this->filesystem->dumpFile($path, $yaml);
        $io->text(sprintf(' <info>updated</info>  %s (prefix /{_locale})', $relativePath));
    }

    private static function overrideTemplate(): string
    {
        return <<<'TWIG'
            {#
                Customised MultiLang language switcher.
                Only override the blocks you need: the others keep following the bundle updates.

                Available blocks:
                    multilang_dropdown > multilang_button > multilang_button_icon, multilang_button_chevron
                                       > multilang_item            (content of each menu entry)
                    multilang_select   > multilang_select_option
                    multilang_list     > multilang_list_item

                Available variables: this.current, options (language, url, current, label), variant, display,
                showFlags, position, align, switcher_label, button_class, menu_class, item_class...

                Colors: override the --multilang-* CSS variables instead of copying classes.
            #}
            {% extends '@!MultiLang/components/LocaleSwitcher.html.twig' %}

            {# Example: replace the globe icon by the current language code
            {% block multilang_button_icon %}
                <span class="text-xs font-bold uppercase">{{ this.current.code }}</span>
            {% endblock %}
            #}

            {# Example: add the English name under each language
            {% block multilang_item %}
                {{ parent() }}
                <span class="text-xs opacity-60">{{ option.language.englishName }}</span>
            {% endblock %}
            #}

            TWIG;
    }
}
