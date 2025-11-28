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
        <canvas ref="statusChartCanvas" aria-label="Status chart" style="width:100%; height:180px;"></canvas>
      </div>
      <div class="chart-card">
        <h4>Топ проектов (по количеству заявок)</h4>
        <canvas ref="projectChartCanvas" aria-label="Projects chart" style="width:100%; height:180px;"></canvas>
      </div>
    </div>
    <div v-if="!isOperator" style="margin-bottom:16px; background:#fff; padding:10px; border-radius:8px; box-shadow:var(--shadow-sm);">
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
import { ref, onMounted, onBeforeUnmount, nextTick, reactive } from 'vue';
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
    const cachedStats = reactive({ byStatus: {}, byProject: {} });
    const projects = ref([]);
    const userProject = ref(null);

    async function load() {
      console.debug('Dashboard load - starting');
      try {
        const user = JSON.parse(localStorage.getItem('qlm_user') || 'null');
        const res = await api.getLeads(user?.id, user?.role).catch(() => ({ leads: [] }));
        leads.value = res.leads || [];
        // Fetch aggregated stats from server for charts
        try {
          const st = await api.getLeadsStats(user?.id, user?.role).catch(() => ({ stats: { total: 0, byStatus: {}, byProject: {} } }));
            const s = st.stats || { total:0, byStatus: {}, byProject: {} };
            console.debug('Dashboard load: server stats response', s);
          stats.value.total = s.total || leads.value.length;
          stats.value.new = s.byStatus?.new || 0;
          stats.value.inProgress = s.byStatus?.in_progress || 0;
          stats.value.success = s.byStatus?.success || 0;
          // Update charts using server-provided data
          // Prepare byStatus/byProject objects and call updateCharts
          // set leads-aggregates to reuse updateCharts
          const byStatus = s.byStatus || {};
          const byProject = s.byProject || {};
          // temporary set for updateCharts to pick up
          // we will compute in updateCharts from server-provided objects
          // store them on a small local object
          cachedStats.byStatus = byStatus;
          cachedStats.byProject = byProject;
          console.debug('Dashboard load - cachedStats set', JSON.parse(JSON.stringify(cachedStats)));
        } catch (err) {
          console.error('Error fetching stats from server:', err);
        }
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
      // update charts after set and after DOM paints and layout
      await nextTick();
      await new Promise(resolve => requestAnimationFrame(resolve));
      console.debug('Dashboard load - calling updateCharts');
      updateCharts();
    }
    onMounted(load);
    onBeforeUnmount(() => {
      if (statusChart) statusChart.destroy();
      if (projectChart) projectChart.destroy();
    });
    function updateCharts() {
      console.debug('Dashboard updateCharts - cachedStats', cachedStats);
      // status data
      // Prefer cachedStats from server if available
      const byStatus = Object.keys(cachedStats.byStatus).length ? cachedStats.byStatus : { new: 0, in_progress: 0, success: 0 };
      const byProject = Object.keys(cachedStats.byProject).length ? cachedStats.byProject : {};
      // keep total from server (or computed elsewhere); do not override with leads length
      stats.value.new = byStatus.new || 0;
      stats.value.inProgress = byStatus.in_progress || 0;
      stats.value.success = byStatus.success || 0;

      // Debug logging for diagnosing empty charts
      console.debug('Dashboard updateCharts - byStatus:', byStatus);
      console.debug('Dashboard updateCharts - byProject:', byProject);

      const statusLabels = Object.keys(byStatus).filter(k => byStatus[k] !== undefined);
      const statusData = statusLabels.map(k => byStatus[k]);

      // If no data present, provide a fallback label to prevent Chart from rendering blank
      if (statusLabels.length === 0 || statusData.every(v => v === 0)) {
        statusLabels.splice(0, statusLabels.length, 'Нет данных');
        statusData.splice(0, statusData.length, 1);
      }

      // enforce pixel-size for canvas so Chart.js renders correctly
      const sCanvas = statusChartCanvas.value;
      if (sCanvas) {
        const rect = sCanvas.getBoundingClientRect();
        const dpr = window.devicePixelRatio || 1;
        sCanvas.width = Math.floor(rect.width * dpr);
        sCanvas.height = Math.floor((rect.height || 180) * dpr);
        const ctxCheck = sCanvas.getContext('2d');
        if (ctxCheck) {
          ctxCheck.setTransform(dpr, 0, 0, dpr, 0, 0);
        } else {
          console.debug('Dashboard updateCharts - status canvas ctx is null');
        }
        console.debug('Dashboard status canvas size', sCanvas.width, sCanvas.height, 'client', rect.width, rect.height);
      }

      console.debug('Dashboard updateCharts - entering status chart branch', !!statusChart, !!statusChartCanvas.value, statusLabels, statusData);
      if (statusChart) {
        try {
          statusChart.data.labels = statusLabels;
          statusChart.data.datasets[0].data = statusData;
          statusChart.update();
          console.debug('Dashboard updateCharts - statusChart.update() ok');
        } catch (err) { console.error('Dashboard updateCharts - statusChart.update() error', err); }
      } else if (statusChartCanvas.value) {
        try {
          statusChart = new Chart(statusChartCanvas.value.getContext('2d'), {
          type: 'doughnut',
          data: {
            labels: statusLabels,
            datasets: [{ data: statusData, backgroundColor: ['#F59E0B', '#2563EB', '#10B981'] }]
          },
          options: { responsive: false, maintainAspectRatio: false }
          });
          console.debug('Dashboard updateCharts - statusChart created');
        } catch (err) { console.error('Dashboard create statusChart error', err); }
      }

      const projectEntries = Object.entries(byProject).sort((a,b) => b[1] - a[1]).slice(0,8);
      if (projectEntries.length === 0) {
        projectEntries.push(['Нет данных', 0]);
      }
      const projectLabels = projectEntries.map(e => e[0]);
      const projectData = projectEntries.map(e => e[1]);
      console.debug('Dashboard updateCharts - projectLabels', projectLabels, 'projectData', projectData);
      const pCanvas = projectChartCanvas.value;
      if (pCanvas) {
        const rect2 = pCanvas.getBoundingClientRect();
        const dpr2 = window.devicePixelRatio || 1;
        pCanvas.width = Math.floor(rect2.width * dpr2);
        pCanvas.height = Math.floor((rect2.height || 180) * dpr2);
        const ctxCheck2 = pCanvas.getContext('2d');
        if (ctxCheck2) {
          ctxCheck2.setTransform(dpr2, 0, 0, dpr2, 0, 0);
        } else {
          console.debug('Dashboard updateCharts - project canvas ctx is null');
        }
        console.debug('Dashboard project canvas size', pCanvas.width, pCanvas.height, 'client', rect2.width, rect2.height);
      }

      console.debug('Dashboard updateCharts - entering project chart branch', !!projectChart, !!projectChartCanvas.value, projectLabels, projectData);
      if (projectChart) {
        try {
          projectChart.data.labels = projectLabels;
          projectChart.data.datasets[0].data = projectData;
          projectChart.update();
          console.debug('Dashboard updateCharts - projectChart.update() ok');
        } catch (err) { console.error('Dashboard updateCharts - projectChart.update() error', err); }
      } else if (projectChartCanvas.value) {
        try {
          projectChart = new Chart(projectChartCanvas.value.getContext('2d'), {
          type: 'bar',
          data: {
            labels: projectLabels,
            datasets: [{ label: 'Заявки', data: projectData, backgroundColor: projectData.map((_,i)=>`hsl(${(i*40)%360} 70% 50%)`) }]
          },
          options: { responsive: false, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
          });
          console.debug('Dashboard updateCharts - projectChart created');
        } catch (err) { console.error('Dashboard create projectChart error', err); }
      }
      // draw a small debug rectangle in canvases to ensure we can draw
      try {
        const sctx = statusChartCanvas.value?.getContext('2d');
        if (sctx) { sctx.fillStyle = 'rgba(255,0,0,0.08)'; sctx.fillRect(2,2,40,20); }
        const pctx = projectChartCanvas.value?.getContext('2d');
        if (pctx) { pctx.fillStyle = 'rgba(0,0,255,0.05)'; pctx.fillRect(2,2,40,20); }
      } catch (err) { console.debug('Dashboard draw test failed', err); }
    }
    function getStatusColor(status) { if (status === 'new') return 'warning'; if (status === 'in_progress') return 'primary'; if (status === 'success') return 'success'; return 'primary'; }
    function getStatusText(status) { if (status === 'new') return 'Новая'; if (status === 'in_progress') return 'В работе'; if (status === 'success') return 'Успешно'; return status; }

    return { leads, stats, getStatusColor, getStatusText, isOperator, projects, userProject, cachedStats, statusChartCanvas, projectChartCanvas };
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
