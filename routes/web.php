<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

Route::get('/','App\Http\Controllers\BackgroundJobController@index');
Route::post('/runBackgroundJob','App\Http\Controllers\BackgroundJobController@runBackgroundJob');
Route::get('/backgroundJobs','App\Http\Controllers\BackgroundJobController@backgroundJobs');
Route::get('/getBackgroundJob','App\Http\Controllers\BackgroundJobController@getBackgroundJob');
Route::get('/getBackgroundJobLog','App\Http\Controllers\BackgroundJobController@getBackgroundJobLog');
Route::get('/cancelBackgroundJob','App\Http\Controllers\BackgroundJobController@cancelBackgroundJob');
Route::get('/phpinfo',function(){return phpinfo();});