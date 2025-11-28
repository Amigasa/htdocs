export default class ApiService {
  constructor() {
    this.baseUrl = 'http://localhost/quicklead-backend/api';
  }

  async request(endpoint, options = {}) {
    try {
      const url = `${this.baseUrl}/${endpoint}`;
      const config = {
        headers: { 'Content-Type': 'application/json' },
        ...options
      };
      if (config.body && typeof config.body !== 'string') config.body = JSON.stringify(config.body);

      const response = await fetch(url, config);
      const text = await response.text();
      let data;
      try { data = JSON.parse(text); } catch (e) { throw new Error('Неверный JSON ответ: ' + text); }
      if (!response.ok) throw new Error(data?.message || `HTTP ${response.status}`);
      return data;
    } catch (err) {
      console.error('API Error:', err);
      throw err;
    }
  }

  login(username, password) { return this.request('auth.php', { method: 'POST', body: { username, password } }); }
  register(userData) { return this.request('register.php', { method: 'POST', body: userData }); }
  getLeads(userId = null, userRole = null) {
    let url = 'leads.php';
    if (userId && userRole) url += `?user_id=${userId}&user_role=${encodeURIComponent(userRole)}`;
    return this.request(url);
  }
  // Fetch a single lead by id: leads.php?lead_id=123 expected
  getLead(leadId) {
    let url = `leads.php?lead_id=${leadId}`;
    return this.request(url);
  }
  incrementLeadViews(leadId) { return this.request('leads.php', { method: 'PUT', body: { id: leadId, increment_views: true } }); }
  getProjects() { return this.request('projects.php'); }
  getUsers(userRole = null) { return this.request(userRole ? `users.php?user_role=${encodeURIComponent(userRole)}` : 'users.php'); }
  createLead(leadData) { return this.request('leads.php', { method: 'POST', body: leadData }); }
  updateLeadStatus(leadId, status) { return this.request('leads.php', { method: 'PUT', body: { id: leadId, status } }); }
  // server-side export: returns Blob
  async exportLeadsBackend(userId = null, userRole = null) {
    let url = `${this.baseUrl}/leads.php?export=csv`;
    if (userId && userRole) url += `&user_id=${userId}&user_role=${userRole}`;
    const resp = await fetch(url);
    if (!resp.ok) throw new Error('Export failed: HTTP ' + resp.status);
    const blob = await resp.blob();
    return blob;
  }
  async getLeadsStats(userId = null, userRole = null) {
    let url = 'leads.php?stats=1';
    if (userId && userRole) url += `&user_id=${userId}&user_role=${encodeURIComponent(userRole)}`;
    return this.request(url);
  }
  createProject(projectData) { return this.request('projects.php', { method: 'POST', body: projectData }); }
  updateProject(id, projectData) { return this.request('projects.php', { method: 'PUT', body: { id, ...projectData } }); }
  deleteProject(projectId) { return this.request('projects.php', { method: 'DELETE', body: { id: projectId } }); }
  createUser(userData) { return this.request('users.php', { method: 'POST', body: userData }); }
  updateUser(userId, userData) { return this.request('users.php', { method: 'PUT', body: { id: userId, ...userData } }); }
  deleteUser(userId) { return this.request('users.php', { method: 'DELETE', body: { id: userId } }); }
}
