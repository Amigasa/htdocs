<template>
  <div class="login-container">
    <div class="login-card card">
      <h1 class="login-title">🔧 QuickLead Manager</h1>
      <div class="auth-tabs">
        <button :class="['auth-tab', page === 'login' ? 'active' : '']" @click="page = 'login'">Вход</button>
        <button :class="['auth-tab', page === 'register' ? 'active' : '']" @click="page = 'register'">Регистрация</button>
      </div>

      <div v-if="page === 'login'" id="loginFormContainer">
        <h2 class="login-subtitle">Вход в систему</h2>
        <form id="loginForm" class="login-form" @submit.prevent="onLogin">
          <div class="form-group form-input-with-icon">
            <span class="icon">👤</span>
            <input id="username" v-model="username" class="form-input" placeholder="Введите логин" required />
          </div>
          <div class="form-group form-input-with-icon">
            <span class="icon">🔒</span>
            <input id="password" v-model="password" type="password" class="form-input" placeholder="Введите пароль" required />
          </div>
          <button type="submit" class="btn btn-primary" style="width:100%">🔐 Войти в систему</button>
        </form>
      </div>

      <div v-else id="registerFormContainer">
        <h2 class="login-subtitle">Регистрация</h2>
        <form id="registerForm" class="login-form" @submit.prevent="onRegister">
          <div class="form-group form-input-with-icon">
            <span class="icon">👤</span>
            <input id="regUsername" v-model="regUsername" class="form-input" placeholder="Придумайте логин" required />
          </div>
          <div class="form-group form-input-with-icon">
            <span class="icon">📝</span>
            <input id="regName" v-model="regName" class="form-input" placeholder="Ваше полное имя" required />
          </div>
          <div class="form-group form-input-with-icon">
            <span class="icon">📧</span>
            <input id="regEmail" v-model="regEmail" type="email" class="form-input" placeholder="Ваш email" required />
          </div>
          <div class="form-group form-input-with-icon">
            <span class="icon">🔒</span>
            <input id="regPassword" v-model="regPassword" type="password" class="form-input" placeholder="Придумайте пароль" required />
          </div>
          <div class="form-group form-input-with-icon">
            <span class="icon">🔒</span>
            <input id="regPasswordConfirm" v-model="regPasswordConfirm" type="password" class="form-input" placeholder="Повторите пароль" required />
          </div>
          <button type="submit" class="btn btn-success" style="width:100%">📝 Зарегистрироваться</button>
        </form>
      </div>

      <div class="login-demo-buttons">
        <button class="btn btn-secondary" @click="demoLogin('admin')" style="width:100%; margin-bottom: 10px;">Демо: Войти как Админ</button>
        <button class="btn btn-secondary" @click="demoLogin('operator')" style="width:100%; margin-bottom: 10px;">Демо: Войти как Оператор</button>
        <button class="btn btn-secondary" @click="demoLogin('client')" style="width:100%; margin-bottom: 10px;">Демо: Войти как Клиент</button>
      </div>

      <div style="margin-top: 20px; padding: 12px; background: #F3F4F6; border-radius: 6px; font-size: 12px; color: #6B7280;">
        <strong>Демо-доступ:</strong><br />
        Логин: admin, operator, client, testuser<br />
        Пароль: password
      </div>
    </div>
  </div>
</template>

<script>
import ApiService from '../services/ApiService';
import { useRouter } from 'vue-router';

export default {
  name: 'Login',
  setup() {
    const api = new ApiService();
    const router = useRouter();
    return { api, router };
  },
  data() {
    return {
      page: 'login',
      username: '',
      password: '',
      regUsername: '',
      regName: '',
      regEmail: '',
      regPassword: '',
      regPasswordConfirm: ''
    };
  },
  methods: {
    async onLogin() {
      try {
        // Try API login; fallback to demo authentication
        const result = await this.api.login(this.username, this.password).catch(() => ({ success: true, user: { username: this.username, name: this.username, role: 'client' } }));
        if (result.success) {
          localStorage.setItem('qlm_user', JSON.stringify(result.user));
          if (result.user.role === 'admin' || result.user.role === 'operator') this.router.push('/admin'); else this.router.push('/client');
        } else {
          alert('Ошибка авторизации: ' + result.message);
        }
      } catch (err) { alert('Ошибка подключения: ' + err.message); }
    },
    async onRegister() {
      if (!this.regPassword || this.regPassword.length < 6) { alert('Пароль должен быть не менее 6 символов'); return; }
      if (this.regPassword !== this.regPasswordConfirm) { alert('Пароли не совпадают'); return; }
      try {
        const result = await this.api.register({ username: this.regUsername, name: this.regName, email: this.regEmail, password: this.regPassword }).catch(() => ({ success: true, message: 'Демо регистрация удачна' }));
        if (result.success) { alert(result.message || 'Успех'); this.page = 'login'; this.username = this.regUsername; } else alert('Ошибка регистрации: ' + result.message);
      } catch (err) { alert('Ошибка: ' + err.message); }
    },
    async demoLogin(role) {
      const users = { admin: { username: 'admin', role: 'admin', name: 'Admin' }, operator: { username: 'operator', role: 'operator', name: 'Operator' }, client: { username: 'client', role: 'client', name: 'Client' } };
      localStorage.setItem('qlm_user', JSON.stringify(users[role]));
      if (users[role].role === 'admin' || users[role].role === 'operator') this.router.push('/admin'); else this.router.push('/client');
    }
  }
};
</script>

<style scoped>
/* Local styles (optional) */
</style>
