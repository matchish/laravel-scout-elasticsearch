<?php

declare(strict_types=1);

namespace Matchish\ScoutElasticSearch\Searchable;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use function in_array;
use Laravel\Scout\Searchable;
use Symfony\Component\Finder\Finder;

final class SearchableListFactory
{
    /**
     * @var array
     */
    private static $declaredClasses;
    /**
     * @var string
     */
    private $namespace;
    /**
     * @var string
     */
    private $appPath;

    /**
     * @param string $namespace
     */
    public function __construct(string $namespace, string $appPath)
    {
        $this->namespace = $namespace;
        $this->appPath = $appPath;
    }

    /**
     * Get a list of searchable models.
     *
     * @return string[]
     */
    private function find(): array
    {
        $appNamespace = $this->namespace;

        return array_values(array_filter($this->getProjectClasses(), function (string $class) use ($appNamespace) {
            return Str::startsWith($class, $appNamespace) && $this->isSearchableModel($class);
        }));
    }

    /**
     * @param  string $class
     *
     * @return bool
     */
    private function isSearchableModel($class): bool
    {
        return in_array(Searchable::class, class_uses_recursive($class), true);
    }

    /**
     * @return array
     */
    private function getProjectClasses(): array
    {
        if (self::$declaredClasses === null) {
            $configFiles = Finder::create()->files()->name('*.php')->in($this->appPath);
            foreach ($configFiles->files() as $file) {
                require_once $file;
            }
            self::$declaredClasses = get_declared_classes();
        }

        return self::$declaredClasses;
    }

    /**
     * @return Collection
     */
    public function make(): Collection
    {
        return new Collection($this->find());
    }
}
