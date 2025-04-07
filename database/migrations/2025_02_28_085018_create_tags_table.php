<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color');
            $table->foreignId('project_id')->constrained('projects');
            $table->foreignId('organisation_id')->constrained('organisations');
            $table->boolean('is_system')->default(false);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['organization_id', 'is_system']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tags');
    }
};
