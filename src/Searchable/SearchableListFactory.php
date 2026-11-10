<?php

declare(strict_types=1);

namespace Matchish\ScoutElasticSearch\Searchable;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

final class SearchableListFactory
{
    /**
     * @var array<string>|null
     */
    private static ?array $searchableClasses = null;
    /**
     * @var string
     */
    private string $namespace;
    /**
     * @var string
     */
    private string $appPath;
    /**
     * @var array<string>
     */
    private array $errors = [];
    /**
     * The class that PHP loads now. It is null when no class load is in progress.
     *
     * @var string|null
     */
    private ?string $loadingClass = null;

    /**
     * @param  string  $namespace
     * @param  string  $appPath
     */
    public function __construct(string $namespace, string $appPath)
    {
        $this->namespace = $namespace;
        $this->appPath = $appPath;
    }

    /**
     * @return array<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @return Collection<int, string>
     */
    public function make(): Collection
    {
        return new Collection($this->find());
    }

    /**
     * Get a list of searchable models.
     *
     * @return string[]
     */
    private function find(): array
    {
        $appNamespace = $this->namespace;

        return array_values(array_filter($this->getSearchableClasses(), static function (string $class) use ($appNamespace) {
            return Str::startsWith($class, $appNamespace);
        }));
    }

    /**
     * @return string[]
     */
    private function getSearchableClasses(): array
    {
        if (self::$searchableClasses === null) {
            register_shutdown_function(function (): void {
                $this->showFatalErrorHint();
            });

            /** @var array<class-string> */
            $classes = $this->getProjectClasses()->filter(function (string $class) {
                /** @var class-string $class */
                return $this->findSearchableTraitRecursively($class);
            })->toArray();
            self::$searchableClasses = $classes;
        }

        return self::$searchableClasses;
    }

    /**
     * List every class that may be declared under the app path.
     *
     * The class name is derived from the file path with the PSR-4 convention,
     * the same way Laravel resolves models in its own `model:prune` command.
     * Names that do not resolve are dropped later by `canAnalyzeClass()`.
     *
     * @return Collection<int, string>
     */
    private function getProjectClasses(): Collection
    {
        $files = Finder::create()->files()->name('*.php')->in($this->appPath);
        $included = array_flip(get_included_files());

        return Collection::make($files)
            ->reject(function (SplFileInfo $file) use ($included): bool {
                // A file that is already loaded but declares no class under its
                // conventional name (a helpers file, or a class that does not
                // match its path) must not be probed. The autoloader would
                // include it a second time, and a redeclared function or class
                // is a fatal error that cannot be caught.
                $path = $file->getRealPath();

                return $path !== false
                    && isset($included[$path])
                    && ! class_exists($this->classFromFile($file), false);
            })
            ->map(function (SplFileInfo $file): string {
                return $this->classFromFile($file);
            })
            ->values();
    }

    /**
     * Build the fully qualified class name a PSR-4 autoloader would map the file to.
     */
    private function classFromFile(SplFileInfo $file): string
    {
        $relativePath = Str::replaceLast('.php', '', $file->getRelativePathname());

        return rtrim($this->namespace, '\\').'\\'.str_replace(['/', DIRECTORY_SEPARATOR], '\\', $relativePath);
    }

    /**
     * @param  class-string  $class
     * @return bool
     */
    private function findSearchableTraitRecursively(string $class): bool
    {
        try {
            // Check if class can be reflected without loading
            if (! $this->canAnalyzeClass($class)) {
                return false;
            }

            // Check traits used by this class (including inherited traits)
            $traits = class_uses_recursive($class);

            if (in_array(Searchable::class, $traits)) {
                return true;
            }

            // Check parent class if it exists
            $reflection = new \ReflectionClass($class);
            $parent = $reflection->getParentClass();
            if ($parent) {
                return $this->findSearchableTraitRecursively($parent->getName());
            }

            return false;
        } catch (\Throwable $e) {
            // Log error but don't fail completely - this matches original behavior
            $this->errors[] = "Error analyzing class {$class}: ".$e->getMessage();

            return false;
        }
    }

    /**
     * Check if a class can be safely analyzed.
     *
     * @param  string  $class
     * @return bool
     */
    private function canAnalyzeClass(string $class): bool
    {
        try {
            // First check without autoloading
            if (class_exists($class, false)) {
                return true;
            }

            // Try to autoload, but catch any errors
            $this->loadingClass = $class;

            return class_exists($class, true);
        } catch (\Throwable $e) {
            // A file that fails to load is worth reporting. A name that maps to
            // no file at all, or to a trait, does not throw and stays quiet.
            $this->errors[] = "Error loading class {$class}: ".$e->getMessage();

            return false;
        } finally {
            // PHP does not execute this block after a fatal error,
            // so showFatalErrorHint() can still see the class.
            $this->loadingClass = null;
        }
    }

    /**
     * Show which class discovery loaded when PHP stopped with a fatal error.
     *
     * A fatal error, for example a file with a duplicate use statement, stops
     * PHP at once. A catch block does not see it, so a shutdown function is
     * the only place that can add this context.
     *
     * @return void
     */
    private function showFatalErrorHint(): void
    {
        $error = error_get_last();

        if ($this->loadingClass === null
            || $error === null
            || ! in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE], true)) {
            return;
        }

        file_put_contents('php://stderr', sprintf(
            "\nPHP stopped when laravel-scout-elasticsearch loaded %s to find the searchable models.\n"
            ."The error is in %s on line %d. Correct this file.\n"
            ."To prevent this search, give the model class to the command, for example:\n"
            ."php artisan scout:import \"App\\Models\\YourModel\"\n",
            $this->loadingClass,
            $error['file'],
            $error['line'],
        ));
    }
}
