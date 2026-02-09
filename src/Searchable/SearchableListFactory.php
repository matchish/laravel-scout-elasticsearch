<?php

declare(strict_types=1);

namespace Matchish\ScoutElasticSearch\Searchable;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Name;
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
            self::$searchableClasses = $this->getProjectClasses()->filter(function (string $class) {
                return $this->findSearchableTraitRecursively($class);
            })->toArray();
        }

        return self::$searchableClasses;
    }

    /**
     * @return Collection<int, string>
     */
    private function getProjectClasses(): Collection
    {
        /** @var Class_[] $nodes */
        $nodes = (new NodeFinder())->find($this->getStmts(), function (Node $node) {
            return $node instanceof Class_;
        });

        return Collection::make($nodes)->map(function (Class_ $node) {
            $namespace = $node->namespacedName;
            if ($namespace instanceof Name) {
                return $namespace->toCodeString();
            }
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

        /** @var \PhpParser\Node[] $stmts */
        return $nodeTraverser->traverse($stmts);
    }

    /**
     * @param  string  $class
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
            return false;
        }
    }
}
