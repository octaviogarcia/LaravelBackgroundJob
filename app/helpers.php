<?php

if(!function_exists('backgroundJobsGetAllowedClasses')){
function backgroundJobsGetAllowedClasses(){
    return [
        App\BackgroundJobs\Trivial::class,
        App\BackgroundJobs\Trivial2::class,
        App\BackgroundJobs\ThrowException::class,
        App\BackgroundJobs\Sleepy::class,
        App\BackgroundJobs\Fails::class,
    ];
}
}

if(!function_exists('backgroundJobsMaxRunning')){
function backgroundJobsMaxRunning(){
    return 1;
}
}

if(!function_exists('backgroundJobFreeSpot')){
function backgroundJobFreeSpot($bjid){
    return DB::table('background_jobs')
    ->where('status','=','RUNNING')
    ->count() < backgroundJobsMaxRunning();
}
}

if(!function_exists('backgroundJobsWaitingDelaySeconds')){
function backgroundJobsWaitingDelaySeconds(){
    return 5;
}
}

if(!function_exists('backgroundJobWaitForRunningJobs')){
function backgroundJobWaitForRunningJobs($bjid){
    while(backgroundJobFreeSpot($bjid) === false){//@HACK: soft guard, not atomic, should add a new table too coordinate
        update_background_job_log($bjid,[
            'status' => 'WAITING',
            'pid'    => getmypid(),
            'ran_at' => date('Y-m-d h:i:s')
        ]);
        sleep(backgroundJobsWaitingDelaySeconds());
    }
}
}


if(!function_exists('backgroundJobValidClass')){
function backgroundJobValidClass(string $class){
    $valid_class_name = preg_match('/^.*$/',$class) == 1;//@TODO
    $allowed_classes = backgroundJobsGetAllowedClasses();
    $class_is_allowed = $allowed_classes === null || in_array($class,$allowed_classes);
    return $valid_class_name && $class_is_allowed;
}
}

if(!function_exists('backgroundJobValidMethod')){
function backgroundJobValidMethod(string $class,string $method){
    if(!backgroundJobValidClass($class)) return false;
    $identifier_regex = '[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*';
    $method_regex = '/^'.$identifier_regex.'$/';
    $valid_method_name = preg_match($method_regex,$method) == 1;
    $method_exists = method_exists($class,$method);
    return $valid_method_name && $method_exists;
}
}

if (!function_exists('runBackgroundJobById')){
function runBackgroundJobById($bjid) {
    $bj = DB::table('background_jobs')
    ->where('id',$bjid)
    ->first();

    $log_file = $bj->log_file;
    $error_file = $bj->error_file;

    $php = escapeshellarg(PHP_BINARY);
    $artisan  = escapeshellarg(base_path('artisan'));
    $log_file = escapeshellarg($log_file);
    $error_file	 = escapeshellarg($error_file);

    $final_command = null;
    if(strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'){
        $final_command = "start /B \"\" $php $artisan app:background-job $bjid > $log_file 2> $error_file";
    }
    else{
        $final_command = "$php $artisan app:background-job $bjid > $log_file 2> $error_file &";
    }
    
    $pipes = null;
    $P = proc_open(
        $final_command,
        [0=> ['pipe','r'],1 => ['pipe','w'],2 => ['pipe','w']],
        $pipes //What to do with pipes?
    );

    return [$bjid,$P !== false,$final_command,[]];
};
}

if (!function_exists('runBackgroundJob')){
function runBackgroundJob(string $class,string $method,string $parameters,?int $tries,int $delay_seconds){
    $created_at = date('Y-m-d h:i:s');
    $bjid = DB::table('background_jobs')
    ->insertGetId([
        'class' => $class,
        'method' => $method,
        'parameters' => $parameters,
        'status' => 'CREATED',
        'tries' => $tries,
        'delay_seconds' => $delay_seconds,
        'pid' => null,
        'exit_code' => null,
        'log_file' => null,
        'error_file' => null,
        'created_at' =>  $created_at,
        'ran_at' => null,
        'done_at' => null,
    ]);

    $uniqid = uniqid();
    $filename = storage_path("$bjid-$uniqid");//@HACK: "posible" name clash

    DB::table('background_jobs')
    ->where('id',$bjid)
    ->update([
        'log_file' => $filename.'.log',
        'error_file' => $filename.'.err',
    ]);

    return runBackgroundJobById($bjid);
}
}

if (!function_exists('update_background_job')){
function update_background_job($bjid,$data){
    if(empty($data)) return [true,DB::table('background_jobs')->where('id',$bjid)->first()];

    try{
        DB::table('background_jobs')
        ->where('id',$bjid)
        ->update($data);

        return [true,DB::table('background_jobs')->where('id',$bjid)->first()];
    }
    catch(\Exception $e){//Error accesing DB
        return [false,$e];
    }
}
}

if (!function_exists('echo_stderr')){
function echo_stderr(string $string){
    $f = fopen('php://stderr','a');
    fwrite($f,"\r\n");
    fwrite($f,date('Y-m-d h:i:s'));
    fwrite($f,"\r\n");
    fwrite($f,$string);
    fclose($f);
}
}

if (!function_exists('update_background_job_log')){
function update_background_job_log($bjid,$data){
    [$ok,$bj_or_ex] = update_background_job($bjid,$data);

    echo_stderr("New value for BackgroundJob = ".$bjid);
    if($ok === true){
        echo_stderr(json_encode($bj_or_ex));
    }
    else{
        echo_stderr("Error setting values");
        echo_stderr(json_encode($data));
        echo_stderr($bj_or_ex->getTraceAsString());
        exit(1);
    }

    return $bj_or_ex;
}
}

if(!function_exists('runBackgroundJobMainThread')){
function runBackgroundJobMainThread($bjid){
    $bj = update_background_job_log($bjid,[]);

    if($bj->delay_seconds > 0){
        $bj = update_background_job_log($bjid,[
            'status' => 'WAITING',
            'pid'    => getmypid(),
            'ran_at' => date('Y-m-d h:i:s')
        ]);
        sleep($bj->delay_seconds);
    }

    backgroundJobWaitForRunningJobs($bjid);

    $bj = update_background_job_log($bjid,[
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

            $bj = update_background_job_log($bjid,[
                'status'=> 'DONE',
                'tries' => $bj->tries !== null? ($bj->tries-1) : null,
                'exit_code' => 0,
                'done_at' => date('Y-m-d h:i:s')
            ]);

            return;
        }
        catch(\Exception $e){
            echo_stderr("Failed try #{$bj->tries}\r\n");
            echo_stderr($e->getTraceAsString());

            if($bj->tries === null) continue;

            $bj->tries--;
            if($bj->tries <= 0){
                $bj = update_background_job_log($bjid,[
                    'status'=> 'ERROR',
                    'tries' => 0,
                    'exit_code' => 1,
                    'done_at' => date('Y-m-d h:i:s')
                ]);
                exit(1);
            }
            else{
                $bj = update_background_job_log($bjid,[
                    'tries' => $bj->tries
                ]);
            }
        }
    }
}
}