import { createRouter, createWebHistory } from 'vue-router'
import { useSessionStore } from '../stores/session'
import HomeView from '../views/HomeView.vue'
import LoginView from '../views/LoginView.vue'
import DashboardView from '../views/DashboardView.vue'
import FarmFormView from '../views/FarmFormView.vue'
import FarmDetailView from '../views/FarmDetailView.vue'
import GoatFormView from '../views/GoatFormView.vue'
import RecordsView from '../views/RecordsView.vue'
import GoatsView from '../views/GoatsView.vue'
import ReportsView from '../views/ReportsView.vue'
import UsersView from '../views/UsersView.vue'
import AccountView from '../views/AccountView.vue'

const router = createRouter({
    history: createWebHistory(),
    scrollBehavior() {
        return { top: 0 }
    },
    routes: [
        { path: '/', name: 'home', component: HomeView, meta: { shell: false, public: true } },
        { path: '/login', name: 'login', component: LoginView, meta: { shell: false, guest: true } },
        { path: '/dashboard', name: 'dashboard', component: DashboardView },
        { path: '/farms/:uuid', name: 'farm-detail', component: FarmDetailView },
        { path: '/farms/:uuid/edit', name: 'farm-edit', component: FarmFormView },
        { path: '/goats/:uuid/edit', name: 'goat-edit', component: GoatFormView },
        { path: '/records', name: 'records', component: RecordsView },
        { path: '/goats', name: 'goats', component: GoatsView },
        { path: '/sync', redirect: '/dashboard' },
        { path: '/offline', redirect: '/dashboard' },
        { path: '/reports', name: 'reports', component: ReportsView },
        { path: '/users', name: 'users', component: UsersView, meta: { admin: true } },
        { path: '/account', name: 'account', component: AccountView },
        { path: '/:pathMatch(.*)*', redirect: '/' },
    ],
})

router.beforeEach(async (to) => {
    const session = useSessionStore()
    if (!session.ready) await session.restore()
    if (to.meta.guest && session.user) return { name: 'dashboard' }
    if (!to.meta.public && !to.meta.guest && !session.user) return { name: 'login', query: { redirect: to.fullPath } }
    if (to.meta.admin && session.user?.role !== 'admin') return { name: 'dashboard' }
    return true
})

export default router
