<?php

namespace App\Library;
use Log;

class FiribotService {

    protected $minAmount = 11;
    protected $cacheExpireSec = 600;

    private $client = null;
    private $headers = [];

    // private $currency = "";// TODO add currency when creating object
    private $fee = 0.005;// Firi fee
    private $minPercentage = 1;// Minimum percentage difference in amount when buying/selling

    public function __construct() {
        $this->headers = [
            'miraiex-access-key'=>config('custom.firi.api_key'),
            'Content-Type'=>"application/json",
        ];
        $this->client = new \GuzzleHttp\Client();
    }

    private function endpoints($key=null, $market=null) {

        $url = config('custom.firi.url');
        $v = "v2";
        $endpoints = [
            'balances' => $url.$v.'/balances',
            'trades' => $url.$v.'/history/trades',
            'markets' => $url.$v.'/markets/'.$market,
            'markets_history' => $url.$v.'/markets/'.$market.'/history',
            'markets_ticker' => $url.$v.'/markets/'.$market.'/ticker',
            'orders' => $url.$v.'/orders',
        ];

        //Value if key exist or entire array if not
        return $endpoints[$key] ?: $endpoints;
    }

    private function request($method, $url, $headers=[], $body=[]) {
        $params = ['headers'=>$headers];

        if (!empty($body)) {
            $params['json'] = $body;
        }

        try{
            $res = $this->client->request($method, $url, $params);
            return json_decode($res->getBody());
        } catch (RequestException $ex) {
            $res = $ex->getres();
            if ($res) Log::error($response->getBody());
            return false;
        } catch (GuzzleException $ex) {
            Log::error($ex);
            return false;
        }
    }

    public function getFee() {
        return $this->fee;
    }

    public function getBalances($currency=null) {
        $balances = collect($this->request('GET', $this->endpoints('balances'), $this->headers));
        if ($currency) {
            $balances = $balances->whereIn('currency', $currency)->first();
        }
        return $balances;
    }

    public function getTrades($market=null) {
        $trades = collect($this->request('GET', $this->endpoints('trades'), $this->headers));
        if ($market) {
            $trades = $trades->whereIn('market', $market);
        }
        return $trades;
    }
    
    public function getMarkets($market=null) {
        return $this->request('GET', $this->endpoints('markets', $market));
    }
    
    public function getMarketsHistory($market=null) {
        return $this->request('GET', $this->endpoints('markets_history', $market));
    }

    public function getMarketsTicker($market=null) {
        return $this->request('GET', $this->endpoints('markets_ticker', $market));
    }
    
    //  Example:
    // ^ array:4 [
        // "market" => "BTCNOK"
        // "type" => "buy"
        // "price" => "363889.3500000000000000"
        // "amount" => "0.0027884300000000"
        // ]
    public function order($body) {
        return $this->request('POST', $this->endpoints('orders'), $this->headers, $body);
    }

//     public function orderPossible($curPriceData, $prevPriceData, $funds, $type) {

//         $prevPrice = $prevPriceData->{$type};
//         $curPrice = $curPriceData->{$type};

//         if ($prevPrice <> $curPrice) {
//             $percentageDiff = (($curPrice - $prevPrice) / $curPrice) * 100;
// dump($percentageDiff);
//             if ($type == "ask" && $percentageDiff > 0 || $type == "bid" && $percentageDiff < 0) {
// // TODO: calc amount from funds?
//                 $amount = (abs($percentageDiff) * $funds) / 100;
// dump($amount);
//                 if ($amount < 11) {
//                     $amount = (abs($percentageDiff) * $funds) / 100;
//                 }
// dd($amount);
// // $amount = 11;


// dump(
//     [
//         'prevPrice' => $prevPrice,
//         'curPrice' => $curPrice,
//         'percentage_diff' => $percentageDiff,
//         'my_amount' => $amount,
//         'funds' => $funds,
//     ]
// );

//                 $body = [
//                     'market' => "BTCNOK",
//                     'type' => ($type == "ask") ? "bid" : "ask",// buying on ask-price and selling on bid-price
//                     'price' => $curPrice,
//                     'amount' => number_format((1/$curPrice) * $amount,6),// how much crypto to buy for given amount
//                 ];

// dump($body);
//                 // return $body;

//                 // if ($amount >= $this->minAmount) {
//                     // return FiriBotService::order($body);
//                     // dump($res);
//                     // $this->log($res);

//                     // storing price that was user for order, so we dont make the same order again
//                     // \Cache::put('previousPrice', ['price'=>$currentPrice, 'timestamp'=>Carbon::now()]);// store price for next run
//                 // }
//             }

//         }
//     }

}