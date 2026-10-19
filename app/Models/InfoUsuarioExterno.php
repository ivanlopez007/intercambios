<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class InfoUsuarioExterno extends Model
{
    use HasFactory;

    protected $connection = 'db_externa';

    protected $table = 'info_usuarios';

    // Campos habilitados para asignación masiva (Mass Assignment)
    protected $fillable = [
        'usuario_id',
        'image_url',
        'nombre',
        'apellido_p',
        'apellido_m',
        'nss',
        'rfc',
        'curp',
        'telefono',
        'fecha_nacimiento',
    ];

    protected $casts = [
        'rfc' => 'encrypted',
        'curp' => 'encrypted',
    ];

    public function user()
    {
        return $this->belongsTo(UserExterno::class, 'usuario_id', 'id');
    }

    /**
     * Accesor para obtener la URL formateada de la foto de perfil (compatible con Cloudflare R2 y almacenamiento local).
     */
    public function getFotoUrlAttribute(): ?string
    {
        if (empty($this->image_url)) {
            return null;
        }

        if (Str::startsWith($this->image_url, ['http://', 'https://', '//'])) {
            return $this->image_url;
        }

        if (Str::startsWith($this->image_url, ['storage/', '/storage/'])) {
            return asset(ltrim($this->image_url, '/'));
        }

        return asset('storage/'.$this->image_url);
    }
}
