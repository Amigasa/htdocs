<template>
  <div>
    <div class="card">
      <h3>Детали заявки (админ)</h3>
      <div v-if="!lead">Загрузка...</div>
      <div v-else>
        <div style="margin-bottom:12px"><button class="btn btn-secondary" @click="$router.push('/admin/leads')">← Назад</button></div>
        <div><strong>#{{ lead.id }}</strong> — {{ lead.name }}</div>
        <div>{{ lead.phone || lead.email }}</div>
        <div>Проект: {{ lead.project_name || '—' }}</div>
        <div>Статус: <span class="badge" :class="'badge-' + getStatusColor(lead.status)">{{ getStatusText(lead.status) }}</span></div>
        <div style="margin-top:6px">Просмотры: <strong>{{ lead.views || 0 }}</strong></div>
        <div style="margin-top:10px">Сообщение:<div class="card" style="padding:12px; margin-top:6px">{{ lead.message || '—' }}</div></div>
      </div>
    </div>
  </div>
</template>

<script>
import ApiService from '../services/ApiService';
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
const api = new ApiService();
export default {
  name: 'AdminLeadDetail',
  setup() {
    const route = useRoute();
    const lead = ref(null);
    async function load() {
      const id = route.params.id;
      if (!id) return;
      const res = await api.getLead(id).catch(() => ({ leads: [] }));
      if (res.leads && res.leads.length > 0) lead.value = res.leads[0];
      // Increment view count for analytics
      try {
        const inc = await api.incrementLeadViews(id).catch(() => ({ success: false }));
        if (inc && inc.success && inc.lead) {
          lead.value = inc.lead;
        }
      } catch (err) { /* ignore */ }
    }
    onMounted(load);
    function getStatusColor(status) { if (status === 'new') return 'warning'; if (status === 'in_progress') return 'primary'; if (status === 'success') return 'success'; return 'primary'; }
    function getStatusText(status) { if (status === 'new') return 'Новая'; if (status === 'in_progress') return 'В работе'; if (status === 'success') return 'Успешно'; return status; }

    return { lead, getStatusColor, getStatusText };
  }
};
</script>

<style scoped>
</style>
