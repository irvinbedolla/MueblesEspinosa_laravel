<?php

namespace App\Enums;

enum UserRole: int
{
    case ADMIN    = 0;
    case VENDEDOR = 1;
    case COBRADOR = 2;

    // Etiqueta legible para la interfaz
    public function label(): string
    {
        return match($this) {
            self::ADMIN    => 'Administrador',
            self::VENDEDOR => 'Vendedor',
            self::COBRADOR => 'Cobrador',
        };
    }
}