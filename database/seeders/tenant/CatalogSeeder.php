<?php

namespace Database\Seeders\tenant;

use App\Models\AppointmentStatus;
use App\Models\InsuranceCompany;
use App\Models\InsurancePlan;
use App\Models\PaymentMethod;
use App\Models\Procedure;
use App\Models\ProductCategory;
use App\Models\Room;
use App\Models\Specialty;
use App\Models\ToothCondition;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['name' => 'agendado', 'label' => 'Agendado', 'cor' => '#0d6efd'],
            ['name' => 'confirmado', 'label' => 'Confirmado', 'cor' => '#198754'],
            ['name' => 'em_atendimento', 'label' => 'Em atendimento', 'cor' => '#ffc107'],
            ['name' => 'atendido', 'label' => 'Atendido', 'cor' => '#6f42c1'],
            ['name' => 'faltou', 'label' => 'Faltou', 'cor' => '#dc3545'],
            ['name' => 'cancelado', 'label' => 'Cancelado', 'cor' => '#6c757d'],
        ];
        foreach ($statuses as $status) {
            AppointmentStatus::firstOrCreate(['name' => $status['name']], $status);
        }

        $paymentMethods = [
            ['name' => 'dinheiro', 'label' => 'Dinheiro'],
            ['name' => 'pix', 'label' => 'PIX'],
            ['name' => 'credito', 'label' => 'Cartão de crédito'],
            ['name' => 'debito', 'label' => 'Cartão de débito'],
            ['name' => 'boleto', 'label' => 'Boleto'],
            ['name' => 'convenio', 'label' => 'Convênio'],
        ];
        foreach ($paymentMethods as $method) {
            PaymentMethod::firstOrCreate(['name' => $method['name']], $method);
        }

        $conditions = [
            ['name' => 'Hígido', 'codigo' => 'higido', 'cor' => '#28a745', 'is_default' => true],
            ['name' => 'Cariado', 'codigo' => 'cariado', 'cor' => '#dc3545'],
            ['name' => 'Restaurado', 'codigo' => 'restaurado', 'cor' => '#0d6efd'],
            ['name' => 'Ausente', 'codigo' => 'ausente', 'cor' => '#6c757d'],
            ['name' => 'Coroa', 'codigo' => 'coroa', 'cor' => '#fd7e14'],
            ['name' => 'Implante', 'codigo' => 'implante', 'cor' => '#6f42c1'],
            ['name' => 'Tratamento de canal', 'codigo' => 'canal', 'cor' => '#ffc107'],
            ['name' => 'Fraturado', 'codigo' => 'fratura', 'cor' => '#e83e8c'],
        ];
        foreach ($conditions as $condition) {
            ToothCondition::firstOrCreate(['codigo' => $condition['codigo']], $condition);
        }

        $procedures = [
            ['codigo' => 'LIMPO', 'nome' => 'Limpeza', 'categoria' => 'Prevenção', 'duracao_minutos' => 60, 'valor_padrao' => 150.00],
            ['codigo' => 'CLIN_GER', 'nome' => 'Consulta clínica geral', 'categoria' => 'Consulta', 'duracao_minutos' => 30, 'valor_padrao' => 100.00],
            ['codigo' => 'RAD_PG', 'nome' => 'Radiografia panorâmica', 'categoria' => 'Exame', 'duracao_minutos' => 15, 'valor_padrao' => 80.00],
            ['codigo' => 'END_FR_A', 'nome' => 'Tratamento de canal (1 raiz)', 'categoria' => 'Endodontia', 'duracao_minutos' => 90, 'valor_padrao' => 600.00],
            ['codigo' => 'RES_AME', 'nome' => 'Restauração em amálgama', 'categoria' => 'Restauradora', 'duracao_minutos' => 45, 'valor_padrao' => 180.00],
            ['codigo' => 'RES_RES', 'nome' => 'Restauração em resina', 'categoria' => 'Restauradora', 'duracao_minutos' => 45, 'valor_padrao' => 220.00],
            ['codigo' => 'EXT_DEC', 'nome' => 'Extração de dente decíduo', 'categoria' => 'Cirurgia', 'duracao_minutos' => 30, 'valor_padrao' => 120.00],
            ['codigo' => 'EXT_INC', 'nome' => 'Extração de dente permanente', 'categoria' => 'Cirurgia', 'duracao_minutos' => 60, 'valor_padrao' => 250.00],
            ['codigo' => 'PROT_COR', 'nome' => 'Coroa de porcelana', 'categoria' => 'Prótese', 'duracao_minutos' => 120, 'valor_padrao' => 1200.00],
            ['codigo' => 'PT_BT_M', 'nome' => 'Clareamento dental', 'categoria' => 'Estética', 'duracao_minutos' => 60, 'valor_padrao' => 400.00],
        ];
        foreach ($procedures as $procedure) {
            Procedure::firstOrCreate(['codigo' => $procedure['codigo']], $procedure);
        }

        $specialties = ['Clínica geral', 'Endodontia', 'Cirurgia', 'Periodontia', 'Ortodontia', 'Implantes', 'Odontopediatria'];
        foreach ($specialties as $specialty) {
            Specialty::firstOrCreate(['name' => $specialty]);
        }

        $categories = ['Descartáveis', 'Instrumentais', 'Materiais restauradores', 'Medicamentos', 'Equipamentos', 'Cimentos', 'Anestésicos'];
        foreach ($categories as $category) {
            ProductCategory::firstOrCreate(['name' => $category]);
        }

        $rooms = ['Sala 1', 'Sala 2', 'Sala 3'];
        foreach ($rooms as $room) {
            Room::firstOrCreate(['nome' => $room]);
        }

        $co = InsuranceCompany::firstOrCreate(
            ['nome' => 'OdontoSaúde'],
            ['cnpj' => '11.111.111/0001-11', 'phone' => '(11) 2000-0000']
        );
        InsurancePlan::firstOrCreate(
            ['insurance_company_id' => $co->id, 'nome' => 'Plano Essencial'],
            ['registro' => 'ANS-0001', 'tipo' => 'ambulatorial']
        );
    }
}
