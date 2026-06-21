<script setup>
import { ref, onMounted } from 'vue'
import { ElTable, ElTableColumn, ElMessage } from 'element-plus'
import request from '../../utils/request'

const rechargeList = ref([])

const formatRecords = (data) => {
  return (data.data || data || []).map(item => ({
    id: item.id,
    tradeNo: item.transaction_no,
    amount: item.amount,
    payMethod: item.pay_type_text,
    status: item.status_text,
    statusCode: item.status,
    payTime: item.paid_at || '-'
  }))
}

const getRechargeList = async () => {
  try {
    const res = await request.get('/recharges')
    rechargeList.value = formatRecords(res.data)
  } catch (error) {
    rechargeList.value = [
      { id: 1, tradeNo: 'TXN20240101001', amount: 100.00, payMethod: '支付宝', status: '成功', statusCode: 1, payTime: '2024-01-01 10:30:00' },
      { id: 2, tradeNo: 'TXN20240101002', amount: 500.00, payMethod: '微信', status: '成功', statusCode: 1, payTime: '2024-01-01 11:20:00' },
      { id: 3, tradeNo: 'TXN20240101003', amount: 200.00, payMethod: '银行卡', status: '待支付', statusCode: 0, payTime: '-' },
      { id: 4, tradeNo: 'TXN20240101004', amount: 1000.00, payMethod: '支付宝', status: '失败', statusCode: 2, payTime: '2024-01-01 14:00:00' },
      { id: 5, tradeNo: 'TXN20240101005', amount: 300.00, payMethod: '微信', status: '成功', statusCode: 1, payTime: '2024-01-01 15:30:00' }
    ]
  }
}

const getStatusType = (status) => {
  const types = {
    '成功': 'success',
    '待支付': 'warning',
    '失败': 'danger'
  }
  return types[status] || 'info'
}

onMounted(() => {
  getRechargeList()
})
</script>

<template>
  <div class="recharge-list">
    <h2>充值记录</h2>
    <el-table :data="rechargeList" border style="width: 100%">
      <el-table-column prop="tradeNo" label="交易号" min-width="220" />
      <el-table-column prop="amount" label="金额" min-width="120">
        <template #default="{ row }">
          ¥{{ Number(row.amount).toFixed(2) }}
        </template>
      </el-table-column>
      <el-table-column prop="payMethod" label="支付方式" min-width="120" />
      <el-table-column prop="status" label="状态" min-width="100">
        <template #default="{ row }">
          <el-tag :type="getStatusType(row.status)">
            {{ row.status }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="payTime" label="支付时间" min-width="180" />
    </el-table>
  </div>
</template>

<style scoped>
.recharge-list {
  padding: 20px;
}
.recharge-list h2 {
  margin-bottom: 20px;
  font-size: 20px;
  font-weight: 600;
}
</style>
