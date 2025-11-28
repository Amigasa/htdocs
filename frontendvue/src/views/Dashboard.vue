<template>
  <div>
    <div v-if="!isOperator" class="stats-grid">
      <div class="stat-card">
        <div class="stat-number">{{ stats.total }}</div>
        <div class="stat-label">Всего заявок</div>
      </div>
      <div class="stat-card">
        <div class="stat-number">{{ stats.new }}</div>
        <div class="stat-label">Новые заявки</div>
      </div>
      <div class="stat-card">
        <div class="stat-number">{{ stats.inProgress }}</div>
        <div class="stat-label">В работе</div>
      </div>
      <div class="stat-card">
        <div class="stat-number">{{ stats.success }}</div>
        <div class="stat-label">Успешно</div>
      </div>
    </div>

    <div v-if="!isOperator" class="charts-row">
      <div class="chart-card">
        <h4>Статистика по статусам</h4>
        <canvas ref="statusChartCanvas" aria-label="Status chart"></canvas>
      </div>
      <div class="chart-card">
        <h4>Топ проектов (по количеству заявок)</h4>
        <canvas ref="projectChartCanvas" aria-label="Projects chart"></canvas>
      </div>
    </div>

    <div class="card">
      <h3>Последние заявки</h3>
      <div v-if="leads.length === 0">Нет заявок</div>
      <div v-for="lead in leads.slice(0,5)" :key="lead.id" class="latest-item">
        <div>
          <div class="lead-title">{{ lead.name }}</div>
          <div class="lead-sub">{{ lead.project_name || 'Без проекта' }}</div>
        </div>
        <div style="text-align:right">
          <div class="lead-contact">{{ lead.phone || lead.email }}</div>
          <span class="badge" :class="'badge-' + getStatusColor(lead.status)">{{ getStatusText(lead.status) }}</span>
        </div>
      </div>
    </div>
    <div v-if="isOperator" class="card" style="margin-top: 16px;">
      <h3>Мой проект</h3>
      <div v-if="userProject">
        <div style="font-weight:600">{{ userProject.name }}</div>
        <div style="color:var(--gray-600); margin-top:8px">Заявок: {{ userProject.lead_count || 0 }}</div>
      </div>
      <div v-else>Проект не назначен</div>
    </div>
  </div>
</template>

<script>
import ApiService from '../services/ApiService';
import { ref, onMounted, onBeforeUnmount } from 'vue';
import Chart from 'chart.js/auto';

export default {
  name: 'Dashboard',
  setup() {
    const api = new ApiService();
    const leads = ref([]);
    const stats = ref({ total: 0, new: 0, inProgress: 0, success: 0 });
    const isOperator = JSON.parse(localStorage.getItem('qlm_user') || 'null')?.role === 'operator';
    const statusChartCanvas = ref(null);
    const projectChartCanvas = ref(null);
    let statusChart = null;
    let projectChart = null;
    const projects = ref([]);
    const userProject = ref(null);

    async function load() {
      try {
        const user = JSON.parse(localStorage.getItem('qlm_user') || 'null');
        const res = await api.getLeads(user?.id, user?.role).catch(() => ({ leads: [] }));
        leads.value = res.leads || [];
        stats.value.total = leads.value.length;
        stats.value.new = leads.value.filter(l => l.status === 'new').length;
        stats.value.inProgress = leads.value.filter(l => l.status === 'in_progress').length;
        stats.value.success = leads.value.filter(l => l.status === 'success').length;
      } catch (err) { console.error(err); }
      // If operator, load projects and find assigned project
      if (isOperator) {
        try {
          const resP = await api.getProjects().catch(() => ({ projects: [] }));
          projects.value = resP.projects || [];
          const user = JSON.parse(localStorage.getItem('qlm_user') || 'null');
          userProject.value = projects.value.find(p => p.id === user?.project_id) || null;
        } catch (err) { /* ignore */ }
      }
      // update charts after set
      updateCharts();
    }
    onMounted(load);
    onBeforeUnmount(() => {
      if (statusChart) statusChart.destroy();
      if (projectChart) projectChart.destroy();
    });
    function updateCharts() {
      // status data
      const byStatus = { new: 0, in_progress: 0, success: 0 };
      const byProject = {};
      leads.value.forEach(l => {
        const s = l.status || 'unknown';
        byStatus[s] = (byStatus[s] || 0) + 1;
        const p = l.project_name || 'Без проекта';
        byProject[p] = (byProject[p] || 0) + 1;
      });
      stats.value.total = leads.value.length;
      stats.value.new = byStatus.new || 0;
      stats.value.inProgress = byStatus.in_progress || 0;
      stats.value.success = byStatus.success || 0;

      const statusLabels = Object.keys(byStatus);
      const statusData = statusLabels.map(k => byStatus[k]);

      if (statusChart) {
        statusChart.data.labels = statusLabels;
        statusChart.data.datasets[0].data = statusData;
        statusChart.update();
      } else if (statusChartCanvas.value) {
        statusChart = new Chart(statusChartCanvas.value.getContext('2d'), {
          type: 'doughnut',
          data: {
            labels: statusLabels,
            datasets: [{ data: statusData, backgroundColor: ['#F59E0B', '#2563EB', '#10B981'] }]
          },
          options: { responsive: true, maintainAspectRatio: false }
        });
      }

      const projectEntries = Object.entries(byProject).sort((a,b) => b[1] - a[1]).slice(0,8);
      const projectLabels = projectEntries.map(e => e[0]);
      const projectData = projectEntries.map(e => e[1]);
      if (projectChart) {
        projectChart.data.labels = projectLabels;
        projectChart.data.datasets[0].data = projectData;
        projectChart.update();
      } else if (projectChartCanvas.value) {
        projectChart = new Chart(projectChartCanvas.value.getContext('2d'), {
          type: 'bar',
          data: {
            labels: projectLabels,
            datasets: [{ label: 'Заявки', data: projectData, backgroundColor: projectData.map((_,i)=>`hsl(${(i*40)%360} 70% 50%)`) }]
          },
          options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
        });
      }
    }
    function getStatusColor(status) { if (status === 'new') return 'warning'; if (status === 'in_progress') return 'primary'; if (status === 'success') return 'success'; return 'primary'; }
    function getStatusText(status) { if (status === 'new') return 'Новая'; if (status === 'in_progress') return 'В работе'; if (status === 'success') return 'Успешно'; return status; }

    return { leads, stats, getStatusColor, getStatusText, isOperator, projects, userProject };
  }
};
</script>

<style scoped>
/* dashboard local styles */
.stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 18px; }
.stat-card { background: #fff; padding: 18px; border-radius: 8px; box-shadow: var(--shadow-sm); }
.stat-number { font-size: 22px; font-weight: 700; }
.stat-label { font-size: 12px; color: var(--gray-600); }
.charts-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 18px; }
.chart-card { background: #fff; padding: 12px; border-radius: 8px; height: 260px; box-shadow: var(--shadow-sm); }
.chart-card h4 { margin: 0 0 8px 0; font-size: 14px; }
.chart-card canvas { width: 100% !important; height: calc(100% - 30px) !important; }
.latest-item { display:flex; justify-content:space-between; padding:12px; background:#F9FAFB;border-radius:8px; margin-bottom:8px; }
.lead-title { font-weight:500; }
.lead-sub { font-size:12px; color:#6B7280; }
.lead-contact { color:#6B7280; }
@media (max-width: 900px) {
  .stats-grid { grid-template-columns: repeat(2, 1fr); }
  .charts-row { grid-template-columns: 1fr; }
}
</style>
