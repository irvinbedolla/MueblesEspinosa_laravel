<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;


class ClienteController extends Controller
{
    public function index(Request $request)
    {
        $busqueda = trim((string) $request->input('buscar', ''));

        // 1. Resumen métrico en UNA sola consulta SQL (excluyendo eliminados)
        $stats = DB::table('clientes')
            ->whereNull('deleted_at')
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'Activo' THEN 1 ELSE 0 END) as activos,
                SUM(CASE WHEN status = 'Lista_Negra' THEN 1 ELSE 0 END) as lista_negra,
                SUM(CASE WHEN (ubicacion IS NOT NULL AND ubicacion <> '') OR (latitud IS NOT NULL AND latitud <> '') THEN 1 ELSE 0 END) as con_gps
            ")
            ->first();

        $resumen = [
            'total'       => (int) ($stats->total ?? 0),
            'activos'     => (int) ($stats->activos ?? 0),
            'lista_negra' => (int) ($stats->lista_negra ?? 0),
            'con_gps'     => (int) ($stats->con_gps ?? 0),
        ];

        // 2. Consulta paginada trayendo solo las columnas necesarias (excluyendo eliminados)
        $query = DB::table('clientes')
            ->whereNull('deleted_at')
            ->select('id', 'nombre', 'ine', 'telefono', 'calle', 'colonia', 'ciudad', 'status', 'ubicacion', 'latitud', 'longitud');

        if ($busqueda !== '') {
            $query->where(function ($q) use ($busqueda) {
                $q->where('nombre', 'like', "%{$busqueda}%")
                  ->orWhere('telefono', 'like', "%{$busqueda}%")
                  ->orWhere('colonia', 'like', "%{$busqueda}%")
                  ->orWhere('ciudad', 'like', "%{$busqueda}%")
                  ->orWhere('ine', 'like', "%{$busqueda}%");
            });
        }

        $clientes = $query->orderBy('nombre', 'asc')
                        ->paginate(20)
                        ->appends(['buscar' => $busqueda]);

        return view('clientes.index', compact('clientes', 'resumen', 'busqueda'));
    }

    public function create()
    {
        $usuarios = User::where('is_active', true)->get();
        return view('clientes.create', compact('usuarios'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre'   => 'required|string|max:100',
            'telefono' => 'nullable|string|max:20',
            'email'    => 'nullable|email|max:100',
            'status'   => 'required|string|in:Activo,Lista_Negra,Inactivo',
        ]);

        // Extraer coordenadas si vienen en el enlace de Google Maps
        $coords = $this->extraerCoordenadas($request->ubicacion);

        Cliente::create([
            'id'             => Str::uuid()->toString(),
            'nombre'         => mb_strtoupper(trim($request->nombre)),
            'telefono'       => $request->telefono,
            'email'          => $request->email,
            'ine'            => $request->ine,
            'calle'          => $request->calle,
            'colonia'        => $request->colonia,
            'ciudad'         => $request->ciudad,
            'estado'         => $request->estado,
            'descripcion'    => $request->descripcion,
            'ubicacion'      => $request->ubicacion,
            'latitud'        => $coords['lat'] ?? $request->latitud,
            'longitud'       => $coords['lng'] ?? $request->longitud,
            'fecha_registro' => now(),
            'status'         => $request->status,
            'user_id'        => $request->user_id,
        ]);

        return redirect()->route('clientes.index')->with('success', 'Cliente registrado correctamente.');
    }

    public function edit(Cliente $cliente)
    {
        $usuarios = User::where('is_active', true)->get();
        return view('clientes.edit', compact('cliente', 'usuarios'));
    }

    public function update(Request $request, Cliente $cliente)
    {
        $request->validate([
            'nombre'   => 'required|string|max:100',
            'telefono' => 'nullable|string|max:20',
            'email'    => 'nullable|email|max:100',
            'status'   => 'required|string|in:Activo,Lista_Negra,Inactivo',
        ]);

        $coords = $this->extraerCoordenadas($request->ubicacion);

        $cliente->update([
            'nombre'      => mb_strtoupper(trim($request->nombre)),
            'telefono'    => $request->telefono,
            'email'       => $request->email,
            'ine'         => $request->ine,
            'calle'       => $request->calle,
            'colonia'     => $request->colonia,
            'ciudad'      => $request->ciudad,
            'estado'      => $request->estado,
            'descripcion' => $request->descripcion,
            'ubicacion'   => $request->ubicacion,
            'latitud'     => $coords['lat'] ?? ($request->filled('ubicacion') ? $cliente->latitud : null),
            'longitud'    => $coords['lng'] ?? ($request->filled('ubicacion') ? $cliente->longitud : null),
            'status'      => $request->status,
        ]);

        return redirect()->route('clientes.index')->with('success', 'Cliente actualizado correctamente.');
    }

    public function destroy(Cliente $cliente)
    {
        $cliente->delete();
        return redirect()->route('clientes.index')->with('success', 'Cliente eliminado correctamente.');
    }

    /**
     * Helper para extraer Latitud/Longitud de un enlace de Google Maps
     */
    private function extraerCoordenadas(?string $url): array
    {
        if (!$url) {
            return ['lat' => null, 'lng' => null];
        }

        // 1. Buscar en formato @lat,lng (Google Maps estándar)
        if (preg_match('/@(-?\d+\.\d+),(-?\d+\.\d+)/', $url, $matches)) {
            return ['lat' => $matches[1], 'lng' => $matches[2]];
        }

        // 2. Buscar en formato ?q=lat,lng (Capturado por nuestro botón GPS)
        if (preg_match('/[?&]q=(-?\d+\.\d+),(-?\d+\.\d+)/', $url, $matches)) {
            return ['lat' => $matches[1], 'lng' => $matches[2]];
        }

        // 3. Buscar coordenadas puras si el usuario solo pegó "19.70025, -101.19234"
        if (preg_match('/^(-?\d+\.\d+),\s*(-?\d+\.\d+)$/', trim($url), $matches)) {
            return ['lat' => $matches[1], 'lng' => $matches[2]];
        }

        return ['lat' => null, 'lng' => null];
    }
}