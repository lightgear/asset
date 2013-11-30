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

    protected $registered = array();

    protected $processed = array(
        'styles' => array(),
        'scripts' => array(),
        'static' => array()
    );

    public function __construct($app)
    {
        $this->config = $app['config'];
    }

    public function register($assets, $options = array())
    {
        // merge passed options with defaults
        $options = array_merge(
            array(
                'group' => 'general',
                'link_resource' => true
            ),
            $options
        );

        // assing assets to group
        foreach ($assets as $asset) {
            $this->registered[$options['group']][$asset] = $options;
        }

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

    public function statics($groups = array())
    {
        return $this->renderAssets($groups, 'static');
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

        foreach ($this->config->get('asset::search_paths') as $searchPath) {
            File::deleteDirectory($assetsDir . '/' . $searchPath);
        }

        File::delete($assetsDir . '/' . $this->config->get('asset::combined_styles'));
        File::delete($assetsDir . '/' . $this->config->get('asset::combined_scripts'));

        foreach ($this->getGroupNames('styles') as $group) {
            Cache::forget('asset.styles.groups.' . $group);
        }

        foreach ($this->getGroupNames('scripts') as $group) {
            Cache::forget('asset.scripts.groups.' . $group);
        }

        foreach ($this->getGroupNames('static') as $group) {
            Cache::forget('asset.static.groups.' . $group);
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
            $groups = $this->getGroupNames();
        }

        $output = '';

        foreach ($groups as $group) {

            // use cached resources, if available
            $cacheKey = 'asset.' . $type . '.groups.' . $group;

            if ($this->config->get('asset::use_cache') && Cache::has($cacheKey))
            {
                $output .= Cache::get($cacheKey);
            } else {
                $this->processAssets($this->registered[$group], $type, $group);
                $output .= $this->publish($type, $group);
            }

        }

        return $output;
    }

    /**
     * Process an assets collection
     *
     * @param  array  $assets The assets to process
     * @param  string $group  The assets group to process
     * @return void
     */
    protected function processAssets($assets, $type, $group)
    {
        foreach ($assets as $path => $options) {

            $files = $this->findAssets($path);

            // skip not found assets
            if ( ! $files) {
                throw new \Exception("No assets found for the path: " . $path, 1);
            }

            foreach ($files as $file) {

                $assetData = array(
                    'is_minified' => $this->isMinified($file->getRealPath()),
                    'src' => $file->getRealPath(),
                );

                // merge in asset options
                $assetData = array_merge($assetData, $options);

                switch ($file->getExtension()) {
                    case 'css':
                        $assetData['contents'] = file_get_contents($file->getRealPath());
                        $assetData += $this->buildTargetPaths($file, 'styles');
                        $this->processed['styles'][$group][$assetData['link']] = $assetData;
                        break;
                    case 'less':
                        $assetData['contents'] = $this->compileLess($file);
                        $assetData += $this->buildTargetPaths($file, 'styles');
                        $this->processed['styles'][$group][$assetData['link']] = $assetData;
                        break;
                    case 'js':
                        $assetData['contents'] = file_get_contents($file->getRealPath());
                        $assetData += $this->buildTargetPaths($file, 'scripts');
                        $this->processed['scripts'][$group][$assetData['link']] = $assetData;
                        break;
                    default:
                        $assetData += $this->buildTargetPaths($file, 'static');
                        $this->processed['static'][$group][$assetData['link']] = $assetData;
                        break;
                }
            }
        }
    }


    /**
     * Search for the assets in the passed path
     * taking into account configured base paths (workbench, vendor, etc)
     *
     * @param  string $path    The path to search
     * @return array|null      The array of SplFileInfo objects or null
     */
    protected function findAssets($path)
    {
        $searchPaths = array_merge($this->paths, $this->config->get('asset::search_paths'));

        foreach ($searchPaths as $searchPath) {

            $fullPath = base_path() . '/' . $searchPath . '/' . $path;

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
     * and 'target' suitable for file creation.
     *
     * @param  Symfony\Component\Finder\SplFileInfo|string $file
     *         The file object or the filename
     * @param  string $type         The assets type
     * @param  string $searchPath   The search path where the asset was found
     * @return array                The target paths array
     */
    protected function buildTargetPaths($file, $type, $searchPath = null)
    {
        if ($file instanceof \Symfony\Component\Finder\SplFileInfo) {
            $path = $file->getRealPath();
        } else {
            $path = '/' . $file;
        }

        $link = '/' . $this->config->get('asset::public_dir');
        $link .= str_ireplace(base_path(), '', $path);

        // replace .less extension by .css
        $link = str_ireplace('.less', '.css', $link);

        return array(
            'link' => $link,
            'target' => public_path() . $link
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

            // publish static assets right away
            if ($type === 'static' || ( ! $asset['link_resource'])) {
                $this->publishStatic($asset);
                continue;
            }

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

        // no further processing needed for static assets
        if ($type === 'static') {
            return;
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
        $filename = $this->config->get('asset::combined_' . $type);

        $assetData = $this->buildTargetPaths(
            $filename,
            $type
        );

        $assetData['contents'] = $contents;

        return $this->publishAsset($assetData, $type);
    }

    protected function publishStatic($asset)
    {
        $this->prepareAssetDirectory($asset['target']);

        return File::copy($asset['src'], $asset['target']);
    }

    protected function prepareAssetDirectory($path)
    {
        if ( ! file_exists(dirname($path))) {
            File::makeDirectory(dirname($path), 0777, true);
        }
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
        $this->prepareAssetDirectory($asset['target']);

        // create the asset file
        File::put($asset['target'], $asset['contents']);

        // add the HTML element
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
            return \Minify_CSS::minify($contents, array('preserveComments' => false));
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
    protected function getGroupNames()
    {
        return array_keys($this->registered);
    }

    protected function getPackageName($path, $searchPath)
    {
        $path = str_ireplace($searchPath, '', $path);

        $pathSegments = explode('/', $path);

        return $pathSegments[0] . '/' . $pathSegments[1];
    }
}
