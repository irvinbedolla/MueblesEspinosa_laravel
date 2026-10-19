<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Roles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
     * Actualizar usuario y su rol
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            'username'  => 'required|string|max:255|unique:users,username,' . $user->id,
            'email'     => 'required|email|max:255|unique:users,email,' . $user->id,
            'password'  => 'nullable|string|min:6',
            'role_id'   => 'required|exists:roles,id',
            'is_active' => 'required|boolean',
        ]);

        $data = [
            'username'  => strtolower(trim($request->username)),
            'email'     => strtolower(trim($request->email)),
            'is_active' => $request->is_active,
        ];

        // Solo actualizar contraseña si se ingresó una nueva
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        // Actualizar su rol
        $user->roles()->sync([$request->role_id]);

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