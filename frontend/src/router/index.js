import { createRouter, createWebHistory } from 'vue-router'

const routes = [
    {
        path: '/login',
        name: 'Login',
        component: () => import('../views/Login.vue'),
    },
    {
        path: '/register',
        name: 'Register',
        component: () => import('../views/Register.vue'),
    },
    {
        path: '/',
        component: () => import('../layouts/MainLayout.vue'),
        meta: { requiresAuth: true },
        children: [
            {
                path: '',
                name: 'Dashboard',
                component: () => import('../views/Dashboard.vue'),
            },
            {
                path: 'wallet',
                name: 'Wallet',
                component: () => import('../views/Wallet.vue'),
            },
            {
                path: 'orders',
                name: 'Orders',
                component: () => import('../views/Orders.vue'),
            },
            {
                path: 'recharge',
                name: 'Recharge',
                component: () => import('../views/Recharge.vue'),
            },
            {
                path: 'balance-retries',
                name: 'BalanceRetries',
                component: () => import('../views/balanceRetry/Index.vue'),
            },
        ],
    },
]

const router = createRouter({
    history: createWebHistory(),
    routes,
})

router.beforeEach((to, from, next) => {
    const token = localStorage.getItem('token')
    if (to.matched.some((r) => r.meta.requiresAuth) && !token) {
        next({ name: 'Login' })
    } else if ((to.name === 'Login' || to.name === 'Register') && token) {
        next({ name: 'Dashboard' })
    } else {
        next()
    }
})

export default router
