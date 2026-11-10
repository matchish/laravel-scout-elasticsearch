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
            register_shutdown_function(function (): void {
                $this->showFatalErrorHint();
            });

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
                // Traverse each file on its own. The use statements of one file
                // must not stay active for the next file.
                $stmts[] = $nodeTraverser->traverse($parser->parse($file->getContents()) ?? []);
            } catch (Error $e) {
                $this->errors[] = $e->getMessage();
            }
        }

        return Collection::make($stmts)->flatten(1)->toArray();
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
            $this->loadingClass = $class;

            return class_exists($class, true);
        } catch (\Throwable $e) {
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
     * A fatal error, for example a class that does not implement an abstract
     * method, stops PHP at once. A catch block does not see it, so a shutdown
     * function is the only place that can add this context.
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
