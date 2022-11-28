<?php

namespace App\Console\Commands;

use App\Http\Controllers\APIController;
use Illuminate\Console\Command;

class go extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'go';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate blog articles from Hubspot into Webflow';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $obj = new APIController();
        $obj->addHubspotPosts();
    }
}
