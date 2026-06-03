<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\HandoverLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class E2eCreateHandoverCommand extends Command
{
    protected $signature = 'e2e:create-handover
                            {asset_tag_code : Tag code of the asset to hand over}
                            {from_user_id : ID of the user initiating the handover}
                            {to_user_id : ID of the recipient user}
                            {token : The raw verification token (will be hashed for storage)}
                            {--notes : Optional notes}';

    protected $description = 'Create a pre-seeded handover log with a known token for E2E testing';

    public function handle(): int
    {
        $assetTagCode = $this->argument('asset_tag_code');
        $fromUserId = (int) $this->argument('from_user_id');
        $toUserId = (int) $this->argument('to_user_id');
        $rawToken = $this->argument('token');

        $asset = Asset::query()->where('tag_code', $assetTagCode)->first();

        if (! $asset) {
            $this->error("Asset with tag code '{$assetTagCode}' not found.");

            return Command::FAILURE;
        }

        $fromUser = User::query()->find($fromUserId);

        if (! $fromUser) {
            $this->error("User with ID {$fromUserId} not found.");

            return Command::FAILURE;
        }

        $toUser = User::query()->find($toUserId);

        if (! $toUser) {
            $this->error("User with ID {$toUserId} not found.");

            return Command::FAILURE;
        }

        $handover = HandoverLog::create([
            'asset_id' => $asset->id,
            'from_user_id' => $fromUser->id,
            'to_user_id' => $toUser->id,
            'from_position_id' => $asset->position_id ?? $fromUser->position_id,
            'to_position_id' => $toUser->position_id,
            'initiated_by' => $fromUser->id,
            'initiated_at' => CarbonImmutable::now(),
            'verified_at' => null,
            'verified_by' => null,
            'verification_token_hash' => hash('sha256', $rawToken),
            'ip_address' => '127.0.0.1',
            'verified_ip_address' => null,
            'signature_png' => null,
            'notes' => $this->option('notes'),
        ]);

        $this->line("{$handover->id}");

        return Command::SUCCESS;
    }
}
