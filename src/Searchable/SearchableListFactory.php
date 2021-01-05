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
use Roave\BetterReflection\BetterReflection;
use Roave\BetterReflection\Reflector\ClassReflector;
use Roave\BetterReflection\Reflector\Exception\IdentifierNotFound;
use Symfony\Component\Finder\Finder;

final class SearchableListFactory
{
    /**
     * @var array
     */
    private static $searchableClasses;
    /**
     * @var string
     */
    private $namespace;
    /**
     * @var string
     */
    private $appPath;
    /**
     * @var array
     */
    private $errors = [];
    /**
     * @var ClassReflector
     */
    private $classReflector;

    /**
     * @param string $namespace
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

        return array_values(array_filter($this->getSearchableClasses(), function (string $class) use ($appNamespace) {
            return Str::startsWith($class, $appNamespace);
        }));
    }

    /**
     * @return string[]
     */
    private function getSearchableClasses(): array
    {
        if (self::$searchableClasses === null) {
            $projectClasses = $this->getProjectClasses();

            self::$searchableClasses = $projectClasses->filter(function ($class) {
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
        $nodeFinder = new NodeFinder();
        /** @var Class_[] $nodes */
        $nodes = $nodeFinder->find($this->getStmts(), function (Node $node) {
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
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        $nameResolverVisitor = new NameResolver();
        $nodeTraverser = new NodeTraverser();
        $nodeTraverser->addVisitor($nameResolverVisitor);
        $stmts = [];
        $finder = Finder::create()->files()->name('*.php')->in($this->appPath);

        foreach ($finder as $file) {
            try {
                $stmts[] = $parser->parse($file->getContents());
            } catch (Error $e) {
                $this->errors[] = $e->getMessage();
                continue;
            }
        }

        $stmts = Collection::make($stmts)->flatten(1)->toArray();
        $stmts = $nodeTraverser->traverse($stmts);

        return $stmts;
    }

    /**
     * @param string $class
     * @return bool
     */
    private function findSearchableTraitRecursively(string $class): bool
    {
        try {
            $reflection = $this->classReflector()->reflect($class);

            if (in_array(Searchable::class, $traits = $reflection->getTraitNames())) {
                return true;
            }

            foreach ($traits as $trait) {
                if ($this->findSearchableTraitRecursively($trait)) {
                    return true;
                }
            }

            if ($parent = $reflection->getParentClass()) {
                if ($this->findSearchableTraitRecursively($parent->getName())) {
                    return true;
                }
            }

            return false;
        } catch (IdentifierNotFound $e) {
            $this->errors[] = $e->getMessage();

            return false;
        }
    }

    /**
     * @return ClassReflector
     */
    private function classReflector(): ClassReflector
    {
        if (null === $this->classReflector) {
            $this->classReflector = (new BetterReflection())->classReflector();
        }

        return $this->classReflector;
    }
}
