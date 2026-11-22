<?php

use App\Services\ClaimTicketFeatureGate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PREREQUISITES = [
        '2026_11_01_070210_add_media_and_revision_support_to_agricultural_announcements',
        '2026_11_01_120000_add_aasms_requirement_versions_and_acknowledgments',
        '2026_11_02_000100_create_aasms_application_document_workflow_tables',
        '2026_11_02_110000_create_aasms_claim_ticket_tables',
    ];

    public function up(): void
    {
        $activationMode = $this->assertActivationTarget();

        if (! DB::connection()->pretending()) {
            if (! Schema::hasTable('migrations')
                || DB::table('migrations')->whereIn('migration', self::PREREQUISITES)->count() !== count(self::PREREQUISITES)) {
                throw new LogicException('The reviewed Phase 6C/6D prerequisites must be applied in this disposable QA database first.');
            }
            if (DB::connection()->getDriverName() === 'mysql') {
                $this->assertTransactionalEngines();
            }
        }

        if ($activationMode === 'qa') {
            Schema::create('agricultural_qa_environment_attestations', function (Blueprint $table): void {
                $table->id();
                $table->string('driver', 16);
                $table->string('database_name', 128)->unique('agri_qa_attest_database_uq');
                $table->string('server_hostname', 128)->nullable();
                $table->unsignedInteger('server_port')->nullable();
                $table->string('server_uuid', 64)->nullable();
                $table->char('source_backup_sha256', 64)->nullable();
                $table->unsignedInteger('source_table_count')->default(0);
                $table->string('attestation_kind', 40)->default('restored_disposable_qa_clone');
                $table->timestamp('attested_at');
                $table->timestamps();
            });
        }

        Schema::table('assistance_distributions', function (Blueprint $table): void {
            $table->foreignId('claim_ticket_id')->nullable()->unique('aasms_distribution_ticket_uq')
                ->constrained('assistance_claim_tickets', 'id', 'aasms_distribution_ticket_fk')
                ->restrictOnDelete();
        });

        Schema::table('assistance_claim_tickets', function (Blueprint $table): void {
            $table->timestamp('redeemed_at')->nullable();
            $table->foreignId('redeemed_by')->nullable()->constrained('users', 'id', 'aasms_ticket_redeemed_by_fk')->restrictOnDelete();
            $table->foreignId('redeemed_distribution_id')->nullable()->unique('aasms_ticket_distribution_uq')
                ->constrained('assistance_distributions', 'id', 'aasms_ticket_distribution_fk')
                ->restrictOnDelete();
        });

        Schema::create('assistance_handover_enrollments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->unique('aasms_handover_enrollment_app_uq')
                ->constrained('assistance_applications', 'id', 'aasms_hand_enr_app_fk')->restrictOnDelete();
            $table->foreignId('first_ticket_id')->unique('aasms_handover_enrollment_ticket_uq')
                ->constrained('assistance_claim_tickets', 'id', 'aasms_hand_enr_ticket_fk')->restrictOnDelete();
            $table->foreignId('enrolled_by')->nullable()->constrained('users', 'id', 'aasms_hand_enr_actor_fk')->restrictOnDelete();
            $table->timestamp('enrolled_at');
            $table->string('enrollment_reason', 48);
            $table->timestamps();
            $table->index(['enrolled_at', 'id'], 'aasms_handover_enrollment_date_idx');
        });

        Schema::create('assistance_identity_confirmations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained('assistance_applications', 'id', 'aasms_idconf_app_fk')->restrictOnDelete();
            $table->foreignId('claim_ticket_id')->constrained('assistance_claim_tickets', 'id', 'aasms_idconf_ticket_fk')->restrictOnDelete();
            $table->foreignId('verification_event_id')->unique('aasms_identity_verification_event_uq')
                ->constrained('assistance_claim_ticket_events', 'id', 'aasms_idconf_event_fk')->restrictOnDelete();
            $table->foreignId('staff_actor_id')->constrained('users', 'id', 'aasms_idconf_actor_fk')->restrictOnDelete();
            $table->string('method', 40);
            $table->string('outcome', 24);
            $table->timestamp('confirmed_at')->nullable();
            $table->char('allocation_snapshot_sha256', 64);
            $table->json('evidence_metadata')->nullable();
            $table->timestamps();
            $table->index(['application_id', 'claim_ticket_id', 'outcome'], 'aasms_identity_confirmation_lookup_idx');
        });

        Schema::create('assistance_handover_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->nullable()->constrained('assistance_applications', 'id', 'aasms_hevent_app_fk')->restrictOnDelete();
            $table->foreignId('claim_ticket_id')->nullable()->constrained('assistance_claim_tickets', 'id', 'aasms_hevent_ticket_fk')->restrictOnDelete();
            $table->foreignId('identity_confirmation_id')->nullable()->constrained('assistance_identity_confirmations', 'id', 'aasms_hevent_identity_fk')->restrictOnDelete();
            $table->foreignId('distribution_id')->nullable()->constrained('assistance_distributions', 'id', 'aasms_hevent_dist_fk')->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users', 'id', 'aasms_hevent_actor_fk')->restrictOnDelete();
            $table->string('event_type', 48);
            $table->string('outcome', 24)->nullable();
            $table->string('reason_code', 80)->nullable();
            $table->json('safe_metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['application_id', 'occurred_at'], 'aasms_handover_event_app_date_idx');
            $table->index(['claim_ticket_id', 'occurred_at'], 'aasms_handover_event_ticket_date_idx');
            $table->index(['actor_id', 'occurred_at'], 'aasms_handover_event_actor_date_idx');
        });

        Schema::create('assistance_handover_acknowledgments', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 48)->unique('aasms_handover_ack_reference_uq');
            $table->foreignId('application_id')->constrained('assistance_applications', 'id', 'aasms_hack_app_fk')->restrictOnDelete();
            $table->foreignId('claim_ticket_id')->unique('aasms_handover_ack_ticket_uq')->constrained('assistance_claim_tickets', 'id', 'aasms_hack_ticket_fk')->restrictOnDelete();
            $table->foreignId('distribution_id')->unique('aasms_handover_ack_distribution_uq')->constrained('assistance_distributions', 'id', 'aasms_hack_dist_fk')->restrictOnDelete();
            $table->foreignId('identity_confirmation_id')->unique('aasms_handover_ack_identity_uq')->constrained('assistance_identity_confirmations', 'id', 'aasms_hack_identity_fk')->restrictOnDelete();
            $table->foreignId('farmer_id')->constrained('farmers', 'id', 'aasms_hack_farmer_fk')->restrictOnDelete();
            $table->foreignId('supply_batch_id')->constrained('supply_batches', 'id', 'aasms_hack_batch_fk')->restrictOnDelete();
            $table->foreignId('distributed_by')->constrained('users', 'id', 'aasms_hack_actor_fk')->restrictOnDelete();
            $table->string('application_reference_snapshot', 80)->nullable();
            $table->string('ticket_reference_snapshot', 40);
            $table->string('farmer_name_snapshot', 255);
            $table->string('program_name_snapshot', 255);
            $table->string('item_name_snapshot', 255);
            $table->string('batch_reference_snapshot', 120);
            $table->decimal('quantity', 14, 2);
            $table->string('confirmation_method', 40);
            $table->string('environment_label', 120);
            $table->timestamp('distributed_at');
            $table->timestamp('acknowledged_at');
            $table->timestamps();
            $table->index(['farmer_id', 'acknowledged_at'], 'aasms_handover_ack_farmer_date_idx');
        });

        if (DB::connection()->pretending()) {
            return;
        }

        // Existing undistributed applications with any issued/revoked ticket
        // become ticket-governed. Existing completed/legacy distribution rows
        // remain untouched and are never assigned a fabricated ticket link.
        $applicationIds = DB::table('assistance_claim_tickets as tickets')
            ->leftJoin('assistance_distributions as distributions', 'distributions.application_id', '=', 'tickets.application_id')
            ->whereNull('distributions.id')
            ->distinct()
            ->orderBy('tickets.application_id')
            ->pluck('tickets.application_id');

        foreach ($applicationIds as $applicationId) {
            $ticketId = DB::table('assistance_claim_tickets')->where('application_id', $applicationId)->orderBy('id')->value('id');
            if ($ticketId) {
                DB::table('assistance_handover_enrollments')->insert([
                    'application_id' => $applicationId,
                    'first_ticket_id' => $ticketId,
                    'enrolled_by' => null,
                    'enrolled_at' => now(),
                    'enrollment_reason' => 'preexisting_ticket_cohort',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        if ($activationMode === 'qa' && DB::connection()->getDriverName() === 'mysql') {
            $identity = (array) DB::selectOne('SELECT DATABASE() AS database_name, @@hostname AS hostname, @@port AS port, @@server_uuid AS server_uuid');
            $tableCount = (int) DB::selectOne(
                'SELECT COUNT(*) AS aggregate FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = \'BASE TABLE\''
            )->aggregate;
            DB::table('agricultural_qa_environment_attestations')->insert([
                'driver' => 'mysql',
                'database_name' => $identity['database_name'],
                'server_hostname' => $identity['hostname'],
                'server_port' => (int) $identity['port'],
                'server_uuid' => $identity['server_uuid'],
                'source_backup_sha256' => strtolower((string) config('agricultural_claim_tickets.qa_mysql_backup_sha256')),
                'source_table_count' => $tableCount,
                'attestation_kind' => 'restored_disposable_qa_clone',
                'attested_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        foreach ([
            'assistance_handover_acknowledgments',
            'assistance_handover_events',
            'assistance_identity_confirmations',
            'assistance_handover_enrollments',
        ] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new LogicException('Phase 6E handover evidence is immutable; this migration cannot be rolled back while records exist.');
            }
        }
        if (DB::table('assistance_claim_tickets')->where('status', 'redeemed')->exists()
            || DB::table('assistance_distributions')->whereNotNull('claim_ticket_id')->exists()) {
            throw new LogicException('A redeemed ticket or linked distribution prevents Phase 6E rollback.');
        }

        Schema::table('assistance_claim_tickets', function (Blueprint $table): void {
            $table->dropForeign('aasms_ticket_distribution_fk');
            $table->dropForeign('aasms_ticket_redeemed_by_fk');
            $table->dropUnique('aasms_ticket_distribution_uq');
            $table->dropColumn(['redeemed_at', 'redeemed_by', 'redeemed_distribution_id']);
        });
        Schema::table('assistance_distributions', function (Blueprint $table): void {
            $table->dropForeign('aasms_distribution_ticket_fk');
            $table->dropUnique('aasms_distribution_ticket_uq');
            $table->dropColumn('claim_ticket_id');
        });
        Schema::dropIfExists('assistance_handover_acknowledgments');
        Schema::dropIfExists('assistance_handover_events');
        Schema::dropIfExists('assistance_identity_confirmations');
        Schema::dropIfExists('assistance_handover_enrollments');
        Schema::dropIfExists('agricultural_qa_environment_attestations');
    }

    private function assertActivationTarget(): string
    {
        $connection = DB::connection();
        $connectionName = (string) config('database.default');
        $connectionConfig = config('database.connections.'.$connectionName, []);
        if (filled($connectionConfig['url'] ?? null)) {
            throw new LogicException('A DB URL is not accepted for physical-handover schema activation.');
        }
        $driver = $connection->getDriverName();
        $database = (string) $connection->getDatabaseName();

        if ($this->liveActivationAllowed($driver, $database)) {
            return 'operational';
        }

        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Phase 6E handover schema is restricted to an explicitly approved operational database or isolated local/testing database.');
        }

        if ($driver === 'sqlite' && app()->environment('testing') && $database === ':memory:') {
            return 'qa';
        }

        if ($driver === 'sqlite') {
            $expectedPath = ClaimTicketFeatureGate::normalizedPath((string) config('agricultural_claim_tickets.qa_sqlite_path'));
            if ($expectedPath !== ''
                && ClaimTicketFeatureGate::normalizedPath($database) === $expectedPath
                && str_contains($expectedPath, '/storage/app/qa/')) {
                return 'qa';
            }
            throw new LogicException('Only the designated disposable Phase 6E SQLite QA file may receive this schema.');
        }

        if ($driver !== 'mysql') {
            throw new LogicException('Unsupported driver for isolated Phase 6E schema activation.');
        }

        $expectedDatabase = (string) config('agricultural_claim_tickets.qa_mysql_database');
        $expectedHost = (string) config('agricultural_claim_tickets.qa_mysql_host');
        $expectedPort = (string) config('agricultural_claim_tickets.qa_mysql_port');
        $expectedUuid = (string) config('agricultural_claim_tickets.qa_mysql_server_uuid');
        $expectedHash = strtolower((string) config('agricultural_claim_tickets.qa_mysql_backup_sha256'));
        $backupPath = realpath((string) config('agricultural_claim_handover.qa_backup_path'));
        $projectPath = realpath(base_path());

        if ($database === 'agriconnect_erp' || ! str_starts_with($database, 'agriconnect_erp_claimtickets_qa_')
            || $database !== $expectedDatabase || $expectedHost === '' || $expectedPort === '' || $expectedUuid === ''
            || ! preg_match('/\A[a-f0-9]{64}\z/i', $expectedHash)
            || ! $backupPath || ! is_file($backupPath) || ! $projectPath
            || str_starts_with(strtolower($backupPath), strtolower($projectPath.DIRECTORY_SEPARATOR))
            || ! hash_equals($expectedHash, strtolower((string) hash_file('sha256', $backupPath)))) {
            throw new LogicException('Phase 6E MySQL activation requires an exact restored QA clone identity and verified private backup checksum.');
        }

        // Laravel's migrate --pretend simulates SELECTs too, so the connection
        // returns empty result rows. The dry run still checks the exact schema
        // config and external backup checksum; actual execution below verifies
        // the server-reported identity before issuing any DDL.
        if ($connection->pretending()) {
            return 'qa';
        }

        $identity = (array) $connection->selectOne('SELECT DATABASE() AS database_name, @@hostname AS hostname, @@port AS port, @@server_uuid AS server_uuid');
        if ($identity['database_name'] !== $expectedDatabase
            || strcasecmp((string) $identity['hostname'], $expectedHost) !== 0
            || (string) $identity['port'] !== $expectedPort
            || ! hash_equals($expectedUuid, (string) $identity['server_uuid'])) {
            throw new LogicException('Resolved MySQL host, port, UUID, or database does not match the verified disposable QA restoration.');
        }

        return 'qa';
    }

    private function liveActivationAllowed(string $driver, string $database): bool
    {
        $expectedDatabase = (string) config('agricultural_claim_tickets.live_database');
        $expectedHash = strtolower((string) config('agricultural_claim_tickets.live_backup_sha256'));
        $backupPath = realpath((string) config('agricultural_claim_tickets.live_backup_path'));
        $projectPath = realpath(base_path());

        return $driver === 'mysql'
            && config('agricultural_claim_tickets.mode') === 'operational'
            && (bool) config('agricultural_claim_tickets.enabled')
            && (bool) config('agricultural_claim_tickets.live_enabled')
            && (bool) config('agricultural_claim_tickets.live_schema_activation_approved')
            && $database !== ''
            && $database === $expectedDatabase
            && preg_match('/\A[a-f0-9]{64}\z/i', $expectedHash) === 1
            && $backupPath !== false
            && is_file($backupPath)
            && $projectPath !== false
            && ! str_starts_with(strtolower($backupPath), strtolower($projectPath.DIRECTORY_SEPARATOR))
            && hash_equals($expectedHash, strtolower((string) hash_file('sha256', $backupPath)));
    }

    private function assertTransactionalEngines(): void
    {
        $required = ['assistance_applications', 'assistance_claim_tickets', 'assistance_claim_ticket_events', 'assistance_distributions', 'assistance_status_histories', 'supply_batches', 'inventory_movements', 'farmers', 'users'];
        $rows = DB::select('SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('.implode(',', array_fill(0, count($required), '?')).')', $required);
        $engines = collect($rows)->keyBy('TABLE_NAME');
        foreach ($required as $table) {
            if (($engines[$table]->ENGINE ?? null) !== 'InnoDB') {
                throw new LogicException('Required Phase 6E transaction table '.$table.' must use InnoDB in the disposable MySQL QA clone.');
            }
        }
    }
};
