<?php

namespace App\Console\Commands;

use App\Services\RoomPruner;
use Illuminate\Console\Command;

class PruneInactiveRooms extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rooms:prune';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete rooms with no active players or that have been inactive for 30+ minutes';

    /**
     * Execute the console command.
     */
    public function handle(RoomPruner $pruner): void
    {
        $count = $pruner->prune();

        $this->info("Pruned {$count} inactive room(s).");
    }
}
