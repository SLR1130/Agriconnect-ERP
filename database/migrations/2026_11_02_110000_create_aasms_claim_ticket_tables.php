<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private const REQUIRED_MIGRATIONS = [
        '2026_11_01_070210_add_media_and_revision_support_to_agricultural_announcements',
        '2026_11_01_120000_add_aasms_requirement_versions_and_acknowledgments',
        '2026_11_02_000100_create_aasms_application_document_workflow_tables',
    ];

    public function up(): void
    {
        $this->assertActivationTarget();

        // Laravel's --pretend mode simulates every query, including schema
        // introspection, so hasTable()/count() would report false negatives.
        // Target identity is still checked in pretend mode; real execution
        // requires the migration history entries before any CREATE statements.
        if (! DB::connection()->pretending()) {
            if (! Schema::hasTable('migrations')) {
                throw new LogicException('Phase 6C.2 AASMS prerequisites are not installed in this isolated database.');
            }

            $applied = DB::table('migrations')->whereIn('migration', self::REQUIRED_MIGRATIONS)->count();
            if ($applied !== count(self::REQUIRED_MIGRATIONS)) {
                throw new LogicException('Apply the three reviewed Phase 6C.2 prerequisites to this isolated QA database before Phase 6D.1.');
            }
        }

        Schema::create('assistance_claim_tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')
                ->constrained('assistance_applications')
                ->restrictOnDelete();
            $table->string('ticket_reference', 40)->unique('aasms_claim_ticket_reference_uq');
            $table->char('token_hash', 64)->unique('aasms_claim_ticket_token_hash_uq');
            $table->text('token_ciphertext')->nullable();
            $table->string('status', 20)->default('issued');
            $table->unsignedTinyInteger('active_slot')->nullable();
            $table->foreignId('approved_supply_item_id')
                ->constrained('supply_items')
                ->restrictOnDelete();
            $table->decimal('approved_quantity', 14, 2);
            $table->timestamp('allocation_confirmed_at');
            $table->char('allocation_snapshot_sha256', 64);
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('issued_at');
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->text('revocation_reason')->nullable();
            $table->foreignId('supersedes_ticket_id')
                ->nullable()
                ->constrained('assistance_claim_tickets')
                ->restrictOnDelete();
            $table->timestamps();

            // MySQL permits multiple NULL values in a unique index. Slot 1 is
            // reserved for an active ticket; terminal tickets use NULL.
            $table->unique(['application_id', 'active_slot'], 'aasms_claim_ticket_active_uq');
            $table->unique('supersedes_ticket_id', 'aasms_claim_ticket_successor_uq');
            $table->index(['status', 'expires_at'], 'aasms_claim_ticket_status_expiry_idx');
            $table->index(['application_id', 'issued_at'], 'aasms_claim_ticket_app_issued_idx');
        });

        Schema::create('assistance_claim_ticket_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')
                ->nullable()
                ->constrained('assistance_claim_tickets')
                ->restrictOnDelete();
            $table->string('event_type', 40);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('verification_method', 20)->nullable();
            $table->string('outcome', 20)->nullable();
            $table->string('reason_code', 80)->nullable();
            $table->timestamp('occurred_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['ticket_id', 'occurred_at'], 'aasms_claim_ticket_event_timeline_idx');
            $table->index(['actor_id', 'occurred_at'], 'aasms_claim_ticket_event_actor_idx');
            $table->index(['outcome', 'occurred_at'], 'aasms_claim_ticket_event_outcome_idx');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('assistance_claim_ticket_events')
            && DB::table('assistance_claim_ticket_events')->exists()) {
            throw new LogicException('Claim-ticket audit events are immutable; this migration cannot be rolled back while evidence exists.');
        }

        if (Schema::hasTable('assistance_claim_tickets')
            && DB::table('assistance_claim_tickets')->exists()) {
            throw new LogicException('Claim tickets are historical authorization records and cannot be removed by migration rollback.');
        }

        Schema::dropIfExists('assistance_claim_ticket_events');
        Schema::dropIfExists('assistance_claim_tickets');
    }

    private function assertActivationTarget(): void
    {
        $connectionName = (string) config('database.default');
        $connectionConfig = config('database.connections.'.$connectionName, []);
        if (filled($connectionConfig['url'] ?? null)) {
            throw new LogicException('A DB URL is not accepted for claim-ticket schema activation.');
        }

        $connection = DB::connection($connectionName);
        $driver = $connection->getDriverName();
        $database = (string) $connection->getDatabaseName();

        if ($this->liveActivationAllowed($driver, $database)) {
            return;
        }

        if (App\Services\ClaimTicketFeatureGate::stagingSchemaActivationAllowed($driver, $database)) {
            return;
        }

        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Claim-ticket schema may only be installed on an explicitly approved operational database or isolated local/testing database.');
        }

        if ($driver === 'sqlite'
            && app()->environment('testing')
            && $database === ':memory:') {
            return;
        }

        if ($driver === 'sqlite') {
            $expected = (string) config('agricultural_claim_tickets.qa_sqlite_path');
            if (App\Services\ClaimTicketFeatureGate::normalizedPath($database)
                === App\Services\ClaimTicketFeatureGate::normalizedPath($expected)
                && str_contains(App\Services\ClaimTicketFeatureGate::normalizedPath($database), '/storage/app/qa/')) {
                return;
            }

            throw new LogicException('Only the designated Phase 6D.1 disposable SQLite QA file is accepted.');
        }

        if ($driver === 'mysql') {
            $prefix = 'agriconnect_erp_claimtickets_qa_';
            $expected = (string) config('agricultural_claim_tickets.qa_mysql_database');
            if ($database !== 'agriconnect_erp'
                && $database !== ''
                && $database === $expected
                && str_starts_with($database, $prefix)) {
                return;
            }

            throw new LogicException('MySQL migration requires the exact designated disposable claim-ticket QA schema.');
        }

        throw new LogicException('Unsupported claim-ticket migration database driver.');
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
};
