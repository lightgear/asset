<?php namespace Lightgear\Asset;

use File,
    HTML,
    Str,
    Cache,
    lessc,
    Symfony\Component\Finder\Finder;

class Asset {

    protected $config = array();

    protected $styles = array();

    protected $scripts = array();

    protected $processed = array(
        'styles' => array(),
        'scripts' => array()
    );

    public function __construct($app)
    {
        $this->config = $app['config'];
    }

    /**
     * Register a styles collection
     *
     * @param  array  $assets  The styles to register
     * @param  string $package The package the styles belong to
     * @return Asset           This class instance
     */
    public function registerStyles($assets, $package = '')
    {
        $this->registerAssets($assets, 'styles', $package);

        return $this;
    }

    /**
     * Register a scripts collection
     *
     * @param  array  $assets  The scripts to register
     * @param  string $package The package the scripts belong to
     * @return Asset           This class instance
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
        // use cached resources, if available
        if (Cache::has('asset.styles'))
        {
            return Cache::get('asset.styles');
        }

        $this->processAssets($this->styles);

        return $this->publish('styles');
    }

    /**
     * Render scripts assets
     *
     * @return void
     */
    public function scripts()
    {
        // use cached resources, if available
        if (Cache::has('asset.scripts'))
        {
            return Cache::get('asset.scripts');
        }

        $this->processAssets($this->scripts);

        return $this->publish('scripts');
    }

    /**
     * Delete published assets
     *
     * @return void
     */
    public function clean()
    {
        $assetsDir = public_path() . '/' . $this->config->get('asset::public_dir');

        File::deleteDirectory($assetsDir, true);

        Cache::forget('asset.styles');
        Cache::forget('asset.scripts');
    }

    /**
     * Register an asset collection
     *
     * @param  array  $assets  The assets to register
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

                    $assetData = array(
                        'is_minified' => $this->isMinified($file->getRealPath())
                    );

                    switch ($file->getExtension()) {
                        case 'css':
                            $assetData['contents'] = file_get_contents($file->getRealPath());
                            $assetData += $this->buildTargetPaths($file, $package, 'styles');
                            $this->processed['styles'][] = $assetData;
                            break;
                        case 'less':
                            $assetData['contents'] = $this->compileLess($file);
                            $assetData += $this->buildTargetPaths($file, $package, 'styles');
                            $this->processed['styles'][] = $assetData;
                            break;
                        case 'js':
                            $assetData['contents'] = file_get_contents($file->getRealPath());
                            $assetData += $this->buildTargetPaths($file, $package, 'scripts');
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
     * @param  string $path    The path to search
     * @param  string $package The package the assets belong to
     * @return array|null      The array of SplFileInfo objects or null
     */
    protected function findAssets($path, $package)
    {
        foreach ($this->config->get('asset::base_paths') as $basePath) {

            $fullPath = $basePath . '/'. $package . '/' . $path;

            if (File::isDirectory($fullPath)) {
                return File::allFiles($fullPath);
            } elseif (File::isFile($fullPath)) {
                return Finder::create()
                            ->depth(0)
                            ->name(basename($fullPath))
                            ->in(dirname($fullPath));
            }
        }
    }

    /**
     * Compile the passed less file to the target
     *
     * @param  SplFileInfo $file   The asset file
     * @param  string      $target The target fullpath
     * @return void
     */
    protected function compileLess($file) {
        var_dump($file);
        $less = new lessc;

        return $less->compileFile($file->getRealPath());
    }

    /**
     * Builds the target paths array.
     * The 'link' ready to be used as asset url
     * and 'full' suitable for file creation.
     *
     * @param  Symfony\Component\Finder\SplFileInfo|string $file
     *         The file object or the filename
     * @param  string $package The package where the asset belongs to
     * @param  string $type    The assets type
     * @return array           The target paths array
     */
    protected function buildTargetPaths($file, $package, $type)
    {
        if ($file instanceof \Symfony\Component\Finder\SplFileInfo) {
            $pathName = $file->getRelativePathname();
        } else {
            $pathName = $file;
        }

        // replace .less extension by .css
        $pathName = str_ireplace('.less', '.css', $pathName);

        $link = '/' . $this->config->get('asset::public_dir') . '/' . $type . '/';

        // add package segment, if any
        if ($package) {
            $link .= $package . '/';
        }

        $link .= $pathName;

        return array(
            'link' => $link,
            'full' => public_path() . $link
        );

    }

    /**
     * Publish a collection of processed assets
     *
     * @param  string $type   The colleciton type to publish
     * @return void
     */
    protected function publish($type)
    {
        $output = '';
        $combinedContents = '';
        $combine = $this->config->get('asset::combine');
        $minify = $this->config->get('asset::minify');
        $useCache = $this->config->get('asset::use_cache');

        foreach ($this->processed[$type] as $asset) {

            // minify, if the asset isn't yet
            if ($minify && ( ! $asset['is_minified'])) {
                $asset['contents'] = $this->minifyAsset($asset['contents'], $type);
            }

            // collect assets contents
            if ($combine) {
                $combinedContents .= $asset['contents'];
                continue;
            }

            // publish files separately
            $output .= $this->publishAsset($asset, $type);

        }

        // publish combined assets
        if ($combine) {
            $output .= $this->publishCombined($combinedContents, $type);
        }

        // cache asset resurce
        if ($useCache) {
            Cache::add('asset.' . $type, $output, 14400);
        }

        return $output;
    }

    /**
     * Publish a combined asset
     *
     * @param  string $contents The combined assets contents
     * @param  string $type     The assets type
     * @return string           The link to the asset resource
     */
    protected function publishCombined($contents, $type)
    {
        if ($type === 'styles') {
            $filename = $this->config->get('asset::combined_styles');
        } elseif ($type === 'scripts') {
            $filename = $this->config->get('asset::combined_scripts');
        }

        $assetData = $this->buildTargetPaths(
            $filename,
            null,
            $type
        );

        $assetData['contents'] = $contents;

        return $this->publishAsset($assetData, $type);
    }

    /**
     * PUblish a single asset
     *
     * @param  array  $asset The asset data
     * @param  string $type  The asset type
     * @return string        The link to the asset resource
     */
    protected function publishAsset($asset, $type)
    {
        $output = '';

        // prepare target directory
        if ( ! file_exists(dirname($asset['full']))) {
            File::makeDirectory(dirname($asset['full']), 0777, true);
        }

        // create the asset file
        File::put($asset['full'], $asset['contents']);

        // add the element
        if ($type === 'styles') {
            $output .= HTML::style($asset['link']);
        } elseif ($type === 'scripts') {
            $output .= HTML::script($asset['link']);
        }

        return $output;
    }

    /**
     * Minifies asset contents
     *
     * @param  string $contents The contents to minify
     * @param  string $type     The type of the asset
     * @return string           The minified contents
     */
    protected function minifyAsset($contents, $type)
    {
        if ($type === 'styles') {
            $cssMin = new \CSSmin;
            return $cssMin->run($contents);
        } elseif ($type === 'scripts') {
            return \JSMin::minify($contents);
        }
    }

    /**
     * Check if an asset is already minified
     *
     * @param  string  $fullpath The full path to the source file
     * @return boolean
     */
    protected function isMinified($fullpath)
    {
        $filename = basename($fullpath);

        foreach ($this->config->get('asset::minify_patterns') as $pattern) {
            if (Str::contains($filename, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
