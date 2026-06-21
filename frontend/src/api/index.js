import axios from 'axios'
import { ElMessage } from 'element-plus'

const api = axios.create({
    baseURL: 'http://localhost:8002/api/v1',
    timeout: 10000,
})

api.interceptors.request.use((config) => {
    const token = localStorage.getItem('token')
    if (token) {
        config.headers.Authorization = `Bearer ${token}`
    }
    return config
})

api.interceptors.response.use(
    (response) => response,
    (error) => {
        const message = error.response?.data?.message || '请求失败'
        if (error.response?.status === 401) {
            localStorage.removeItem('token')
            window.location.href = '/login'
        } else {
            ElMessage.error(message)
        }
        return Promise.reject(error)
    }
)

export default api
