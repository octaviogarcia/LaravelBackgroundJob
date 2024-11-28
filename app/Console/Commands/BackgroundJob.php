<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackgroundJob extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:background-job {id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run a background job with an id';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {        
        $bjid = $this->argument('id');

        $bj = update_background_job_log((object)['id' => $bjid],[]);

        if(is_null($bj)){
            echo_stderr("Background Job '$bjid' not found");
            return;
        }

        runBackgroundJobMainThread($bj);
    }
}
