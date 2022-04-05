<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Library\FiribotService;
use App\Models\Order;
use Carbon\Carbon;

class FiribotOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'firibot:orders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Looking for open orders and checks if they should be closed. Looks for status on sale-order';

    private function log($text) {
        \Log::info($text);
        $this->line($text);
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->log("________________________________");
        $this->log("Checking order status");
        $firibot = new FiribotService();

        //forget about too old orders
        $orders = Order::
        where('status', 'open')
        ->where('created_at', '>=', Carbon::now()->subDays(60))
        ->get();

        // TODO. use endpoint that fetches all active orders at once?
        foreach($orders as $order) {
            $firiOrder = $firibot->getOrder($order->firi_sale_id);
            if ($firiOrder) {
                $order->status = "closed";
                $order->save();
                $this->log("Order ".$order->firi_id." was closed successfully");
            }
        }
    }
}


