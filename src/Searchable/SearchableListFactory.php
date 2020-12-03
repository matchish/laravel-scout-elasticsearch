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
            self::$declaredClasses = [];

            $configFiles = Finder::create()->files()->name('*.php')->in($this->appPath);

            foreach ($configFiles->files() as $file) {
                if ($className = $this->classNameFromFileContents($file->getPathname())) {
                    self::$declaredClasses[] = $className;
                }
            }
        }

        return self::$declaredClasses;
    }

    /**
     * @param string $path
     * @return string|null
     * @link https://stackoverflow.com/a/7153391/1359273
     */
    private function classNameFromFileContents($path)
    {
        $fp = fopen($path, 'r');

        if (false === $fp) {
            return null;
        }

        $class = $namespace = $buffer = '';

        while (! $class) {
            if (feof($fp)) {
                break;
            }

            $buffer .= fread($fp, 512);
            $tokens = token_get_all($buffer);

            if (strpos($buffer, '{') === false) {
                continue;
            }

            for ($i = 0, $iMax = count($tokens); $i < $iMax; $i++) {
                if ($tokens[$i][0] === T_NAMESPACE) {
                    for ($j = $i + 1, $jMax = count($tokens); $j < $jMax; $j++) {
                        if ($tokens[$j][0] === T_STRING) {
                            $namespace .= $tokens[$j][1];
                        } elseif ($tokens[$j] === '{' || $tokens[$j] === ';') {
                            break;
                        }
                    }
                }

                if ($tokens[$i][0] === T_CLASS) {
                    for ($j = $i + 1, $jMax = count($tokens); $j < $jMax; $j++) {
                        if ($tokens[$j] === '{') {
                            $class = $tokens[$i + 2][1];
                        }
                    }
                }
            }
        }

        if (! $class) {
            return null;
        }

        return $namespace ? "{$namespace}\\{$class}" : $class;
    }

    /**
     * @return Collection
     */
    public function make(): Collection
    {
        return new Collection($this->find());
    }
}
