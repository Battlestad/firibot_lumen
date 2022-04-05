<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MarketPrice;
use Carbon\Carbon;

class FiribotSetPriceDiffs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'firibot:setpricediffs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'updating prices with diff in price between latest price and average prices in the last 24 hours';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    private function log($text) {
        \Log::info($text);
        $this->line($text);
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // TODO. fetch markets dynamically
        $markets = [
            // 'BTCNOK',
            // 'ETHNOK',
            // 'XRPNOK',
            'DAINOK',
            // 'LTCNOK',
            // 'ADANOK',
        ];

        foreach($markets as $market) {

            MarketPrice::where('market', $market)
            // ->whereNull('bid_price_diff_pst')
            // ->whereNull('ask_price_diff_pst')
            ->chunk(200, function($marketPrices){
    
                foreach($marketPrices as $marketPrice) {
                    usleep(002);// DISK  I/O error when loop is running too fast. SQLite problem?
                    $priceDatahistory = MarketPrice::
                    where('market', $marketPrice->market)
                    ->where('created_at', '>=', Carbon::now()->parse($marketPrice->created_at)->subHours(24))
                    ->where('created_at', '<', $marketPrice->created_at)
                    ->where('id', '<', $marketPrice->id)
                    ->get();
    
                    // skipping incomplete periods (24 hours should contain around 1440 prices, one for each minute of the day)
                    if ($priceDatahistory->count() < 1000) {
                        $this->log($marketPrice->market.": ".$marketPrice->created_at->format("Y-m-d H:i:s"));
                        continue;
                    }
    
                    $avgBidPrice = round($priceDatahistory->avg('bid_price'),2);
                    $avgAskPrice = round($priceDatahistory->avg('ask_price'),2);

                    $marketPrice->bid_price_diff_pst = $this->diffInPst($marketPrice->bid_price, $avgBidPrice);
                    $marketPrice->ask_price_diff_pst = $this->diffInPst($marketPrice->ask_price, $avgAskPrice);

                    $marketPrice->save();
                    
                    $this->log($marketPrice->market.": ".$marketPrice->created_at->format("Y-m-d H:i:s")." ".$avgAskPrice." ".$marketPrice->ask_price." ".$marketPrice->ask_price_diff_pst);
                }
            });
        }

    }


    private function diffInPst($newPrice, $oldPrice) {
        if ($newPrice > $oldPrice) {// price increase
            $diffPst = (($newPrice - $oldPrice) / $oldPrice) * 100;
        } else {// price decrease
            $diffPst = -1*(($oldPrice - $newPrice) / $oldPrice) * 100;
        }
        
        return number_format($diffPst,2);
    }
}
