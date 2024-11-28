<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\BackgroundJobs;

$available_background_jobs = [
   App\BackgroundJobs\Trivial::class,
   App\BackgroundJobs\Trivial2::class,
   App\BackgroundJobs\ThrowException::class,
];

function runBackgroundJobById($bjid){
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
    
    $output = null;
    $exit_code = null;
    $ran = exec($final_command,$output,$exit_code);

    return [$bjid,$ran !== false,$final_command,$output];
};

function runBackgroundJob(string $class,string $method,string $parameters){
    $created_at = date('Y-m-d h:i:s');
    $bjid = DB::table('background_jobs')
    ->insertGetId([
        'class' => $class,
        'method' => $method,
        'parameters' => $parameters,
        'status' => 'CREATED',
        'pid' => null,
        'exit_code' => null,
        'log_file' => null,
        'error_file' => null,
        'created_at' =>  $created_at,
        'ran_at' => null,
        'done_at' => null,
    ]);

    $uniqid = uniqid();
    //$filename = storage_path("$bjid|$class-$method-$uniqid");//@HACK: "posible" name clash
    $filename = storage_path("$bjid-$uniqid");//@HACK: "posible" name clash

    DB::table('background_jobs')
    ->where('id',$bjid)
    ->update([
        'log_file' => $filename.'.log',
        'error_file' => $filename.'.err',
    ]);

    return runBackgroundJobById($bjid);
}

Route::get('/', function () use ($available_background_jobs){
    return view('backgroundJobs',compact('available_background_jobs'));
});

use Illuminate\Support\Facades\DB;

Route::post('/runBackgroundJob',function(Request $request) use ($available_background_jobs){
    $identifier_regex = '[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*';
    $method_regex = '/^'.$identifier_regex.'$/';
    $class_regex = '/^.*$/';//@TODO
    Validator::make($request->all(), [
        'class' => ['required','regex:'.$class_regex],
        'method' => ['required','regex:'.$method_regex],
        'parameters' => ['required','json'],
    ],[],[])->after(function($v) use ($available_background_jobs){
        if($v->errors()->any()) return;
        $d = $v->getData();
        if(!in_array($d['class'],$available_background_jobs)){
            return $v->errors()->add('class','Class is not in the valid list.');
        }
        if(class_exists($d['class']) === false){
            return $v->errors()->add('class','Class doesn\'t exists');
        }
        if(method_exists($d['class'],$d['method']) === false){
            return $v->errors()->add('class','Method doesn\'t exists');
        }
    })->validate();

    [$bjid,$started,$command,$output] = runBackgroundJob($request->class,$request->method,$request->parameters);
    
    if($started === false){
        DB::table('background_jobs')
        ->where('id',$bjid)->delete();
        $out = "Error running {$request->class}->{$request->method}({$request->params}) ID $bjid";
        $out .= '<br>';
        $out .= $command;
        $out .= '<br>';
        $out .= implode('<br>',$output);
        return $out;
    }

    $out = "Created {$request->class}->{$request->method}({$request->params}) ID $bjid";
    $out .= '<br>';
    $out .= $command;
    $out .= '<br>';
    $out .= implode('<br>',$output);
    return $out;
});

Route::get('/backgroundJobs',function(Request $request){
    return DB::table('background_jobs')
    ->orderBy('id','desc')
    ->get();
});


Route::get('/phpinfo', function () {
    return phpinfo();
});

//This works!
Route::get('/debugWindows',function(){
    $php = escapeshellarg(PHP_BINARY);
    $artisan = escapeshellarg(base_path('artisan'));
    $command = "start /B \"\" $php $artisan app:background-job 38";
    $output = exec($command);
    return $command.'<br>'.htmlspecialchars($output);
});

//To be tested
Route::get('/debugUnix',function(){
    $php = escapeshellarg(PHP_BINARY);
    $artisan = escapeshellarg(base_path('artisan'));
    $command = "$php $artisan app:background-job 38 &";
    $output = exec($command);
    return $command.'<br>'.htmlspecialchars($output);
});

Route::get('/ByClassTest',function(){
    $ret = runBackgroundJob(App\BackgroundJobs\ThrowException::class,'args',json_encode([1,2,3,4]));
    $bjid = $ret[0];
    $bj = DB::table('background_jobs')
    ->where('id',$bjid)->first();
    if($bj === null) throw new \Exception('Error creating job');

    $seconds_sleep = 2;
    $max_wait = 20;
    while($bj->status != 'DONE' && $bj->status != 'ERROR' && $bj->status != 'KILLED'){
        if($max_wait <= 0){
            throw new \Exception('Timeout waiting job '.$bjid);
        }

        sleep($seconds_sleep);
        $max_wait -= $seconds_sleep;

        $bj = DB::table('background_jobs')
        ->where('id',$bjid)->first();
    }

    return file_get_contents($bj->log_file);
});