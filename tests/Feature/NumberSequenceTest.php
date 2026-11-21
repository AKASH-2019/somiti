<?php

namespace Tests\Feature;

use App\Services\NumberSequenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NumberSequenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_numbers_are_sequential_and_independent_per_name(): void
    {
        $seq = app(NumberSequenceService::class);
        $year = now()->format('Y');

        $this->assertSame("HO-$year-000001", $seq->next('handover', 'HO'));
        $this->assertSame("HO-$year-000002", $seq->next('handover', 'HO'));
        $this->assertSame("RCP-$year-000001", $seq->next('receipt', 'RCP'));
        $this->assertSame('SM-00001', $seq->next('somiti_account', 'SM', 5, false));
    }
}
