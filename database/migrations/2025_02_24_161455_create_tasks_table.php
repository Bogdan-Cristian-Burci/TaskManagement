<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description');
            $table->foreignId('project_id')->constrained('projects');
            $table->foreignId('board_id')->constrained('boards');
            $table->foreignId('board_column_id')->nullable()->constrained('board_columns')->nullOnDelete();
            $table->foreignId('status_id')->constrained('statuses');
            $table->foreignId('priority_id')->constrained('priorities');
            $table->foreignId('task_type_id')->constrained('task_types');
            $table->foreignId('responsible_id')->constrained('users');
            $table->foreignId('reporter_id')->constrained('users');
            $table->string('task_number');
            $table->foreignId('parent_task_id')->nullable()->constrained('tasks');
            $table->decimal('estimated_hours',8,2)->nullable();
            $table->decimal('spent_hours',8,2)->nullable();
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->integer('position')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
