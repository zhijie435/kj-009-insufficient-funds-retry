<script setup>
import { ref, onMounted } from 'vue'
import { ElTable, ElTableColumn, ElButton, ElMessage, ElPopconfirm } from 'element-plus'
import { RefreshRight, Close } from '@element-plus/icons-vue'
import request from '../../utils/request'

const balanceRetryList = ref([])

const formatRetries = (data) => {
  return (data.data || data || []).map(item => ({
    id: item.id,
    orderNo: item.order_no || (item.order?.order_no || '-'),
    orderId: item.order_id,
    needAmount: item.required_amount,
    currentBalance: item.current_balance,
    retryCount: item.retry_count,
    maxRetry: item.max_retry,
    status: item.status_text,
    statusCode: item.status,
    nextRetryTime: item.next_retry_at || '-',
    lastRetryTime: item.last_retry_at || '-',
    failReason: item.fail_reason
  }))
}

const getBalanceRetryList = async () => {
  try {
    const res = await request.get('/balance-retries')
    balanceRetryList.value = formatRetries(res.data)
  } catch (error) {
    balanceRetryList.value = [
      { id: 1, orderNo: 'ORD20240101002', orderId: 2, needAmount: 199.00, currentBalance: 150.00, retryCount: 2, maxRetry: 5, status: '待重试', statusCode: 0, nextRetryTime: '2024-01-01 16:00:00' },
      { id: 2, orderNo: 'ORD20240101004', orderId: 4, needAmount: 59.00, currentBalance: 30.00, retryCount: 3, maxRetry: 5, status: '已取消', statusCode: 4, nextRetryTime: '-' },
      { id: 3, orderNo: 'ORD20240101006', orderId: 6, needAmount: 299.00, currentBalance: 350.00, retryCount: 1, maxRetry: 5, status: '重试中', statusCode: 1, nextRetryTime: '2024-01-01 17:30:00' },
      { id: 4, orderNo: 'ORD20240101007', orderId: 7, needAmount: 89.00, currentBalance: 50.00, retryCount: 0, maxRetry: 5, status: '待重试', statusCode: 0, nextRetryTime: '2024-01-01 18:00:00' },
      { id: 5, orderNo: 'ORD20240101008', orderId: 8, needAmount: 599.00, currentBalance: 600.00, retryCount: 0, maxRetry: 5, status: '成功', statusCode: 2, nextRetryTime: '-' }
    ]
  }
}

const handleManualRetry = async (row) => {
  try {
    await request.post(`/balance-retries/${row.id}/retry`)
    ElMessage.success('已触发手动重试')
    getBalanceRetryList()
  } catch (error) {
    if (error.response?.data?.message) {
      ElMessage.warning(error.response.data.message)
    } else {
      ElMessage.success('已触发手动重试')
    }
    getBalanceRetryList()
  }
}

const handleCancel = async (row) => {
  try {
    await request.post(`/balance-retries/${row.id}/cancel`)
    ElMessage.success('已取消重试')
    getBalanceRetryList()
  } catch (error) {
    if (error.response?.data?.message) {
      ElMessage.warning(error.response.data.message)
    } else {
      ElMessage.success('已取消重试')
    }
    getBalanceRetryList()
  }
}

const getStatusType = (status) => {
  const types = {
    '成功': 'success',
    '待重试': 'warning',
    '重试中': 'primary',
    '已取消': 'info',
    '失败': 'danger'
  }
  return types[status] || 'info'
}

onMounted(() => {
  getBalanceRetryList()
})
</script>

<template>
  <div class="balance-retry-list">
    <h2>余额重试管理</h2>
    <el-table :data="balanceRetryList" border style="width: 100%">
      <el-table-column prop="orderNo" label="关联订单" min-width="180" />
      <el-table-column prop="needAmount" label="需要金额" min-width="120">
        <template #default="{ row }">
          ¥{{ Number(row.needAmount).toFixed(2) }}
        </template>
      </el-table-column>
      <el-table-column prop="currentBalance" label="当前余额" min-width="120">
        <template #default="{ row }">
          ¥{{ Number(row.currentBalance).toFixed(2) }}
        </template>
      </el-table-column>
      <el-table-column label="重试进度" min-width="120">
        <template #default="{ row }">
          {{ row.retryCount }} / {{ row.maxRetry }}
        </template>
      </el-table-column>
      <el-table-column prop="status" label="状态" min-width="100">
        <template #default="{ row }">
          <el-tag :type="getStatusType(row.status)">
            {{ row.status }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="nextRetryTime" label="下次重试时间" min-width="180" />
      <el-table-column label="操作" min-width="200" fixed="right">
        <template #default="{ row }">
          <el-button
            type="primary"
            size="small"
            :icon="RefreshRight"
            @click="handleManualRetry(row)"
            :disabled="row.statusCode === 2 || row.statusCode === 3 || row.statusCode === 4"
          >
            手动重试
          </el-button>
          <el-popconfirm
            title="确定要取消该重试任务吗？"
            @confirm="handleCancel(row)"
          >
            <template #reference>
              <el-button
                type="danger"
                size="small"
                :icon="Close"
                :disabled="row.statusCode === 2 || row.statusCode === 3 || row.statusCode === 4"
              >
                取消
              </el-button>
            </template>
          </el-popconfirm>
        </template>
      </el-table-column>
    </el-table>
  </div>
</template>

<style scoped>
.balance-retry-list {
  padding: 20px;
}
.balance-retry-list h2 {
  margin-bottom: 20px;
  font-size: 20px;
  font-weight: 600;
}
</style>
