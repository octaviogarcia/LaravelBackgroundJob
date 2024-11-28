<?php

namespace App\BackgroundJobs;

class AverageRandoms {
    public function run($args){ 
        $min = $args['min'] ?? 0;
        $max = $args['max'] ?? 1;
        $samples = $args['samples'] ?? 80000000;

        if($samples <= 0) throw new \Exception('Sample size must be positive integer');

        $sum = 0;
        for($s=0;$s<$samples;$s++){
           $sum += rand($min,$max);
        }

        return $sum/$samples; 
    } 
}