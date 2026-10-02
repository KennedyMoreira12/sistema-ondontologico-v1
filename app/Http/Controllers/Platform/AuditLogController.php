<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Central\AdminUser;
use App\Models\Central\Clinic;
use App\Models\Central\PlatformAuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Auditoria do painel da empresa: quem mexeu em qual clínica, quando e de onde.
 *
 * Lê `platform_audit_logs` (banco central). O que acontece DENTRO de uma clínica
 * fica no `audit_logs` do banco dela e não aparece aqui — são historicamente
 * coisas diferentes.
 */
class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $registros = PlatformAuditLog::query()
            ->with(['adminUser', 'clinic'])
            ->when($request->filled('acao'), fn ($query) => $query->where('action', 'like', $request->string('acao').'%'))
            ->when($request->filled('clinica'), fn ($query) => $query->where('clinic_id', $request->integer('clinica')))
            ->when($request->filled('admin'), fn ($query) => $query->where('admin_user_id', $request->integer('admin')))
            ->when($request->filled('de'), fn ($query) => $query->whereDate('created_at', '>=', $request->date('de')))
            ->when($request->filled('ate'), fn ($query) => $query->whereDate('created_at', '<=', $request->date('ate')))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('platform.auditoria.index', [
            'registros' => $registros,
            'clinicas' => Clinic::query()->orderBy('name')->get(['id', 'name']),
            'admins' => AdminUser::query()->orderBy('name')->get(['id', 'name']),
            'acoes' => PlatformAuditLog::query()
                ->distinct()
                ->orderBy('action')
                ->pluck('action'),
        ]);
    }
}
