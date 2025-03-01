<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('task_histories', function (Blueprint $table) {
            $table->id();
            $table->string('old_value');
            $table->string('new_value');
            $table->foreignId('task_id')->constrained('tasks');
            $table->foreignId('changed_by')->constrained('users');
            $table->foreignId('change_type_id')->constrained('change_types');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_histories');
    }
};
