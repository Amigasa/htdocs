<template>
  <div>
    <div class="page-header">
      <div class="page-actions">
        <button class="btn btn-secondary" @click="showStatistics">📊 Статистика</button>
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
          <td><button class="btn-icon" @click="viewLeadDetails(lead.id)">👁️</button> <button v-if="isOperator || isAdmin" class="btn-icon" @click="editLeadStatus(lead.id, lead.status)">✏️</button></td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script>
import ApiService from '../services/ApiService';
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
const api = new ApiService();
export default {
  name: 'Leads',
  setup() {
    const leads = ref([]);
    const user = JSON.parse(localStorage.getItem('qlm_user') || 'null');
    const isOperator = user?.role === 'operator';
    const isAdmin = user?.role === 'admin';
    const router = useRouter();
    async function load() { const res = await api.getLeads(user?.id, user?.role).catch(() => ({ leads: [] })); leads.value = res.leads || []; }
    onMounted(load);
    function getStatusColor(status) { if (status === 'new') return 'warning'; if (status === 'in_progress') return 'primary'; if (status === 'success') return 'success'; return 'primary'; }
    function getStatusText(status) { if (status === 'new') return 'Новая'; if (status === 'in_progress') return 'В работе'; if (status === 'success') return 'Успешно'; return status; }
    function showStatistics() { alert('Показать статистику (demo)'); }
    function exportLeads() { alert('Выгрузка CSV (demo)'); }
    function viewLeadDetails(id) { if (user?.role === 'client') router.push({ name: 'client-request-detail', params: { id } }); else router.push({ name: 'admin-lead-detail', params: { id } }); }
    function editLeadStatus(id, current) { if (!(isOperator || isAdmin)) { alert('Нет прав'); return; } const status = prompt('Новый статус (new, in_progress, success)', current); if (!status) return; api.updateLeadStatus(id, status).then(r => { if (r.success) { alert('Обновлено'); load(); } else { alert('Ошибка: ' + r.message); }}).catch(err => alert(err.message)); }
    return { leads, isOperator, getStatusColor, getStatusText, showStatistics, exportLeads, viewLeadDetails, editLeadStatus };
  }
};
</script>

<style scoped>
/* Leads styles rely on global CSS */
</style>
