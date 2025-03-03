<?php

namespace Spatie\EmailCampaigns\Commands;

use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Spatie\EmailCampaigns\Jobs\CalculateStatisticsJob;
use Spatie\EmailCampaigns\Models\Campaign;

class CalculateStatisticsCommand extends Command
{
    public $name = 'email-campaigns:calculate-statistics';

    public $description = 'Calculate the statistics of the recently sent campaigns';

    /** @var \Illuminate\Support\Carbon */
    protected $now;

    public function handle()
    {
        $this->comment('Start calculating statistics...');

        $this->now = now();

        collect([
            [CarbonInterval::minute(0), CarbonInterval::minute(5), CarbonInterval::minute(0)],
            [CarbonInterval::minute(0), CarbonInterval::minute(5), CarbonInterval::minute(0)],
            [CarbonInterval::minute(5), CarbonInterval::hour(2), CarbonInterval::minute(10)],
            [CarbonInterval::hour(2), CarbonInterval::day(), CarbonInterval::hour()],
            [CarbonInterval::hour(2), CarbonInterval::weeks(2), CarbonInterval::hour(4)],
        ])->each(function (array $recalculatePeriod) {
            [$startInterval, $endInterval, $recalculateThreshold] = $recalculatePeriod;

            $this
                ->findCampaignsWithStatisticsToRecalculate($startInterval, $endInterval, $recalculateThreshold)
                ->each(function (Campaign $campaign) {
                    $this->info("Calculating statistics for campaign id {$campaign->id}...");
                    dispatch_now(new CalculateStatisticsJob($campaign));
                });
        });

        $this->comment('All done!');
    }

    public function calculateStatistics(
        CarbonInterval $startInterval,
        CarbonInterval $endInterval,
        CarbonInterval $recalculateThreshold
    ): Collection
    {
        $periodStart = $this->now->copy()->add($startInterval);
        $periodEnd = $this->now->copy()->add($endInterval);

        return Campaign::sentBetween($periodStart, $periodEnd)
            ->filter(function (Campaign $campaign) use ($recalculateThreshold) {
                $threshold = $this->now->copy()->add($recalculateThreshold);

                return $campaign->statistics_calculated_at->isBefore($threshold);
            });
    }
}

