<template>
  <div>
    <div class="page-header">
      <button class="btn btn-primary" @click="createUser">👤 Добавить пользователя</button>
    </div>
    <table class="table">
      <thead><tr><th>ID</th><th>Имя</th><th>Email</th><th>Роль</th><th>Проект</th><th>Дата</th><th>Действия</th></tr></thead>
      <tbody>
        <tr v-for="u in users" :key="u.id">
          <td>{{ u.id }}</td>
          <td>{{ u.name }}</td>
          <td>{{ u.email }}</td>
          <td><span class="badge" :class="u.role === 'admin' ? 'badge-danger' : 'badge-primary'">{{ u.role }}</span></td>
          <td>{{ u.project_id ? (projects.find(p => p.id === u.project_id)?.name || u.project_id) : '-' }}</td>
          <td>{{ new Date(u.created_at).toLocaleDateString() }}</td>
          <td><button class="btn-icon" @click="editUser(u.id)">✏️</button> <button class="btn-icon" style="color:#EF4444" @click="deleteUser(u.id, u.name)">🗑️</button></td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script>
import ApiService from '../services/ApiService';
import { ref, onMounted } from 'vue';
const api = new ApiService();
export default {
  name: 'Users',
  setup() {
    const users = ref([]);
    const projects = ref([]);
    async function load() { const res = await api.getUsers().catch(() => ({ users: [] })); users.value = res.users || []; }
    async function loadProjects() { const res = await api.getProjects().catch(() => ({ projects: [] })); projects.value = res.projects || []; }
    onMounted(() => { load(); loadProjects(); });
    async function createUser() { const name = prompt('Введите имя'); const email = prompt('Email'); const role = prompt('Роль (admin, operator, client)', 'client'); const password = prompt('Пароль (мин 6)'); if (!name || !email || !password) { alert('Заполните поля'); return; } let project_id = null; if (role === 'operator') { const choices = projects.value.map(p => `${p.id}: ${p.name}`).join('\n'); const pid = prompt('ID проекта для оператора (оставьте пустым если не нужно):\n' + choices); if (pid) project_id = parseInt(pid); } const res = await api.createUser({ username: email.split('@')[0], name, email, role, password, project_id }).catch(err => { alert('Ошибка: ' + err.message); return null; }); if (res && res.success) { alert('Создан'); load(); } }
    async function editUser(id) {
      const res = await api.getUsers().catch(() => ({ users: [] }));
      const u = res.users.find(x => x.id === id);
      if (!u) return;
      const name = prompt('Новое имя', u.name);
      const email = prompt('Новый email', u.email);
      const role = prompt('Роль', u.role);
      let project_id = u.project_id || null;
      if (role === 'operator') {
        const choices = projects.value.map(p => `${p.id}: ${p.name}`).join('\n');
        const pid = prompt('ID проекта для оператора (оставьте пустым если не нужно):\n' + choices, project_id);
        project_id = pid ? parseInt(pid) : null;
      } else {
        project_id = null;
      }
      const updated = await api.updateUser(id, { name, email, role, project_id }).catch(err => { alert('Ошибка: ' + err.message); return null; });
      if (updated && updated.success) { alert('Обновлено'); load(); }
    }
    async function deleteUser(id, name) { if (!confirm(`Удалить пользователя ${name}?`)) return; const res = await api.deleteUser(id).catch(err => { alert('Ошибка: ' + err.message); return null; }); if (res && res.success) { alert('Удален'); load(); } }

    return { users, createUser, editUser, deleteUser };
  }
};
</script>

<style scoped>
/* Users styles rely on global CSS */
</style>
