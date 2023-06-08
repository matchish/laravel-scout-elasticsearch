<?php

namespace Matchish\ScoutElasticSearch\Searchable;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use RuntimeException;

final class SearchableListFactory  
{  
    /**
     * @var array|null
     */
    private static ?array $searchableClasses = null;  

    /**
     * @var array
     */
    private array $errors = [];  

    /**
     * @var Reflector|null
     */
    private ?Reflector $reflector = null;  

    /**
     * @return array  
     */
    public function getErrors(): array  
    {  
        return $this->errors;  
    }  

    /**
     * @return Collection  
     */
    public function make(): Collection  
    {  
        return new Collection($this->find());  
    }  

    private function find(): array  
    {
        list($sources, $namespaces) = $this->inferProjectSourcePaths();
        
        $classes = [];
        foreach($sources as $index => $source) {
            $appPath = $source;
            $namespace = $namespaces[$index];
            $classes = array_merge($classes, $this->getSearchableClasses($namespace, $appPath));
        }

        return array_values($classes);
    }  

    private function getSearchableClasses(string $namespace, string $appPath): array  
    {  
        if (self::$searchableClasses === null) {  
            self::$searchableClasses = $this->getProjectClasses($namespace, $appPath)->filter(function ($class) {  
                return $this->findSearchableTraitRecursively($class);  
            })->toArray();  
        }  

        return self::$searchableClasses;  
    }  

    private function getProjectClasses(string $namespace, string $appPath): Collection  
    {  
        /** @var Class_[] $nodes */  
        $nodes = (new NodeFinder())->find($this->getStmts($namespace, $appPath), function (Node $node) {  
            return $node instanceof Class_;  
        });  

        return Collection::make($nodes)->map(function ($node) {  
            return $node->namespacedName->toCodeString();  
        });  
    }  

    private function inferProjectSourcePaths(): array  
    {  
        if (! ($composer = file_get_contents(base_path('composer.json')))) {  
            throw new RuntimeException('Error reading composer.json');  
        }  
        $autoload = json_decode($composer, true)['autoload'] ?? [];  

        if (! isset($autoload['psr-4'])) {  
            throw new RuntimeException('psr-4 autoload mappings are not present in composer.json');  
        }  

        $psr4 = collect($autoload['psr-4']);  

        $sources = $psr4->values()->map(function ($path) {  
            return base_path($path);  
        })->toArray();  
        $namespaces = $psr4->keys()->toArray();  

        return [$sources, $namespaces];  
    }  
}
