<?php

namespace Tests\Feature;

use App\Enums\EmailSummaryFrequency;
use App\Mail\AccountChangesSummary;
use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\Liability;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendAccountSummariesTest extends TestCase
{
    public function test_sends_daily_summary_to_opted_in_user(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email_summary_frequencies' => ['daily'],
            'email_summary_sections'    => ['budgeting'],
        ]);
        $account = CashAccount::factory()->for($user)->create();
        CashTransaction::factory()->for($account)->deposit()->create(['amount' => 250]);

        $this->artisan('email:send-summaries')->assertSuccessful();

        Mail::assertSent(AccountChangesSummary::class, fn ($mail) => $mail->hasTo($user->email)
            && $mail->frequency === EmailSummaryFrequency::Daily
            && $mail->data['budgeting']['deposits'] === 250.0);

        $this->assertNotNull($user->refresh()->last_email_summary_sent_at);
    }

    public function test_skips_user_with_no_frequency_selected(): void
    {
        Mail::fake();

        User::factory()->create(['email_summary_frequencies' => [], 'email_summary_sections' => ['budgeting']]);

        $this->artisan('email:send-summaries')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_skips_user_with_no_sections_selected(): void
    {
        Mail::fake();

        User::factory()->create(['email_summary_frequencies' => ['daily'], 'email_summary_sections' => []]);

        $this->artisan('email:send-summaries')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_weekly_summary_only_sent_on_monday(): void
    {
        Mail::fake();

        $this->travelTo(now()->next(Carbon::TUESDAY));

        User::factory()->create([
            'email_summary_frequencies' => ['weekly'],
            'email_summary_sections'    => ['budgeting'],
        ]);

        $this->artisan('email:send-summaries')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_weekly_summary_sent_on_monday(): void
    {
        Mail::fake();

        $this->travelTo(now()->next(Carbon::MONDAY));

        $user = User::factory()->create([
            'email_summary_frequencies' => ['weekly'],
            'email_summary_sections'    => ['budgeting'],
        ]);

        $this->artisan('email:send-summaries')->assertSuccessful();

        Mail::assertSent(AccountChangesSummary::class, fn ($mail) => $mail->hasTo($user->email));
    }

    public function test_monthly_summary_only_sent_on_first_of_month(): void
    {
        Mail::fake();

        $this->travelTo(now()->startOfMonth()->addDays(5));

        User::factory()->create([
            'email_summary_frequencies' => ['monthly'],
            'email_summary_sections'    => ['budgeting'],
        ]);

        $this->artisan('email:send-summaries')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_summary_email_renders_upcoming_bill_warning(): void
    {
        Mail::fake();

        // A liability with no over-budget envelope, no negative balance, and no emergency-fund
        // shortfall is the only warning present — the "Needs Attention" section must still render.
        $user = User::factory()->create([
            'email_summary_frequencies' => ['daily'],
            'email_summary_sections'    => ['warnings'],
        ]);
        Liability::factory()->for($user)->create([
            'name'            => 'Visa Card',
            'payment_day'     => 15,
            'minimum_payment' => 200,
        ]);

        $this->artisan('email:send-summaries')->assertSuccessful();

        Mail::assertSent(AccountChangesSummary::class, fn ($mail) => str_contains($mail->render(), 'Visa Card')
            && str_contains($mail->render(), '$200.00'));
    }

    public function test_skips_daily_when_weekly_also_due_same_day(): void
    {
        Mail::fake();

        $this->travelTo(now()->next(Carbon::MONDAY));

        $user = User::factory()->create([
            'email_summary_frequencies' => ['daily', 'weekly'],
            'email_summary_sections'    => ['budgeting'],
        ]);

        $this->artisan('email:send-summaries')->assertSuccessful();

        Mail::assertSentCount(1);
        Mail::assertSent(AccountChangesSummary::class, fn ($mail) => $mail->hasTo($user->email)
            && $mail->frequency === EmailSummaryFrequency::Weekly);
    }

    public function test_sends_separate_email_per_opted_in_frequency(): void
    {
        Mail::fake();

        $this->travelTo(now()->startOfMonth());

        $user = User::factory()->create([
            'email_summary_frequencies' => ['daily', 'monthly'],
            'email_summary_sections'    => ['budgeting'],
        ]);

        $this->artisan('email:send-summaries')->assertSuccessful();

        Mail::assertSentCount(2);
    }
}
