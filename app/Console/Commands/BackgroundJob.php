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
        $bj = DB::table('background_jobs')
        ->where('id',$bjid)->first();

        if(is_null($bj)){
            echo 'Error!';
            return;
        }

        DB::table('background_jobs')
        ->where('id',$bjid)
        ->update([
            'status'=> 'RUNNING',
            'pid' => getmypid(),
            'ran_at' => date('Y-m-d h:i:s')
        ]);

        //TODO DO WORK!

        DB::table('background_jobs')
        ->where('id',$bjid)
        ->update([
            'status'=> 'DONE',
            'exit_code' => 0,
            'done_at' => date('Y-m-d h:i:s')
        ]);

        echo 'Done!';
    }
}
