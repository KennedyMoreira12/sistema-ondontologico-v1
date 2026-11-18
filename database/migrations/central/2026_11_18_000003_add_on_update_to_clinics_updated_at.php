<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * `clinics.updated_at` passa a ser atualizado pelo próprio banco.
     *
     * O Eloquent grava `updated_at` em todo `save()`, mas nem toda alteração
     * passa por ele: correção operacional com `mysql -e`, rotina administrativa,
     * script de suporte. Nesses casos a coluna ficava congelada na data do
     * último save, e a trilha de auditoria da clínica mentia — dizia que o
     * registro não mudou quando mudou.
     *
     * Só a tabela `clinics` é ajustada aqui. As outras 68 tabelas centrais têm o
     * mesmo problema e ficam para a etapa de higiene de banco: mudar 69 tabelas
     * de uma vez é uma operação de schema, não um ajuste pontual.
     *
     * SQLite (usado na suíte de testes) não tem `ON UPDATE`; a migration sai
     * sem fazer nada, em vez de falhar.
     */
    public function up(): void
    {
        $this->ajustar(true);
    }

    public function down(): void
    {
        $this->ajustar(false);
    }

    /**
     * @param  bool  $comOnUpdate  true acrescenta, false remove o `ON UPDATE`.
     */
    protected function ajustar(bool $comOnUpdate): void
    {
        $conexao = DB::connection();

        if ($conexao->getDriverName() !== 'mysql') {
            return;
        }

        $coluna = $conexao->selectOne(
            'select column_type, is_nullable, column_default, extra
               from information_schema.columns
              where table_schema = database()
                and table_name = ?
                and column_name = ?',
            ['clinics', 'updated_at']
        );

        if ($coluna === null) {
            return;
        }

        $jaTem = str_contains(strtolower((string) $coluna->extra), 'on update');

        if ($jaTem === $comOnUpdate) {
            return;
        }

        $definicao = $coluna->column_type
            .($coluna->is_nullable === 'YES' ? ' NULL' : ' NOT NULL')
            .($coluna->column_default === null ? ' DEFAULT NULL' : " DEFAULT '{$coluna->column_default}'");

        if ($comOnUpdate) {
            $definicao .= ' ON UPDATE CURRENT_TIMESTAMP';
        }

        $conexao->statement("alter table `clinics` modify `updated_at` {$definicao}");
    }
};
