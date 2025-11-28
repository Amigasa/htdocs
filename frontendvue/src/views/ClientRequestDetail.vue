<template>
  <div>
    <div class="card">
      <h3>Детали заявки</h3>
      <div v-if="!lead">Загрузка...</div>
      <div v-else>
        <div style="margin-bottom:12px"><button class="btn btn-secondary" @click="router.push('/client/requests')">← Назад</button></div>
        <div><strong>#{{ lead.id }}</strong> — {{ lead.name }}</div>
        <div>{{ lead.phone || lead.email }}</div>
        <div>Проект: {{ lead.project_name || '—' }}</div>
        <div style="margin-top:8px">Статус: <span class="badge" :class="'badge-' + getStatusColor(lead.status)">{{ getStatusText(lead.status) }}</span></div>
        <div style="margin-top:12px">
          <h4>Статус заявки</h4>
          <div class="status-timeline">
            <div :class="['status-item', statusClass('new', lead.status)]">
              <div class="status-dot"></div>
              <div class="status-content"><div class="status-name">Новая</div><div class="status-date">{{ formatDate(lead.created_at) }}</div></div>
            </div>
            <div :class="['status-item', statusClass('in_progress', lead.status)]">
              <div class="status-dot"></div>
              <div class="status-content"><div class="status-name">В работе</div><div class="status-date">--</div></div>
            </div>
            <div :class="['status-item', statusClass('success', lead.status)]">
              <div class="status-dot"></div>
              <div class="status-content"><div class="status-name">Успешно</div><div class="status-date">--</div></div>
            </div>
          </div>
        </div>
        <div style="margin-top:12px">Просмотры: <strong>{{ lead.views || 0 }}</strong></div>
        <div style="margin-top:10px">Сообщение:<div class="card" style="padding:12px; margin-top:6px">{{ lead.message || '—' }}</div></div>
        <div v-if="lead.operator_comment" style="margin-top:12px"><h4>Комментарий оператора:</h4><div class="card" style="padding:12px; margin-top:6px">{{ lead.operator_comment }}</div></div>
      </div>
    </div>
  </div>
</template>

<script>
import ApiService from '../services/ApiService';
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
const api = new ApiService();
export default {
  name: 'ClientRequestDetail',
  setup() {
    const route = useRoute();
    const router = useRouter();
    const lead = ref(null);
    async function load() {
      const id = route.params.id;
      if (!id) return;
      const res = await api.getLead(id).catch(() => ({ leads: [] }));
      // getLead returns { leads: [...] } per leads.php structure
      if (res.leads && res.leads.length > 0) lead.value = res.leads[0];
      // increment views for tracking (optional)
      try { await api.incrementLeadViews(id).then(r => { if (r && r.lead) lead.value = r.lead; }); } catch (e) {}
    }
    onMounted(load);
    function getStatusColor(status) { if (status === 'new') return 'warning'; if (status === 'in_progress') return 'primary'; if (status === 'success') return 'success'; return 'primary'; }
    function getStatusText(status) { if (status === 'new') return 'Новая'; if (status === 'in_progress') return 'В работе'; if (status === 'success') return 'Успешно'; return status; }
    function statusClass(stepKey, current) {
      if (!current) return '';
      const order = ['new','in_progress','success'];
      const stepIdx = order.indexOf(stepKey);
      const curIdx = order.indexOf(current);
      if (stepIdx < 0) return '';
      if (curIdx === stepIdx) return 'current';
      if (curIdx > stepIdx) return 'completed';
      return '';
    }
    function formatDate(dt) { if (!dt) return '--'; const d = new Date(dt); return d.toLocaleDateString() + ' ' + d.toLocaleTimeString(); }
    return { lead, getStatusColor, getStatusText, statusClass, formatDate, router };
  }
};
</script>

<style scoped>
/* Minimal styles, reusing global CSS */
</style>
