<?php

namespace App\BackgroundJobs;

class Fails {
    private $fails = null;
    public function run($args){
        if($this->fails === null){//Init
            $this->fails = $args['fails'] ?? 0;
        }

        if($this->fails > 0){
            $this->fails--;
            throw new \Exception('Fail!');
        }

        return 'Ok!';
    }
}