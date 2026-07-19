<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Demand\ActivityTracker;
use PHPUnit\Framework\TestCase;

final class ActivityTrackerTest extends TestCase
{
    public function testFlagName(): void
    {
        $this->assertSame('demand_analytics', ActivityTracker::FLAG);
    }

    /**
     * The validated vocabulary must cover every funnel stage the spec lists, so
     * reporting stays consistent and unknown names are rejected.
     */
    public function testEventVocabularyIsComplete(): void
    {
        $required = [
            'location_manually_selected', 'category_selected', 'need_form_started', 'need_submitted',
            'provider_search_completed', 'provider_impression', 'provider_profile_viewed', 'no_provider_found',
            'search_radius_expanded', 'provider_phone_clicked', 'provider_email_clicked', 'provider_website_clicked',
            'provider_directions_clicked', 'provider_request_sent', 'provider_saved', 'provider_unsaved',
            'provider_responded', 'quote_received', 'provider_selected', 'job_booked', 'job_completed',
            'job_cancelled', 'outcome_unknown', 'review_submitted', 'demand_gap_reported',
        ];
        foreach ($required as $event) {
            $this->assertContains($event, ActivityTracker::EVENTS, "Missing event: {$event}");
        }
    }

    public function testEventVocabularyHasNoDuplicates(): void
    {
        $this->assertSame(
            count(ActivityTracker::EVENTS),
            count(array_unique(ActivityTracker::EVENTS))
        );
    }

    /** Disabled-by-default safety: recording is a no-op without the flag/DB. */
    public function testRecordIsNoOpWhenDisabled(): void
    {
        $this->assertNull(ActivityTracker::record('provider_profile_viewed', ['provider_id' => 1]));
        $this->assertNull(ActivityTracker::record('not_a_real_event'));
    }
}
