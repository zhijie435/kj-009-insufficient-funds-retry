<script setup>
import { ref, onMounted } from 'vue'
import { walletApi } from '../api/modules'

const wallet = ref({ balance: 0, frozen: 0, available_balance: 0 })
const transactions = ref([])
const loading = ref(false)
const pagination = ref({ current_page: 1, per_page: 15, total: 0 })

const formatAmount = (val) => (val / 100).toFixed(2)

const typeMap = {
    deposit: { label: '充值', type: 'success' },
    deduct: { label: '扣款', type: 'danger' },
}

const fetchWallet = async () => {
    try {
        const res = await walletApi.show()
        wallet.value = res.data.data
    } catch {}
}

const fetchTransactions = async (page = 1) => {
    loading.value = true
    try {
        const res = await walletApi.transactions({ page, per_page: pagination.value.per_page })
        transactions.value = res.data.data || []
        pagination.value = {
            current_page: res.data.current_page,
            per_page: res.data.per_page,
            total: res.data.total,
        }
    } catch {}
    loading.value = false
}

const handlePageChange = (page) => {
    fetchTransactions(page)
}

onMounted(() => {
    fetchWallet()
    fetchTransactions()
})
</script>

<template>
  <div>
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
      <template #header>交易记录</template>
      <el-table :data="transactions" v-loading="loading" stripe style="width: 100%">
        <el-table-column label="类型" width="100">
          <template #default="{ row }">
            <el-tag :type="typeMap[row.type]?.type || 'info'" size="small">
              {{ typeMap[row.type]?.label || row.type }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="金额" width="150">
          <template #default="{ row }">
            <span :style="{ color: row.type === 'deposit' ? '#67c23a' : '#f56c6c' }">
              {{ row.type === 'deposit' ? '+' : '-' }}{{ formatAmount(row.amount) }} 元
            </span>
          </template>
        </el-table-column>
        <el-table-column label="变动前" width="150">
          <template #default="{ row }">{{ formatAmount(row.balance_before) }} 元</template>
        </el-table-column>
        <el-table-column label="变动后" width="150">
          <template #default="{ row }">{{ formatAmount(row.balance_after) }} 元</template>
        </el-table-column>
        <el-table-column prop="remark" label="备注" />
        <el-table-column label="时间" width="180">
          <template #default="{ row }">{{ new Date(row.created_at).toLocaleString() }}</template>
        </el-table-column>
      </el-table>
      <div style="margin-top: 16px; display: flex; justify-content: flex-end">
        <el-pagination
          v-model:current-page="pagination.current_page"
          :page-size="pagination.per_page"
          :total="pagination.total"
          layout="total, prev, pager, next"
          @current-change="handlePageChange"
        />
      </div>
    </el-card>
  </div>
</template>
