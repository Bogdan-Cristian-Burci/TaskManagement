<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description');
            $table->string('color',20)->nullable();
            $table->boolean('is_default')->default(false);
            $table->string('icon',50)->nullable();
            $table->integer('position')->default(1);
            $table->string('category')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statuses');
    }
};
