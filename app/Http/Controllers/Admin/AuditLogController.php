<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('auditoria.view');

        $query = AuditLog::with('user')->latest();

        if ($request->filled('acao')) {
            $query->where('acao', 'like', '%'.$request->string('acao').'%');
        }

        if ($request->filled('tabela')) {
            $query->where('tabela', $request->string('tabela'));
        }

        $logs = $query->paginate(20);

        return view('admin.auditoria.index', compact('logs'));
    }
}