<template>
  <div>
    <div class="page-header">
      <button v-if="isAdmin" class="btn btn-primary" @click="createProject">➕ Создать проект</button>
    </div>
    <table class="table">
      <thead><tr><th>ID</th><th>Название</th><th>API-ключ</th><th>Описание</th><th>Заявок</th><th>Действия</th></tr></thead>
      <tbody>
        <tr v-for="p in projects" :key="p.id">
          <td>{{ p.id }}</td>
          <td>{{ p.name }}</td>
          <td><code>{{ p.api_key }}</code></td>
          <td>{{ p.description }}</td>
          <td>{{ p.lead_count || '0' }}</td>
          <td>
            <button v-if="isAdmin" class="btn-icon" @click="editProject(p.id)">✏️</button>
            <button v-if="isAdmin" class="btn-icon" style="color:#EF4444" @click="deleteProject(p.id, p.name)">🗑️</button>
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
  name: 'Projects',
  setup() {
    const projects = ref([]);
    const user = JSON.parse(localStorage.getItem('qlm_user') || 'null');
    const isAdmin = user?.role === 'admin';
    async function load() { const res = await api.getProjects().catch(() => ({ projects: [] })); projects.value = res.projects || []; }
    onMounted(load);

    async function createProject() {
      if (!isAdmin) { alert('Нет прав'); return; }
      const name = prompt('Введите название проекта:');
      if (!name) return; const desc = prompt('Описание проекта (необязательно):');
      const res = await api.createProject({ name, description: desc }).catch(err => { alert('Ошибка: ' + err.message); return null; });
      if (res && res.success) { alert(`Проект "${name}" создан`); load(); }
    }

    async function editProject(id) { if (!isAdmin) { alert('Нет прав'); return; } const res = (await api.getProjects().catch(() => ({ projects: [] }))); const p = res.projects.find(x => x.id === id); if (!p) return; const newName = prompt('Новое имя', p.name); const newDesc = prompt('Описание', p.description || ''); if (newName && newName !== p.name) { const updated = await api.updateProject(id, { name: newName, description: newDesc }).catch(err => { alert('Ошибка: ' + err.message); return null; }); if (updated && updated.success) { alert('Обновлено'); load(); }} }

    async function deleteProject(id, name) { if (!isAdmin) { alert('Нет прав'); return; } if (!confirm(`Удалить проект ${name}?`)) return; const res = await api.deleteProject(id).catch(err => { alert('Ошибка: ' + err.message); return null; }); if (res && res.success) { alert('Удален'); load(); } }

    return { projects, createProject, editProject, deleteProject, isAdmin };
  }
};
</script>

<style scoped>
/* Projects styles rely on global CSS */
</style>
