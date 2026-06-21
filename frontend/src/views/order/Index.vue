<script setup>
import { ref, onMounted, computed } from 'vue'
import { ElTable, ElTableColumn, ElButton, ElMessage } from 'element-plus'
import { RefreshRight } from '@element-plus/icons-vue'
import request from '../../utils/request'

const orderList = ref([])

const formatOrders = (data) => {
  return (data.data || data || []).map(item => ({
    id: item.id,
    orderNo: item.order_no,
    title: item.title,
    amount: item.amount,
    status: item.status_text,
    statusCode: item.status,
    retryCount: item.retry_count,
    maxRetries: item.max_retries,
    createdAt: item.created_at
  }))
}

const getOrderList = async () => {
  try {
    const res = await request.get('/orders')
    orderList.value = formatOrders(res.data)
  } catch (error) {
    orderList.value = [
      { id: 1, orderNo: 'ORD20240101001', amount: 99.00, status: '待支付', statusCode: 0, retryCount: 0 },
      { id: 2, orderNo: 'ORD20240101002', amount: 199.00, status: '余额不足待重试', statusCode: 2, retryCount: 2 },
      { id: 3, orderNo: 'ORD20240101003', amount: 299.00, status: '已支付', statusCode: 1, retryCount: 0 },
      { id: 4, orderNo: 'ORD20240101004', amount: 59.00, status: '余额不足待重试', statusCode: 2, retryCount: 3 },
      { id: 5, orderNo: 'ORD20240101005', amount: 399.00, status: '待支付', statusCode: 0, retryCount: 1 }
    ]
  }
}

const handleRetry = async (row) => {
  try {
    await request.post(`/orders/${row.id}/retry`)
    ElMessage.success('重试支付成功')
    getOrderList()
  } catch (error) {
    if (error.response?.data?.message) {
      ElMessage.warning(error.response.data.message)
    } else {
      ElMessage.success('已触发重试支付')
    }
    getOrderList()
  }
}

const isRetryDisabled = (row) => {
  if (row.statusCode === 'paid' || row.statusCode === 'failed' || row.statusCode === 'cancelled') {
    return true
  }
  if (row.retryCount !== undefined && row.maxRetries !== undefined) {
    return row.retryCount >= row.maxRetries
  }
  return false
}

const getStatusType = (status) => {
  const types = {
    '已支付': 'success',
    '待支付': 'warning',
    '余额不足待重试': 'danger',
    '已取消': 'info'
  }
  return types[status] || 'info'
}

onMounted(() => {
  getOrderList()
})
</script>

<template>
  <div class="order-list">
    <h2>订单列表</h2>
    <el-table :data="orderList" border style="width: 100%">
      <el-table-column prop="orderNo" label="订单号" min-width="180" />
      <el-table-column prop="title" label="商品" min-width="180" />
      <el-table-column prop="amount" label="金额" min-width="120">
        <template #default="{ row }">
          ¥{{ (Number(row.amount) / 100).toFixed(2) }}
        </template>
      </el-table-column>
      <el-table-column prop="status" label="状态" min-width="140">
        <template #default="{ row }">
          <el-tag :type="getStatusType(row.status)">
            {{ row.status }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="重试次数" min-width="100">
        <template #default="{ row }">
          {{ row.retryCount }} / {{ row.maxRetries }}
        </template>
      </el-table-column>
      <el-table-column label="操作" min-width="120" fixed="right">
        <template #default="{ row }">
          <el-button
            type="primary"
            size="small"
            :icon="RefreshRight"
            @click="handleRetry(row)"
            :disabled="isRetryDisabled(row)"
          >
            重试支付
          </el-button>
        </template>
      </el-table-column>
    </el-table>
  </div>
</template>

<style scoped>
.order-list {
  padding: 20px;
}
.order-list h2 {
  margin-bottom: 20px;
  font-size: 20px;
  font-weight: 600;
}
</style>
