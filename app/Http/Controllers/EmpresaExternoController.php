<?php

/*
 * ************************************************************
 * Empresas externas y códigos QR
 * Archivos necesarios:
 *   EmpresaExternoController.php, QrEmpresaController.php, EmpresaRequest.php,
 *   EmpresaExterno.php, AreaExterno.php, empresas/index.blade.php, generarqr.blade.php,
 *   selector-empresa.blade.php, selector-empresa.js
 * Fecha: 10 ago 2026 - 21 ago 2026
 * Realizado por: Ivan Lopez
 * ************************************************************
 */

namespace App\Http\Controllers;

use App\Http\Requests\EmpresaRequest;
use App\Models\EmpresaExterno;

class EmpresaExternoController extends Controller
{
    public function index()
    {
        $empresas = EmpresaExterno::latest()->paginate(10);

        return view('admin.catalogos.empresas.index', compact('empresas'));
    }

    public function store(EmpresaRequest $request)
    {
        EmpresaExterno::create($request->datosEmpresa());

        return redirect()->route('admin.empresas.index')
            ->with('success', 'Empresa registrada correctamente.');
    }

    public function update(EmpresaRequest $request, EmpresaExterno $empresa)
    {
        $empresa->update($request->datosEmpresa());

        return redirect()->route('admin.empresas.index')
            ->with('success', 'Empresa actualizada correctamente.');
    }

    public function destroy(EmpresaExterno $empresa)
    {
        $empresa->delete();

        return redirect()->route('admin.empresas.index')
            ->with('success', 'Empresa eliminada correctamente.');
    }
}
