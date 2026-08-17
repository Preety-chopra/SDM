<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $rolePivotKey = $columnNames['role_pivot_key'] ?? 'role_id';
        $permissionPivotKey = $columnNames['permission_pivot_key'] ?? 'permission_id';
        $modelMorphKey = $columnNames['model_morph_key'];

        Schema::create($tableNames['permissions'], function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create($tableNames['roles'], function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create($tableNames['model_has_permissions'], function (Blueprint $table) use ($tableNames, $permissionPivotKey, $modelMorphKey): void {
            $table->unsignedBigInteger($permissionPivotKey);
            $table->string('model_type');
            $table->unsignedBigInteger($modelMorphKey);
            $table->index([$modelMorphKey, 'model_type'], 'model_has_permissions_model_id_model_type_index');
            $table->foreign($permissionPivotKey)->references('id')->on($tableNames['permissions'])->cascadeOnDelete();
            $table->primary([$permissionPivotKey, $modelMorphKey, 'model_type'], 'model_has_permissions_permission_model_type_primary');
        });

        Schema::create($tableNames['model_has_roles'], function (Blueprint $table) use ($tableNames, $rolePivotKey, $modelMorphKey): void {
            $table->unsignedBigInteger($rolePivotKey);
            $table->string('model_type');
            $table->unsignedBigInteger($modelMorphKey);
            $table->index([$modelMorphKey, 'model_type'], 'model_has_roles_model_id_model_type_index');
            $table->foreign($rolePivotKey)->references('id')->on($tableNames['roles'])->cascadeOnDelete();
            $table->primary([$rolePivotKey, $modelMorphKey, 'model_type'], 'model_has_roles_role_model_type_primary');
        });

        Schema::create($tableNames['role_has_permissions'], function (Blueprint $table) use ($tableNames, $rolePivotKey, $permissionPivotKey): void {
            $table->unsignedBigInteger($permissionPivotKey);
            $table->unsignedBigInteger($rolePivotKey);
            $table->foreign($permissionPivotKey)->references('id')->on($tableNames['permissions'])->cascadeOnDelete();
            $table->foreign($rolePivotKey)->references('id')->on($tableNames['roles'])->cascadeOnDelete();
            $table->primary([$permissionPivotKey, $rolePivotKey], 'role_has_permissions_permission_id_role_id_primary');
        });
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names');

        Schema::dropIfExists($tableNames['role_has_permissions']);
        Schema::dropIfExists($tableNames['model_has_roles']);
        Schema::dropIfExists($tableNames['model_has_permissions']);
        Schema::dropIfExists($tableNames['roles']);
        Schema::dropIfExists($tableNames['permissions']);
    }
};
