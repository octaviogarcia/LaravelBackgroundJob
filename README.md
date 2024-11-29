## About

This is a test task for a recruitment. It's the implementation of a background job system in PHP without using Laravel's queue.

## Implementation

This implementation uses process forking. I have found that other solutions such as supervisord/cron tend to break in production.

## Using the application

1- Git clone the application from Github

2- Install PHP, Composer and Laravel following [these instructions](https://laravel.com/docs/11.x#installing-php)  
    
  NOTE: If you are on windows, change "'https://php.new/install/windows/8.3" to "'https://php.new/install/windows" in the installation command.  
  
  Also install [NPM](https://www.npmjs.com/) If you don't have it (check with "npm --version")

3- Open the project in your command line and input these commands

    npm install

    npm run build

    composer install

    cp .env.example .env

    php artisan key:generate

    php artisan migrate

    composer run dev

4- Go to http://localhost:8000 in your browser

5- You'll see on the left a table with background jobs (empty) and a panel with inputs on the right. 

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
    This is similitar to Sleepy::run but it does an actual calculation. It averages rand(<min>,<max>) with <samples> calls.
    With default values one would expect a return close to ~0.5

## Implementation Architecture
Frontend: a single blade template (resources/views/backgroundJobs.blade.php) with inline javascript.

Panel Backend: routing in routes/web.php, and handlers for those routes in app/Http/Controllers/BackgroundJobController.php

Background Jobs Runner: Since the implementation architecture wasn't specified I went as simple as posible:
  - app/backgroundJobs.php : All functions that engine the application.  
  You can "overload" these by adding a file in your composer.json such that it loads before.
  Useful if you want to change things such as allowed classes or maximum jobs allowed to be running.

  - app/Console/BackgroundJob.php : This Laravel command acts as a proxy such that you can use a full Laravel environment in your job.

  - app/BackgroundJobs: this is merely for exemplification. If you are overloading the allowed classes you may put them wherever you want as long as you reference them correctly.  
  If you return NULL in your implementation, all clases are allowed.

Global functions defined (that may also be overloaded):

  - backgroundJobsGetAllowedClasses(): array  
    Returns an array of allowed classes

  - backgroundJobsMaxRunning(): int  
    Maximum jobs to be running at the same time. Currently "2" (easier for testing). NULL for infinite

  - backgroundJobLogFile(BackgroundJobId): string  
    Path to a file where all logging will be appended.

  - backgroundJobFreeSpot(): bool  
    Logic for waiting/running

  - backgroundJobsWaitingDelaySeconds(): int  
    Seconds to wait between retries for free spot.

  - backgroundJobWaitForRunningJobs(BackgroundJob): BackgroundJob  
    Hangs the job until it finds a free spot

  - backgroundJobValidClass(string $class): bool  
    Validates that a class is valid (only used in frontend)

  - backgroundJobValidMethod(string $class,string $method): bool  
    Validates that a class and a method are valid (only used in frontend)

  - executeBackgroundJob(BackgroundJob): [int,bool,string]  
    Executes a background job (forks into a background-job Laravel command).  
    Returns: [backgroundJobId,executed,output]

  - **runBackgroundJob(string $class,string $method,string $parameters,?int $tries,int $delay_seconds,int $priority)**  
    Returns: [backgroundJobId,executed,output]  
    Creates a background job with the argument data and sets it off to run.

  - cancelBackgroundJobByID(BackgroundJobId): [bool,BackgroundJob|Exception]
    Returns: [true,BackgroundJob] or [false,Exception]  
    Kills the job if it's running and update its status if it wasn't finished

  - updateBackgroundJob(BackGroundjob,array $data) : [bool,BackgroundJob|Exception]  
    Returns: [true,BackgroundJob] or [false,Exception]  
    Updates the BackGroundjob with the attributes in $data and returns it

  - echoFile(string $path,string $output): void  
    Outputs to file indicated in path with timestamps, used for logging

  - updateBackgroundJobLog(BackGroundjob,array $data,bool $exit_on_fail = true) : [bool,BackgroundJob|Exception] 
    Updates the BackGroundjob with the attributes in $data. By default, if an error occurs,  
    it logs into its log_file and exits with code 1.
  
  - getBackgroundJob(BackgroundJobId): ?BackgroundJob
    Returns a background job (if it finds it)

  - deleteBackgroundJob(BackgroundJobId): bool
    Deletes a background job. Returns false if it doesn't find anything

  - runBackgroundJobMainThread(BackGroundjob) : void  
    Runs the background job, used after forking but It might also be used by the main Laravel application

## Example Runs

- To check that everything is working. It should add a job that instantly finishes.

    class = App\BackgroundJobs\Trivial
    method = run
    parameters (empty)
    tries (empty)
    delay (empty)
    priority (empty)

- To check that it handles exceptions. The job should error out.

    class = App\BackgroundJobs\ThrowException
    method = run
    parameters (empty)
    tries 1
    delay (empty)
    priority (empty)

- To check that it handles delays. The job should have a diference of 5 seconds between created and ran 

    class = App\BackgroundJobs\Trivial
    method = run
    parameters (empty)
    tries (empty)
    delay 5
    priority (empty)

- To check its Queue system. Since the default Queue size is 2, we are going to overflow it.
  You should see a waiting job.

    class = App\BackgroundJobs\Sleepy
    method = run
    parameters = {"seconds" : 15}
    tries 1
    delay (empty)
    priority (empty)

    class = App\BackgroundJobs\Sleepy
    method = run
    parameters = {"seconds" : 15}
    tries 1
    delay (empty)
    priority (empty)

    class = App\BackgroundJobs\Sleepy
    method = run
    parameters = {"seconds" : 15}
    tries 1
    delay (empty)
    priority (empty)

- To check its Priority system. Since the default Queue size is 2, we are going to overflow it, but with different priorities.
  You should see a waiting job that has been created (4th one) later run before the other (3rd one).

    class = App\BackgroundJobs\Sleepy
    method = run
    parameters = {"seconds" : 15}
    tries 1
    delay (empty)
    priority (empty)

    class = App\BackgroundJobs\Sleepy
    method = run
    parameters = {"seconds" : 15}
    tries 1
    delay (empty)
    priority (empty)

    class = App\BackgroundJobs\Sleepy
    method = run
    parameters = {"seconds" : 15}
    tries 1
    delay (empty)
    priority (empty)

    class = App\BackgroundJobs\Sleepy
    method = run
    parameters = {"seconds" : 15}
    tries 1
    delay (empty)
    priority = 10


## License
  This code is licensed under the [Mozilla Public License Version 2.0](https://www.mozilla.org/media/MPL/2.0/index.f75d2927d3c1.txt)
