<?php

namespace App\Http\Controllers;

use App\Models\EmpresaExterno;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrEmpresaController extends Controller
{
    public function index()
    {
        $empresas = EmpresaExterno::orderBy('nombre')->get(['id', 'nombre']);

        return view('caseta.generarqr', compact('empresas'));
    }

    /**
     * Catálogo de empresas (id y nombre) para el buscador de las pantallas de entrada y
     * salida. El celular lo guarda para poder buscar empresas sin conexión.
     */
    public function catalogo(): JsonResponse
    {
        return response()
            ->json(['empresas' => EmpresaExterno::orderBy('nombre')->get(['id', 'nombre'])])
            ->header('Cache-Control', 'no-store');
    }

    /**
     * Genera en el servidor el QR permanente de una empresa. El contenido del QR es su ID,
     * que es lo que leen las pantallas de entrada y salida.
     */
    public function show(EmpresaExterno $empresa): Response
    {
        $svg = QrCode::format('svg')
            ->size(512)
            ->margin(1)
            ->errorCorrection('M')
            ->generate((string) $empresa->id);

        return response((string) $svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
