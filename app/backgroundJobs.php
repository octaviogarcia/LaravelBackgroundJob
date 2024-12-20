<?php

if(!function_exists('backgroundJobsGetAllowedClasses')){
function backgroundJobsGetAllowedClasses(){
    return [
        App\BackgroundJobs\Trivial::class,
        App\BackgroundJobs\Trivial2::class,
        App\BackgroundJobs\ThrowException::class,
        App\BackgroundJobs\Sleepy::class,
        App\BackgroundJobs\Fails::class,
        App\BackgroundJobs\AverageRandoms::class
    ];
}
}

if(!function_exists('backgroundJobsMaxRunning')){
function backgroundJobsMaxRunning(){
    return 2;
}
}

if(!function_exists('backgroundJobLogFile')){
function backgroundJobLogFile($bjid){
    return storage_path('background_jobs_errors.log');
}
}

if(!function_exists('backgroundJobFreeSpot')){
function backgroundJobFreeSpot($bj){
    $max_jobs = backgroundJobsMaxRunning();

    if(is_null($max_jobs)) return true;

    $available_free_spot = DB::table('background_jobs')
    ->where('status','=','RUNNING')
    ->count() < $max_jobs;

    if($available_free_spot === false) return false;

    $no_others_with_higer_priority = DB::table('background_jobs')
    ->where('id','<>',$bj->id)
    ->where('status','=','WAITING')
    ->where(function($q) use ($bj){
        return $q->where('priority','>',$bj->priority)
        ->orWhere(function($q2) use ($bj){
            return $q2->where('priority','=',$bj->priority)
            ->whereDateTime('created_at','<',$bj->created_at);
        });
    })->count() == 0;

    return $no_others_with_higer_priority;
}
}

if(!function_exists('backgroundJobsWaitingDelaySeconds')){
function backgroundJobsWaitingDelaySeconds(){
    return 2;
}
}

if(!function_exists('backgroundJobWaitForRunningJobs')){
function backgroundJobWaitForRunningJobs($bj){
    [$ok,$bj] = updateBackgroundJobLog($bj,[
        'status' => 'WAITING',
        'pid'    => getmypid(),
    ]);
    while(backgroundJobFreeSpot($bj) === false){//@HACK: soft guard, not atomic, should add a new table to coordinate
        sleep(backgroundJobsWaitingDelaySeconds());
    }
    return $bj;
}
}

if(!function_exists('backgroundJobValidClass')){
function backgroundJobValidClass(string $class){
    $valid_class_name = preg_match('/^(?:\\\\?[A-Za-z_][A-Za-z0-9_]*)+$/',$class) == 1;
    $allowed_classes = backgroundJobsGetAllowedClasses();
    $class_is_allowed = $allowed_classes === null || in_array($class,$allowed_classes);
    return $valid_class_name && $class_is_allowed && class_exists($class);
}
}

if(!function_exists('backgroundJobValidMethod')){
function backgroundJobValidMethod(string $class,string $method){
    if(!backgroundJobValidClass($class)) return false;
    $valid_method_name = preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/',$method) == 1;
    $method_exists = method_exists($class,$method);
    return $valid_method_name && $method_exists;
}
}

if (!function_exists('executeBackgroundJob')){
function executeBackgroundJob($bj) {
    $bjid = $bj->id;
    $log_file = $bj->log_file;

    $php = escapeshellarg(PHP_BINARY);
    $artisan  = escapeshellarg(base_path('artisan'));
    $log_file = escapeshellarg($log_file);

    $final_command = null;
    if(strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'){
        $final_command = "start /B \"\" $php $artisan app:background-job $bjid 2>&1";
    }
    else{
        $final_command = "$php $artisan app:background-job $bjid 2>&1 &";
    }
    
    $pipes = null;
    $P = proc_open(
        $final_command,
        [0=> ['pipe','r'],1 => ['pipe','w'],2 => ['pipe','w']],
        $pipes //What to do with pipes?
    );

    return [$bjid,$P !== false,$final_command];
};
}

