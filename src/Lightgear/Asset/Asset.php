<?php namespace Lightgear\Asset;

use Illuminate\Support\Facades\File,
    Illuminate\Support\Facades\HTML,
    Illuminate\Support\Str,
    Illuminate\Support\Facades\Cache,
    Symfony\Component\Finder\Finder,
    lessc;

class Asset {

    protected $paths = array();

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
     * @param  string $group   The group the styles belong to
     * @return Asset           This class instance
     */
    public function registerStyles($assets, $package = '', $group = 'general')
    {
        $this->registerAssets($assets, 'styles', $package, $group);

        return $this;
    }

    /**
     * Register a scripts collection
     *
     * @param  array  $assets  The scripts to register
     * @param  string $package The package the scripts belong to
     * @param  string $group   The group the styles belong to
     * @return Asset           This class instance
     */
    public function registerScripts($assets, $package = '', $group = 'general')
    {
        $this->registerAssets($assets, 'scripts', $package, $group);

        return $this;
    }

    /**
     * Render styles assets
     *
     * @param array $groups The groups to render
     * @return void
     */
    public function styles($groups = array())
    {
        return $this->renderAssets($groups, 'styles');
    }

    /**
     * Render scripts assets
     *
     * @param array $groups The groups to render
     * @return void
     */
    public function scripts($groups = array())
    {
        return $this->renderAssets($groups, 'scripts');
    }

    /**
     * Adds a new search path
     *
     * @param string $path The path to add
     */
    public function addPath($path)
    {
        $this->paths[] = $path;
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

        foreach ($this->getGroupNames('styles') as $group) {
            Cache::forget('asset.styles.groups.' . $group);
        }

        foreach ($this->getGroupNames('scripts') as $group) {
            Cache::forget('asset.scripts.groups.' . $group);
        }
    }

    /**
     * Render the assets
     *
     * @param  array|string $groups The groups to render
     * @param  string       $type   The assets type
     * @return string       The assets resurces
     */
    protected function renderAssets($groups, $type)
    {
        $groups = (array) $groups;

        if (empty($groups)) {
            $groups = $this->getGroupNames($type);
        }

        $output = '';

        foreach ($groups as $group) {

            // use cached resources, if available
            $cacheKey = 'asset.' . $type . '.groups.' . $group;
            if ($this->config->get('asset::use_cache') && Cache::has($cacheKey))
            {
                $output .= Cache::get($cacheKey);
            } else {
                $this->processAssets($this->{$type}[$group], $group);
                $output .= $this->publish($type, $group);
            }

        }

        return $output;
    }

    /**
     * Register an asset collection
     *
     * @param  array  $assets  The assets to register
     * @param  string $type    The type of the assets: styles or scripts
     * @param  string $package The package the assets belong to
     * @param  string $group   The group the assets belong to
     * @return void
     */
    protected function registerAssets($assets, $type, $package, $group)
    {
        if ( ! isset($this->{$type}[$group][$package])) {
            $this->{$type}[$group][$package] = array();
        }

        $this->{$type}[$group][$package] = array_unique(array_merge($this->{$type}[$group][$package], $assets));
    }

    /**
     * Process an assets collection
     *
     * @param  array  $assets The assets to process
     * @param  string $group  The assets group to process
     * @return void
     */
    protected function processAssets($assets, $group)
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
                            $this->processed['styles'][$group][] = $assetData;
                            break;
                        case 'less':
                            $assetData['contents'] = $this->compileLess($file);
                            $assetData += $this->buildTargetPaths($file, $package, 'styles');
                            $this->processed['styles'][$group][] = $assetData;
                            break;
                        case 'js':
                            $assetData['contents'] = file_get_contents($file->getRealPath());
                            $assetData += $this->buildTargetPaths($file, $package, 'scripts');
                            $this->processed['scripts'][$group][] = $assetData;
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
        $paths = array_merge($this->paths, $this->config->get('asset::search_paths'));

        foreach ($paths as $searchPath) {

            $fullPath = base_path() . $searchPath . '/'. $package . '/' . $path;

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
     * @param  string $group  The group to publish
     * @return string $output The assets resource
     */
    protected function publish($type, $group)
    {
        $output = '';
        $combinedContents = '';
        $combine = $this->config->get('asset::combine');
        $minify = $this->config->get('asset::minify');
        $useCache = $this->config->get('asset::use_cache');

        // no assets to publish, stop here!
        if ( ! isset($this->processed[$type][$group])) {
            return;
        }

        foreach ($this->processed[$type][$group] as $asset) {

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
        $cacheKey = 'asset.' . $type . '.groups.' . $group;

        if ($useCache) {
            Cache::forever($cacheKey, $output);
        } elseif (Cache::has($cacheKey)) {
            $this->clean();
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
        $link = $asset['link'] . '?' . str_random(10);
        if ($type === 'styles') {
            $output .= HTML::style($link);
        } elseif ($type === 'scripts') {
            $output .= HTML::script($link);
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

    /**
     * Gets the registered groups for a specific type
     *
     * @param  string $type The assets type
     * @return array        The group names
     */
    protected function getGroupNames($type)
    {
        return array_keys($this->{$type});
    }
}
