# Simple and effective assets management for Laravel 4

## Overview
The Lightgear/Asset package is meant to simplify the creation and maintenance of the essential assets of a Laravel 4 based application.

### Supported asset types
Currently there is support for "**less**", "**css**"" and "**javascript**" files.
I do NOT plan to add support for other types like Coffeescript simply because I want to keep the package footprint as small as possible.

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
As you notice in the example both files and directories can be registered.
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


 