if (!function_exists('runBackgroundJob')){
function runBackgroundJob(string $class,string $method,string $parameters,?int $tries,int $delay_seconds,int $priority){
    if(backgroundJobValidMethod($class,$method) === false){
        throw new \Exception("$class::$method is not runnable");
    }

    $created_at = date('Y-m-d h:i:s');
    $bjid = DB::table('background_jobs')
    ->insertGetId([
        'class' => $class,
        'method' => $method,
        'parameters' => $parameters,
        'status' => 'CREATED',
        'tries' => $tries,
        'delay_seconds' => $delay_seconds,
        'priority' => $priority,
        'pid' => null,
        'exit_code' => null,
        'created_at' =>  $created_at,
        'ran_at' => null,
        'done_at' => null,
    ]);

    $log_file = backgroundJobLogFile($bjid);

    [$ok,$bj] = updateBackgroundJob((object)['id' => $bjid],compact('log_file'));

    if($ok === false) throw $bj;

    return executeBackgroundJob($bj);
}
}
if (!function_exists('cancelBackgroundJobByID')){
function cancelBackgroundJobByID($bjid){
    $bj = getBackgroundJob($bjid);
    if($bj === null)
        return [false,null];
    if(in_array($bj->status,['CREATED','DONE','KILLED','ERROR']))
        return [true,$bj];

    if($bj->pid !== null){
        $final_command = null;
        if(strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'){
            $final_command = "taskkill /pid {$bj->pid} /f";
        }
        else{
            $final_command = "kill -9 {$bj->pid}";
        }
        echoFile($bj->log_file,"\r\nExecuting: ".$final_command);
        echoFile($bj->log_file,"\r\nOutput: ".shell_exec($final_command));
    }

    return updateBackgroundJobLog($bj,['status' => 'KILLED'],$bj->log_file,false);
}
}


if (!function_exists('updateBackgroundJob')){
function updateBackgroundJob($bj,$data){
    try{
        $affected = DB::table('background_jobs')
        ->where('id',$bj->id)
        ->update($data);

        if($affected === 0) throw new \Exception("BackgroundJob ID = {$bj->id} not found");

        return [true,getBackgroundJob($bj->id)];
    }
    catch(\Exception $e){//Error accesing DB
        return [false,$e];
    }
}
}

if (!function_exists('echoFile')){
function echoFile($fpath,string $string){
    $f = fopen($fpath,'a');
    fwrite($f,"\r\n");
    fwrite($f,date('Y-m-d h:i:s'));
    fwrite($f,"\r\n");
    fwrite($f,$string);
    fclose($f);
}
}

if (!function_exists('updateBackgroundJobLog')){
function updateBackgroundJobLog($bj,$data,$exit_on_fail = true){
    [$ok,$bj_or_ex] = updateBackgroundJob($bj,$data);

    echoFile($bj->log_file,"New value for BackgroundJob = ".$bj->id);
    if($ok === true){
        echoFile($bj->log_file,json_encode($bj_or_ex));
    }
    else{
        echoFile($bj->log_file,"Error setting values");
        echoFile($bj->log_file,json_encode($data));
        echoFile($bj->log_file,$bj_or_ex->getTraceAsString());
        if($exit_on_fail){
            exit(1);
        }
    }

    return [$ok,$bj_or_ex];
}
}

if (!function_exists('getBackgroundJob')){
function getBackgroundJob($bjid){
    return DB::table('background_jobs')->where('id',$bjid)->first();
}
}


if (!function_exists('deleteBackgroundJob')){
function deleteBackgroundJob($bjid){
    return DB::table('background_jobs')
    ->where('id',$bjid)->delete() > 0;
}
}


if(!function_exists('runBackgroundJobMainThread')){
function runBackgroundJobMainThread($bj){
    if($bj->delay_seconds > 0){
        [$ok,$bj] = updateBackgroundJobLog($bj,[
            'status' => 'WAITING',
            'pid'    => getmypid(),
            'ran_at' => date('Y-m-d h:i:s')
        ]);
        sleep($bj->delay_seconds);
    }

    $bj = backgroundJobWaitForRunningJobs($bj);

    [$ok,$bj] = updateBackgroundJobLog($bj,[
        'status' => 'RUNNING',
        'pid'    => getmypid(),
        'ran_at' => date('Y-m-d h:i:s')
    ]);

    $obj = new $bj->class;
    $parameters = json_decode($bj->parameters ?? '{}',true);

    while($bj->tries > 0 || $bj->tries === null){
        try{
            $output = $obj->{$bj->method}($parameters,$bj);

            [$ok,$bj] = updateBackgroundJobLog($bj,[
                'status'=> 'DONE',
                'tries' => $bj->tries !== null? ($bj->tries-1) : null,
                'exit_code' => 0,
                'output' => json_encode($output),
                'done_at' => date('Y-m-d h:i:s')
            ]);

            return $bj;
        }
        catch(\Exception $e){
            echoFile($bj->log_file,"\r\nFailed try #{$bj->tries}");
            echoFile($bj->log_file,"\r\n$e->getTraceAsString()");

            if($bj->tries === null) continue;

            $bj->tries--;
            if($bj->tries <= 0){
                [$ok,$bj] = updateBackgroundJobLog($bj,[
                    'status'=> 'ERROR',
                    'tries' => 0,
                    'exit_code' => 1,
                    'done_at' => date('Y-m-d h:i:s')
                ]);
                exit(1);
            }
            else{
                [$ok,$bj] = updateBackgroundJobLog($bj,[
                    'tries' => $bj->tries
                ]);
            }
        }
    }
}
}