<script setup>
import { ref, onMounted } from 'vue'
import { walletApi, orderApi } from '../api/modules'

const wallet = ref({ balance: 0, frozen: 0, available_balance: 0 })
const recentOrders = ref([])
const loading = ref(false)

const formatAmount = (val) => (val / 100).toFixed(2)

const statusMap = {
    pending: { label: '待支付', type: 'info' },
    paid: { label: '已支付', type: 'success' },
    insufficient_balance: { label: '余额不足', type: 'warning' },
    failed: { label: '已失败', type: 'danger' },
}

const fetchData = async () => {
    loading.value = true
    try {
        const [walletRes, ordersRes] = await Promise.all([
            walletApi.show(),
            orderApi.list({ per_page: 5 }),
        ])
        wallet.value = walletRes.data.data
        recentOrders.value = ordersRes.data.data || []
    } catch {}
    loading.value = false
}

onMounted(fetchData)
</script>

<template>
  <div v-loading="loading">
    <el-row :gutter="20" style="margin-bottom: 20px">
      <el-col :span="8">
        <el-card shadow="hover">
          <template #header>账户余额</template>
          <div style="font-size: 28px; font-weight: bold; color: #409eff">
            {{ formatAmount(wallet.balance) }} 元
          </div>
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card shadow="hover">
          <template #header>可用余额</template>
          <div style="font-size: 28px; font-weight: bold; color: #67c23a">
            {{ formatAmount(wallet.available_balance) }} 元
          </div>
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card shadow="hover">
          <template #header>冻结金额</template>
          <div style="font-size: 28px; font-weight: bold; color: #e6a23c">
            {{ formatAmount(wallet.frozen) }} 元
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-card shadow="hover">
      <template #header>
        <div style="display: flex; justify-content: space-between; align-items: center">
          <span>最近订单</span>
          <el-button type="primary" size="small" @click="$router.push('/orders')">查看全部</el-button>
        </div>
      </template>
      <el-table :data="recentOrders" stripe style="width: 100%">
        <el-table-column prop="order_no" label="订单号" width="200" />
        <el-table-column prop="title" label="商品" />
        <el-table-column label="金额" width="120">
          <template #default="{ row }">{{ formatAmount(row.amount) }} 元</template>
        </el-table-column>
        <el-table-column label="状态" width="120">
          <template #default="{ row }">
            <el-tag :type="statusMap[row.status]?.type || 'info'" size="small">
              {{ statusMap[row.status]?.label || row.status }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="创建时间" width="180">
          <template #default="{ row }">{{ new Date(row.created_at).toLocaleString() }}</template>
        </el-table-column>
      </el-table>
    </el-card>
  </div>
</template>
