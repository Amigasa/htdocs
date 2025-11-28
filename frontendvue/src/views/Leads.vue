<template>
  <div>
    <div class="page-header">
      <div class="page-actions">
        <button v-if="!isOperator" class="btn btn-secondary" @click="showStatistics">📊 Статистика</button>
        <button class="btn btn-primary" @click="exportLeads">📤 Выгрузить</button>
      </div>
    </div>

    <table class="table">
      <thead><tr><th>ID</th><th>Имя</th><th>Телефон</th><th>Проект</th><th>Статус</th><th>Дата</th><th>Действия</th></tr></thead>
      <tbody>
        <tr v-for="lead in leads" :key="lead.id">
          <td>{{ lead.id }}</td>
          <td>{{ lead.name }}</td>
          <td>{{ lead.phone || '-' }}</td>
          <td>{{ lead.project_name || '-' }}</td>
          <td><span class="badge" :class="'badge-' + getStatusColor(lead.status)">{{ getStatusText(lead.status) }}</span></td>
          <td>{{ new Date(lead.created_at).toLocaleDateString() }}</td>
          <td><button class="btn-icon" @click="viewLeadDetails(lead.id)">👁️</button> <button v-if="isOperator" class="btn-icon" @click="editLeadStatus(lead.id, lead.status)">✏️</button></td>
        </tr>
      </tbody>
    </table>
  
    <!-- Statistics modal -->
    <div v-if="statsVisible" class="modal-overlay" @click.self="statsVisible = false">
        <div class="modal-card">
          <h3>Статистика по заявкам</h3>
          <div><strong>Всего заявок:</strong> {{ statsData.total }}</div>
          <div class="modal-chart-row" style="margin-top:8px">
            <div class="modal-chart">
              <h4 style="margin:0 0 8px 0; font-size:14px;">По статусам</h4>
              <canvas ref="statusChartCanvas"></canvas>
            </div>
            <div class="modal-chart">
              <h4 style="margin:0 0 8px 0; font-size:14px;">По проектам</h4>
              <canvas ref="projectChartCanvas"></canvas>
            </div>
          </div>
          <div style="margin-top:12px"><button class="btn btn-secondary" @click="statsVisible = false">Закрыть</button></div>
        </div>
    </div>
  </div>
</template>

