<?php

namespace App\Console\Commands;

use App\Models\HandoverLog;
use Illuminate\Console\Command;

class E2ePrepareHandoverVerificationCommand extends Command
{
    protected $signature = 'e2e:prepare-handover-verification
                            {asset_tag_code : Tag code of the handed-over asset}
                            {recipient_email : Recipient email address}
                            {token : The raw verification token to store for the handover}
                            {--wait=0 : Seconds to wait for a pending handover to exist}';

    protected $description = 'Set a known verification token on the latest pending handover for E2E testing';

    public function handle(): int
    {
        $assetTagCode = $this->argument('asset_tag_code');
        $recipientEmail = $this->argument('recipient_email');
        $token = $this->argument('token');
        $waitSeconds = max(0, (int) $this->option('wait'));

        $handover = null;
        $deadline = microtime(true) + $waitSeconds;

        do {
            $handover = HandoverLog::query()
                ->whereNull('verified_at')
                ->whereHas('asset', fn ($query) => $query->where('tag_code', $assetTagCode))
                ->whereHas('toUser', fn ($query) => $query->where('email', $recipientEmail))
                ->latest('id')
                ->first();

            if ($handover instanceof HandoverLog) {
                break;
            }

            if ($waitSeconds > 0) {
                usleep(250000);
            }
        } while (microtime(true) < $deadline);

        if (! $handover) {
            $this->error("Pending handover not found for asset '{$assetTagCode}' and recipient '{$recipientEmail}'.");

            return Command::FAILURE;
        }

        $handover->forceFill([
            'verification_token_hash' => hash('sha256', $token),
        ])->save();

        $this->line((string) $handover->id);

        return Command::SUCCESS;
    }
}
