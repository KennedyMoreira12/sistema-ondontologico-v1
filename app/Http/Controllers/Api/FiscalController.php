<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotaFiscalResource;
use App\Models\Appointment;
use App\Models\NotaFiscal;
use App\Models\Paciente;
use App\Services\Fiscal\FiscalException;
use App\Services\Fiscal\NotaFiscalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * API do módulo fiscal.
 *
 * Mesmas regras da tela: os dois controllers delegam ao NotaFiscalService.
 * Erro de provedor chega como 422 com mensagem legível, nunca como 500.
 *
 * A autorização não passa por `authorize()`: não há sessão nem usuário aqui.
 * Quem chama é um token de integração, e a ability exigida por rota está em
 * `IntegrationAbilities::MAP` (aplicada pelo `EnsureIntegrationAbility`).
 * Emitir e cancelar têm ability própria, então um token de leitura não
 * movimenta nota.
 *
 * Os parâmetros de rota são resolvidos aqui, e não por route model binding:
 * o `SubstituteBindings` roda antes do `AuthenticateIntegration`, que é quem
 * aponta a conexão para o banco da clínica. Binding implícito consultaria a
 * conexão `tenant` vazia e estouraria 500 antes da checagem de ability. É o
 * mesmo motivo pelo qual a API do WhatsApp e a de posts sociais recebem ids.
 */
class FiscalController extends Controller
{
    public function __construct(private readonly NotaFiscalService $fiscal) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $notas = NotaFiscal::query()
            ->with('paciente')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->value()))
            ->when($request->filled('paciente_id'), fn ($q) => $q->where('paciente_id', $request->integer('paciente_id')))
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo', $request->string('tipo')->value()))
            ->orderByDesc('id')
            ->paginate(min($request->integer('per_page', 15), 100));

        return NotaFiscalResource::collection($notas);
    }

    public function show(string $notaFiscal): NotaFiscalResource
    {
        $notaFiscal = NotaFiscal::query()->findOrFail($notaFiscal);
        $notaFiscal->load(['paciente', 'consulta']);

        return new NotaFiscalResource($notaFiscal);
    }

    public function store(Request $request): JsonResponse
    {
        $dados = $this->validar($request);

        $nota = DB::transaction(function () use ($dados) {
            $valor = (float) $dados['valor_total'];
            $desconto = (float) ($dados['valor_desconto'] ?? 0);
            $liquido = max(0, $valor - $desconto);
            $aliquota = (float) ($dados['aliquota_iss'] ?? config('fiscal.aliquota_iss', 5));

            return NotaFiscal::create(array_merge($dados, [
                'status' => NotaFiscal::STATUS_RASCUNHO,
                'ambiente' => config('fiscal.ambiente', 'homologacao'),
                'serie' => ($dados['serie'] ?? null) ?: (string) config('fiscal.serie', '1'),
                'valor_liquido' => $liquido,
                'aliquota_iss' => $aliquota,
                'valor_iss' => round($liquido * ($aliquota / 100), 2),
            ]));
        });

        return (new NotaFiscalResource($nota->load('paciente')))
            ->response()
            ->setStatusCode(201);
    }

    public function emitir(string $notaFiscal): JsonResponse
    {
        $notaFiscal = NotaFiscal::query()->findOrFail($notaFiscal);

        try {
            $notaFiscal = $this->fiscal->emitir($notaFiscal);
        } catch (FiscalException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($notaFiscal->status === NotaFiscal::STATUS_REJEITADA) {
            return response()->json([
                'message' => 'Nota rejeitada pelo provedor.',
                'motivo' => $notaFiscal->motivo_rejeicao,
                'nota' => new NotaFiscalResource($notaFiscal->load('paciente')),
            ], 422);
        }

        return (new NotaFiscalResource($notaFiscal->load('paciente')))->response();
    }

    public function cancelar(Request $request, string $notaFiscal): JsonResponse
    {
        $notaFiscal = NotaFiscal::query()->findOrFail($notaFiscal);

        $dados = $request->validate([
            'motivo' => ['required', 'string', 'min:15', 'max:255'],
        ]);

        try {
            $notaFiscal = $this->fiscal->cancelar($notaFiscal, $dados['motivo']);
        } catch (FiscalException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return (new NotaFiscalResource($notaFiscal->load('paciente')))->response();
    }

    public function consultar(string $notaFiscal): JsonResponse
    {
        $notaFiscal = NotaFiscal::query()->findOrFail($notaFiscal);

        try {
            $notaFiscal = $this->fiscal->consultar($notaFiscal);
        } catch (FiscalException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return (new NotaFiscalResource($notaFiscal->load('paciente')))->response();
    }

    public function xml(string $notaFiscal): Response
    {
        $notaFiscal = NotaFiscal::query()->findOrFail($notaFiscal);

        return response($this->fiscal->xml($notaFiscal), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="nota-fiscal-'.$notaFiscal->id.'.xml"',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request): array
    {
        return $request->validate([
            'paciente_id' => [
                'nullable',
                'integer',
                function (string $atributo, mixed $valor, \Closure $falhar) {
                    if ($valor !== null && ! Paciente::query()->whereKey($valor)->exists()) {
                        $falhar('Paciente inexistente nesta clínica.');
                    }
                },
            ],
            'consulta_id' => [
                'nullable',
                'integer',
                function (string $atributo, mixed $valor, \Closure $falhar) {
                    if ($valor !== null && ! Appointment::query()->whereKey($valor)->exists()) {
                        $falhar('Agendamento inexistente nesta clínica.');
                    }
                },
            ],
            'tipo' => ['required', Rule::in(['nfse', 'nfe', 'nfce'])],
            'serie' => ['nullable', 'string', 'max:10'],
            'valor_total' => ['required', 'numeric', 'gt:0'],
            'valor_desconto' => ['nullable', 'numeric', 'min:0', 'lte:valor_total'],
            'aliquota_iss' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'codigo_servico' => ['nullable', 'string', 'max:20'],
            'descricao_servico' => ['nullable', 'string', 'max:255'],
            'tomador_nome' => ['nullable', 'string', 'max:255'],
            'tomador_documento' => ['nullable', 'string', 'max:20'],
            'tomador_email' => ['nullable', 'email', 'max:191'],
            'tomador_endereco' => ['nullable', 'string', 'max:255'],
            'tomador_cidade' => ['nullable', 'string', 'max:120'],
            'tomador_uf' => ['nullable', 'string', 'size:2'],
            'tomador_cep' => ['nullable', 'string', 'max:10'],
        ]);
    }
}
