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
        $tries = $request->tries ?? null;//null = infinite tries
        $delay_seconds = $request->delay_seconds ?? 0;
        [$bjid,$started,$command,$output] = runBackgroundJob($request->class,$request->method,$parameters,$tries,$delay_seconds);
        
        if($started === false){
            DB::table('background_jobs')
            ->where('id',$bjid)->delete();
            $out = "Error running {$request->class}->{$request->method}($parameters) ID $bjid";
            $out .= '<br>';
            $out .= $command;
            $out .= '<br>';
            $out .= implode('<br>',$output);
            return $out;
        }

        $out = "Created {$request->class}->{$request->method}($parameters) ID $bjid";
        $out .= '<br>';
        $out .= $command;
        $out .= '<br>';
        $out .= implode('<br>',$output);
        return $out;
    }

    public function backgroundJobs(Request $request){
        return DB::table('background_jobs')
        ->orderBy('id','desc')
        ->get();
    }
}
