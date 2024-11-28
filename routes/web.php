<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

Route::get('/','App\Http\Controllers\BackgroundJobController@index');
Route::post('/runBackgroundJob','App\Http\Controllers\BackgroundJobController@runBackgroundJob');
Route::get('/backgroundJobs','App\Http\Controllers\BackgroundJobController@backgroundJobs');
Route::get('/phpinfo',function(){return phpinfo();});