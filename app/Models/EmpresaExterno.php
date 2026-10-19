<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmpresaExterno extends Model
{
    use HasFactory;

    protected $connection = 'db_externa';

    protected $table = 'empresas';

    protected $fillable = [
        'nombre',
        'razon_social',
        'categoria',
        'logo_url',
        'sitio_web',
    ];
}
