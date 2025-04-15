<?php

namespace App\Jobs;

use App\Jobs\ProcessSingleCampaignLogJob;
use App\Models\CampaignLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Bus;

class ProcessCampaignMessagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public $timeout = 3600;
    public $tries = 3;

    public function handle()
    {
        try {
            // Process logs in chunks to avoid memory issues
            CampaignLog::with(['campaign', 'contact'])
                ->where('status', 'pending')
                ->whereHas('campaign', function ($query) {
                    $query->where('status', 'ongoing');
                })
                ->chunk(1000, function ($pendingLogs) {
                    $jobs = $pendingLogs->map(function ($log) {
                        return new ProcessSingleCampaignLogJob($log);
                    })->toArray();

                    // Dispatch jobs in batches
                    Bus::batch($jobs)
                        ->allowFailures()
                        ->onQueue('campaign-messages')
                        ->dispatch();
                });
        } catch (\Exception $e) {
            Log::error('Error in ProcessCampaignMessagesJob: ' . $e->getMessage());
            throw $e;
        }
    }
}