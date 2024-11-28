<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('background_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('class');
            $table->string('method');
            $table->json('parameters');
            $table->enum('status',['CREATED','WAITING','RUNNING','DONE','KILLED','ERROR']);
            $table->integer('pid')->nullable();
            $table->integer('exit_code')->nullable();
            $table->string('log_file')->nullable();
            $table->string('error_file')->nullable();
            $table->string('created_at');
            $table->string('ran_at')->nullable();
            $table->string('done_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('background_jobs');
    }
};
