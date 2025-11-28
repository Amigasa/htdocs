<template>
  <div class="admin-layout">
    <aside class="sidebar">
      <div class="sidebar-header"><h2>🏠 QuickLead Admin</h2></div>
        <nav class="sidebar-nav">
          <router-link to="/admin" class="nav-item" data-page="dashboard" active-class="active">📊 Дашборд</router-link>
          <router-link to="/admin/projects" class="nav-item" data-page="projects" active-class="active">📁 Проекты</router-link>
          <router-link v-if="userRole === 'admin'" to="/admin/users" class="nav-item" data-page="users" active-class="active">👥 Пользователи</router-link>
          <router-link to="/admin/leads" class="nav-item" data-page="leads" active-class="active">📨 Заявки</router-link>
          <button class="nav-item logout-btn" data-page="logout" @click="logout">🚪 Выход</button>
        </nav>
    </aside>
    <main class="admin-main">
      <header class="admin-header"><h1>{{ pageTitle }}</h1><div class="user-menu">👤 {{ userName }} ({{ userRole }})</div></header>
      <div class="admin-content"><router-view /></div>
    </main>
  </div>
</template>

<script>
import { computed } from 'vue';
import { useRouter, useRoute } from 'vue-router';

export default {
  name: 'AdminLayout',
  setup() {
    const router = useRouter();
    const route = useRoute();
    const user = JSON.parse(localStorage.getItem('qlm_user') || 'null');
    const pageTitle = computed(() => {
      const name = route.name || 'admin-dashboard';
      const mapping = {
        'admin-dashboard': '📊 Дашборд',
        'admin-projects': '📁 Проекты',
        'admin-users': '👥 Пользователи',
        'admin-leads': '📨 Заявки'
      };
      return mapping[name] || 'Панель управления';
    });
    return { router, route, userName: user?.name || 'Guest', userRole: user?.role || 'guest', pageTitle };
  },
  methods: {
    logout() { localStorage.removeItem('qlm_user'); this.$router.push('/login'); }
  }
};
</script>

<style scoped>
/* Local styles for Admin layout */
</style>
