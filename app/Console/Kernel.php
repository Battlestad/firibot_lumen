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
        // Commands\FiriBot::class,
        Commands\FiribotPrices::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command("firibot:fetchprices")->everyMinute()->runInBackGround();

        // $schedule->command("firibot:execute BTCNOK")->everyMinute()->runInBackGround();
        // $schedule->command("firibot:execute ETHNOK")->everyMinute()->runInBackGround();
        // $schedule->command("firibot:execute XRPNOK")->everyMinute()->runInBackGround();
        // $schedule->command("firibot:execute ADANOK")->everyMinute()->runInBackGround();
        // $schedule->command("firibot:execute LTCNOK")->everyMinute()->runInBackGround();
        // $schedule->command("firibot:execute DAINOK")->everyMinute()->runInBackGround();

        // $schedule->command("firibot:execute TEST 1")->everyMinute()->runInBackGround();
    }
}
