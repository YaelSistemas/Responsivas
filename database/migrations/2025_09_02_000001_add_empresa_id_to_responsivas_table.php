<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Comprueba si existe una FK por nombre en esta BD */
    private function fkExists(string $table, string $fkName): bool
    {
        $db = DB::getDatabaseName();

        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', $db)
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $fkName)
            ->exists();
    }

    public function up(): void
    {
        // 1) Columna e índice básicos
        Schema::table('responsivas', function (Blueprint $table) {
            if (!Schema::hasColumn('responsivas', 'empresa_id')) {
                $table->unsignedBigInteger('empresa_id')->nullable()->after('id');
            }
            // El índice no es estrictamente necesario si agregas FK; InnoDB lo crea si falta.
            // Si quieres asegurar el índice y evitar duplicados, deja que lo cree la FK.
        });

        // 2) Agregar FK sólo si NO existe (evita errno 121)
        $fkName = 'responsivas_empresa_id_foreign';
        if (!$this->fkExists('responsivas', $fkName) && Schema::hasTable('empresas')) {
            // Asegúrate de que 'empresas.id' sea UNSIGNED BIGINT (lo normal con increments/bigincrements)
            DB::statement("
                ALTER TABLE `responsivas`
                ADD CONSTRAINT `{$fkName}`
                FOREIGN KEY (`empresa_id`) REFERENCES `empresas`(`id`)
                ON UPDATE CASCADE
                ON DELETE SET NULL
            ");
        }

        // 3) Backfill SEGURO (sólo si existen las columnas)
        // 3.a) Desde colaboradores.empresa_id (si existe)
        if (Schema::hasTable('colaboradores') && Schema::hasColumn('colaboradores', 'empresa_id')) {
            DB::statement("
                UPDATE responsivas r
                JOIN colaboradores c ON c.id = r.colaborador_id
                SET r.empresa_id = COALESCE(r.empresa_id, c.empresa_id)
                WHERE r.empresa_id IS NULL
            ");
        }

        // 3.b) Desde colaboradores.subsidiaria_id -> subsidiarias.empresa_id
        if (
            Schema::hasTable('colaboradores') && Schema::hasColumn('colaboradores', 'subsidiaria_id') &&
            Schema::hasTable('subsidiarias')   && Schema::hasColumn('subsidiarias', 'empresa_id')
        ) {
            DB::statement("
                UPDATE responsivas r
                JOIN colaboradores c ON c.id = r.colaborador_id
                JOIN subsidiarias s ON s.id = c.subsidiaria_id
                SET r.empresa_id = COALESCE(r.empresa_id, s.empresa_id)
                WHERE r.empresa_id IS NULL
            ");
        }

        // 3.c) Desde users.empresa_id (si lo guardas ahí)
        if (
            Schema::hasTable('users') &&
            Schema::hasColumn('users', 'empresa_id') &&
            Schema::hasColumn('responsivas', 'user_id')
        ) {
            DB::statement("
                UPDATE responsivas r
                JOIN users u ON u.id = r.user_id
                SET r.empresa_id = COALESCE(r.empresa_id, u.empresa_id)
                WHERE r.empresa_id IS NULL
            ");
        }
    }

    public function down(): void
    {
        $fkName = 'responsivas_empresa_id_foreign';

        // Quitar FK si existe
        if ($this->fkExists('responsivas', $fkName)) {
            DB::statement("ALTER TABLE `responsivas` DROP FOREIGN KEY `{$fkName}`");
        }

        // Quitar columna
        if (Schema::hasColumn('responsivas', 'empresa_id')) {
            Schema::table('responsivas', function (Blueprint $table) {
                $table->dropColumn('empresa_id');
            });
        }
    }
};
