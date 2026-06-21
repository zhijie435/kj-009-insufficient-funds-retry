<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { authApi, walletApi } from '../api/modules'

const router = useRouter()
const user = ref(null)
const wallet = ref({ balance: 0, frozen: 0, available_balance: 0 })
const loading = ref(false)

const fetchUser = async () => {
    try {
        const res = await authApi.me()
        user.value = res.data.data
    } catch {
        handleLogout()
    }
}

const fetchWallet = async () => {
    try {
        const res = await walletApi.show()
        wallet.value = res.data.data
    } catch {}
}

const handleLogout = async () => {
    try {
        await authApi.logout()
    } catch {}
    localStorage.removeItem('token')
    router.push('/login')
}

const formatAmount = (val) => {
    return (val / 100).toFixed(2)
}

onMounted(() => {
    fetchUser()
    fetchWallet()
})

const activeMenu = ref('/')
</script>

<template>
  <el-container style="min-height: 100vh">
    <el-aside width="220px" style="background-color: #304156">
      <div style="padding: 20px; text-align: center">
        <h2 style="color: #fff; margin: 0; font-size: 16px">电商订单库存后台</h2>
      </div>
      <el-menu
        :default-active="$route.path"
        router
        background-color="#304156"
        text-color="#bfcbd9"
        active-text-color="#409eff"
      >
        <el-menu-item index="/">
          <el-icon><HomeFilled /></el-icon>
          <span>仪表盘</span>
        </el-menu-item>
        <el-menu-item index="/wallet">
          <el-icon><Wallet /></el-icon>
          <span>我的钱包</span>
        </el-menu-item>
        <el-menu-item index="/orders">
          <el-icon><Document /></el-icon>
          <span>订单列表</span>
        </el-menu-item>
        <el-menu-item index="/recharge">
          <el-icon><CreditCard /></el-icon>
          <span>充值中心</span>
        </el-menu-item>
      </el-menu>
    </el-aside>

    <el-container>
      <el-header style="background: #fff; border-bottom: 1px solid #e6e6e6; display: flex; align-items: center; justify-content: space-between; padding: 0 20px">
        <span style="font-size: 14px; color: #666">
          余额: <el-tag type="success" size="small">{{ formatAmount(wallet.available_balance) }} 元</el-tag>
        </span>
        <div style="display: flex; align-items: center; gap: 12px">
          <span style="font-size: 14px; color: #333">{{ user?.name || '-' }}</span>
          <el-button type="danger" size="small" @click="handleLogout">退出</el-button>
        </div>
      </el-header>

      <el-main style="background: #f5f7fa; padding: 20px">
        <router-view @wallet-updated="fetchWallet" />
      </el-main>
    </el-container>
  </el-container>
</template>
