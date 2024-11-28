## About

This is a test task for a recruitment. It's the implementation of a background job system in PHP without using Laravel's queue.

## Implementation

This implementation uses process forking. I have found that other solutions such as supervisord/cron tend to break in production.

## Using the application

1- Git clone the application from Github

2- Install PHP, Composer and Laravel following [these instructions](https://laravel.com/docs/11.x#installing-php)
    NOTE: If you are on windows, change "'https://php.new/install/windows/8.3" to "'https://php.new/install/windows" in the installation command.

3- Open the project in your command line and input these commands
    npm install && npm run build
    
    composer run dev

4- Go to http://localhost:8000 in your browser

5- You'll see on the left and a panel with inputs on the right. 
    Class -> Class to use in the background job.
    Method -> Method to use in the background job.
    Parameters -> Parameters sent to Class::method, must be valid JSON.
    Tries -> Maximum tries for failing jobs. Defaults to 1.  
        If you leave it empty, it defaults to infinite tries.
    Delay -> Delay in seconds until the job starts running.
    Priority -> When jobs are waiting on Queue, those with higher priority will start running first.

6- These are the classes and methods
    Trivial::run -> doesn't use arguments. Immediately returns 1.

    Trivial2::run -> doesn't use arguments. Immediately returns 2.

    ThrowException::run -> Throws an exception such to make the job fail. 

    ThrowException::run2 -> Throws another exception such to make the job fail.

    ThrowException::args -> Returns the arguments immediately.

    Sleepy::run -> Uses argument in the form of {"seconds" : YOUR_SECONDS_HERE}.  
    If it doesn't find the correct JSON argument, it defaults to 10 seconds.
    This is used to simulate a long running job.

    Fails::run ->  Uses argument in the form of {"fails" : YOUR_FAILS_HERE}.
    If it doesn't find the correct JSON argument, it defaults to 0 fails (no fails).
    This is used to simulate a failing application, such that you need to configure tries.

    AverageRandoms::run -> Uses argument in the form of {"min" : YOUR_MIN,"max": YOUR_MAX, "samples": YOUR_SAMPLES}
    Uses default values of min=0, max=1, samples =  80000000.
    This is similitar to Sleepy::run but it does an actual calculation. It averages #samle rand(min,max) calls.
    With default values one would expect a return close to ~0.5

## License
  This code is licensed under the [Mozilla Public License Version 2.0](https://www.mozilla.org/media/MPL/2.0/index.f75d2927d3c1.txt)