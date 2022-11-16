[![](https://img.shields.io/packagist/v/inspiredminds/contao-wordpressimport.svg)](https://packagist.org/packages/inspiredminds/contao-wordpressimport)
[![](https://img.shields.io/packagist/dt/inspiredminds/contao-wordpressimport.svg)](https://packagist.org/packages/inspiredminds/contao-wordpressimport)

Contao WordPress Import
=======================

Contao 4 extension that allows you to import news articles from WordPress posts via the WordPress JSON API.

## Installation

Require the bundle via composer:
```
composer require inspiredminds/contao-wordpressimport
```
If you use the Contao Standard Edition, you will have to add
```php
new WordPressImportBundle\WordPressImportBundle()
```
to your `AppKernel.php`. 

Then execute the Contao Install Tool to update the database.

## Usage

Once installed, you will have new options within a news archive:

![Contao news archive settings](https://github.com/inspiredminds/contao-wordpressimport/raw/master/newsarchive-settings.png)

* __WordPress URL__: This is the URL to your remote WordPress installation, from which you want to import news. This WordPress installation must have the [WP REST API](http://v2.wp-api.org/) available. It's included and active by default in WordPress 4.7 and higher.
* __Import periodically__: Instead of importing the WordPress posts via console command (see below), you can activate a periodic import here, which will be done via Contao's cronjob.
* __Default author__: Every imported news item will have this author assigned, if no other author is available (see next option).
* __Import authors__: This will generate new backend users for each new found author. Existing authors are identified by their name, so if your Contao installation already has a backend user with the same name as an author from a WordPress post, that backend user will be used as the author. _Note:_ the automatically generated authors are just bare entries. They will only have their name set. They will not have a username or password and they will be disabled by default.
* __Import comments__: This will import the comments of each WordPress post, if the Contao Comments Bundle is present. _Note:_ the email field of each commenter will __not__ be filled, since that is obviously not available via the public WP REST API.
* __Import folder__: When a WordPress posts gets imported, its teaser image and all images from its detailed content are saved to this folder.
* __Category__: This is an optional root category, under which every imported category of the WordPress posts will reside. If you do not specify a root category, the imported categories will be imported to the root. _Note:_ this option will only be available, if you have the [`news_categories`](https://github.com/codefog/contao-news_categories) extension installed.

### Console Command

To import the WordPress posts into your Contao installation, you can use the following console command:
```
vendor/bin/contao-console wordpressimport
```
Optionally you can define the limit of how many WordPress posts are imported in one go:
```
vendor/bin/contao-console wordpressimport 10
```
This will only import 10 WordPress posts at a time.

Use `bin/console` in the Contao Standard Edition.

### Periodic import

If you activated the periodic import, the WordPress posts will be imported _hourly_ by Contao's cronjob. 

_Note:_ by default only __10__ items will be imported with each cronjob execution. The import can take a long time (depending on the number of WordPress posts and the number of images to be downloaded). This limit is there so that a cronjob execution does not block a user's request for a long time (or at least until the `max_execution_time` limit is hit). You can change this limit in the _System Settings_.

## Events

Version `2.1.0` introduced an `WordPressImportBundle\Event\ImportWordPressPostEvent` which is fired for each imported WordPress post after it has been fully processed by the extension. It holds references to the used HTTP client instance, the WordPress post object and the `Contao\NewsModel` instance. This allows you to modify the imported news article.
