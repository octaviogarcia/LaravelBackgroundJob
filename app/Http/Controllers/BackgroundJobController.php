<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BackgroundJobController extends Controller
{
    static private $instance = null;
    static public function getInstance(){
        self::$instance = self::$instance ?? (new self);
        return self::$instance;
    }

    private $MAX_RUNNING_JOBS = 2;
    private $SECONDS_SLEEP_WAITING = 2;

    public function index(){
        $available_background_jobs = backgroundJobsGetAllowedClasses();
        return view('backgroundJobs',compact('available_background_jobs'));
    }

    public function runBackgroundJob(Request $request){
        Validator::make($request->all(), [
            'class' => 'required',
            'method' => 'required',
            'parameters' => ['nullable','json'],
            'tries' => ['nullable','integer','min:1'],
            'priority' => ['nullable','integer'],
        ],[],[])->after(function($v){
            if($v->errors()->any()) return;
            $d = $v->getData();
            if(!backgroundJobValidClass($d['class'])){
                return $v->errors()->add('class','Class is not valid');
            }
            if(!backgroundJobValidMethod($d['class'],$d['method'])){
                return $v->errors()->add('method','Method is not valid');
            }
        })->validate();

        $parameters = $request->parameters ?? '{}';
        $tries = $request->tries ?? 1;
        $delay_seconds = $request->delay_seconds ?? 0;
        $priority = $request->priority ?? 0;
        [$bjid,$started,$command] = runBackgroundJob($request->class,$request->method,$parameters,$tries,$delay_seconds,$priority);
        
        if($started === false){
            DB::table('background_jobs')
            ->where('id',$bjid)->delete();
            $out = "Error running {$request->class}->{$request->method}($parameters) ID $bjid";
            $out .= '<br>';
            $out .= $command;
            return $out;
        }

        $out = "Created {$request->class}->{$request->method}($parameters) ID $bjid";
        $out .= '<br>';
        $out .= $command;
        return $out;
    }

    public function getBackgroundJob(Request $request){
        Validator::make($request->all(), [
            'id' => 'required|exists:background_jobs,id',
        ],[],[])->after(function($v){})->validate();

        [$ok,$bj] = updateBackgroundJob((object)['id' => $request->id],[]);
        if($ok === false) throw $bj;

        return $bj;
    }

    public function getBackgroundJobLog(Request $request){
        Validator::make($request->all(), [
            'id' => 'required|exists:background_jobs,id',
        ],[],[])->after(function($v){})->validate();

        [$ok,$bj] = updateBackgroundJob((object)['id' => $request->id],[]);
        if($ok === false) throw $bj;

        return '<pre><code>'.file_get_contents($bj->log_file).'</code></pre>';
    }

    public function backgroundJobs(Request $request){
        return DB::table('background_jobs')
        ->orderBy('id','desc')
        ->get();
    }
}
