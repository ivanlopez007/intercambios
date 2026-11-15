# Bitácora de desarrollo · Sistema de Caseta e Intercambios

**Realizado por:** Ivan Lopez
**Periodo:** julio 2026 – 13 de noviembre de 2026

Línea de tiempo de los módulos del sistema, en el orden en que se fueron construyendo.

---

### 1. Estructura base, layouts y menús
- **Fecha:** 01 jul 2026 – 11 jul 2026
- **Archivos necesarios:** `config/menus.php`, `resources/views/admin/layout/layout.blade.php`, `resources/views/caseta/layout/layout.blade.php`, `resources/views/components/menu-lateral.blade.php`, `resources/css/app.css`, `resources/js/app.js`
- **Realizado por:** Ivan Lopez

### 2. Login y control de acceso por rol
- **Fecha:** 14 jul 2026 – 25 jul 2026
- **Archivos necesarios:** `AuthController.php`, `auth/login.blade.php`, `EnsureUserHasRole.php`, `EnsureUserIsActive.php`, `User.php`, `create_users_table.php`, `routes/web.php`
- **Realizado por:** Ivan Lopez

### 3. Administración de usuarios
- **Fecha:** 28 jul 2026 – 07 ago 2026
- **Archivos necesarios:** `UsuarioController.php`, `UsuarioRequest.php`, `admin/usuarios/index.blade.php`, `admin/usuarios/form.blade.php`
- **Realizado por:** Ivan Lopez

### 4. Empresas externas y códigos QR
- **Fecha:** 10 ago 2026 – 21 ago 2026
- **Archivos necesarios:** `EmpresaExternoController.php`, `QrEmpresaController.php`, `EmpresaRequest.php`, `EmpresaExterno.php`, `AreaExterno.php`, `admin/catalogos/empresas/index.blade.php`, `caseta/generarqr.blade.php`, `components/selector-empresa.blade.php`, `selector-empresa.js`
- **Realizado por:** Ivan Lopez

### 5. Caseta: entradas y salidas de visitantes (con firma y fotos)
- **Fecha:** 24 ago 2026 – 11 sep 2026
- **Archivos necesarios:** `VisitaExternoController.php`, `VisitaExterno.php`, `BitacoraVisita.php`, `FotoVisitaExterno.php`, `RequerimientoFotoVisitaExterno.php`, `UserExterno.php`, `InfoUsuarioExterno.php`, `caseta/entradas-salidas/entrada.blade.php`, `caseta/entradas-salidas/salida.blade.php`, `caseta/menu.blade.php`, `components/caseta/modal-resultado.blade.php`, `caseta.js`, `caseta-ui.js`, `create_bitacora_visitas_table.php`
- **Realizado por:** Ivan Lopez

### 6. Modo sin conexión (PWA y cola offline)
- **Fecha:** 14 sep 2026 – 25 sep 2026
- **Archivos necesarios:** `public/sw.js`, `pwa.js`, `cola-offline.js`
- **Realizado por:** Ivan Lopez

### 7. Historial de entradas y salidas (administración)
- **Fecha:** 28 sep 2026 – 02 oct 2026
- **Archivos necesarios:** `VisitaExternoController.php` (historial), `admin/entradas-salidas/historial.blade.php`
- **Realizado por:** Ivan Lopez

### 8. Catálogos de intercambios
- **Fecha:** 29 sep 2026 – 07 oct 2026
- **Archivos necesarios:** `CatalogoIntercambioController.php`, `CatalogosIntercambio.php`, `Caja.php`, `TipoCaja.php`, `Tractocamion.php`, `Operador.php`, `InfoOperador.php`, `DocumentoOperador.php`, `TipoLicencia.php`, `LineaTransportista.php`, `Cliente.php`, `Destino.php`, `Concerns/SoloLectura.php`, `admin/catalogos/intercambio.blade.php`
- **Realizado por:** Ivan Lopez

### 9. Captura de intercambios (asistente: checklist, llantas, fotos, firmas)
- **Fecha:** 08 oct 2026 – 19 oct 2026
- **Archivos necesarios:** `IntercambioController.php`, `RegistrarIntercambio.php`, `GuardarIntercambioRequest.php`, `Intercambio.php`, `BitacoraIntercambio.php`, `FotoIntercambio.php`, `LlantaIntercambio.php`, `PuntoInspeccion.php`, `RevisionPunto.php`, `caseta/intercambios/nuevo.blade.php`, `partials/checklist.blade.php`, `partials/diagrama-llantas.blade.php`, `partials/fotos.blade.php`, `partials/punto.blade.php`, `partials/estado-subida.blade.php`, `intercambio.js`, `intercambio-almacen.js`, `config/intercambios.php`, `create_bitacora_intercambios_table.php`
- **Realizado por:** Ivan Lopez

### 10. PDF de contrato de intercambio e inspección CTPAT
- **Fecha:** 20 oct 2026 – 29 oct 2026
- **Archivos necesarios:** `PdfIntercambio.php`, `pdf/contrato-intercambio.blade.php`, `pdf/inspeccion-ctpat.blade.php`
- **Realizado por:** Ivan Lopez

### 11. Historial y detalle de intercambios
- **Fecha:** 26 oct 2026 – 31 oct 2026
- **Archivos necesarios:** `IntercambioController.php` (index, show, foto, pdf, firma), `admin/intercambio/historial.blade.php`, `admin/intercambio/detalle.blade.php`
- **Realizado por:** Ivan Lopez

### 12. Dashboard y gráficas
- **Fecha:** 02 nov 2026 – 06 nov 2026
- **Archivos necesarios:** `DashboardController.php`, `admin/dashboard.blade.php`, `graficas.js`
- **Realizado por:** Ivan Lopez

### 13. Estado del patio
- **Fecha:** 03 nov 2026 – 07 nov 2026
- **Archivos necesarios:** `PatioController.php`, `EstadoPatio.php`, `admin/intercambio/patio.blade.php`
- **Realizado por:** Ivan Lopez

### 14. Limpieza y depuración automática de fotos
- **Fecha:** 09 nov 2026 – 11 nov 2026
- **Archivos necesarios:** `LimpiarFotosIntercambios.php`, `DepurarFotosIntercambios.php`, `routes/console.php`
- **Realizado por:** Ivan Lopez

### 15. Reportes y exportación (historial y patio)
- **Fecha:** 10 nov 2026 – 13 nov 2026
- **Archivos necesarios:** `ReporteIntercambioController.php`, `ReporteIntercambios.php`, `admin/intercambio/exportar.blade.php`, `pdf/reporte.blade.php`
- **Realizado por:** Ivan Lopez
