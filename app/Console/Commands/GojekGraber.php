<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;

use App\Libs\Cli\Graber\GojekGraber as Gojek;
use App\Libs\Cli\Graber\GojekNotifier;

class GojekGraber extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'graber:gojek';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Gojek Graber Tools :D";

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function fire()
    {
        $type = $this->option('type');

        $this->info("--- START ---");

        switch ($type) {
            case 'notifier':

                $notifier = new GojekNotifier;
                $notifier->sendNotification();

                break;
            
            default:

                $gojek = new Gojek();
                $gojek->check();

                break;
        }

        $this->info("--- DONE ---");
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array(
            array('type', null, InputOption::VALUE_OPTIONAL, 'Type of the command', 'graber'),
        );
    }

}