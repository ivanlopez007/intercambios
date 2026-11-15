<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AreaExterno extends Model
{
    use HasFactory;

    protected $table = 'areas';

    protected $fillable = ['area'];

    // Un área tiene muchos usuarios asignados (empleados de RH)
    public function users()
    {
        return $this->hasMany(UserExterno::class, 'area_id');
    }
}
