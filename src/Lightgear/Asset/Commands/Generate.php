<?php namespace Lightgear\Asset\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class Generate extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'asset:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Generate and publish the registered assets";

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function fire()
    {
        $asset = \App::make('asset');
        $asset->styles();
        $asset->scripts();

        $this->line('Generated and published assets');
    }

}
