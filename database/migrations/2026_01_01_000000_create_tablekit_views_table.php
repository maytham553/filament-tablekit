<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection(config('tablekit.connection'));

        $schema->create(config('tablekit.table', 'tablekit_views'), function (Blueprint $table) {
            $table->id();
            $table->string('user_type', 100)->nullable();
            $table->string('user_id', 64);
            $table->string('name', 100);
            $table->string('icon', 64)->nullable();
            $table->string('color', 32)->nullable();
            $table->string('resource', 255);
            $table->jsonb('state');
            $table->boolean('is_favorite')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->string('tenant_type', 255)->nullable();
            $table->string('tenant_id', 64)->nullable();
            $table->timestamps();

            $table->index(
                ['user_type', 'user_id', 'resource', 'tenant_type', 'tenant_id', 'is_favorite', 'sort'],
                'tablekit_views_lookup',
            );
        });
    }

    public function down(): void
    {
        Schema::connection(config('tablekit.connection'))->dropIfExists(config('tablekit.table', 'tablekit_views'));
    }
};
