<p align="center">
    <h1>Stay with Ukraine</h1>
    <p><a href="https://u24.gov.ua/robots_fight">UNITED24</a> launches the first fundraiser towards terrestrial robotic platforms. Squads of robots will save the lives of our military and civilians. They will become logistics devices, tow trucks, minelayers and deminers, as well as self-destructive robots. They will fight alongside people and for people.

The first robots are already proving their effectiveness on the battlefield. There will be more of them soon. Many more.</p>
  <a href="https://u24.gov.ua/robots_fight">
    <img alt="Support Ukraine" src="https://files.u24.gov.ua/pages/robotsFight/_processed/robots-og-en.jpg" >
  </a>
<!--   <a href="https://github.com/matchish/laravel-scout-elasticsearch">
    <img alt="Scout ElasticSearch" src="https://raw.githubusercontent.com/matchish/laravel-scout-elasticsearch/master/docs/banner.svg?sanitize=true" >
  </a> -->
  
  <img alt="Import progress report" src="https://raw.githubusercontent.com/matchish/laravel-scout-elasticsearch/master/docs/demo.gif" >

  <p align="center">
    <a href="#"><img src="https://github.com/matchish/laravel-scout-elasticsearch/actions/workflows/test-application.yaml/badge.svg" alt="Build Status"></img></a>
    <a href="https://app.codecov.io/gh/matchish/laravel-scout-elasticsearch"><img src="https://codecov.io/gh/matchish/laravel-scout-elasticsearch/branch/coverage-badge/graph/badge.svg" alt="Coverage"></img></a>
    <a href="https://packagist.org/packages/matchish/laravel-scout-elasticsearch"><img src="https://poser.pugx.org/matchish/laravel-scout-elasticsearch/d/total.svg" alt="Total Downloads"></a>
    <a href="https://packagist.org/packages/matchish/laravel-scout-elasticsearch"><img src="https://poser.pugx.org/matchish/laravel-scout-elasticsearch/v/stable.svg" alt="Latest Version"></a>
    <a href="https://packagist.org/packages/matchish/laravel-scout-elasticsearch"><img src="https://poser.pugx.org/matchish/laravel-scout-elasticsearch/license.svg" alt="License"></a>
  </p>
</p>

#### For Laravel Framework < 6.0.0 use [3.x](https://github.com/matchish/laravel-scout-elasticsearch/tree/3.x) branch

