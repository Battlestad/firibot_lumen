<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Library\FiribotService;
use App\Models\MarketPrice;

class FiribotPrices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'firibot:fetchprices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Stores prices in database';

    private function log($text) {
        \Log::info($text);
        $this->line($text);
    }

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $firibot = new FiribotService();

        foreach($firibot->getMarketsTickers() as $market) {
            $marketPrice = MarketPrice::create([
                'market'=>$market->market,
                'bid_price'=>$market->bid,
                'ask_price'=>$market->ask,
            ]);

            $this->log("Inserted new marketprice for ".$market->market." bid:".$market->bid." ask:".$market->ask);
        }

    }
}
