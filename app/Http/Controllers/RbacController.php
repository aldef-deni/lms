<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RbacController extends Controller
{
    public function index()
    {
        $roles = Role::with('permissions')->orderBy('id')->get();
        $permissions = Permission::orderBy('name')->get()->groupBy(fn ($permission) => str($permission->name)->before('.')->value());

        return view('admin.rbac', compact('roles', 'permissions'));
    }

    public function update(Request $request, Role $role)
    {
        $data = $request->validate(['permissions' => 'nullable|array', 'permissions.*' => 'string|exists:permissions,name']);
        $role->syncPermissions($data['permissions'] ?? []);
        ActivityLog::create(['user_id' => $request->user()->id, 'action' => 'Updated role permissions', 'subject' => $role->name]);

        return back()->with('success', $role->name.' permissions updated.');
    }
}
