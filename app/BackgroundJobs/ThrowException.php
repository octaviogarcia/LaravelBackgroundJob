<?php

namespace App\BackgroundJobs;

class ThrowException {
    public function run($args){ throw new \Exception('TEST'); } 
    public function run2($args){ throw new \Exception('TEST2'); } 
    public function args($args){ return $args; } 
}