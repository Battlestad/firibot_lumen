<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Library\FiribotService;
use App\Models\MarketPrice;
use Carbon\Carbon;

class firibot extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'firibot:execute {market}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Bot for making great earnings... all of the time.. for meeeeeeee';

    private function log($text) {
        $text = $this->argument('market').": ".$text;
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
        $market = $this->argument('market');
        $this->log("Started bot for market: ".$market);

        $firibot = new FiribotService();

        $currentPriceData = $firibot->getMarketsTicker($market);
        
        $marketPrice = MarketPrice::create([
            'market'=>$market,
            'bid_price'=>$currentPriceData->bid,
            'ask_price'=>$currentPriceData->ask,
        ]);

        $this->log("Inserted new market prices:");
        $this->log($marketPrice);

        $trades = $firibot->getTrades($market);

        $lastTrade = $trades->shift();
        if (!$lastTrade) {
            $this->log("No last trade found. exiting...");
            return;
        }

        // $lastTrade = $trades->shift();
        // $lastTrade = $trades->shift();
        // $lastTrade = $trades->shift();
        // dump($lastTrade);

        $tradesToMerge = [];
        // transactions can sometimes get split up
        // example:
        // this is one transaction for 1000NOK that got split up:
        // ^ {#458
        //     +"id": "2d66c098-37ba-57da-8ec9-583d5d468c06"
        //     +"market": "BTCNOK"
        //     +"price": "358530.0300000000000000"
        //     +"price_currency": "NOK"
        //     +"amount": "0.0015087400000000"
        //     +"amount_currency": "BTC"
        //     +"cost": "540.9286050059000000"
        //     +"cost_currency": "NOK"
        //     +"side": "ask"
        //     +"isMaker": false
        //     +"date": "2022-03-20T18:51:50.591Z"
        //   }
        //   ^ {#444
        //     +"id": "4680a07d-abbf-59e0-a246-56356e9a95c6"
        //     +"market": "BTCNOK"
        //     +"price": "359495.0700000000000000"
        //     +"price_currency": "NOK"
        //     +"amount": "0.0012770000000000"
        //     +"amount_currency": "BTC"
        //     +"cost": "459.0752107750000000"
        //     +"cost_currency": "NOK"
        //     +"side": "ask"
        //     +"isMaker": false
        //     +"date": "2022-03-20T18:51:50.591Z"
        //   }

        // loop trough transactions to fetch those made within -3 seconds from the last trade of same type
        foreach($trades as $trade) {
            $timestamp = Carbon::now()->parse($trade->date)->diffInSeconds($lastTrade->date);
            if ($timestamp <= 3 && $trade->side == $lastTrade->side) {
                $tradesToMerge[] = $trade;
            }
        }

        // if multiple transactions where found, add amount and cost, and calc average price on $lastTrade
        if (!empty($tradesToMerge)) {
            foreach($tradesToMerge as $mergedTrade) {
                $lastTrade->price+=$mergedTrade->price;
                $lastTrade->amount+=$mergedTrade->amount;
                $lastTrade->cost+=$mergedTrade->cost;
            }
            $lastTrade->price = $lastTrade->price / (count($tradesToMerge) + 1);
        }

        $feeIncVal = 1 + $firibot->getFee();
        
        $previousCost = 0;
        $nextCost = 0;
        $diffAmount = 0;
        $diffPercentage = 0;

        $orderData = [
            'market' => $market,
            'type' => "",
            'price' => 0,
            'amount' => $lastTrade->amount,
        ];

        $this->log("Prev type: ".$lastTrade->side);
        $this->log("Prev price: ".$lastTrade->price);

        if ($lastTrade->side == "bid") {// if lastTrade was buy, sell
            $previousCost = $lastTrade->cost;
            $nextCost = ($lastTrade->amount * $currentPriceData->bid) * $feeIncVal;
            
            $diffAmount = $nextCost - $previousCost;
            $diffPercentage = (($nextCost - $previousCost) / $nextCost) * 100;

            $this->log("Next price: ".$currentPriceData->bid);

            // only selling if earnings goes in my favour
            if ($nextCost > $previousCost) {
                $orderData['type'] = "ask";
                $orderData['price'] = $currentPriceData->bid;
            }
        } elseif ($lastTrade->side == "ask") {// if lastTrade was sale, buy
            $previousCost = ($lastTrade->amount * $lastTrade->price) / $feeIncVal;
            $nextCost = ($lastTrade->amount * $currentPriceData->ask) * $feeIncVal;

            $diffAmount = $previousCost - $nextCost;
            $diffPercentage = (($previousCost - $nextCost) / $previousCost) * 100;

            $this->log("Next price: ".$currentPriceData->ask);
            
            // only buying if cheaper than lastTrade
            if ($nextCost < $previousCost) {
                $orderData['type'] = "bid";
                $orderData['price'] = $currentPriceData->ask;
            }
        }
        
        $this->log("Prev cost: ".$previousCost);
        $this->log("Next cost: ".$nextCost);
        $this->log("Potential earned amount: ".$diffAmount);
        $this->log("Potential earned percentage: ".$diffPercentage);

        // $orderData['type'] = "bid";
        // $orderData['price'] = $currentPriceData->ask;
        
        if ($orderData['type'] != "" && $orderData['price'] != 0 && $diffPercentage >= 1.5) {
            $this->log("Initiating new ".$orderData['type']." order");
            $this->log("Order_data:");
            $this->log($orderData);
            $firibot->order($orderData);
        } else {
            $this->log("No order was made");
        }
        $this->log("__________________________");
    }
}


