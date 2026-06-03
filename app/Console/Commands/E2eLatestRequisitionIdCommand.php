<?php

namespace App\Console\Commands;

use App\Models\Requisition;
use Illuminate\Console\Command;

class E2eLatestRequisitionIdCommand extends Command
{
    protected $signature = 'e2e:latest-requisition-id
                            {requester_email : Requester email address}';

    protected $description = 'Get the latest requisition ID for an E2E requester';

    public function handle(): int
    {
        $requesterEmail = $this->argument('requester_email');

        $requisition = Requisition::query()
            ->whereHas('requester', fn ($query) => $query->where('email', $requesterEmail))
            ->latest('id')
            ->first();

        if (! $requisition) {
            $this->error("No requisition found for requester '{$requesterEmail}'.");

            return Command::FAILURE;
        }

        $this->line((string) $requisition->id);

        return Command::SUCCESS;
    }
}
