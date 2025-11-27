<template>
  <div class="request-form-container">
    <div class="request-form-card card">
      <h2 class="request-title">Оставить заявку</h2>
      <div class="request-subtitle">Заполните форму и отправьте заявку</div>
      <form id="clientRequestForm" @submit.prevent="submit">
        <div class="form-group">
          <label class="form-label">Имя</label>
          <input id="clientName" v-model="name" class="form-input" />
        </div>
        <div class="form-group">
          <label class="form-label">Телефон</label>
          <input id="clientPhone" v-model="phone" class="form-input" />
        </div>
        <div class="form-group">
          <label class="form-label">Email</label>
          <input id="clientEmail" v-model="email" type="email" class="form-input" />
        </div>
        <div class="form-group">
          <label class="form-label">Проект</label>
          <select id="clientProject" v-model="project_id" class="form-input">
            <option :value="null">Выберите проект</option>
            <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
        </div>
        <button class="btn btn-primary" type="submit" style="width:100%">Отправить</button>
      </form>
      <div class="form-info">Поле с пометкой * обязательно</div>
    </div>
  </div>
</template>

<script>
import ApiService from '../services/ApiService';
import { ref, onMounted } from 'vue';
const api = new ApiService();
export default {
  name: 'ClientRequest',
  setup() {
    const name = ref(''); const phone = ref(''); const email = ref(''); const project_id = ref(null); const projects = ref([]);
    async function load() { const res = await api.getProjects().catch(() => ({ projects: [] })); projects.value = res.projects || []; }
    onMounted(load);
    async function submit() {
      if (!name.value) { alert('Введите имя'); return; }
      const payload = { name: name.value, phone: phone.value, email: email.value, project_id: project_id.value };
      const res = await api.createLead(payload).catch(err => { alert('Ошибка: ' + err.message); return null; });
      if (res && res.success) { alert('Заявка отправлена'); name.value = ''; phone.value = ''; email.value = ''; project_id.value = null; }
    }
    return { name, phone, email, project_id, projects, submit };
  }
};
</script>

<style scoped>
/* ClientRequest local styles (global CSS applied in main.css) */
</style>
