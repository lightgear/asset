<?php namespace Lightgear\Asset\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class Clean extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'asset:clean';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Delete existing published assets";

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function fire()
    {
        $asset = \App::make('asset');
        $asset->clean();

        $this->line('Cleaned up all published assets.');
    }

}
