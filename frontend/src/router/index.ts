import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/',
      redirect: '/vault',
    },
    {
      path: '/login',
      name: 'login',
      component: () => import('../views/auth/LoginView.vue'),
      meta: { requiresGuest: true },
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('../views/auth/RegisterView.vue'),
      meta: { requiresGuest: true },
    },
    {
      path: '/unlock',
      name: 'unlock',
      component: () => import('../views/auth/UnlockView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/vault',
      name: 'vault',
      component: () => import('../views/vault/VaultView.vue'),
      meta: { requiresAuth: true, requiresUnlock: true },
    },
    {
      path: '/vault/item/:id',
      name: 'vault-item',
      component: () => import('../views/vault/VaultItemView.vue'),
      meta: { requiresAuth: true, requiresUnlock: true },
    },
    {
      path: '/vault/settings',
      name: 'vault-settings',
      component: () => import('../views/vault/VaultSettingsView.vue'),
      meta: { requiresAuth: true, requiresUnlock: true },
    },
    {
      path: '/profile',
      name: 'profile',
      component: () => import('../views/user/ProfileView.vue'),
      meta: { requiresAuth: true, requiresUnlock: true },
    },
    {
      path: '/security-log',
      name: 'security-log',
      component: () => import('../views/user/SecurityLogView.vue'),
      meta: { requiresAuth: true, requiresUnlock: true },
    },
    {
      path: '/two-factor',
      name: 'two-factor',
      component: () => import('../views/auth/TwoFactorView.vue'),
    },
    {
      path: '/email-change/confirm',
      name: 'email-change-confirm',
      component: () => import('../views/auth/EmailChangeConfirmView.vue'),
    },
  ],
})

router.beforeEach(async (to, _from) => {
  const authStore = useAuthStore()
  await authStore.initFromStorage()

  // Страница 2FA доступна только при наличии pending сессии
  if (to.name === 'two-factor' && !authStore.requiresTwoFactor) {
    return { name: 'login' }
  }

  if (to.meta.requiresGuest && authStore.isAuthenticated) {
    if (authStore.isUnlocked) {
      return { name: 'vault' }
    }
    return { name: 'unlock' }
  }

  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    return { name: 'login' }
  }

  if (to.meta.requiresUnlock && authStore.isAuthenticated && !authStore.isUnlocked) {
    return { name: 'unlock' }
  }
})

export default router
