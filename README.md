# Simple and effective assets management for Laravel 4

## Overview
The Lightgear/Asset package is meant to simplify the creation and maintenance of the essential assets of a Laravel 4 based application.

## Features

* **Supported asset types**: "**less**", "**css**"" and "**javascript**" files.
I do NOT plan to add support for other types like Coffeescript simply because I want to keep the package footprint as small as possible.
* **Combining and minifying** (any combination of the two) are fully supported
* Simple but effective **caching** support is provided.
This avoids generation of the assets on every request.
Caching needs to be turned on in the config (since you probably only want to do this on production).
* **Asset groups**

## Installation

### Via Laravel 4 Package Installer
```bash
php artisan package:install lightgear/asset
```
### Manual
Just require
```json
"lightgear/asset": "1.1"
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
and, optionally, the alias 
```php
'Asset' => 'Lightgear\Asset\Facades\Asset'
```
in **app/config/app.php**

Publish configuration with
```bash
php artisan config:publish lightgear/asset
```
This will ceate the **app/config/packages/lightgear/asset/config.php** file.

Finally create the directory specified as "public_dir" in the config file and give it full writing permissions.

## Usage
All you need to do is register your assets with either **registerStyles()** or **registerScripts()** methods.
Important: assets need to be registered **in a file which is always loaded** (ex. in a package's ServiceProvider).
For example, to register a package assets you would use something like this in your service provider:

```php
/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
	    $styles = array(
	        'src/assets/styles',
	        'src/assets/pure/pure/pure-min.css'
	    );

		$asset = $this->app->make('asset');

        // register styles of a vendor package and assign them
        // to the default "general" group
        $asset->registerStyles($styles, 'vendor/package');

        // register styles of a vendor package and assign them
        // to the "frontend" group
        $asset->registerStyles($styles, 'vendor/package', 'frontend');

        // the same goes with scripts for whom you would use for example
        $asset->registerScripts(array('src/scripts')), 'vendor/package');
	}
```
or you could register assets located in **app/assets** with
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

## Templating
The assets can be printed out in a (blade) template by using
```php
// prints all the registered styles
{{ Asset::styles() }}

// prints only the "frontend" group
{{ Asset::styles('frontend') }}

// prints the "frontend" and "mygroup" groups
{{ Asset::styles(array('frontend', 'mygroup')) }}

```
and the same syntax is used for the scripts
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

## Changelog
1.0: add support for asset groups and improve cache handling  
0.8: initial release
 
