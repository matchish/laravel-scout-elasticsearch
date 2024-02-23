<p align="center">
  <a href="https://savelife.in.ua/en/donate/">
    <img alt="Support Ukraine" src="http://supportua.org.ua/wp-content/uploads/2015/05/content-logo-main.png" >
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

If you need any help, [stack overflow](https://stackoverflow.com/questions/tagged/laravel-scout%20laravel%20elasticsearch) is the preferred and recommended way to ask support questions.

## :two_hearts: Features  
Don't forget to :star: the package if you like it. :pray:

- Laravel Scout 10.x support
- Laravel Nova support
- [Search amongst multiple models](#search-amongst-multiple-models)
- [**Zero downtime** reimport](#zero-downtime-reimport) - it’s a breeze to import data in production.
- [Eager load relations](#eager-load) - speed up your import.
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

`composer require matchish/laravel-scout-elasticsearch`

Set env variables
```
SCOUT_DRIVER=Matchish\ScoutElasticSearch\Engines\ElasticSearchEngine
```

The package uses `\ElasticSearch\Client` from official package, but does not try to configure it, 
so feel free do it in your app service provider. 
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

### Search amongst multiple models
You can do it with `MixedSearch` class, just pass indices names separated by commas to the `within` method.
```php
MixedSearch::search('title:Barcelona or to:Barcelona')
    within(implode(',', [
        (new Ticket())->searchableAs(),
        (new Book())->searchableAs(),
    ]))
->get();
```
In this example you will get collection of `Ticket` and `Book` models where ticket's arrival city or
book title is `Barcelona`

### Working with results
Often your response isn't collection of models but aggregations or models with higlights an so on.
In this case you need to implement your own implementation of `HitsIteratorAggregate` and bind it in your service provider

[Here is a case](https://github.com/matchish/laravel-scout-elasticsearch/issues/28)

## :free: License
Scout ElasticSearch is an open-sourced software licensed under the [MIT license](LICENSE.md).
