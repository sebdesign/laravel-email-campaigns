<?php

namespace Spatie\EmailCampaigns\Tests\Models;

use Illuminate\Support\Facades\Mail;
use Spatie\EmailCampaigns\Enums\SubscriptionStatus;
use Spatie\EmailCampaigns\Mails\ConfirmSubscriptionMail;
use Spatie\EmailCampaigns\Tests\TestCase;
use Spatie\EmailCampaigns\Models\EmailList;
use Spatie\EmailCampaigns\Models\Subscriber;
use Spatie\EmailCampaigns\Models\Subscription;

class SubscriberTest extends TestCase
{
    /** @var \Spatie\EmailCampaigns\Models\Subscriber */
    private $subscriber;

    public function setUp(): void
    {
        parent::setUp();

        $this->subscriber = factory(Subscriber::class)->create();

        Mail::fake();
    }

    /** @test */
    public function it_can_subscribe_itself_to_a_list()
    {
        $list = factory(EmailList::class)->create();

        $this->assertFalse($this->subscriber->isSubscribedTo($list));
        $subscription = $this->subscriber->subscribeTo($list);
        $this->assertTrue($this->subscriber->isSubscribedTo($list));

        $this->assertEquals(SubscriptionStatus::SUBSCRIBED, $subscription->status);
    }

    public function it_will_only_subscribe_a_subscriber_once()
    {
        $list = factory(EmailList::class)->create();

        $this->assertFalse($this->subscriber->isSubscribedTo($list));
        $this->subscriber->subscribeTo($list);
        $this->subscriber->subscribeTo($list);

        $this->assertEquals(1, Subscription::count());
    }

    /** @test */
    public function it_can_resubscribe_someone()
    {
        $list = factory(EmailList::class)->create();

        $subscription = $this->subscriber->subscribeTo($list);
        $this->assertTrue($this->subscriber->isSubscribedTo($list));

        $subscription->markAsUnsubscribed();
        $this->assertFalse($this->subscriber->isSubscribedTo($list));

        $this->subscriber->subscribeTo($list);
        $this->assertTrue($this->subscriber->isSubscribedTo($list));
    }

    /** @test */
    public function it_will_send_a_confirmation_mail_if_the_list_requires_double_optin()
    {
        $list = factory(EmailList::class)->create([
            'requires_double_opt_in' => true
        ]);

        $subscription = $this->subscriber->subscribeTo($list);

        $this->assertFalse($this->subscriber->isSubscribedTo($list));

        Mail::assertQueued(ConfirmSubscriptionMail::class, function (ConfirmSubscriptionMail $mail) use ($subscription) {
            $this->assertEquals($subscription->id, $mail->subscription->id);
            return true;
        });
    }

    /** @test */
    public function no_email_will_be_sent_when_adding_someone_that_was_already_subscribed()
    {
        $subscription = factory(Subscription::class)->create();

        $subscription->emailList->update(['requires_double_opt_in' => true]);

        $subscription->subscriber->subscribeTo($subscription->emailList);

        Mail::assertNothingQueued();
    }
}
