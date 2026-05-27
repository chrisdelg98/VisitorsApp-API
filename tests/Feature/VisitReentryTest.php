<?php

namespace Tests\Feature;

use App\Models\Station;
use App\Models\Visit;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class VisitReentryTest extends TestCase
{
    use RefreshDatabase;

    private function station(): Station
    {
        return Station::factory()->create();
    }

    /** Authenticated tablet headers for the given station. */
    private function headers(Station $station): array
    {
        return ['X-API-Key' => $station->api_key];
    }

    public function test_normal_checkout_marks_completed_with_visitor_type_and_sent_time(): void
    {
        $station = $this->station();
        $visit = Visit::factory()->for($station)->create();

        $checkout = '2026-05-27T17:30:00-06:00';

        $this->withHeaders($this->headers($station))
            ->patchJson("/api/v1/visits/{$visit->id}/checkout", ['check_out' => $checkout])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.checkout_type', 'visitor');

        $visit->refresh();
        $this->assertSame('completed', $visit->status);
        $this->assertSame('visitor', $visit->checkout_type);
        $this->assertTrue($visit->check_out->equalTo(Carbon::parse($checkout)));
    }

    public function test_checkout_without_body_defaults_to_now(): void
    {
        $station = $this->station();
        $visit = Visit::factory()->for($station)->create();

        Carbon::setTestNow('2026-05-27 12:00:00');

        $this->withHeaders($this->headers($station))
            ->patchJson("/api/v1/visits/{$visit->id}/checkout")
            ->assertOk();

        $visit->refresh();
        $this->assertTrue($visit->check_out->equalTo(Carbon::parse('2026-05-27 12:00:00')));

        Carbon::setTestNow();
    }

    public function test_reentry_reopens_a_completed_visit(): void
    {
        $station = $this->station();
        $visit = Visit::factory()->for($station)->completed()->create();

        $lastReentry = '2026-05-27T13:00:00-06:00';

        $this->withHeaders($this->headers($station))
            ->patchJson("/api/v1/visits/{$visit->id}/reentry", [
                'reentry_count' => 1,
                'last_reentry_at' => $lastReentry,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.check_out', null)
            ->assertJsonPath('data.checkout_type', null)
            ->assertJsonPath('data.reentry_count', 1);

        $visit->refresh();
        $this->assertSame('active', $visit->status);
        $this->assertNull($visit->check_out);
        $this->assertNull($visit->checkout_type);
        $this->assertSame(1, $visit->reentry_count);
        $this->assertTrue($visit->last_reentry_at->equalTo(Carbon::parse($lastReentry)));
    }

    public function test_reentry_without_count_increments_existing_count(): void
    {
        $station = $this->station();
        $visit = Visit::factory()->for($station)->completed()->create(['reentry_count' => 2]);

        $this->withHeaders($this->headers($station))
            ->patchJson("/api/v1/visits/{$visit->id}/reentry")
            ->assertOk()
            ->assertJsonPath('data.reentry_count', 3);

        $this->assertSame(3, $visit->refresh()->reentry_count);
    }

    public function test_reentry_then_second_checkout_keeps_the_afternoon_time(): void
    {
        // The original bug: midday checkout, re-entry, afternoon checkout.
        $station = $this->station();
        $visit = Visit::factory()->for($station)->create();

        // Midday checkout.
        $this->withHeaders($this->headers($station))
            ->patchJson("/api/v1/visits/{$visit->id}/checkout", [
                'check_out' => '2026-05-27T12:00:00-06:00',
            ])->assertOk();

        // Re-entry after lunch.
        $this->withHeaders($this->headers($station))
            ->patchJson("/api/v1/visits/{$visit->id}/reentry", [
                'reentry_count' => 1,
                'last_reentry_at' => '2026-05-27T13:00:00-06:00',
            ])->assertOk();

        // Final afternoon checkout.
        $afternoon = '2026-05-27T17:30:00-06:00';
        $this->withHeaders($this->headers($station))
            ->patchJson("/api/v1/visits/{$visit->id}/checkout", ['check_out' => $afternoon])
            ->assertOk();

        $visit->refresh();
        $this->assertSame('completed', $visit->status);
        $this->assertTrue(
            $visit->check_out->equalTo(Carbon::parse($afternoon)),
            'check_out should be the afternoon time, not the midday one.'
        );
    }

    public function test_reentry_is_idempotent_when_resent_with_same_count(): void
    {
        $station = $this->station();
        $visit = Visit::factory()->for($station)->completed()->create();

        $payload = [
            'reentry_count' => 1,
            'last_reentry_at' => '2026-05-27T13:00:00-06:00',
        ];

        $this->withHeaders($this->headers($station))
            ->patchJson("/api/v1/visits/{$visit->id}/reentry", $payload)
            ->assertOk()
            ->assertJsonPath('data.reentry_count', 1);

        // Resending the same payload must not inflate the count.
        $this->withHeaders($this->headers($station))
            ->patchJson("/api/v1/visits/{$visit->id}/reentry", $payload)
            ->assertOk()
            ->assertJsonPath('data.reentry_count', 1);

        $this->assertSame(1, $visit->refresh()->reentry_count);
    }

    public function test_reentry_on_foreign_station_visit_is_rejected(): void
    {
        $owner = $this->station();
        $other = $this->station();
        $visit = Visit::factory()->for($owner)->completed()->create();

        $this->withHeaders($this->headers($other))
            ->patchJson("/api/v1/visits/{$visit->id}/reentry")
            ->assertStatus(403)
            ->assertJsonPath('code', 'VISIT_FOREIGN_STATION');
    }

    public function test_checkout_on_foreign_station_visit_is_rejected(): void
    {
        $owner = $this->station();
        $other = $this->station();
        $visit = Visit::factory()->for($owner)->create();

        $this->withHeaders($this->headers($other))
            ->patchJson("/api/v1/visits/{$visit->id}/checkout")
            ->assertStatus(403)
            ->assertJsonPath('code', 'VISIT_FOREIGN_STATION');
    }

    public function test_creating_visit_with_original_id_closes_the_open_original(): void
    {
        $stationA = $this->station();
        $stationB = $this->station();
        $visitor = Visitor::factory()->create();

        $original = Visit::factory()->for($stationA)->for($visitor)->create();

        Carbon::setTestNow('2026-05-27 14:00:00');

        $this->withHeaders($this->headers($stationB))
            ->postJson('/api/v1/visits', [
                'visitor_id' => $visitor->id,
                'visitor_type' => 'guest',
                'visit_reason' => 'Meeting',
                'visiting_person' => 'Jane Doe',
                'original_visit_id' => $original->id,
                'reentry_from_station_id' => $stationA->id,
            ])
            ->assertCreated();

        $original->refresh();
        $this->assertSame('completed', $original->status);
        $this->assertSame('reentry', $original->checkout_type);
        // Closed at the new visit's check_in (now()).
        $this->assertTrue($original->check_out->equalTo(Carbon::parse('2026-05-27 14:00:00')));

        Carbon::setTestNow();
    }

    public function test_creating_visit_with_original_id_does_not_overwrite_existing_checkout(): void
    {
        $stationA = $this->station();
        $stationB = $this->station();
        $visitor = Visitor::factory()->create();

        $realCheckout = Carbon::parse('2026-05-27 10:00:00');
        $original = Visit::factory()->for($stationA)->for($visitor)->create([
            'status' => 'completed',
            'check_out' => $realCheckout,
            'checkout_type' => 'visitor',
        ]);

        $this->withHeaders($this->headers($stationB))
            ->postJson('/api/v1/visits', [
                'visitor_id' => $visitor->id,
                'visitor_type' => 'guest',
                'visit_reason' => 'Meeting',
                'visiting_person' => 'Jane Doe',
                'original_visit_id' => $original->id,
                'reentry_from_station_id' => $stationA->id,
            ])
            ->assertCreated();

        $original->refresh();
        $this->assertSame('visitor', $original->checkout_type);
        $this->assertTrue($original->check_out->equalTo($realCheckout));
    }
}
