<template>
  <div>
    <div class="page-header">
      <button v-if="isAdmin" class="btn btn-primary" @click="createUser">👤 Добавить пользователя</button>
    </div>
    <table class="table">
      <thead><tr><th>ID</th><th>Имя</th><th>Email</th><th>Роль</th><th>Дата</th><th>Действия</th></tr></thead>
      <tbody>
        <tr v-for="u in users" :key="u.id">
          <td>{{ u.id }}</td>
          <td>{{ u.name }}</td>
          <td>{{ u.email }}</td>
          <td><span class="badge" :class="u.role === 'admin' ? 'badge-danger' : 'badge-primary'">{{ u.role }}</span></td>
          <td>{{ new Date(u.created_at).toLocaleDateString() }}</td>
          <td>
            <button v-if="isAdmin" class="btn-icon" @click="editUser(u.id)">✏️</button>
            <button v-if="isAdmin" class="btn-icon" style="color:#EF4444" @click="deleteUser(u.id, u.name)">🗑️</button>
          </td>
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
    const user = JSON.parse(localStorage.getItem('qlm_user') || 'null');
    const isAdmin = user?.role === 'admin';
    async function load() { const res = await api.getUsers().catch(() => ({ users: [] })); users.value = res.users || []; }
    onMounted(load);
    async function createUser() { if (!isAdmin) { alert('Нет прав'); return; } const name = prompt('Введите имя'); const email = prompt('Email'); const role = prompt('Роль (admin, operator, client)', 'client'); const password = prompt('Пароль (мин 6)'); if (!name || !email || !password) { alert('Заполните поля'); return; } const res = await api.createUser({ username: email.split('@')[0], name, email, role, password }).catch(err => { alert('Ошибка: ' + err.message); return null; }); if (res && res.success) { alert('Создан'); load(); } }
    async function editUser(id) { if (!isAdmin) { alert('Нет прав'); return; } const res = await api.getUsers().catch(() => ({ users: [] })); const u = res.users.find(x => x.id === id); if (!u) return; const name = prompt('Новое имя', u.name); const email = prompt('Новый email', u.email); const role = prompt('Роль', u.role); const updated = await api.updateUser(id, { name, email, role }).catch(err => { alert('Ошибка: ' + err.message); return null; }); if (updated && updated.success) { alert('Обновлено'); load(); } }
    async function deleteUser(id, name) { if (!isAdmin) { alert('Нет прав'); return; } if (!confirm(`Удалить пользователя ${name}?`)) return; const res = await api.deleteUser(id).catch(err => { alert('Ошибка: ' + err.message); return null; }); if (res && res.success) { alert('Удален'); load(); } }

    return { users, createUser, editUser, deleteUser, isAdmin };
  }
};
</script>

<style scoped>
/* Users styles rely on global CSS */
</style>
