<?php

namespace App\Console\Commands;

use App\Http\Services\PromotionService;
use Illuminate\Console\Command;

class SyncPromotions extends Command
{
    protected $signature = 'promotions:sync';

    protected $description = 'Apply active promotions and remove expired promotion discounts';

    public function handle(PromotionService $promotionService): int
    {
        $count = $promotionService->syncDiscountPrices();

        $this->info("Synchronized promotion prices for {$count} products.");

        return self::SUCCESS;
    }
}
