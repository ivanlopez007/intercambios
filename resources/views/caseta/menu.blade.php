@extends('caseta.layout.layout')

@section('title', 'Menú')
@section('page-title', 'Menú Principal')

@section('contenido')
<div class="max-w-7xl mx-auto py-4 px-2">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

        <!-- Entradas y Salidas de visitantes -->
        <div class="bg-white rounded-2xl p-6 border-2 border-slate-200 shadow-sm hover:shadow-lg hover:border-indigo-300 transition-all flex flex-col justify-between">
            <div>
                <div class="flex items-center gap-4 mb-4">
                    <div class="w-16 h-16 rounded-2xl bg-indigo-100 text-indigo-600 flex items-center justify-center text-3xl shrink-0 shadow-inner">
                        <i class="fa-solid fa-id-badge"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-extrabold text-slate-800">Entradas y Salidas</h3>
                        <span class="inline-block mt-1 px-2.5 py-0.5 text-xs font-semibold bg-indigo-50 text-indigo-700 rounded-full border border-indigo-200">
                            Visitantes y Proveedores
                        </span>
                    </div>
                </div>
                <p class="text-slate-600 text-sm mb-6 font-medium">
                    Bitácora de acceso para visitantes, contratistas y proveedores.
                </p>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <a href="{{ route('caseta.entrada') }}" class="flex items-center justify-center gap-2.5 py-3.5 px-4 bg-emerald-500 hover:bg-emerald-600 active:bg-emerald-700 text-white font-bold rounded-xl shadow-md transition-all text-base">
                    <i class="fa-solid fa-user-check text-xl"></i>
                    <span>Entrada</span>
                </a>
                <a href="{{ route('caseta.salida') }}" class="flex items-center justify-center gap-2.5 py-3.5 px-4 bg-rose-500 hover:bg-rose-600 active:bg-rose-700 text-white font-bold rounded-xl shadow-md transition-all text-base">
                    <i class="fa-solid fa-user-xmark text-xl"></i>
                    <span>Salida</span>
                </a>
            </div>
        </div>

        <!-- QR para empresas -->
        <div class="bg-white rounded-2xl p-6 border-2 border-slate-200 shadow-sm hover:shadow-lg hover:border-violet-300 transition-all flex flex-col justify-between">
            <div>
                <div class="flex items-center gap-4 mb-4">
                    <div class="w-16 h-16 rounded-2xl bg-violet-100 text-violet-600 flex items-center justify-center text-3xl shrink-0 shadow-inner">
                        <i class="fa-solid fa-qrcode"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-extrabold text-slate-800">Generar QR</h3>
                        <span class="inline-block mt-1 px-2.5 py-0.5 text-xs font-semibold bg-violet-50 text-violet-700 rounded-full border border-violet-200">
                            Pases Rápidos
                        </span>
                    </div>
                </div>
                <p class="text-slate-600 text-sm mb-6 font-medium">
                    Creación de QR permanentes para las empresas.
                </p>
            </div>

            <a href="{{ route('caseta.qr') }}" class="w-full flex items-center justify-center gap-2.5 py-3.5 px-4 bg-violet-600 hover:bg-violet-700 active:bg-violet-800 text-white font-bold rounded-xl shadow-md transition-all text-base">
                <i class="fa-solid fa-wand-magic-sparkles text-xl"></i>
                <span>Generar Nuevo QR</span>
            </a>
        </div>

        <!-- Intercambios (en desarrollo) -->
        <div class="bg-white rounded-2xl p-6 border-2 border-dashed border-slate-200 shadow-sm flex flex-col justify-between md:col-span-2 lg:col-span-1">
            <div>
                <div class="flex items-center gap-4 mb-4">
                    <div class="w-16 h-16 rounded-2xl bg-blue-100 text-blue-600 flex items-center justify-center text-3xl shrink-0 shadow-inner">
                        <i class="fa-solid fa-boxes-stacked"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-extrabold text-slate-800">Intercambios</h3>
                        <span class="inline-block mt-1 px-2.5 py-0.5 text-xs font-semibold bg-amber-50 text-amber-700 rounded-full border border-amber-200">
                            En desarrollo
                        </span>
                    </div>
                </div>
                <p class="text-slate-600 text-sm mb-6 font-medium">
                    Registro de intercambios de carga. Próximamente disponible.
                </p>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <span class="flex items-center justify-center gap-2.5 py-3.5 px-4 bg-slate-100 text-slate-400 font-bold rounded-xl text-base cursor-not-allowed" aria-disabled="true">
                    <i class="fa-solid fa-circle-arrow-down text-xl"></i>
                    <span>Entrada</span>
                </span>
                <span class="flex items-center justify-center gap-2.5 py-3.5 px-4 bg-slate-100 text-slate-400 font-bold rounded-xl text-base cursor-not-allowed" aria-disabled="true">
                    <i class="fa-solid fa-circle-arrow-up text-xl"></i>
                    <span>Salida</span>
                </span>
            </div>
        </div>

    </div>
</div>
@endsection
