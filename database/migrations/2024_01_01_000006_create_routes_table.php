<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The database connection that should be used by the migration.
     *
     * @var string
     */
    protected $connection = 'stellify';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id')->nullable();
            $table->string('project_id', 255)->nullable();
            $table->string('name', 255)->nullable();
            $table->string('uuid', 255);
            $table->string('path', 255)->default('');
            $table->string('controller', 255)->nullable()->default('');
            $table->string('controller_method', 255)->nullable()->default('');
            $table->string('middleware_group', 255)->default('web');
            $table->string('redirect_url', 255)->default('');
            $table->string('status_code', 3)->default('');
            $table->string('type', 255)->nullable()->default('web');
            $table->string('method', 10)->default('GET');
            $table->boolean('public')->default(false);
            $table->boolean('ssr')->default(false);
            $table->boolean('email_verify')->default(false);
            $table->boolean('subview')->default(false);
            $table->json('data');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('routes');
    }
};
