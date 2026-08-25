import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/',
      name: 'home',
      component: () => import('@/views/front/HomeView.vue'),
      meta: { title: '拾光小说' },
    },
    {
      path: '/category/:id',
      name: 'category',
      component: () => import('@/views/front/CategoryView.vue'),
      meta: { title: '分类' },
    },
    {
      path: '/novel/:id',
      name: 'novel-detail',
      component: () => import('@/views/front/NovelDetailView.vue'),
      meta: { title: '小说详情' },
    },
    {
      path: '/read/:id/:no',
      name: 'read',
      component: () => import('@/views/front/ReadView.vue'),
      meta: { title: '阅读' },
    },
    {
      path: '/admin/login',
      name: 'admin-login',
      component: () => import('@/views/admin/LoginView.vue'),
      meta: { title: '登录', public: true },
    },
    {
      path: '/admin',
      component: () => import('@/views/admin/AdminLayout.vue'),
      meta: { requiresAuth: true },
      children: [
        {
          path: '',
          name: 'admin-dashboard',
          component: () => import('@/views/admin/DashboardView.vue'),
          meta: { title: '仪表盘' },
        },
        {
          path: 'novels',
          name: 'admin-novels',
          component: () => import('@/views/admin/NovelsView.vue'),
          meta: { title: '小说管理' },
        },
        {
          path: 'novels/:id',
          name: 'admin-novel-detail',
          component: () => import('@/views/admin/NovelDetailView.vue'),
          meta: { title: '小说详情' },
        },
        {
          path: 'ideas',
          name: 'admin-ideas',
          component: () => import('@/views/admin/IdeasView.vue'),
          meta: { title: 'AI 点子' },
        },
        {
          path: 'ideas/:id/chat',
          name: 'admin-idea-chat',
          component: () => import('@/views/admin/IdeaChatView.vue'),
          meta: { title: '点子聊天' },
        },
        {
          path: 'ai/config',
          name: 'admin-ai-config',
          component: () => import('@/views/admin/AiConfigView.vue'),
          meta: { title: 'AI 配置' },
        },
        {
          path: 'ai/prompts',
          name: 'admin-prompts',
          component: () => import('@/views/admin/PromptsView.vue'),
          meta: { title: 'Prompt 管理' },
        },
        {
          path: 'ai/logs',
          name: 'admin-logs',
          component: () => import('@/views/admin/LogsView.vue'),
          meta: { title: 'AI 日志' },
        },
        {
          path: 'settings',
          name: 'admin-settings',
          component: () => import('@/views/admin/SettingsView.vue'),
          meta: { title: '系统设置' },
        },
      ],
    },
    {
      path: '/:pathMatch(.*)*',
      redirect: '/',
    },
  ],
  scrollBehavior() {
    return { top: 0 }
  },
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  if (!to.meta.public && to.path.startsWith('/admin')) {
    if (!auth.checked) {
      await auth.fetchMe()
    }
    if (!auth.isLoggedIn) {
      return {
        name: 'admin-login',
        query: { redirect: to.fullPath },
      }
    }
  }
  document.title = (to.meta.title as string) || '拾光小说'
  return true
})

export default router
