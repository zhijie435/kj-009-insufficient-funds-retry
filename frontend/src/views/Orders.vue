<script setup>
import { ref, reactive, onMounted } from 'vue'
import { orderApi, rechargeApi } from '../api/modules'
import { ElMessage, ElMessageBox } from 'element-plus'

const orders = ref([])
const loading = ref(false)
const pagination = ref({ current_page: 1, per_page: 15, total: 0 })
const filters = reactive({ status: '', search: '' })
const retryLoading = ref({})

const formatAmount = (val) => (val / 100).toFixed(2)

const statusMap = {
    pending: { label: '待支付', type: 'info' },
    paid: { label: '已支付', type: 'success' },
    insufficient_balance: { label: '余额不足', type: 'warning' },
    failed: { label: '已失败', type: 'danger' },
}

const fetchOrders = async (page = 1) => {
    loading.value = true
    try {
        const res = await orderApi.list({
            page,
            per_page: pagination.value.per_page,
            ...filters,
        })
        orders.value = res.data.data || []
        pagination.value = {
            current_page: res.data.current_page,
            per_page: res.data.per_page,
            total: res.data.total,
        }
    } catch {}
    loading.value = false
}

const handleSearch = () => {
    fetchOrders(1)
}

const handleReset = () => {
    filters.status = ''
    filters.search = ''
    fetchOrders(1)
}

const handleRetry = async (order) => {
    try {
        await ElMessageBox.confirm(
            `确认重试订单「${order.title}」？将从余额中扣除 ${formatAmount(order.amount)} 元`,
            '重试订单',
            { confirmButtonText: '确认重试', cancelButtonText: '取消', type: 'warning' }
        )
    } catch {
        return
    }

    retryLoading.value[order.id] = true
    try {
        const res = await orderApi.retry(order.id)
        ElMessage.success(res.data.message)
        fetchOrders(pagination.value.current_page)
        emit('wallet-updated')
    } catch {}
    retryLoading.value[order.id] = false
}

const showRetryResult = (retryResult) => {
    if (!retryResult || retryResult.total === 0) {
        return
    }
    const { total, success, still_insufficient, failed, orders } = retryResult
    const orderDetails = orders.map(o => {
        const statusText = o.status === 'paid' ? '✓ 支付成功'
            : o.status === 'insufficient_balance' ? '⚠ 余额仍不足'
            : o.status === 'not_retryable' ? '✗ 不可重试'
            : '✗ 处理失败'
        return `  ${statusText} - ${o.title} (${(o.amount / 100).toFixed(2)}元)`
    }).join('\n')

    const summary = `自动处理 ${total} 笔余额不足订单：\n`
        + `  ✓ 支付成功: ${success}\n`
        + `  ⚠ 余额仍不足: ${still_insufficient}\n`
        + `  ✗ 处理失败: ${failed}\n\n`
        + `详情：\n${orderDetails}`

    ElMessageBox.alert(summary, '充值后自动重试结果', {
        confirmButtonText: '好的',
        dangerouslyUseHTMLString: false,
    })
}

const handleRecharge = (order) => {
    const needed = order.amount - (window.__currentWalletBalance || 0)
    const suggestAmount = Math.max(order.amount, needed > 0 ? needed : order.amount)
    ElMessageBox.prompt(
        `订单「${order.title}」余额不足，需要 ${formatAmount(order.amount)} 元\n建议充值 ${formatAmount(suggestAmount)} 元或更多，请输入充值金额（元）`,
        '快速充值并自动重试',
        {
            confirmButtonText: '充值并重试',
            cancelButtonText: '取消',
            inputPattern: /^\d+(\.\d{1,2})?$/,
            inputErrorMessage: '请输入有效金额',
            inputValue: formatAmount(suggestAmount),
        }
    ).then(async ({ value }) => {
        const amount = Math.round(parseFloat(value) * 100)
        try {
            const res = await rechargeApi.create({ amount, payment_method: 'manual' })
            ElMessage.success(res.data.message)
            if (res.data.retry_result) {
                showRetryResult(res.data.retry_result)
            }
            emit('wallet-updated')
            fetchOrders(pagination.value.current_page)
        } catch {}
    }).catch(() => {})
}

const emit = defineEmits(['wallet-updated'])

onMounted(() => fetchOrders())
</script>

<template>
  <div>
    <el-card shadow="hover" style="margin-bottom: 20px">
      <el-form :inline="true" :model="filters">
        <el-form-item label="状态">
          <el-select v-model="filters.status" placeholder="全部" clearable style="width: 150px">
            <el-option label="待支付" value="pending" />
            <el-option label="已支付" value="paid" />
            <el-option label="余额不足" value="insufficient_balance" />
            <el-option label="已失败" value="failed" />
          </el-select>
        </el-form-item>
        <el-form-item label="搜索">
          <el-input v-model="filters.search" placeholder="订单号/商品名" clearable style="width: 200px" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">搜索</el-button>
          <el-button @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card shadow="hover">
      <template #header>
        <div style="display: flex; justify-content: space-between; align-items: center">
          <span>订单列表</span>
          <el-tag type="warning" v-if="orders.filter(o => o.status === 'insufficient_balance').length > 0">
            {{ orders.filter(o => o.status === 'insufficient_balance').length }} 笔订单余额不足
          </el-tag>
        </div>
      </template>

      <el-table :data="orders" v-loading="loading" stripe style="width: 100%">
        <el-table-column prop="order_no" label="订单号" width="200" />
        <el-table-column prop="title" label="商品" min-width="150" />
        <el-table-column label="金额" width="120">
          <template #default="{ row }">
            <span style="font-weight: 600">{{ formatAmount(row.amount) }} 元</span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="120">
          <template #default="{ row }">
            <el-tag :type="statusMap[row.status]?.type || 'info'" size="small">
              {{ statusMap[row.status]?.label || row.status }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="重试" width="100">
          <template #default="{ row }">
            <span>{{ row.retry_count }}/{{ row.max_retries }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="fail_reason" label="失败原因" min-width="180">
          <template #default="{ row }">
            <span v-if="row.fail_reason" style="color: #f56c6c; font-size: 12px">{{ row.fail_reason }}</span>
            <span v-else style="color: #c0c4cc">-</span>
          </template>
        </el-table-column>
        <el-table-column label="创建时间" width="170">
          <template #default="{ row }">{{ new Date(row.created_at).toLocaleString() }}</template>
        </el-table-column>
        <el-table-column label="操作" width="200" fixed="right">
          <template #default="{ row }">
            <template v-if="row.status === 'insufficient_balance'">
              <el-button
                type="primary"
                size="small"
                :loading="retryLoading[row.id]"
                @click="handleRetry(row)"
              >
                重试支付
              </el-button>
              <el-button
                type="success"
                size="small"
                @click="handleRecharge(row)"
              >
                充值
              </el-button>
            </template>
            <el-tag v-else-if="row.status === 'paid'" type="success" size="small">已完成</el-tag>
            <el-tag v-else-if="row.status === 'failed'" type="danger" size="small">已终了</el-tag>
            <span v-else style="color: #c0c4cc">-</span>
          </template>
        </el-table-column>
      </el-table>

      <div style="margin-top: 16px; display: flex; justify-content: flex-end">
        <el-pagination
          v-model:current-page="pagination.current_page"
          :page-size="pagination.per_page"
          :total="pagination.total"
          layout="total, prev, pager, next"
          @current-change="(page) => fetchOrders(page)"
        />
      </div>
    </el-card>
  </div>
</template>
