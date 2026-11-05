<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Roles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;


class UserController extends Controller
{
    /**
     * Listado principal de usuarios
     */
    public function index()
    {
        $users = User::with('roles')->latest()->get();
        return view('users.index', compact('users'));
    }

    /**
     * Formulario de creación
     */
    public function create()
    {
        $roles = Roles::all();
        return view('users.create', compact('roles'));
    }

    /**
     * Guardar usuario y asignar su rol
     */
    public function store(Request $request)
    {
        $request->validate([
            'username'  => 'required|string|max:255|unique:users,username',
            'email'     => 'required|email|max:255|unique:users,email',
            'password'  => 'required|string|min:6',
            'role_id'   => 'required|exists:roles,id',
            'is_active' => 'required|boolean',
        ]);

        $user = User::create([
            'id'        => Str::uuid()->toString(),
            'username'  => strtolower(trim($request->username)),
            'email'     => strtolower(trim($request->email)),
            'password'  => Hash::make($request->password),
            'is_active' => $request->is_active,
        ]);

        // Asignar el rol al usuario en la tabla pivote
        $user->roles()->sync([$request->role_id]);

        return redirect()->route('users.index')->with('success', 'Usuario registrado correctamente.');
    }

    /**
     * Formulario de edición
     */
    public function edit(User $user)
    {
        $roles = Roles::all();
        $userRoleId = $user->roles->first()?->id;

        return view('users.edit', compact('user', 'roles', 'userRoleId'));
    }

    /**
     * Actualizar usuario y su rol (Soporta UUID e ID Legacy)
     */
    public function update(Request $request, $id)
    {
        // 1. Buscar al usuario explícitamente por UUID 'id' o por 'user_id_legacy'
        $user = User::where('id', $id)
                    ->orWhere('user_id_legacy', $id)
                    ->firstOrFail();

        // 2. Normalizar strings antes de validar
        $usernameClean = strtolower(trim($request->username));
        $emailClean    = strtolower(trim($request->email));

        $request->merge([
            'username' => $usernameClean,
            'email'    => $emailClean,
        ]);

        // 3. Validar pasando directamente el UUID real ($user->id)
        $request->validate([
            'email'     => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id, 'id')->withoutTrashed()
            ],
            'password'  => 'nullable|string|min:6',
            'role_id'   => 'required',
            'is_active' => 'required|boolean',
        ]);

        $data = [
            'username'  => $usernameClean,
            'email'     => $emailClean,
            'is_active' => (bool) $request->is_active,
        ];

        // Actualizar contraseña solo si se ingresó un valor
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        // 4. Sincronizar el rol en la tabla pivote
        $user->roles()->sync([$request->role_id]);

        // 5. LIMPIAR LA CACHÉ DE ROLES Y PERMISOS
        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            // Si usas Spatie Laravel-Permission:
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } else {
            // Si usas la Caché estándar de Laravel:
            \Illuminate\Support\Facades\Cache::forget('spatie.permission.cache');
            \Illuminate\Support\Facades\Cache::flush();
        }

        return redirect()->route('users.index')->with('success', 'Usuario actualizado correctamente.');
    }
    /**
     * Eliminar usuario (Baja lógica)
     */
    public function destroy(User $user)
    {
        // Evitar que un usuario se elimine a sí mismo
        if ($user->id === auth()->id()) {
            return back()->withErrors(['msg' => 'No puedes eliminar tu propia cuenta de usuario.']);
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'Usuario eliminado correctamente.');
    }
}