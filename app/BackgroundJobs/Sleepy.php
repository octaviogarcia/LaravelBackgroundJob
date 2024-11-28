<?php

namespace App\BackgroundJobs;

class Sleepy {
    public function run($args){ 
        $seconds = ($args ?? [])['seconds'] ?? 10;
        sleep($seconds); 
        return $seconds;
    }
}