<?php

declare(strict_types=1);

namespace Matchish\ScoutElasticSearch\Searchable;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use Symfony\Component\Finder\Finder;

final class SearchableListFactory
{
    /**
     * @var array|null
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
     * @var array
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
            self::$searchableClasses = $this->getProjectClasses()->filter(function ($class) {
                return $this->findSearchableTraitRecursively($class);
            })->toArray();
        }

        return self::$searchableClasses;
    }

    /**
     * @return Collection
     */
    private function getProjectClasses(): Collection
    {
        /** @var Class_[] $nodes */
        $nodes = (new NodeFinder())->find($this->getStmts(), function (Node $node) {
            return $node instanceof Class_;
        });

        return Collection::make($nodes)->map(function ($node) {
            return $node->namespacedName->toCodeString();
        });
    }

    /**
     * @return array
     */
    private function getStmts(): array
    {
        $parser = (new ParserFactory())->createForHostVersion();
        $nameResolverVisitor = new NameResolver();
        $nodeTraverser = new NodeTraverser();
        $nodeTraverser->addVisitor($nameResolverVisitor);
        $stmts = [];
        foreach (Finder::create()->files()->name('*.php')->in($this->appPath) as $file) {
            try {
                $stmts[] = $parser->parse($file->getContents());
            } catch (Error $e) {
                $this->errors[] = $e->getMessage();
            }
        }

        $stmts = Collection::make($stmts)->flatten(1)->toArray();

        return $nodeTraverser->traverse($stmts);
    }

    /**
     * @param  string  $class
     * @return bool
     */
    private function findSearchableTraitRecursively(string $class): bool
    {
        $traits = class_uses_recursive($class);

        foreach ($traits as $trait) {
            if ($trait === Searchable::class) {
                return true;
            }

            if ($this->findSearchableTraitRecursively($trait)) {
                return true;
            }
        }

        return false;
    }
}
