<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection(config('table-views.connection'));
        $name = config('table-views.preferences_table', 'table_view_preferences');

        $schema->create($name, function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 64);
            $table->string('tenant_type', 255)->nullable();
            $table->string('tenant_id', 64)->nullable();
            $table->string('resource', 255);
            $table->string('view_type', 16);
            $table->string('view_key', 255);
            $table->boolean('is_favorite')->nullable();
            $table->boolean('is_hidden')->default(false);
            $table->unsignedInteger('sort')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'resource', 'tenant_type', 'tenant_id'], 'table_view_preferences_lookup');
        });

        // coalesce makes rows without a tenant collide too (NULLs are distinct in a plain unique index).
        if (in_array($schema->getConnection()->getDriverName(), ['pgsql', 'sqlite'], true)) {
            $connection = $schema->getConnection();
            $wrapped = $connection->getSchemaGrammar()->wrapTable($name);

            $connection->statement(
                "create unique index table_view_preferences_unique on {$wrapped} "
                ."(user_id, resource, view_type, view_key, coalesce(tenant_type, ''), coalesce(tenant_id, ''))"
            );
        }
    }

    public function down(): void
    {
        Schema::connection(config('table-views.connection'))->dropIfExists(config('table-views.preferences_table', 'table_view_preferences'));
    }
};
