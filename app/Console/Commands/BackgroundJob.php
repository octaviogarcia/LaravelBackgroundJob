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

    private function echo_stderr(string $string){
        $f = fopen('php://stderr','a');
        fwrite($f,$string);
        fclose($f);
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {        
        $bjid = $this->argument('id');
        $bj = DB::table('background_jobs')
        ->where('id',$bjid)->first();

        if(is_null($bj)){
            $this->echo_stderr("Background Job '$bjid' not found");
            return;
        }

        DB::table('background_jobs')
        ->where('id',$bjid)
        ->update([
            'status'=> 'RUNNING',
            'pid' => getmypid(),
            'ran_at' => date('Y-m-d h:i:s')
        ]);

        try{
            $obj = new $bj->class;
            $output = $obj->{$bj->method}(json_decode($bj->parameters ?? '{}',true));
            echo json_encode($output);

            DB::table('background_jobs')
            ->where('id',$bjid)
            ->update([
                'status'=> 'DONE',
                'exit_code' => 0,
                'done_at' => date('Y-m-d h:i:s')
            ]);
        }
        catch(\Exception $e){
            $this->echo_stderr($e->getTraceAsString());
            DB::table('background_jobs')
            ->where('id',$bjid)
            ->update([
                'status'=> 'ERROR',
                'exit_code' => 1,
                'done_at' => date('Y-m-d h:i:s')
            ]);
        }
    }
}
