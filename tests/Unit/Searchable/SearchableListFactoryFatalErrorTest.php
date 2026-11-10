<?php

declare(strict_types=1);

namespace Tests\Unit\Searchable;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * A fatal error stops PHP, so each test runs discovery in its own PHP process.
 */
class SearchableListFactoryFatalErrorTest extends TestCase
{
    private string $appPath;

    protected function setUp(): void
    {
        parent::setUp();

        $path = sys_get_temp_dir().'/scout-discovery-'.uniqid();
        mkdir($path);
        $this->appPath = (string) realpath($path);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->appPath.'/*.php') ?: []);
        rmdir($this->appPath);

        parent::tearDown();
    }

    public function test_it_shows_the_class_and_the_file_after_a_fatal_error_in_a_class_file(): void
    {
        file_put_contents($this->appPath.'/Broken.php', "<?php\nnamespace Fixture;\nclass Broken implements \\Countable {}\n");

        $process = $this->discover();

        $this->assertSame(255, $process->getExitCode());
        $this->assertStringContainsString('loaded Fixture\Broken to find the searchable models', $process->getErrorOutput());
        $this->assertStringContainsString($this->appPath.'/Broken.php on line 3', $process->getErrorOutput());
        $this->assertStringContainsString('php artisan scout:import', $process->getErrorOutput());
    }

    public function test_it_shows_no_hint_for_a_fatal_error_after_discovery(): void
    {
        file_put_contents($this->appPath.'/Fine.php', "<?php\nnamespace Fixture;\nclass Fine {}\n");

        $process = $this->discover("eval('class Later implements \\Countable {}');");

        $this->assertSame(255, $process->getExitCode());
        $this->assertStringNotContainsString('to find the searchable models', $process->getErrorOutput());
    }

    public function test_it_shows_no_hint_when_a_class_file_stops_php_without_a_fatal_error(): void
    {
        file_put_contents($this->appPath.'/Exits.php', "<?php\nnamespace Fixture;\ntrigger_error('not fatal', E_USER_WARNING);\nexit(0);\nclass Exits {}\n");

        $process = $this->discover();

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringNotContainsString('to find the searchable models', $process->getErrorOutput());
    }

    private function discover(string $thenRun = ''): Process
    {
        $code = sprintf(
            <<<'PHP'
            require %1$s;
            spl_autoload_register(function (string $class) {
                $file = %2$s.'/'.substr($class, strlen('Fixture\\')).'.php';
                if (is_file($file)) {
                    require $file;
                }
            });
            (new Matchish\ScoutElasticSearch\Searchable\SearchableListFactory('Fixture\\', %2$s))->make();
            %3$s
            PHP,
            var_export(dirname(__DIR__, 3).'/vendor/autoload.php', true),
            var_export($this->appPath, true),
            $thenRun,
        );

        $process = new Process([PHP_BINARY, '-r', $code]);
        $process->run();

        return $process;
    }
}
