<?php

namespace BradTipper\AppBackend\OpenApi;

use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * `sake tasks:openapi-export --output=openapi.json` writes the API contract.
 *
 * Every controller routed in `Director.rules` that has a static `describe(Spec $spec)` method is described.
 * Shared schemas come from the classes listed in `schema_providers` (each has a static `register(Spec $spec)`).
 */
class OpenApiExportTask extends BuildTask
{
    use Configurable;

    protected static string $commandName = 'openapi-export';

    protected string $title = 'Export the OpenAPI document';

    protected static string $description = 'Writes openapi.json from the describe() methods on every routed controller.';

    /** @var string[] classes with a static register(Spec $spec) */
    private static array $schema_providers = [];

    /** Server URL written into the document. Empty: the app's public_url when it has one, else the site base URL. */
    private static string $server_url = '';

    public function getOptions(): array
    {
        return [
            new InputOption('output', 'o', InputOption::VALUE_REQUIRED, 'File to write (default: stdout)'),
            new InputOption('title', null, InputOption::VALUE_REQUIRED, 'API title', 'App API'),
        ];
    }

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $json = self::build((string) $input->getOption('title'))->toJson();

        $file = $input->getOption('output');
        if ($file) {
            file_put_contents($file, $json);
            $output->writeln("Wrote {$file}");
        } else {
            $output->writeln($json);
        }
        return Command::SUCCESS;
    }

    public static function build(string $title = 'App API'): Spec
    {
        $spec = new Spec($title, '1.0.0', self::serverUrl());

        foreach ((array) static::config()->get('schema_providers') as $provider) {
            if (class_exists($provider) && method_exists($provider, 'register')) {
                $provider::register($spec);
            }
        }

        // Only controllers that are actually routed are described (shared controllers are opt-in per app).
        $classes = [];
        foreach ((array) Director::config()->get('rules') as $target) {
            $class = ltrim(is_array($target) ? (string) ($target['Controller'] ?? '') : (string) $target, '\\');
            if ($class !== '') {
                $classes[$class] = true;
            }
        }
        $classes = array_keys($classes);
        sort($classes);
        foreach ($classes as $class) {
            if (!class_exists($class) || (new \ReflectionClass($class))->isAbstract()) {
                continue;
            }
            if (method_exists($class, 'describe')) {
                $class::describe($spec);
            }
        }
        return $spec;
    }

    private static function serverUrl(): string
    {
        $configured = trim((string) static::config()->get('server_url'));
        if ($configured !== '') {
            return $configured;
        }
        $appConfig = 'BradTipper\AppBackend\AppConfig';
        if (class_exists($appConfig)) {
            return (string) $appConfig::get('public_url');
        }
        return rtrim(Director::absoluteBaseURL(), '/');
    }
}
