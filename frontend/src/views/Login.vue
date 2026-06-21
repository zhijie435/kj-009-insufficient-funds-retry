<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { authApi } from '../api/modules'
import { ElMessage } from 'element-plus'

const router = useRouter()
const form = reactive({ email: '', password: '' })
const loading = ref(false)

const handleLogin = async () => {
    if (!form.email || !form.password) {
        ElMessage.warning('请填写邮箱和密码')
        return
    }
    loading.value = true
    try {
        const res = await authApi.login(form)
        localStorage.setItem('token', res.data.data.token)
        ElMessage.success('登录成功')
        router.push('/')
    } catch {}
    loading.value = false
}
</script>

<template>
  <div style="min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f5f7fa">
    <el-card style="width: 400px; padding: 20px">
      <template #header>
        <h2 style="text-align: center; margin: 0">登录</h2>
      </template>
      <el-form :model="form" label-width="0">
        <el-form-item>
          <el-input v-model="form.email" placeholder="邮箱" prefix-icon="Message" />
        </el-form-item>
        <el-form-item>
          <el-input v-model="form.password" type="password" placeholder="密码" prefix-icon="Lock" @keyup.enter="handleLogin" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" :loading="loading" style="width: 100%" @click="handleLogin">登录</el-button>
        </el-form-item>
      </el-form>
      <div style="text-align: center">
        <el-link type="primary" @click="router.push('/register')">没有账号？去注册</el-link>
      </div>
    </el-card>
  </div>
</template>
