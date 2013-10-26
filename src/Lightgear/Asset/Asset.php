<?php namespace Lightgear\Asset;

use File,
    lessc;

class Asset {

    protected $config = array();

    protected $styles = array();

    protected $scripts = array();

    protected $processed = array();

    public function __construct($app)
    {
        $this->config = $app['config'];
    }

    /**
     * Register a styles collection
     *
     * @param  array $assets The styles to register
     * @param  string $package The package the styles belong to
     * @return Asset This class instance
     */
    public function registerStyles($assets, $package = '')
    {
        $this->registerAssets($assets, 'styles', $package);

        return $this;
    }

    /**
     * Register a scripts collection
     *
     * @param  array $assets The scripts to register
     * @param  string $package The package the scripts belong to
     * @return Asset This class instance
     */
    public function registerScripts($assets, $package = '')
    {
        $this->registerAssets($assets, 'scripts', $package);

        return $this;
    }

    /**
     * Render styles assets
     *
     * @return void
     */
    public function styles()
    {
        $this->processAssets($this->styles);
    }

    /**
     * Render scripts assets
     *
     * @return void
     */
    public function scripts()
    {
        $this->processAssets($this->scripts);
    }

    /**
     * Register an asset collection
     *
     * @param  array $assets  The assets to register
     * @param  string $type    The type of the assets: styles or scripts
     * @param  string $package The package the assets belong to
     * @return void
     */
    protected function registerAssets($assets, $type, $package)
    {
        if ( ! isset($this->{$type}[$package])) {
            $this->{$type}[$package] = array();
        }

        $this->{$type}[$package] = array_merge($this->{$type}[$package], $assets);
    }

    /**
     * Process an assets collection
     *
     * @param  array $assets The assets to process
     * @return void
     */
    protected function processAssets($assets)
    {
        foreach ($assets as $package => $paths) {

            foreach ($paths as $path) {

                $files = $this->findAssets($path, $package);

                // skip not found assets
                if ( ! $files) {
                    continue;
                }

                foreach ($files as $file) {

                    $assetData = array();

                    switch ($file->getExtension()) {
                        case 'css':
                            $assetData['contents'] = file_get_contents($file->getRealPath());
                        case 'less':
                            $assetData['contents'] = $this->compileLess($file);
                        case 'css':
                        case 'less':
                            $assetData += $this->buildTargetPaths($file, $package, 'styles');
                            $this->processed['styles'][] = $assetData;
                            break;
                        case 'js':
                            $assetData['contents'] = file_get_contents($file->getRealPath());
                            $assetData += $this->buildTargetPaths($file, $package, 'styles');
                            $this->processed['scripts'][] = $assetData;
                            break;
                        default:
                            break;
                    }
                }
            }
        }
    }

    /**
     * Search for the assets in the passed path
     * taking into account configured base paths (workbench, vendor, etc)
     *
     * @param  string $path The path to search
     * @param  string $package The package the assets belong to
     * @return array|null   The array of SplFileInfo objects or null
     */
    protected function findAssets($path, $package)
    {
        foreach ($this->config->get('asset::base_paths') as $basePath) {

            $fullPath = $basePath . '/'. $package . '/' . $path;

            if (File::isDirectory($fullPath)) {
                return File::allFiles($fullPath);
            } elseif (File::isFile($fullPath)) {
                return array(new \SplFileInfo($fullPath));
            }
        }

        return null;
    }

    /**
     * Compile the passed less file to the target
     *
     * @param  SplFileInfo $file The asset file
     * @param  string $target The target fullpath
     * @return void
     */
    protected function compileLess($file) {

        $less = new lessc;
        return $less->compileFile($file->getRealPath());


        // TODO: implement caching
        // load the cache
        $cacheFile = storage_path('cache/less') . $source.".cache";

        if (file_exists($cacheFile)) {
            $cache = unserialize(file_get_contents($cacheFile));
        } else {
            $cache = $source;
        }

        $less = new lessc;
        $newCache = $less->cachedCompile($cache);

        if (!is_array($cache) || $newCache["updated"] > $cache["updated"]) {
            file_put_contents($cacheFile, serialize($newCache));
            file_put_contents($target, $newCache['compiled']);
        }
    }

    /**
     * Builds the target paths array.
     * The 'link' ready to be used as asset url
     * and 'full' suitable for file creation.
     *
     * @param  SplFileInfo $file The target file
     * @param  string $package The package where the asset belongs to
     * @param  string $type    The assets type
     * @return array The target paths array
     */
    protected function buildTargetPaths($file, $package, $type)
    {
        // replace .less extension by .css
        $pathName = str_ireplace('.less', '.css', $file->getRelativePathname());

        $link = '/assets/' . $type . '/' . $package . '/' . $pathName;

        return array(
            'link' => $link,
            'full' => public_path() . $link
        );

    }

    /**
     * Publish an asset
     *
     * @param  string $target   The full destination path
     * @param  string $contents The asset contents
     * @return void
     */
    protected function publish($target, $contents)
    {
        if ( ! file_exists(dirname($target))) {
            File::makeDirectory(dirname($target), 0777, true);
        }

        File::put($target, $contents);
    }
}
