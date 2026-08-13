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

        return Collection::make($files)
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
            return class_exists($class, true);
        } catch (\Throwable $e) {
            // A file that fails to load is worth reporting. A name that maps to
            // no file at all, or to a trait, does not throw and stays quiet.
            $this->errors[] = "Error loading class {$class}: ".$e->getMessage();

            return false;
        }
    }
}
