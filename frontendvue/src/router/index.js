import { createRouter, createWebHistory } from 'vue-router';
import Login from '../views/Login.vue';
import AdminLayout from '../views/AdminLayout.vue';
import Dashboard from '../views/Dashboard.vue';
import Projects from '../views/Projects.vue';
import Users from '../views/Users.vue';
import Leads from '../views/Leads.vue';
import ClientLayout from '../views/ClientLayout.vue';
import ClientRequest from '../views/ClientRequest.vue';
import ClientRequests from '../views/ClientRequests.vue';

const routes = [
  { path: '/', name: 'root', redirect: '/login' },
  { path: '/login', name: 'login', component: Login },
  { path: '/admin', name: 'admin', component: AdminLayout, children: [
    { path: '', name: 'admin-dashboard', component: Dashboard },
    { path: 'projects', name: 'admin-projects', component: Projects },
    { path: 'users', name: 'admin-users', component: Users },
    { path: 'leads', name: 'admin-leads', component: Leads }
  ]},
  { path: '/client', name: 'client', component: ClientLayout, children: [
    { path: '', name: 'client-request', component: ClientRequest },
    { path: 'requests', name: 'client-requests', component: ClientRequests }
  ]},
  { path: '/:catchAll(.*)', redirect: '/login' }
];

const router = createRouter({ history: createWebHistory(), routes });

export default router;
