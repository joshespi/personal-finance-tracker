<?php

namespace Tests\Feature;

use App\Models\JournalEntry;
use App\Models\Portfolio;
use App\Models\User;
use Tests\TestCase;

class JournalEntryTest extends TestCase
{
    public function test_journal_index_requires_auth(): void
    {
        $portfolio = Portfolio::factory()->create();

        $this->get(route('portfolios.journal.index', $portfolio))
            ->assertRedirect(route('login'));
    }

    public function test_journal_index_returns_ok(): void
    {
        $portfolio = Portfolio::factory()->create();

        $this->actingAs($portfolio->user)
            ->get(route('portfolios.journal.index', $portfolio))
            ->assertOk();
    }

    public function test_journal_index_forbidden_for_other_user(): void
    {
        $portfolio = Portfolio::factory()->create();
        $other     = User::factory()->create();

        $this->actingAs($other)
            ->get(route('portfolios.journal.index', $portfolio))
            ->assertForbidden();
    }

    public function test_can_create_journal_entry(): void
    {
        $portfolio = Portfolio::factory()->create();

        $this->actingAs($portfolio->user)
            ->post(route('portfolios.journal.store', $portfolio), [
                'title'      => 'Why I bought AAPL',
                'body'       => '<p>Strong fundamentals.</p>',
                'entry_date' => '2024-06-01',
            ])
            ->assertRedirect(route('portfolios.journal.index', $portfolio));

        $this->assertDatabaseHas('journal_entries', [
            'portfolio_id' => $portfolio->id,
            'title'        => 'Why I bought AAPL',
            'entry_date'   => '2024-06-01',
        ]);
    }

    public function test_create_entry_without_title(): void
    {
        $portfolio = Portfolio::factory()->create();

        $this->actingAs($portfolio->user)
            ->post(route('portfolios.journal.store', $portfolio), [
                'title'      => '',
                'body'       => '<p>Some thoughts.</p>',
                'entry_date' => '2024-06-01',
            ])
            ->assertRedirect(route('portfolios.journal.index', $portfolio));

        $this->assertDatabaseHas('journal_entries', [
            'portfolio_id' => $portfolio->id,
            'title'        => null,
        ]);
    }

    public function test_create_entry_requires_body(): void
    {
        $portfolio = Portfolio::factory()->create();

        $this->actingAs($portfolio->user)
            ->post(route('portfolios.journal.store', $portfolio), [
                'body'       => '',
                'entry_date' => '2024-06-01',
            ])
            ->assertSessionHasErrors('body');
    }

    public function test_create_entry_requires_date(): void
    {
        $portfolio = Portfolio::factory()->create();

        $this->actingAs($portfolio->user)
            ->post(route('portfolios.journal.store', $portfolio), [
                'body' => '<p>Some thoughts.</p>',
            ])
            ->assertSessionHasErrors('entry_date');
    }

    public function test_journal_index_shows_entries(): void
    {
        $portfolio = Portfolio::factory()->create();
        JournalEntry::factory()->for($portfolio)->create([
            'title'      => 'My big bet',
            'entry_date' => '2024-03-15',
        ]);

        $this->actingAs($portfolio->user)
            ->get(route('portfolios.journal.index', $portfolio))
            ->assertOk()
            ->assertSee('My big bet')
            ->assertSee('Mar 15, 2024');
    }

    public function test_can_update_journal_entry(): void
    {
        $portfolio = Portfolio::factory()->create();
        $entry     = JournalEntry::factory()->for($portfolio)->create(['title' => 'Original title']);

        $this->actingAs($portfolio->user)
            ->put(route('portfolios.journal.update', [$portfolio, $entry]), [
                'title'      => 'Updated title',
                'body'       => '<p>Updated body.</p>',
                'entry_date' => '2024-02-01',
            ])
            ->assertRedirect(route('portfolios.journal.index', $portfolio));

        $this->assertDatabaseHas('journal_entries', [
            'id'    => $entry->id,
            'title' => 'Updated title',
        ]);
    }

    public function test_can_delete_journal_entry(): void
    {
        $portfolio = Portfolio::factory()->create();
        $entry     = JournalEntry::factory()->for($portfolio)->create();

        $this->actingAs($portfolio->user)
            ->delete(route('portfolios.journal.destroy', [$portfolio, $entry]))
            ->assertRedirect(route('portfolios.journal.index', $portfolio));

        $this->assertDatabaseMissing('journal_entries', ['id' => $entry->id]);
    }

    public function test_cannot_edit_other_users_entry(): void
    {
        $portfolio = Portfolio::factory()->create();
        $other     = User::factory()->create();
        $entry     = JournalEntry::factory()->for($portfolio)->create();

        $this->actingAs($other)
            ->put(route('portfolios.journal.update', [$portfolio, $entry]), [
                'title'      => 'Hacked',
                'body'       => '<p>Hacked.</p>',
                'entry_date' => '2024-01-01',
            ])
            ->assertForbidden();
    }

    public function test_deleting_portfolio_cascades_to_entries(): void
    {
        $portfolio = Portfolio::factory()->create();
        JournalEntry::factory()->for($portfolio)->create();

        $portfolio->delete();

        $this->assertDatabaseMissing('journal_entries', ['portfolio_id' => $portfolio->id]);
    }
}
