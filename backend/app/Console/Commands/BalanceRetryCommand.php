<?php

namespace App\Console\Commands;

use App\Enums\BalanceRetryStatus;
use App\Models\BalanceRetry;
use App\Jobs\BalanceRetryJob;
use Illuminate\Console\Command;

class BalanceRetryCommand extends Command
{
    protected $signature = 'balance:retry {--limit=100} {--dry-run}';

    protected $description = '批量处理余额不足的订单重试任务';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');

        $query = BalanceRetry::with(['order', 'user'])
            ->dueForRetry()
            ->orderBy('next_retry_at', 'asc')
            ->limit($limit);

        if ($dryRun) {
            $retries = $query->get();

            if ($retries->isEmpty()) {
                $this->info('没有需要处理的重试任务');
                return Command::SUCCESS;
            }

            $this->info("找到 {$retries->count()} 个需要处理的重试任务");

            $this->table(
                ['ID', '订单号', '用户ID', '需要金额', '当前余额', '重试次数', '下次重试时间'],
                $retries->map(fn ($r) => [
                    $r->id,
                    $r->order->order_no,
                    $r->user_id,
                    $r->required_amount,
                    $r->current_balance,
                    $r->retry_count,
                    $r->next_retry_at,
                ])
            );

            return Command::SUCCESS;
        }

        $retries = $query->get();

        if ($retries->isEmpty()) {
            $this->info('没有需要处理的重试任务');
            return Command::SUCCESS;
        }

        $this->info("找到 {$retries->count()} 个需要处理的重试任务");

        $processed = 0;
        $success = 0;
        $scheduled = 0;
        $failed = 0;

        foreach ($retries as $retry) {
            try {
                BalanceRetryJob::dispatchSync($retry->order);
                $processed++;

                $freshRetry = $retry->fresh();

                if ($freshRetry->status === BalanceRetryStatus::SUCCESS) {
                    $success++;
                    $this->info("✓ 订单 {$retry->order->order_no} 处理成功");
                } elseif ($freshRetry->status === BalanceRetryStatus::PENDING) {
                    $scheduled++;
                    $this->line("○ 订单 {$retry->order->order_no} 已安排下次重试");
                } else {
                    $failed++;
                    $this->warn("△ 订单 {$retry->order->order_no} 处理结束，状态: {$freshRetry->status_text}");
                }
            } catch (\Exception $e) {
                $failed++;
                $this->error("✗ 订单 {$retry->order->order_no} 处理失败: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("处理完成: 总计 {$processed}, 成功 {$success}, 待重试 {$scheduled}, 失败 {$failed}");

        return Command::SUCCESS;
    }
}
