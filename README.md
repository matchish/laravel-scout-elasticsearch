<p align="center">
  <a href="https://github.com/matchish/laravel-scout-elasticsearch">
    <img alt="Scout ElasticSearch" src="https://raw.githubusercontent.com/matchish/laravel-scout-elasticsearch/master/docs/banner.png" >
  </a>

  <p align="center">
    <a href="https://travis-ci.org/matchish/laravel-scout-elasticsearch"><img src="https://img.shields.io/travis/matchish/laravel-scout-elasticsearch/master.svg" alt="Build Status"></img></a>
    <a href="https://scrutinizer-ci.com/g/algolia/scout-extended"><img src="https://img.shields.io/scrutinizer/g/matchish/laravel-scout-elasticsearch.svg" alt="Quality Score"></img></a>
    <a href="https://scrutinizer-ci.com/g/algolia/scout-extended"><img src="https://scrutinizer-ci.com/g/matchish/laravel-scout-elasticsearch/badges/coverage.png?b=master" alt="Coverage"></img></a>
    <a href="https://packagist.org/packages/matchish/laravel-scout-elasticsearch"><img src="https://poser.pugx.org/matchish/laravel-scout-elasticsearch/d/total.svg" alt="Total Downloads"></a>
    <a href="https://packagist.org/packages/matchish/laravel-scout-elasticsearch"><img src="https://poser.pugx.org/matchish/laravel-scout-elasticsearch/v/stable.svg" alt="Latest Version"></a>
    <a href="https://packagist.org/packages/matchish/laravel-scout-elasticsearch"><img src="https://poser.pugx.org/matchish/laravel-scout-elasticsearch/license.svg" alt="License"></a>
  </p>
</p>

**Full power of ElasticSearch in your Laravel application**.

The package provides the perfect starting point to integrate
ElasticSearch into your Laravel application. It is carefully crafted to simplify the usage
of ElasticSearch within the [Laravel Framework](https://laravel.com).

It’s built on top of the latest release of [Laravel Scout](https://laravel.com/docs/scout), the official Laravel search
package. Using Scout Extended, you are free to take advantage of all of Laravel Scout’s
great features, and at the same time leverage the complete set of ElasticSearch’s search experience.

## 💕 Features

- [**Zero downtime** reimports]() - it’s a breeze to import data in production.
- Bulk indexing.
- A fully configurable mapping for each model.
- Full power of ElasticSearch in your queries

## ❕ Requirements

- PHP version >= 7.1.3
- Laravel Framework version >= 5.6
- Elasticsearch version >= 6

## 🚀 Installation

Use composer to install the package:

`composer require babenkoivan/scout-elasticsearch-driver`

## 💡 Usage

> **Note:** This package adds functionalities to [Laravel Scout](https://github.com/laravel/scout), and for this reason, we encourage you to **read the Scout documentation first**. Documentation for Scout can be found on the [Laravel website](https://github.com/laravel/scout).

## 🆓 License
Scout ElasticSearch is an open-sourced software licensed under the [MIT license](LICENSE.md).
