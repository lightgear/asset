<?php namespace Lightgear\Asset;

class Asset {

    protected $stylesheets = array();

    protected $scripts = array();

    public function registerScripts()
    {
        return $this;
    }

    public function registerStyles()
    {
        return $this;
    }

    public function styles()
    {
        return 'styles...';
    }

    public function scripts()
    {
        return 'scripts..';
    }
}
