<template>
  <div class="client-layout">
    <header class="client-header">
      <nav class="client-nav">
        <div class="client-logo">🚀 QuickLead</div>
        <div class="client-nav-links">
          <router-link to="/client" class="client-nav-link" data-page="client-request" active-class="active">Оставить заявку</router-link>
          <router-link to="/client/requests" class="client-nav-link" data-page="client-requests" active-class="active">Мои заявки</router-link>
          <span style="color:var(--gray-600)">👤 {{ userName }}</span>
          <a href="#" class="client-nav-link" data-page="logout" @click.prevent="logout">Выйти</a>
        </div>
      </nav>
    </header>
    <main class="client-main"><router-view /></main>
  </div>
</template>

<script>
export default {
  name: 'ClientLayout',
  computed: {
    user() { return JSON.parse(localStorage.getItem('qlm_user') || 'null'); },
    userName() { return this.user?.name || 'Guest'; }
  },
  methods: { logout() { localStorage.removeItem('qlm_user'); this.$router.push('/login'); } }
};
</script>

<style scoped>
/* Client layout uses global styles from quicklead.css */
</style>