<script>
import ApiService from '../services/ApiService';
import { ref, onMounted, onBeforeUnmount, computed, watch, nextTick } from 'vue';
import Chart from 'chart.js/auto';
import { useRouter } from 'vue-router';
const api = new ApiService();
export default {
  name: 'Leads',
  setup() {
    const leads = ref([]);
    const isOperator = JSON.parse(localStorage.getItem('qlm_user') || 'null')?.role === 'operator';
    const statsVisible = ref(false);
    const statsData = ref({ total: 0, byStatus: {}, byProject: {} });
    const statsProjects = computed(() => Object.entries(statsData.value.byProject || {}).sort((a, b) => b[1] - a[1]).slice(0, 10));
    const statusChartCanvas = ref(null);
    const projectChartCanvas = ref(null);
    let statusChart = null;
    let projectChart = null;
    const router = useRouter();
    async function load() { const user = JSON.parse(localStorage.getItem('qlm_user') || 'null'); const res = await api.getLeads(user?.id, user?.role).catch(() => ({ leads: [] })); leads.value = res.leads || []; }
    onMounted(load);
    function getStatusColor(status) { if (status === 'new') return 'warning'; if (status === 'in_progress') return 'primary'; if (status === 'success') return 'success'; return 'primary'; }
    function getStatusText(status) { if (status === 'new') return 'Новая'; if (status === 'in_progress') return 'В работе'; if (status === 'success') return 'Успешно'; return status; }
    async function showStatistics() {
      // Ensure leads are loaded
      if (!leads.value || leads.value.length === 0) { load(); }
      // Compute statistics from leads
      const total = leads.value.length;
      const byStatus = {};
      const byProject = {};
      leads.value.forEach(l => {
        const s = l.status || 'unknown';
        byStatus[s] = (byStatus[s] || 0) + 1;
        const p = l.project_name || '—';
        byProject[p] = (byProject[p] || 0) + 1;
      });
      statsData.value = { total, byStatus, byProject };
      statsVisible.value = true;
      // wait for DOM to render and then update charts
      await nextTick();
      updateCharts();
    }

    function updateCharts() {
      const byStatus = statsData.value.byStatus || {};
      const statusLabels = Object.keys(byStatus);
      const statusValues = statusLabels.map(k => byStatus[k] || 0);

      // create/update status doughnut
      if (statusChart) {
        statusChart.data.labels = statusLabels;
        statusChart.data.datasets[0].data = statusValues;
        statusChart.update();
      } else if (statusChartCanvas.value) {
        const ctx = statusChartCanvas.value.getContext('2d');
        statusChart = new Chart(ctx, {
          type: 'doughnut',
          data: { labels: statusLabels, datasets: [{ data: statusValues, backgroundColor: ['#F59E0B', '#2563EB', '#10B981', '#6B7280'] }] },
          options: { responsive: true, maintainAspectRatio: false }
        });
      }

      // projects
      const byProject = statsData.value.byProject || {};
      const projectEntries = Object.entries(byProject).sort((a, b) => b[1] - a[1]).slice(0, 8);
      const projectLabels = projectEntries.map(e => e[0]);
      const projectData = projectEntries.map(e => e[1]);
      if (projectChart) {
        projectChart.data.labels = projectLabels;
        projectChart.data.datasets[0].data = projectData;
        projectChart.update();
      } else if (projectChartCanvas.value) {
        const ctx2 = projectChartCanvas.value.getContext('2d');
        projectChart = new Chart(ctx2, {
          type: 'bar',
          data: { labels: projectLabels, datasets: [{ label: 'Заявки', data: projectData, backgroundColor: projectData.map((_, i) => `hsl(${(i*50)%360} 70% 50%)`) }] },
          options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
        });
      }
    }
    async function exportLeads() {
      // Try server-side CSV export first
      try {
        const user = JSON.parse(localStorage.getItem('qlm_user') || 'null');
        const blob = await api.exportLeadsBackend(user?.id, user?.role);
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.setAttribute('download', `leads_export_${new Date().toISOString().slice(0,19).replace(/[:T]/g,'-')}.csv`);
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        return;
      } catch (err) {
        // Fallback to client-side CSV generation
        const rows = leads.value || [];
        if (!rows.length) { alert('Нет данных для выгрузки'); return; }
        const cols = ['id','name','phone','email','project_name','status','created_at','views','message'];
        const escapeCell = (v) => {
          if (v === null || v === undefined) return '';
          const s = String(v).replace(/"/g, '""');
          return '"' + s + '"';
        };
        const csv = [cols.join(',')].concat(rows.map(r => cols.map(c => escapeCell(r[c])).join(','))).join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.setAttribute('download', `leads_export_${new Date().toISOString().slice(0,19).replace(/[:T]/g,'-')}.csv`);
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
      }
    }
    function viewLeadDetails(id) { router.push(`/admin/leads/${id}`); }
    function editLeadStatus(id, current) { if (!isOperator) { alert('Нет прав'); return; } const status = prompt('Новый статус (new, in_progress, success)', current); if (!status) return; api.updateLeadStatus(id, status).then(r => { if (r.success) { alert('Обновлено'); load(); } else { alert('Ошибка: ' + r.message); }}).catch(err => alert(err.message)); }
    onBeforeUnmount(() => {
      if (statusChart) { statusChart.destroy(); statusChart = null; }
      if (projectChart) { projectChart.destroy(); projectChart = null; }
    });

    watch(statsVisible, (v) => { if (!v) { if (statusChart) { statusChart.destroy(); statusChart = null; } if (projectChart) { projectChart.destroy(); projectChart = null; } } });

    return { leads, isOperator, getStatusColor, getStatusText, showStatistics, exportLeads, viewLeadDetails, editLeadStatus, statsVisible, statsData, statsProjects, statusChartCanvas, projectChartCanvas };
  }
};
</script>

<style scoped>
/* Leads styles rely on global CSS */
 .modal-overlay { position: fixed; inset: 0; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.4); z-index: 2000; }
 .modal-card { background: white; padding: 16px; border-radius: 8px; width: 900px; max-width: calc(100% - 32px); box-shadow: 0 6px 16px rgba(0,0,0,0.16); display:flex; gap:12px; flex-direction:column; }
 .modal-chart-row { display:flex; gap:12px; align-items:stretch; }
 .modal-chart { flex: 1; height: 260px; }
 .modal-card h3 { margin-top: 0; }
</style>
