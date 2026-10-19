<?php

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
