<script setup>
import { ref, reactive, onMounted } from 'vue'
import { walletApi, orderApi, rechargeApi } from '../api/modules'
import { ElMessage, ElMessageBox } from 'element-plus'

const wallet = ref({ balance: 0, frozen: 0, available_balance: 0 })
const recentOrders = ref([])
const loading = ref(false)

const orderForm = reactive({
    title: '',
    amount: '',
})
const creatingOrder = ref(false)
const rechargeDialogVisible = ref(false)
const lastInsufficientOrder = ref(null)
const rechargeAmount = ref('')
const rechargeSuggestion = ref(0)

const formatAmount = (val) => (val / 100).toFixed(2)

const statusMap = {
    pending: { label: '待支付', type: 'info' },
    paid: { label: '已支付', type: 'success' },
    insufficient_balance: { label: '余额不足', type: 'warning' },
    failed: { label: '已失败', type: 'danger' },
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

const fetchData = async () => {
    loading.value = true
    try {
        const [walletRes, ordersRes] = await Promise.all([
            walletApi.show(),
            orderApi.list({ per_page: 5 }),
        ])
        wallet.value = walletRes.data.data
        window.__currentWalletBalance = walletRes.data.data.available_balance || 0
        recentOrders.value = ordersRes.data.data || []
    } catch {}
    loading.value = false
}

const emit = defineEmits(['wallet-updated'])

const handleCreateOrder = async () => {
    if (!orderForm.title || !orderForm.amount) {
        ElMessage.warning('请填写商品名称和金额')
        return
    }
    const amountFen = Math.round(parseFloat(orderForm.amount) * 100)
    if (amountFen <= 0) {
        ElMessage.warning('请输入有效金额')
        return
    }

    creatingOrder.value = true
    try {
        const res = await orderApi.create({
            title: orderForm.title,
            amount: amountFen,
        })
        ElMessage.success(res.data.message)

        if (res.data.data.status === 'insufficient_balance') {
            lastInsufficientOrder.value = res.data.data
            const available = wallet.value.available_balance || 0
            rechargeSuggestion.value = Math.max(amountFen, amountFen - available)
            rechargeAmount.value = formatAmount(rechargeSuggestion.value)
            try {
                await ElMessageBox.confirm(
                    `订单「${orderForm.title}」创建成功，但余额不足。\n需要 ${formatAmount(amountFen)} 元，当前可用余额 ${formatAmount(available)} 元。\n是否立即充值？`,
                    '余额不足',
                    {
                        confirmButtonText: '立即充值',
                        cancelButtonText: '稍后再说',
                        type: 'warning',
                    }
                )
                rechargeDialogVisible.value = true
            } catch {}
        }

        orderForm.title = ''
        orderForm.amount = ''
        fetchData()
        emit('wallet-updated')
    } catch {}
    creatingOrder.value = false
}

const handleRechargeFromDialog = async () => {
    const yuan = parseFloat(rechargeAmount.value)
    if (!yuan || yuan <= 0) {
        ElMessage.warning('请输入有效金额')
        return
    }
    const amount = Math.round(yuan * 100)
    try {
        const res = await rechargeApi.create({ amount, payment_method: 'manual' })
        ElMessage.success(res.data.message)
        if (res.data.retry_result) {
            showRetryResult(res.data.retry_result)
        }
        rechargeDialogVisible.value = false
        fetchData()
        emit('wallet-updated')
    } catch {}
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

    <el-card shadow="hover" style="margin-bottom: 20px">
      <template #header>快速下单</template>
      <el-form :inline="true" :model="orderForm">
        <el-form-item label="商品名称">
          <el-input v-model="orderForm.title" placeholder="请输入商品名称" style="width: 220px" />
        </el-form-item>
        <el-form-item label="金额（元）">
          <el-input v-model="orderForm.amount" placeholder="请输入金额" type="number" style="width: 180px" />
        </el-form-item>
        <el-form-item>
          <el-button
            type="primary"
            :loading="creatingOrder"
            @click="handleCreateOrder"
          >
            创建订单
          </el-button>
        </el-form-item>
      </el-form>
    </el-card>

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

    <el-dialog v-model="rechargeDialogVisible" title="充值并支付余额不足订单" width="480px">
      <div style="margin-bottom: 16px">
        <el-alert
          :title="`订单「${lastInsufficientOrder?.title || ''}」需要 ${lastInsufficientOrder ? formatAmount(lastInsufficientOrder.amount) : '0'} 元，当前可用余额 ${formatAmount(wallet.available_balance)} 元。`"
          type="warning"
          :closable="false"
          show-icon
        />
      </div>
      <el-form :inline="true">
        <el-form-item label="充值金额（元）">
          <el-input v-model="rechargeAmount" type="number" placeholder="请输入充值金额" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleRechargeFromDialog">充值并自动重试</el-button>
        </el-form-item>
      </el-form>
      <div style="font-size: 12px; color: #909399; margin-top: 8px">
        提示：建议充值 {{ formatAmount(rechargeSuggestion) }} 元或更多，充值成功后将自动重试所有余额不足的订单。
      </div>
    </el-dialog>
  </div>
</template>
