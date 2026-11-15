/*
 * ************************************************************
 * Dashboard y gráficas
 * Archivos necesarios:
 *   DashboardController.php, admin/dashboard.blade.php, graficas.js
 * Fecha: 02 nov 2026 - 06 nov 2026
 * Realizado por: Ivan Lopez
 * ************************************************************
 */

// Gráficas del dashboard de administración. Debe cargarse antes de app.js (Alpine).
import Chart from 'chart.js/auto';

window.Chart = Chart;
