<script setup>
import { ref, reactive, onMounted } from 'vue'
import { rechargeApi, orderApi, walletApi } from '../api/modules'
import { ElMessage, ElMessageBox } from 'element-plus'

const recharges = ref([])
const loading = ref(false)
const pagination = ref({ current_page: 1, per_page: 15, total: 0 })

const formatAmount = (val) => (val / 100).toFixed(2)

const statusMap = {
    pending: { label: '处理中', type: 'info' },
    completed: { label: '已完成', type: 'success' },
    failed: { label: '失败', type: 'danger' },
}

const quickAmounts = [1000, 5000, 10000, 50000, 100000]
const customAmount = ref('')

const showRetryResult = (retryResult) => {
    if (!retryResult || retryResult.total === 0) {
        return
    }
    const { total, success, still_insufficient, failed, orders } = retryResult

    const statusIcon = (s) => {
        const map = {
            paid: '✅',
            insufficient_balance: '⚠️',
            not_retryable: '⛔',
            error: '❌',
        }
        return map[s] || '❌'
    }
    const statusLabel = (s) => {
        const map = {
            paid: '支付成功',
            insufficient_balance: '余额仍不足',
            not_retryable: '不可重试',
            error: '处理失败',
        }
        return map[s] || '处理失败'
    }

    const orderRows = orders.map(o =>
        `<tr>
            <td style="padding:6px 10px;border-bottom:1px solid #ebeef5;">${o.order_no || '-'}</td>
            <td style="padding:6px 10px;border-bottom:1px solid #ebeef5;">${o.title || '-'}</td>
            <td style="padding:6px 10px;border-bottom:1px solid #ebeef5;text-align:right;">${(o.amount / 100).toFixed(2)} 元</td>
            <td style="padding:6px 10px;border-bottom:1px solid #ebeef5;">${statusIcon(o.status)} ${statusLabel(o.status)}</td>
            <td style="padding:6px 10px;border-bottom:1px solid #ebeef5;color:#909399;">${o.message || ''}</td>
        </tr>`
    ).join('')

    const html = `
        <div style="margin-bottom:14px;font-size:14px;">
            自动处理 <b>${total}</b> 笔余额不足订单：
        </div>
        <div style="margin-bottom:16px;font-size:13px;line-height:1.8;">
            <span style="color:#67c23a;">✅ 支付成功: ${success}</span>&emsp;
            <span style="color:#e6a23c;">⚠️ 余额仍不足: ${still_insufficient}</span>&emsp;
            <span style="color:#f56c6c;">❌ 处理失败: ${failed}</span>
        </div>
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="background:#f5f7fa;">
                    <th style="padding:8px 10px;text-align:left;border-bottom:2px solid #ebeef5;">订单号</th>
                    <th style="padding:8px 10px;text-align:left;border-bottom:2px solid #ebeef5;">商品</th>
                    <th style="padding:8px 10px;text-align:right;border-bottom:2px solid #ebeef5;">金额</th>
                    <th style="padding:8px 10px;text-align:left;border-bottom:2px solid #ebeef5;">状态</th>
                    <th style="padding:8px 10px;text-align:left;border-bottom:2px solid #ebeef5;">说明</th>
                </tr>
            </thead>
            <tbody>${orderRows}</tbody>
        </table>
    `

    ElMessageBox.alert(html, '充值后自动重试结果', {
        confirmButtonText: '好的',
        dangerouslyUseHTMLString: true,
        customClass: 'retry-result-dialog',
    })
}

const fetchRecharges = async (page = 1) => {
    loading.value = true
    try {
        const res = await rechargeApi.list({ page, per_page: pagination.value.per_page })
        recharges.value = res.data.data || []
        pagination.value = {
            current_page: res.data.current_page,
            per_page: res.data.per_page,
            total: res.data.total,
        }
    } catch {}
    loading.value = false
}

const handleRecharge = async (amount) => {
    try {
        await ElMessageBox.confirm(
            `确认充值 ${formatAmount(amount)} 元？\n充值成功后将自动重试您所有余额不足的订单`,
            '充值确认',
            { confirmButtonText: '确认充值', cancelButtonText: '取消', type: 'info' }
        )
    } catch {
        return
    }

    try {
        const res = await rechargeApi.create({ amount, payment_method: 'manual' })
        ElMessage.success(res.data.message)
        if (res.data.retry_result) {
            showRetryResult(res.data.retry_result)
        }
        emit('wallet-updated')
        fetchRecharges(pagination.value.current_page)
    } catch {}
}

const handleCustomRecharge = () => {
    const yuan = parseFloat(customAmount.value)
    if (!yuan || yuan <= 0) {
        ElMessage.warning('请输入有效金额')
        return
    }
    const amount = Math.round(yuan * 100)
    handleRecharge(amount)
}

const emit = defineEmits(['wallet-updated'])

onMounted(() => fetchRecharges())
</script>

<template>
  <div>
    <el-card shadow="hover" style="margin-bottom: 20px">
      <template #header>快速充值</template>
      <el-row :gutter="12" style="margin-bottom: 16px">
        <el-col :span="4" v-for="amt in quickAmounts" :key="amt">
          <el-button @click="handleRecharge(amt)" style="width: 100%">
            {{ formatAmount(amt) }} 元
          </el-button>
        </el-col>
      </el-row>
      <el-row :gutter="12" align="middle">
        <el-col :span="8">
          <el-input v-model="customAmount" placeholder="自定义金额（元）" type="number" />
        </el-col>
        <el-col :span="4">
          <el-button type="primary" @click="handleCustomRecharge">充值</el-button>
        </el-col>
      </el-row>
    </el-card>

    <el-card shadow="hover">
      <template #header>充值记录</template>
      <el-table :data="recharges" v-loading="loading" stripe style="width: 100%">
        <el-table-column prop="transaction_no" label="交易号" width="220" />
        <el-table-column label="金额" width="150">
          <template #default="{ row }">
            <span style="font-weight: 600; color: #67c23a">+{{ formatAmount(row.amount) }} 元</span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="120">
          <template #default="{ row }">
            <el-tag :type="statusMap[row.status]?.type || 'info'" size="small">
              {{ statusMap[row.status]?.label || row.status }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="支付方式" width="120">
          <template #default="{ row }">
            {{ row.payment_method === 'manual' ? '手动' : row.payment_method === 'alipay' ? '支付宝' : '微信' }}
          </template>
        </el-table-column>
        <el-table-column label="完成时间" width="180">
          <template #default="{ row }">{{ row.paid_at ? new Date(row.paid_at).toLocaleString() : '-' }}</template>
        </el-table-column>
        <el-table-column label="创建时间" width="180">
          <template #default="{ row }">{{ new Date(row.created_at).toLocaleString() }}</template>
        </el-table-column>
      </el-table>

      <div style="margin-top: 16px; display: flex; justify-content: flex-end">
        <el-pagination
          v-model:current-page="pagination.current_page"
          :page-size="pagination.per_page"
          :total="pagination.total"
          layout="total, prev, pager, next"
          @current-change="(page) => fetchRecharges(page)"
        />
      </div>
    </el-card>
  </div>
</template>

<style>
.retry-result-dialog {
    max-width: 720px;
    width: 90vw;
}
.retry-result-dialog .el-message-box__message {
    max-height: 50vh;
    overflow-y: auto;
}
</style>
