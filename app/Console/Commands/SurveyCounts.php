<?php

namespace App\Console\Commands;

use App\Models\Farm;
use App\Models\Goat;
use Illuminate\Console\Command;

class SurveyCounts extends Command
{
    protected $signature = 'survey:counts';

    protected $description = 'Show how many farm and goat records are stored in the central database';

    public function handle(): int
    {
        $this->line('Farms: '.Farm::query()->count());
        $this->line('Goats: '.Goat::query()->count());
        $this->line('Archived farms: '.Farm::onlyTrashed()->count());
        $this->line('Archived goats: '.Goat::onlyTrashed()->count());

        return self::SUCCESS;
    }
}
