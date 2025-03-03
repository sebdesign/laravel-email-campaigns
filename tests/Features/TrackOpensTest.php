<?php

namespace Spatie\EmailCampaigns\Tests\Features;

use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Spatie\EmailCampaigns\Jobs\SendCampaignJob;
use Spatie\EmailCampaigns\Models\CampaignClick;
use Spatie\EmailCampaigns\Tests\Factories\CampaignFactory;
use Spatie\EmailCampaigns\Tests\TestCase;
use Symfony\Component\DomCrawler\Crawler;

class TrackOpensTest extends TestCase
{
    /** @var \Spatie\EmailCampaigns\Models\Campaign */
    private $campaign;

    /** @var string */
    private $openLink;

    /** @test */
    public function it_can_register_an_open()
    {
        $this->withoutExceptionHandling();

        $this->sendCampaign([
            'track_opens' => true,
            'html' => '<body></body>',
        ]);

        $this
            ->get($this->openLink)
            ->assertSuccessful();

        $this->assertDatabaseHas('campaign_opens', [
            'campaign_id' => $this->campaign->id,
            'email_list_subscriber_id' => $this->campaign->emailList->subscribers->first()->id,
        ]);
    }

    protected function sendCampaign(array $attributes)
    {
        $this->campaign = (new CampaignFactory())->withSubscriberCount(1)->create($attributes);

        Event::listen(MessageSent::class, function (MessageSent $event) {
            $link = (new Crawler($event->message->getBody()))
                ->filter('img')->first()->attr('src');

            $this->assertStringStartsWith('http://localhost', $link);
            $this->openLink = Str::after($link, 'http://localhost');
        });

        dispatch(new SendCampaignJob($this->campaign));
    }
}
