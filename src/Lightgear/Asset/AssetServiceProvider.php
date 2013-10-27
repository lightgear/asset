<?php namespace Lightgear\Asset;

use Illuminate\Support\ServiceProvider;

class AssetServiceProvider extends ServiceProvider {

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

	/**
	 * Bootstrap the application.
	 *
	 * @return void
	 */
	public function boot()
	{
		$this->package('lightgear/asset');
    }

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->app['asset'] = $this->app->share(function($app) {

			return new Asset($app);
		});

		$this->app['asset.generate'] = $this->app->share(function($app)
		{
			return new Commands\Generate;
		});

		$this->app['asset.clean'] = $this->app->share(function($app)
		{
			return new Commands\Clean;
		});

		$this->commands('asset.generate');
		$this->commands('asset.clean');
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array();
	}
}
