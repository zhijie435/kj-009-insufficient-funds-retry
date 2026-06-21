<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  Document,
  Wallet,
  RefreshRight
} from '@element-plus/icons-vue'

const route = useRoute()
const router = useRouter()

const activeMenu = ref(route.path)

const menuItems = [
  { index: '/orders', title: '订单管理', icon: Document },
  { index: '/recharges', title: '充值记录', icon: Wallet },
  { index: '/balance-retries', title: '余额重试', icon: RefreshRight }
]

const handleMenuSelect = (index) => {
  activeMenu.value = index
  router.push(index)
}
</script>

<template>
  <el-container class="main-layout">
    <el-aside width="220px" class="sidebar">
      <div class="logo">
        <h2>订单管理系统</h2>
      </div>
      <el-menu
        :default-active="activeMenu"
        class="el-menu-vertical"
        background-color="#304156"
        text-color="#bfcbd9"
        active-text-color="#409EFF"
        @select="handleMenuSelect"
      >
        <el-menu-item
          v-for="item in menuItems"
          :key="item.index"
          :index="item.index"
        >
          <el-icon><component :is="item.icon" /></el-icon>
          <span>{{ item.title }}</span>
        </el-menu-item>
      </el-menu>
    </el-aside>
    <el-container>
      <el-header class="header">
        <span>欢迎使用订单库存后台管理系统</span>
      </el-header>
      <el-main class="main-content">
        <router-view />
      </el-main>
    </el-container>
  </el-container>
</template>

<style scoped>
.main-layout {
  height: 100vh;
}
.sidebar {
  background-color: #304156;
  overflow: hidden;
}
.logo {
  height: 60px;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: #2b2f3a;
}
.logo h2 {
  color: #fff;
  font-size: 18px;
  margin: 0;
}
.el-menu-vertical {
  border-right: none;
}
.header {
  background-color: #fff;
  border-bottom: 1px solid #e4e7ed;
  display: flex;
  align-items: center;
  padding: 0 20px;
  font-size: 16px;
  font-weight: 500;
}
.main-content {
  background-color: #f0f2f5;
  padding: 0;
}
</style>
