<template>
  <div>
    <div class="stats-grid">
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
    <div class="card">
      <h3>Последние заявки</h3>
      <div v-if="leads.length === 0">Нет заявок</div>
      <div v-for="lead in leads.slice(0,5)" :key="lead.id" style="display:flex; justify-content:space-between; padding:12px; background:#F9FAFB;border-radius:8px; margin-bottom:8px;">
        <div>
          <div style="font-weight:500">{{ lead.name }}</div>
          <div style="font-size:12px;color:#6B7280">{{ lead.project_name || 'Без проекта' }}</div>
        </div>
        <div style="text-align:right">
          <div style="color:#6B7280">{{ lead.phone || lead.email }}</div>
          <span class="badge" :class="'badge-' + getStatusColor(lead.status)">{{ getStatusText(lead.status) }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import ApiService from '../services/ApiService';
import { ref, onMounted } from 'vue';

export default {
  name: 'Dashboard',
  setup() {
    const api = new ApiService();
    const leads = ref([]);
    const stats = ref({ total: 0, new: 0, inProgress: 0, success: 0 });

    async function load() {
      try {
        const res = await api.getLeads().catch(() => ({ leads: [] }));
        leads.value = res.leads || [];
        stats.value.total = leads.value.length;
        stats.value.new = leads.value.filter(l => l.status === 'new').length;
        stats.value.inProgress = leads.value.filter(l => l.status === 'in_progress').length;
        stats.value.success = leads.value.filter(l => l.status === 'success').length;
      } catch (err) { console.error(err); }
    }
    onMounted(load);
    function getStatusColor(status) { if (status === 'new') return 'warning'; if (status === 'in_progress') return 'primary'; if (status === 'success') return 'success'; return 'primary'; }
    function getStatusText(status) { if (status === 'new') return 'Новая'; if (status === 'in_progress') return 'В работе'; if (status === 'success') return 'Успешно'; return status; }

    return { leads, stats, getStatusColor, getStatusText };
  }
};
</script>

<style scoped>
/* dashboard local styles (none required, global CSS in main.css) */
</style>
