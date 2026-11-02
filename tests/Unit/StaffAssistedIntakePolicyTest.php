<?php

namespace Tests\Unit;

use App\Services\StaffAssistedIntakePolicy;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StaffAssistedIntakePolicyTest extends TestCase
{
    public function test_mysql_is_never_an_eligible_database_for_the_phase_six_c_three_qa_intake_gate(): void
    {
        config([
            'agricultural_staff_assisted.enabled' => true,
            'agricultural_staff_assisted.qa_isolated_database' => true,
            'agricultural_staff_assisted.synthetic_qa_attestations' => true,
        ]);
        DB::shouldReceive('connection')->once()->andReturn(new class {
            public function getDriverName(): string { return 'mysql'; }
        });

        $this->assertFalse(app(StaffAssistedIntakePolicy::class)->enabledForIsolatedQa());
    }

    public function test_only_the_named_sqlite_file_under_the_dedicated_qa_directory_is_allowed_outside_memory_tests(): void
    {
        config([
            'agricultural_staff_assisted.enabled' => true,
            'agricultural_staff_assisted.qa_isolated_database' => true,
            'agricultural_staff_assisted.synthetic_qa_attestations' => true,
            'agricultural_staff_assisted.qa_database_path' => storage_path('app/qa/agriconnect-phase6c3-qa.sqlite'),
        ]);
        DB::shouldReceive('connection')->once()->andReturn(new class {
            public function getDriverName(): string { return 'sqlite'; }
            public function getConfig(string $key): ?string { return $key === 'database' ? storage_path('app/qa/agriconnect-phase6c3-qa.sqlite') : null; }
        });

        $this->assertTrue(app(StaffAssistedIntakePolicy::class)->enabledForIsolatedQa());
    }
}
