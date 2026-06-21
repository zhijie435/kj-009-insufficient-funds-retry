import api from './index'

export const authApi = {
    register: (data) => api.post('/register', data),
    login: (data) => api.post('/login', data),
    logout: () => api.post('/logout'),
    me: () => api.get('/me'),
}

export const walletApi = {
    show: () => api.get('/wallet'),
    transactions: (params) => api.get('/wallet/transactions', { params }),
}

export const orderApi = {
    list: (params) => api.get('/orders', { params }),
    create: (data) => api.post('/orders', data),
    show: (id) => api.get(`/orders/${id}`),
    retry: (id) => api.post(`/orders/${id}/retry`),
}

export const rechargeApi = {
    list: (params) => api.get('/recharges', { params }),
    create: (data) => api.post('/recharges', data),
    show: (id) => api.get(`/recharges/${id}`),
}
