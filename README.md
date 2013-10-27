# Simple and effective assets management for Laravel 4

## Overview
The Lightgear/Asset package is meant to simplify the creation and maintenance of the essential assets of a Laravel 4 based application.

## Supported asset types
Currently there is support for "**less**", "**css**"" and "**javascript**" files.
I do NOT plan to add support for other types like Coffeescript simply because I want to keep the package footprint as small as possible.

## Installation
Just require
```json
"lightgear/asset": "dev-master"
```
in your composer.json
and run
```bash
composer update
```

Then register the service provider
```php
'Lightgear\Asset\AssetServiceProvider'
```
and, optionally, alias 
```php
'Asset' => 'Lightgear\Asset\Facades\Asset'
```
in **app/config/app.php**

## Usage
All you need to do is register your assets with either **registerStyles()** or **registerScripts()** methods.
For example, to register a package assets you would use something like this in your service provider:

```php
/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->app->make('asset')
			 ->registerStyles(array(
					'src/assets/styles',
			 	), 'vendor/package'
			 )
			 ->registerScripts(array(
					'src/assets/scripts',
			 	), 'vendor/package'
			 )
			 ->registerScripts(array(
					'build/yui/yui-min.js',
			 	), 'yui/yui3'
	    );
	}
```
or you can register an asset from **app/assets** (for instance from within a route closure) with
```php
Asset::registerStyles(array(
        'css/shared.less'
    )
);
or
Asset::registerScripts(array(
        'js/shared.js'
    )
);
```

As you notice in the examples both files and directories can be registered.
It's worth noticing that **directories are added recursively**.

## Configuration
A number of config options allow you to customize the handling of the assets.
Please check **src/config/config.php** for details.

## Combining and minifying
Both are fully supported

## Caching
Simple caching support is provided.
This avoids generation of the assets on every request.
Caching needs to be turned on in the config (since you probably only want to do this on production).

## Templating
The assets can be printed out in a (blade) template by using
```php
{{ Asset::styles() }}
```
and
```php
{{ Asset::scripts() }}
```

## Artisan commands
The package comes with 2 commands:
```bash
php artisan asset:clean
```
which deletes all published and cached assets
and
```bash
php artisan asset:generate
```
which generates and publishes the registered assets

## Permissions
If you experience permissions issues when running the above commands, it's because the user running artisan is different from the one that generates the assets through the webserver (www-data for example).
The issue is explained in greater details at
http://symfony.com/doc/current/book/installation.html#configuration-and-setup
To fix the issue it's enough to follow the steps outlined in this page.
For example on Ubuntu I run the following commands from the project root
```bash
sudo setfacl -R -m u:www-data:rwX -m u:`whoami`:rwX public/assets
sudo setfacl -dR -m u:www-data:rwX -m u:`whoami`:rwX public/assets
```
When using caching, you would need to do the same
```bash
sudo setfacl -R -m u:www-data:rwX -m u:`whoami`:rwX app/storage
sudo setfacl -dR -m u:www-data:rwX -m u:`whoami`:rwX app/storage
```


 
