<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\View\View;

class PermissionController extends Controller
{
    public function index(): View
    {
        $this->authorize('admin.view');

        $permissions = Permission::orderBy('module')->orderBy('action')->get()->groupBy('module');

        return view('admin.permissoes.index', compact('permissions'));
    }
}
