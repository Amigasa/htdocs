<template>
  <div class="my-requests-container">
    <div class="card">
      <h3>Мои заявки</h3>
      <div class="requests-list">
        <div v-for="r in requests" :key="r.id" class="request-item" @click="openRequest(r.id)" style="cursor:pointer">
          <div class="request-header">
            <div class="request-id">#{{ r.id }}</div>
            <div class="request-status"><span class="badge" :class="'badge-' + getStatusColor(r.status)">{{ getStatusText(r.status) }}</span></div>
          </div>
          <div class="request-description">{{ r.message || '-' }}</div>
          <div class="request-date">{{ new Date(r.created_at).toLocaleDateString() }}</div>
        </div>
        <div v-if="requests.length === 0">Нет заявок</div>
      </div>
    </div>
  </div>
</template>

<script>
import ApiService from '../services/ApiService';
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
const api = new ApiService();
export default {
  name: 'ClientRequests',
  setup() {
    const requests = ref([]);
    const router = useRouter();
    async function load() { const user = JSON.parse(localStorage.getItem('qlm_user') || 'null'); const res = await api.getLeads(user?.id, user?.role).catch(() => ({ leads: [] })); requests.value = res.leads || []; }
    onMounted(load);
    function getStatusColor(status) { if (status === 'new') return 'warning'; if (status === 'in_progress') return 'primary'; if (status === 'success') return 'success'; return 'primary'; }
    function getStatusText(status) { if (status === 'new') return 'Новая'; if (status === 'in_progress') return 'В работе'; if (status === 'success') return 'Успешно'; return status; }
    function openRequest(id) { router.push({ name: 'client-request-detail', params: { id } }); }
    return { requests, load, getStatusColor, getStatusText, openRequest };
  }
};
</script>

<style scoped>
/* ClientRequests uses global CSS */
</style>
