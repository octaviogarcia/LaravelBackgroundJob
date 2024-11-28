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

    private function update_background_job_log($bjid,$data){
        [$ok,$bj_or_ex] = update_background_job($bjid,$data);

        $this->echo_stderr("\r\n");
        $this->echo_stderr("\r\nNew value for BackgroundJob = ".$bjid);
        if($ok === true){
            $this->echo_stderr("\r\nOk");
            $this->echo_stderr("\r\n");
            $this->echo_stderr(json_encode($bj_or_ex));
        }
        else{
            $this->echo_stderr("\r\nError setting values");
            $this->echo_stderr(json_encode($data));
            $this->echo_stderr("\r\n");
            $this->echo_stderr($bj_or_ex->getTraceAsString());
            $this->echo_stderr("\r\n");
            exit(1);
        }

        return $bj_or_ex;
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {        
        $bjid = $this->argument('id');
        $bj = $this->update_background_job_log($bjid,[]);

        if(is_null($bj)){
            $this->echo_stderr("Background Job '$bjid' not found");
            return;
        }

        if($bj->delay_seconds > 0){
            $bj = $this->update_background_job_log($bjid,[
                'status' => 'WAITING',
                'pid'    => getmypid(),
                'ran_at' => date('Y-m-d h:i:s')
            ]);
            sleep($bj->delay_seconds);
        }

        $bj = $this->update_background_job_log($bjid,[
            'status' => 'RUNNING',
            'pid'    => getmypid(),
            'ran_at' => date('Y-m-d h:i:s')
        ]);

        $obj = new $bj->class;
        $parameters = json_decode($bj->parameters ?? '{}',true);

        while($bj->tries > 0 || $bj->tries === null){
            try{
                $output = $obj->{$bj->method}($parameters,$bj);
                echo json_encode($output);

                $bj = $this->update_background_job_log($bjid,[
                    'status'=> 'DONE',
                    'tries' => $bj->tries !== null? ($bj->tries-1) : null,
                    'exit_code' => 0,
                    'done_at' => date('Y-m-d h:i:s')
                ]);

                return;
            }
            catch(\Exception $e){
                $this->echo_stderr("\r\n");
                $this->echo_stderr("\r\nFailed try #{$bj->tries}\r\n");
                $this->echo_stderr($e->getTraceAsString());

                if($bj->tries === null) continue;

                $bj->tries--;
                if($bj->tries <= 0){
                    $bj = $this->update_background_job_log($bjid,[
                        'status'=> 'ERROR',
                        'tries' => 0,
                        'exit_code' => 1,
                        'done_at' => date('Y-m-d h:i:s')
                    ]);
                    exit(1);
                }
                else{
                    $bj = $this->update_background_job_log($bjid,[
                        'tries' => $bj->tries
                    ]);
                }
            }
        }
    }
}
