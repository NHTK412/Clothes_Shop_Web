<?php

namespace App\Console\Commands;

use App\Http\Services\OrderService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:sync-order')]
#[Description('Cancel unpaid VNPAY orders after the payment timeout')]
class SyncOrder extends Command
{
    public function handle(OrderService $orderService): int
    {
        $count = $orderService->syncOrders();

        $this->info("Synchronized {$count} orders.");

        return self::SUCCESS;
    }
}
