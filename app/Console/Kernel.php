<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Laravel\Lumen\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\Firibot::class,
        Commands\FiribotPrices::class,
        Commands\FiribotOrders::class,
        Commands\FiribotSetPriceDiffs::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command("firibot:fetchprices")->everyMinute()->environments(['production']);
        
        $schedule->command("firibot:execute BTCNOK 100 1.1")->everyMinute()->runInBackGround()->environments(['production']);
        $schedule->command("firibot:execute ETHNOK 100 1.2")->everyMinute()->runInBackGround()->environments(['production']);
        $schedule->command("firibot:execute XRPNOK 100 1.1")->everyMinute()->runInBackGround()->environments(['production']);
        $schedule->command("firibot:execute ADANOK 100 1.3")->everyMinute()->runInBackGround()->environments(['production']);
        $schedule->command("firibot:execute LTCNOK 100 1.1")->everyMinute()->runInBackGround()->environments(['production']);
        $schedule->command("firibot:execute DAINOK 100 0.5")->everyMinute()->runInBackGround()->environments(['production']);
        
        $schedule->command("firibot:orders")->hourly()->environments(['production']);
        
    }
}
