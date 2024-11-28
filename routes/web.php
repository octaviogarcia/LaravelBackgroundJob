<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\BackgroundJobs;

$available_background_jobs = [
   App\BackgroundJobs\FibonacciCalculator::class,
   App\BackgroundJobs\FibonacciCalculator2::class,
];

function runBackgroundJobById($id,$log_file,$err_file){
    $php = escapeshellarg(PHP_BINARY);
    $artisan  = escapeshellarg(base_path('artisan'));
    $log_file = escapeshellarg($log_file);
    $err_file = escapeshellarg($err_file);

    $final_command = null;
    if(strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'){
        $final_command = "start /B \"\" $php $artisan app:background-job $id > $log_file 2> $err_file";
    }
    else{
        $final_command = "$php $artisan app:background-job $id > $log_file 2> $err_file &";
    }
    
    $output = null;
    $exit_code = null;
    $ran = exec($final_command,$output,$exit_code);

    return [$ran !== false,$final_command.'<br>'.implode('<br>',$output)];
};

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

    $log_file = storage_path(uniqid().'.log');
    $err_file = storage_path(uniqid().'.err');
    $bjid = DB::table('background_jobs')
    ->insertGetId([
        'class' => $request->class,
        'method' => $request->method,
        'parameters' => $request->parameters,
        'status' => 'CREATED',
        'pid' => null,
        'exit_code' => null,
        'log_file' => $log_file,
        'error_file' => $err_file,
        'created_at' =>  date('Y-m-d h:i:s'),
        'ran_at' => null,
        'done_at' => null,
    ]);

    [$started,$output] = runBackgroundJobById($bjid,$log_file,$err_file);

    if($started === false){
        DB::table('background_jobs')
        ->where('id',$bjid)->delete();
        return 'Error '.$output;
    }

    return 'Created And Started out: '.$output;
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