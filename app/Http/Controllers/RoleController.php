<?php

namespace App\Http\Controllers;

use App\Models\Roles;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RoleController extends Controller
{
    /**
     * Listado principal de roles
     */
    public function index()
    {
        $roles = Roles::withCount('permissions', 'users')->get();
        return view('roles.index', compact('roles'));
    }

    /**
     * Formulario para crear un nuevo rol
     */
    public function create()
    {
        // Agrupar permisos por módulo para mostrarlos organizados en la vista
        $permissionsByModule = Permission::all()->groupBy('module');
        return view('roles.create', compact('permissionsByModule'));
    }

    /**
     * Guardar nuevo rol y sincronizar sus permisos
     */
    public function store(Request $request)
    {
        $request->validate([
            'display_name' => 'required|string|max:255',
            'description'  => 'nullable|string|max:500',
            'permissions'  => 'nullable|array',
            'permissions.*'=> 'exists:permissions,id',
        ]);

        $role = Roles::create([
            'id'           => Str::uuid()->toString(),
            'name'         => Str::slug($request->display_name, '_'),
            'display_name' => $request->display_name,
            'description'  => $request->description,
        ]);

        if ($request->has('permissions')) {
            $role->permissions()->sync($request->permissions);
        }

        return redirect()->route('roles.index')->with('success', 'Rol creado correctamente.');
    }

    /**
     * Formulario de edición de rol y sus permisos
     */
    public function edit(Roles $role)
    {
        $permissionsByModule = Permission::all()->groupBy('module');
        $rolePermissions = $role->permissions->pluck('id')->toArray();

        return view('roles.edit', compact('role', 'permissionsByModule', 'rolePermissions'));
    }

    /**
     * Actualizar rol y permisos asignados
     */
    public function update(Request $request, Roles $role)
    {
        $request->validate([
            'display_name' => 'required|string|max:255',
            'description'  => 'nullable|string|max:500',
            'permissions'  => 'nullable|array',
            'permissions.*'=> 'exists:permissions,id',
        ]);

        $role->update([
            'display_name' => $request->display_name,
            'description'  => $request->description,
        ]);

        // Sincronizar permisos (asigna los marcados y remueve los no marcados)
        $role->permissions()->sync($request->input('permissions', []));

        return redirect()->route('roles.index')->with('success', 'Rol y permisos actualizados correctamente.');
    }

    /**
     * Eliminar rol
     */
    public function destroy(Roles $role)
    {
        // Evitar eliminar roles que tengan usuarios asignados
        if ($role->users()->count() > 0) {
            return back()->withErrors(['msg' => 'No se puede eliminar el rol porque tiene usuarios asignados.']);
        }

        $role->delete();

        return redirect()->route('roles.index')->with('success', 'Rol eliminado correctamente.');
    }
}