<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Library\FiribotService;
use App\Models\MarketPrice;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class Firibot extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'firibot:execute {marketNOK} {amountNOK} {priceDiffLimit}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Bot for making great earnings... all of the time.. for meeeeeeee';

    private function log($text) {
        $text = $this->argument('marketNOK').": ".$text;
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
        $market = $this->argument('marketNOK');
        $amount = $this->argument('amountNOK');// how much NOK the bot is allowed to trade for
        $priceDiffLimit = (float)$this->argument('priceDiffLimit');// how much the price must have decreased in % vs average in the past 24 hours before making a buy-order

        $this->log("________________________________");
        $this->log("Started bot for market: ".$market);
        $firibot = new FiribotService();

        // check if sale-order id was cached in previous run (it means it was unsuccessful and we need to try again)
        $prevBuyOrderId = Cache::get($market."_prev_buy_order_id");
        $prevSaleOrderStatus = Cache::get($market."_prev_sale_order_status");
        $prevSaleOrderData = Cache::get($market."prev_sale_order_data");

        
        // try again, in case sale-order failed in previous run
        if ($prevSaleOrderStatus && $prevSaleOrderStatus == "failed") {
            $this->log("Retrying sale-order...");
            
            $res = $firibot->order($prevSaleOrderData);
            
            if ($res && $res->id) {
                $this->log("Retry order-id: ".$res->id);
                $this->log(json_encode($prevSaleOrderData));
                // update cache variable if retry sale-order was successful
                Cache::put($market."_prev_sale_order_status", "success");// if this is stored in cache, it means sale-order was successful
                Cache::forget($market."prev_sale_order_data");
                
                $prevSaleAmount = $prevSaleOrderData['amount'] * $prevSaleOrderData['price'];
                $prevEarnedAmount = $saleAmount - $amount;
                
                $preBuyOrder = Order::where('firi_id', $prevBuyOrderId)->where('status')->first();

                $preBuyOrder->firi_sale_id = $res->id;
                $preBuyOrder->sale_amount = $prevSaleAmount;
                $preBuyOrder->earned_amount = $prevEarnedAmount;
                $preBuyOrder->save();

                $this->log("Retry sale-order was successful. Exiting...");
                return;
            } else {
                $this->log("Retry sale-order was unsuccessful. Retrying next run.. Exiting...");
                return;
            }
        }

        // if active order already exists on this market, cancel
        $orders = $firibot->getOrders($market);
        
        if ($orders) {
            $this->log("Active orders found: ");
            foreach($orders as $order) {
                $this->log("Order: ".$order->id);
            }
            $this->log("Exiting...");
            return;
        }
        
        // if no active orders are found, check if we are going to perform a new buy-order (we're placing pending sale order immediately after a buy order)

        $balance = $firibot->getBalances("NOK")->available;
        if ($balance < $amount) {
            $this->log("Insufficient funds: ".$balance.". Exiting...");
            return;
        }
            
        // calc average prices from the last 24 hours
        $priceDatahistory = MarketPrice::
        where('market', $market)
        ->where('created_at', '>=', Carbon::now()->subHours(24))
        ->get();
            
        $lastPrice = $priceDatahistory->last();

        $currentPriceData = $firibot->getMarketsTicker($market);

        // if this price is the same as last stored price, remove last price before calculating avg
        if ($lastPrice->bid_price == $currentPriceData->bid && $lastPrice->ask_price == $currentPriceData->ask) {
            $lastPrice = $priceDatahistory->pop();
        }

        $avgPrice = $priceDatahistory->avg('ask_price');
        $curPrice = $currentPriceData->ask;

        $this->log("Avg price: ".$avgPrice);
        $this->log("Cur price: ".$curPrice);

        $priceDiff = (($avgPrice - $curPrice) / $avgPrice) * 100;
        $this->log("Price diff: ".$priceDiff);

        // if price has decreased more than x% compared to the average price in the last 24 hours, BUY
        if ($priceDiff > $priceDiffLimit) {

            $fee = 1.005;// Firi fee is 0.5%
            $cryptoAmount = (1/$curPrice) * ($amount / $fee);

            $buyOrderData = [
                'market' => $market,
                'type' => "bid",
                'price' => $curPrice * 1.001,// increase the price a bit to make sure order goes through
                'amount' => number_format($cryptoAmount,6),// firi is taking its fee from the local currency when buying
            ];
            $this->log("Initializing buy-order:");
            $this->log(json_encode($buyOrderData));
            
            $res = $firibot->order($buyOrderData);
            if ($res && $res->id) {
                $this->log("Order-id: ".$res->id);
                
                Cache::put($market."_prev_buy_order_id", $res->id);

                $buyOrder = New Order();
                $buyOrder->firi_id = $res->id;
                $buyOrder->market = $buyOrderData['market'];
                $buyOrder->type = $buyOrderData['type'];
                $buyOrder->price = $buyOrderData['price'];
                $buyOrder->amount = $buyOrderData['amount'];
                $buyOrder->save();

                // wait for buy-order to go through before initiating sale-order
                sleep(5);

                // placing pending sale-order immediately with the price I wanna sale for
                $saleOrderData = [
                    'market' => $market,
                    'type' => "ask",
                    'price' => $curPrice * ((100 + ($priceDiffLimit * 2)) / 100),// only selling when price has increased $priceDiffLimit*2 from when I bought
                    'amount' => number_format(round($cryptoAmount / ($fee+0.005),8,PHP_ROUND_HALF_DOWN),8), // firi is taking its fee from the crypto currency when selling. subtracting additional 0.5% for keeping a bit of crypto for myself
                ];
                Cache::put($market."prev_sale_order_data", $saleOrderData);// store in cache in case sale-order is unsuccessful, use this to try again in the beginning of next run
                Cache::put($market."_prev_sale_order_status", "failed");// init value is always set to fail

                $this->log("Initializing sale-order:");
                $this->log(json_encode($saleOrderData));

                $res = $firibot->order($saleOrderData);

                $saleAmount = $saleOrderData['amount'] * $saleOrderData['price'];
                $earnedAmount = $saleAmount - $amount;

                $this->log("Potential sale amount: ". $saleAmount." NOK");
                $this->log("Potential earned amount: ". $earnedAmount. "NOK");

                if ($res && $res->id) {
                    $this->log("Order-id: ".$res->id);
                    Cache::put($market."_prev_sale_order_status", "success");

                    $buyOrder->firi_sale_id = $res->id;
                    $buyOrder->sale_amount = $saleAmount;
                    $buyOrder->earned_amount = $earnedAmount;
                    $buyOrder->save();
                }
                
            } else {
                $this->log("Buy order failed...");
            }
        }

        
    }
}