The package provides the perfect starting point to integrate
ElasticSearch into your Laravel application. It is carefully crafted to simplify the usage
of ElasticSearch within the [Laravel Framework](https://laravel.com).

It’s built on top of the latest release of [Laravel Scout](https://laravel.com/docs/scout), the official Laravel search
package. Using this package, you are free to take advantage of all of Laravel Scout’s
great features, and at the same time leverage the complete set of ElasticSearch’s search experience.

## :two_hearts: Features  
Don't forget to :star: the package if you like it. :pray:

- Laravel Scout 10.x support
- Laravel Nova support
- [Search amongst multiple models](#search-amongst-multiple-models)
- [**Zero downtime** reimport](#zero-downtime-reimport) - it’s a breeze to import data in production.
- [Eager load relations](#eager-load) - speed up your import.
- Parallel import to make your import as fast as possible (in [alpha version](https://github.com/matchish/laravel-scout-elasticsearch/releases/tag/8.0.0-alpha.3) for now)
- Import all searchable models at once.
- A fully configurable mapping for each model.
- Full power of ElasticSearch in your queries.
## :warning: Requirements

- PHP version >= 8.0
- Laravel Framework version >= 8.0.0

| Elasticsearch version | ElasticsearchDSL version |
|-----------------------|--------------------------|
| >= 8.0                | >= 8.0.0                 |
| >= 7.0                | >= 3.0.0                 |
| >= 6.0, < 7.0         | < 3.0.0                  |

## :rocket: Installation

Use composer to install the package:

```
composer require matchish/laravel-scout-elasticsearch
```

Set env variables
```
SCOUT_DRIVER=Matchish\ScoutElasticSearch\Engines\ElasticSearchEngine
```

The package uses `\ElasticSearch\Client` from official package, but does not try to configure it 
beyond connection configuration, so feel free do it in your app service provider. 
But if you don't want to do it right now, 
you can use `Matchish\ElasticSearchServiceProvider` from the package.  
Register the provider, adding to `config/app.php`
```php
'providers' => [
    // Other Service Providers

    \Matchish\ScoutElasticSearch\ElasticSearchServiceProvider::class
],
```
Set `ELASTICSEARCH_HOST` env variable
```
ELASTICSEARCH_HOST=host:port
```
or use commas as separator for additional nodes
```
ELASTICSEARCH_HOST=host:port,host:port
```

You can disable SSL verification by setting the following in your env
```
ELASTICSEARCH_SSL_VERIFICATION=false
```

And publish config example for elasticsearch  
`php artisan vendor:publish --tag config`

## :bulb: Usage

> **Note:** This package adds functionalities to [Laravel Scout](https://github.com/laravel/scout), and for this reason, we encourage you to **read the Scout documentation first**. Documentation for Scout can be found on the [Laravel website](https://laravel.com/docs/scout).

### Index [settings](https://www.elastic.co/guide/en/elasticsearch/reference/current/indices-create-index.html#create-index-settings) and [mappings](https://www.elastic.co/guide/en/elasticsearch/reference/current/indices-create-index.html#mappings)
It is very important to define the mapping when we create an index — an inappropriate preliminary definition and mapping may result in the wrong search results.

To define mappings or settings for index, set config with right value. 

For example if method `searchableAs` returns 
`products` string

Config key for mappings should be  
`elasticsearch.indices.mappings.products`  
Or you you can specify default mappings with config key 
`elasticsearch.indices.mappings.default`

Same way you can define settings

For index `products` it will be  
`elasticsearch.indices.settings.products`  

And for default settings  
`elasticsearch.indices.settings.default`

### Eager load
To speed up import you can eager load relations on import using global scopes.

You should configure `ImportSourceFactory` in your service provider(`register` method)
```php
use Matchish\ScoutElasticSearch\Searchable\ImportSourceFactory;
...
public function register(): void
{
$this->app->bind(ImportSourceFactory::class, MyImportSourceFactory::class);
``` 
Here is an example of `MyImportSourceFactory`
```php
namespace Matchish\ScoutElasticSearch\Searchable;

final class MyImportSourceFactory implements ImportSourceFactory
{
    public static function from(string $className): ImportSource
    {
        //Add all required scopes
        return new DefaultImportSource($className, [new WithCommentsScope()]);
    }
}

class WithCommentsScope implements Scope {

    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param \Illuminate\Database\Eloquent\Builder $builder
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return void
     */
    public function apply(Builder $builder, Model $model)
    {
        $builder->with('comments');
    }
}
```

You can also customize your indexed data when you save models by leveraging the [`toSearchableArray`](https://laravel.com/docs/9.x/scout#configuring-searchable-data) method
provided by Laravel Scout through the `Searchable` trait

#### Example:
```php
class Product extends Model 
{
    use Searchable;

    /**
     * Get the indexable data array for the model.
     *
     * @return array
     */
    public function toSearchableArray()
    {
        $with = [
            'categories',
        ];

        $this->loadMissing($with);

        return $this->toArray();
    }
}
```

This example will make sure the categories relationship gets always loaded on the model when 
saving it.
### Zero downtime reimport
While working in production, to keep your existing search experience available while reimporting your data, you also can use `scout:import` Artisan command:  

`php artisan scout:import`

The command create new temporary index, import all models to it, and then switch to the index and remove old index.

### Search

To be fully compatible with original scout package, this package does not add new methods.  
So how we can build complex queries?
There is two ways.   
By default, when you pass a query to the `search` method, the engine builds a [query_string](https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-query-string-query.html) query, so you can build queries like this

```php
Product::search('(title:this OR description:this) AND (title:that OR description:that)')
```

If it's not enough in your case you can pass a callback to the query builder

```php
$results = Product::search('zonga', function(\Elastic\Elasticsearch\Client $client, $body) {

    $minPriceAggregation = new MinAggregation('min_price');
    $minPriceAggregation->setField('price');
    
    $maxPriceAggregation = new MaxAggregation('max_price');
    $maxPriceAggregation->setField('price');
    
    $brandTermAggregation = new TermsAggregation('brand');
    $brandTermAggregation->setField('brand');

    $body->addAggregation($minPriceAggregation);
    $body->addAggregation($brandTermAggregation);
    
    return $client->search(['index' => 'products', 'body' => $body->toArray()])->asArray();
})->raw();
```

> Note : The callback function will get 2 parameters. First one is `$client` and it is an object of `\Elastic\Elasticsearch\Client` 
> class from [elasticsearch/elasticsearch](https://packagist.org/packages/elasticsearch/elasticsearch) package. 
> And the second one is `$body` which is an object of `\ONGR\ElasticsearchDSL\Search` from 
> [ongr/elasticsearch-dsl](https://packagist.org/packages/handcraftedinthealps/elasticsearch-dsl) package. So, while
> as you can see the example above, `$client->search(....)` method will return an 
> `\Elastic\Elasticsearch\Response\Elasticsearch` object. And you need to use `asArray()` method to get array result. 
> Otherwise, the `HitsIteratorAggregate` class will throw an error. You can check the issue 
> [here](https://github.com/matchish/laravel-scout-elasticsearch/issues/215).

### Conditions ###

Scout supports only 3 conditions: `->where(column, value)` (strict equation), `->whereIn(column, array)` and `->whereNotIn(column, array)`: 

```php
Product::search('(title:this OR description:this) AND (title:that OR description:that)')
    ->where('price', 100)
    ->whereIn('type', ['used', 'like new'])
    ->whereNotIn('type', ['new', 'refurbished']);
```

Scout does not support any operators, but you can pass ElasticSearch terms like `RangeQuery` as value to `->where()`:

```php

use ONGR\ElasticsearchDSL\Query\TermLevel\RangeQuery;

Product::search('(title:this OR description:this) AND (title:that OR description:that)')
    ->where('price', new RangeQuery('price', [
        RangeQuery::GTE => 100,
        RangeQuery::LTE => 1000,
    ]);
```

And if you just want to search using RangeQuery without any query_string, you can call the search() method directly and leave the param empty.

```php

use ONGR\ElasticsearchDSL\Query\TermLevel\RangeQuery;

Product::search()
    ->where('price', new RangeQuery('price', [
        RangeQuery::GTE => 100,
    ]);
```

Full list of ElasticSearch terms is in `vendor/handcraftedinthealps/elasticsearch-dsl/src/Query/TermLevel`.

### Limiting returned fields
Sometimes your indexed models have fields that should not appear in returned result.
You can set returned fields in `source` option `->options(['source' => ['this', 'that', 'something', 'else']])` 

### Pagination
The engine supports [Elasticsearch pagination](https://www.elastic.co/guide/en/elasticsearch/reference/current/paginate-search-results.html)
with [Scout Builder pagination](https://laravel.com/docs/11.x/scout#pagination) or by setting page sizes 
and offsets using the `->take($size)` method and `->options(['from' => $from])`.

> Caution : Builder pagination takes precedence over the `take()` and `options()` setting.

For example:

```php
Product::search()
    ->take(20)
    ->options([
        'from' => 20,
    ])
    ->paginate(50);
```
This will return the first 50 results, ignoring the specified offset.

### Search amongst multiple models
You can do it with `MixedSearch` class, just pass indices names separated by commas to the `within` method.
```php
MixedSearch::search('title:Barcelona or to:Barcelona')
    ->within(implode(',', [
        (new Ticket())->searchableAs(),
        (new Book())->searchableAs(),
    ]))
    ->get();
```
In this example you will get collection of `Ticket` and `Book` models where ticket's arrival city or
book title is `Barcelona`

### Working with results

Sometimes you need more than models in the result. For example, the highlighted text or the score of each hit.

The engine builds the result collection with a `HitsIteratorAggregate` taken from the container. You can bind your own class there and put anything you want into the result.

The example below adds highlights to the models.

#### 1. Ask Elasticsearch for highlights

Elasticsearch does not send highlights by default. Add a `Highlight` object to the search body in a callback:

```php
use ONGR\ElasticsearchDSL\Highlight\Highlight;

$products = Product::search('zonga', function (\Elastic\Elasticsearch\Client $client, $body) {
    $highlight = new Highlight();
    $highlight->addField('title');
    $highlight->addField('description');

    $body->addHighlight($highlight);

    return $client->search([
        'index' => (new Product)->searchableAs(),
        'body' => $body->toArray(),
    ])->asArray();
})->get();
```

Now every hit in the response has a `highlight` key. But `->get()` returns only models, so this data is lost. The next step keeps it.

#### 2. Write your own `HitsIteratorAggregate`

```php
namespace App\Search;

use ArrayIterator;
use Illuminate\Support\Collection;
use Laravel\Scout\Builder;
use Matchish\ScoutElasticSearch\ElasticSearch\HitsIteratorAggregate;

final class HighlightedHitsIteratorAggregate implements HitsIteratorAggregate
{
    private array $results;

    /** @var callable|null */
    private $callback;

    public function __construct(array $results, ?callable $callback = null)
    {
        $this->results = $results;
        $this->callback = $callback;
    }

    public function getIterator(): ArrayIterator
    {
        $hits = $this->results['hits']['hits'] ?? [];

        // MixedSearch can return hits of different classes.
        // Group them by class and load each group with one query.
        $models = collect($hits)
            ->groupBy('_source.__class_name')
            ->flatMap(function (Collection $classHits, string $class) {
                $model = new $class;
                $model->setKeyType('string');

                $builder = new Builder($model, '');

                // Keep the ->query() callback so eager loading still works.
                if ($this->callback) {
                    $builder->query($this->callback);
                }

                return $model->getScoutModelsByIds($builder, $classHits->pluck('_id')->all())
                    ->keyBy(function ($model) use ($class) {
                        return $class.'::'.$model->getScoutKey();
                    });
            });

        // Sort the models in the same order as the hits
        // and copy the highlights to each model.
        $result = collect($hits)->map(function (array $hit) use ($models) {
            $model = $models->get(($hit['_source']['__class_name'] ?? '').'::'.$hit['_id']);

            // The document is in the index, but the model is not in the database.
            if ($model === null) {
                return null;
            }

            $model->highlight = $hit['highlight'] ?? [];

            return $model;
        })->filter()->values()->all();

        return new ArrayIterator($result);
    }
}
```

#### 3. Bind the class in a service provider

```php
use Matchish\ScoutElasticSearch\ElasticSearch\HitsIteratorAggregate;
use App\Search\HighlightedHitsIteratorAggregate;
...
public function register(): void
{
    $this->app->bind(HitsIteratorAggregate::class, HighlightedHitsIteratorAggregate::class);
}
```

#### 4. Read the highlights

```php
foreach ($products as $product) {
    $product->highlight; // ['title' => ['<em>zonga</em> sneakers']]
}
```

#### Good to know

- The engine creates your class with `makeWith(['results' => ..., 'callback' => ...])`. Do not change the constructor signature.
- `$callback` is the callback from the Scout `->query()` method. Give it to the `Builder`. If you skip this, eager loading with `->query()` no longer works.
- `$model->highlight = ...` creates a normal model attribute. It appears in `toArray()`, and `save()` tries to write a `highlight` column. To avoid this, declare `public array $highlight = [];` in your model. Then PHP sets a real property, and Eloquent does not see an attribute.
- Aggregations are in `$this->results['aggregations']`. You can read them in the same class. If you need only the raw response, call `->raw()` instead.

## :free: License
Scout ElasticSearch is an open-sourced software licensed under the [MIT license](LICENSE.md).
