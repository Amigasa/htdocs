import { createRouter, createWebHistory } from 'vue-router';
import Login from '../views/Login.vue';
import AdminLayout from '../views/AdminLayout.vue';
import Dashboard from '../views/Dashboard.vue';
import Projects from '../views/Projects.vue';
import Users from '../views/Users.vue';
import Leads from '../views/Leads.vue';
import AdminLeadDetail from '../views/AdminLeadDetail.vue';
import ClientLayout from '../views/ClientLayout.vue';
import ClientRequest from '../views/ClientRequest.vue';
import ClientRequestDetail from '../views/ClientRequestDetail.vue';
import ClientRequests from '../views/ClientRequests.vue';

const routes = [
  { path: '/', name: 'root', redirect: '/login' },
  { path: '/login', name: 'login', component: Login },
  { path: '/admin', name: 'admin', component: AdminLayout, children: [
    { path: '', name: 'admin-dashboard', component: Dashboard },
    { path: 'projects', name: 'admin-projects', component: Projects },
    { path: 'users', name: 'admin-users', component: Users },
    { path: 'leads', name: 'admin-leads', component: Leads },
    { path: 'leads/:id', name: 'admin-lead-detail', component: AdminLeadDetail, props: true }
  ]},
  { path: '/client', name: 'client', component: ClientLayout, children: [
    { path: '', name: 'client-request', component: ClientRequest },
    { path: 'requests', name: 'client-requests', component: ClientRequests },
    { path: 'requests/:id', name: 'client-request-detail', component: ClientRequestDetail, props: true }
  ]},
  { path: '/:catchAll(.*)', redirect: '/login' }
];

const router = createRouter({ history: createWebHistory(), routes });

// Global guard: ensure authenticated users are routed based on role and unauthorized access is blocked
router.beforeEach((to, from, next) => {
  const user = JSON.parse(localStorage.getItem('qlm_user') || 'null');
  // Allow login page always
  if (to.path === '/login') return next();
  // Not logged in -> login
  if (!user) return next('/login');
  // Admin/operator allowed to /admin
  if (to.path.startsWith('/admin')) {
    if (user.role === 'admin' || user.role === 'operator') return next();
    // otherwise redirect to client's page
    return next('/client');
  }
  // Client allowed to /client
  if (to.path.startsWith('/client')) {
    if (user.role === 'client') return next();
    // otherwise redirect to admin's page
    if (user.role === 'admin' || user.role === 'operator') return next('/admin');
    return next('/login');
  }
  // Default: if user is admin/operator -> admin, else client
  if (user.role === 'admin' || user.role === 'operator') return next('/admin');
  return next('/client');
});

export default router;
