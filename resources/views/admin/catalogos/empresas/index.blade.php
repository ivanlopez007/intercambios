@extends('admin.layout.layout')

@section('title', 'Gestión de Empresas')
@section('page-title', 'Catálogo de Empresas')

@section('contenido')
<div class="max-w-5xl mx-auto py-6 px-2">

    <!-- Tarjeta Principal -->
    <div class="bg-white border border-slate-200 rounded-3xl p-5 md:p-7 shadow-sm space-y-6">

        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-blue-50 border border-blue-200 flex items-center justify-center text-blue-600">
                    <i class="fa-solid fa-building text-xl"></i>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-slate-900 leading-tight">Empresas Externas</h2>
                    <p class="text-xs text-slate-500">Gestión de catálogo de empresas</p>
                </div>
            </div>

            <button type="button" onclick="abrirModalCrear()"
                class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-4 py-2.5 rounded-xl transition flex items-center gap-2 text-sm shadow-sm">
                <i class="fa-solid fa-plus text-base"></i>
                <span>Nueva Empresa</span>
            </button>
        </div>

        <!-- Mensajes de Estado (Sesión Laravel) -->
        @if (session('success'))
        <div class="p-4 rounded-2xl text-sm font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200">
            {{ session('success') }}
        </div>
        @endif

        @if (session('error'))
        <div class="p-4 rounded-2xl text-sm font-semibold bg-rose-100 text-rose-800 border border-rose-200">
            {{ session('error') }}
        </div>
        @endif

        @if ($errors->any())
        <div class="p-4 rounded-2xl text-sm font-semibold bg-rose-100 text-rose-800 border border-rose-200">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <!-- Tabla de Empresas -->
        <div class="overflow-x-auto rounded-2xl border border-slate-200">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="bg-slate-50 text-slate-500 uppercase text-[11px] font-bold tracking-wider border-b border-slate-200">
                    <tr>
                        <th scope="col" class="px-4 py-3.5">ID</th>
                        <th scope="col" class="px-4 py-3.5">Empresa</th>
                        <th scope="col" class="px-4 py-3.5">Razón Social</th>
                        <th scope="col" class="px-4 py-3.5">Categoría</th>
                        <th scope="col" class="px-4 py-3.5">Sitio Web</th>
                        <th scope="col" class="px-4 py-3.5 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse($empresas as $empresa)
                    <tr class="hover:bg-slate-50/80 transition-colors">
                        <td class="px-4 py-3 font-semibold text-slate-900">#{{ $empresa->id }}</td>
                        <td class="px-4 py-3 font-bold text-slate-800">
                            <div class="flex items-center gap-2">
                                @if($empresa->logo_url)
                                <img src="{{ $empresa->logo_url }}" class="w-6 h-6 rounded-full object-cover border border-slate-200" alt="logo">
                                @endif
                                <span>{{ $empresa->nombre }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-slate-500 text-xs">{{ $empresa->razon_social ?? 'N/A' }}</td>
                        <td class="px-4 py-3 text-xs">
                            @if($empresa->categoria)
                            <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-600 font-semibold border border-slate-200">
                                {{ $empresa->categoria }}
                            </span>
                            @else
                            <span class="text-slate-400">Sin categoría</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs">
                            @if($empresa->sitio_web)
                            <a href="{{ $empresa->sitio_web }}" target="_blank" class="text-blue-600 hover:underline">
                                <i class="fa-solid fa-globe mr-1"></i>Visitar
                            </a>
                            @else
                            <span class="text-slate-400">N/A</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right space-x-1">
                            <button type="button"
                                onclick="abrirModalEditar({{ json_encode($empresa) }})"
                                title="Editar"
                                class="px-2.5 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-lg text-xs font-bold transition border border-blue-200">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>

                            <button type="button"
                                onclick="abrirModalEliminar({{ $empresa->id }})"
                                title="Eliminar"
                                class="px-2.5 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 rounded-lg text-xs font-bold transition border border-rose-200">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-8 text-slate-400 font-medium">
                            No hay empresas registradas.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        <div class="pt-2">
            {{ $empresas->links() }}
        </div>

    </div>
</div>

<!-- ========================================== -->
<!-- MODAL POP-UP: CREAR / EDITAR EMPRESA      -->
<!-- ========================================== -->
<div id="modal-empresa" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-md hidden items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 space-y-5">

        <!-- Header Modal -->
        <div class="flex items-center justify-between border-b border-slate-200 pb-3">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-blue-50 border border-blue-200 flex items-center justify-center text-blue-600">
                    <i class="fa-solid fa-building text-lg"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-900" id="modal-titulo">Nueva Empresa</h3>
            </div>
            <button type="button" onclick="cerrarModal()" class="text-slate-400 hover:text-slate-600 transition p-1">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>

        <!-- Formulario Nativo HTML/Laravel -->
        <form id="form-empresa" method="POST" action="{{ route('admin.empresas.store') }}" class="space-y-4">
            @csrf
            <div id="method-container"></div>

            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">
                    Nombre *
                </label>
                <input type="text" id="nombre" name="nombre" required placeholder="Ej. Walmart"
                    class="w-full px-4 py-3 bg-white border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 focus:border-blue-600 outline-none text-slate-900 text-sm shadow-sm">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">
                        Razón Social
                    </label>
                    <input type="text" id="razon_social" name="razon_social" placeholder="Ej. Nueva Wal-Mart S. de R.L."
                        class="w-full px-4 py-3 bg-white border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 focus:border-blue-600 outline-none text-slate-900 text-sm shadow-sm">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">
                        Categoría
                    </label>
                    <input type="text" id="categoria" name="categoria" placeholder="Ej. Retail / Comercio"
                        class="w-full px-4 py-3 bg-white border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 focus:border-blue-600 outline-none text-slate-900 text-sm shadow-sm">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">
                    URL del Logo
                </label>
                <input type="url" id="logo_url" name="logo_url" placeholder="https://ejemplo.com/logo.png"
                    class="w-full px-4 py-3 bg-white border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 focus:border-blue-600 outline-none text-slate-900 text-sm shadow-sm">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">
                    Sitio Web
                </label>
                <input type="url" id="sitio_web" name="sitio_web" placeholder="https://www.walmart.com.mx"
                    class="w-full px-4 py-3 bg-white border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 focus:border-blue-600 outline-none text-slate-900 text-sm shadow-sm">
            </div>

            <!-- Botones Modal -->
            <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                <button type="button" onclick="cerrarModal()"
                    class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold rounded-xl text-sm transition">
                    Cancelar
                </button>
                <button type="submit" id="btn-guardar"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-5 py-2.5 rounded-xl transition text-sm shadow-sm">
                    <span id="btn-guardar-text">Guardar Empresa</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL POP-UP: CONFIRMAR ELIMINACIÓN        -->
<!-- ========================================== -->
<div id="modal-eliminar" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-md hidden items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-sm w-full p-6 shadow-2xl border border-slate-200 text-center space-y-4">
        <div class="w-12 h-12 rounded-2xl bg-rose-50 border border-rose-200 flex items-center justify-center text-rose-600 mx-auto">
            <i class="fa-solid fa-triangle-exclamation text-2xl"></i>
        </div>
        <div>
            <h3 class="text-lg font-bold text-slate-900">¿Eliminar esta empresa?</h3>
            <p class="text-xs text-slate-500 mt-1">Esta acción eliminará el registro de la base de datos.</p>
        </div>

        <form id="form-eliminar" method="POST" action="" class="flex items-center justify-center gap-2 pt-2">
            @csrf
            @method('DELETE')
            <button type="button" onclick="cerrarModalEliminar()"
                class="w-full py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold rounded-xl text-sm transition">
                Cancelar
            </button>
            <button type="submit"
                class="w-full py-2.5 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded-xl text-sm transition shadow-sm">
                Eliminar
            </button>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const routeStore = "{{ route('admin.empresas.store') }}";

    function abrirModalCrear() {
        const form = document.getElementById('form-empresa');
        form.action = routeStore;
        form.reset();

        document.getElementById('method-container').innerHTML = '';
        document.getElementById('modal-titulo').innerText = 'Nueva Empresa';
        document.getElementById('btn-guardar-text').innerText = 'Guardar Empresa';

        const modal = document.getElementById('modal-empresa');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function abrirModalEditar(empresa) {
        const form = document.getElementById('form-empresa');

        // Genera la URL relativa correcta: /empresas/{id}
        let updateUrl = "{{ route('admin.empresas.update', ':id') }}";
        form.action = updateUrl.replace(':id', empresa.id);

        // Se inserta el campo HTML de método PUT directamente
        document.getElementById('method-container').innerHTML = '<input type="hidden" name="_method" value="PUT">';

        document.getElementById('nombre').value = empresa.nombre || '';
        document.getElementById('razon_social').value = empresa.razon_social || '';
        document.getElementById('categoria').value = empresa.categoria || '';
        document.getElementById('logo_url').value = empresa.logo_url || '';
        document.getElementById('sitio_web').value = empresa.sitio_web || '';

        document.getElementById('modal-titulo').innerText = 'Editar Empresa';
        document.getElementById('btn-guardar-text').innerText = 'Actualizar Empresa';

        const modal = document.getElementById('modal-empresa');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function cerrarModal() {
        const modal = document.getElementById('modal-empresa');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function abrirModalEliminar(id) {
        const form = document.getElementById('form-eliminar');

        // Genera la URL relativa correcta: /empresas/{id}
        let deleteUrl = "{{ route('admin.empresas.destroy', ':id') }}";
        form.action = deleteUrl.replace(':id', id);

        const modal = document.getElementById('modal-eliminar');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function cerrarModalEliminar() {
        const modal = document.getElementById('modal-eliminar');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
</script>
@endpush