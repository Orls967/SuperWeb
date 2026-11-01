<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::unprepared("
                CREATE TRIGGER IF NOT EXISTS prevent_bank_ledger_entries_update
                BEFORE UPDATE ON bank_ledger_entries
                BEGIN
                    SELECT RAISE(ABORT, 'bank_ledger_entries is append-only');
                END;
            ");

            DB::unprepared("
                CREATE TRIGGER IF NOT EXISTS prevent_bank_ledger_entries_delete
                BEFORE DELETE ON bank_ledger_entries
                BEGIN
                    SELECT RAISE(ABORT, 'bank_ledger_entries is append-only');
                END;
            ");

            DB::unprepared("
                CREATE TRIGGER IF NOT EXISTS prevent_bank_ledger_transactions_update
                BEFORE UPDATE ON bank_ledger_transactions
                BEGIN
                    SELECT RAISE(ABORT, 'bank_ledger_transactions is append-only');
                END;
            ");

            DB::unprepared("
                CREATE TRIGGER IF NOT EXISTS prevent_bank_ledger_transactions_delete
                BEFORE DELETE ON bank_ledger_transactions
                BEGIN
                    SELECT RAISE(ABORT, 'bank_ledger_transactions is append-only');
                END;
            ");
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            DB::unprepared("
                CREATE TRIGGER prevent_bank_ledger_entries_update
                BEFORE UPDATE ON bank_ledger_entries
                FOR EACH ROW
                BEGIN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'bank_ledger_entries is append-only';
                END;
            ");

            DB::unprepared("
                CREATE TRIGGER prevent_bank_ledger_entries_delete
                BEFORE DELETE ON bank_ledger_entries
                FOR EACH ROW
                BEGIN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'bank_ledger_entries is append-only';
                END;
            ");

            DB::unprepared("
                CREATE TRIGGER prevent_bank_ledger_transactions_update
                BEFORE UPDATE ON bank_ledger_transactions
                FOR EACH ROW
                BEGIN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'bank_ledger_transactions is append-only';
                END;
            ");

            DB::unprepared("
                CREATE TRIGGER prevent_bank_ledger_transactions_delete
                BEFORE DELETE ON bank_ledger_transactions
                FOR EACH ROW
                BEGIN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'bank_ledger_transactions is append-only';
                END;
            ");
        } elseif ($driver === 'pgsql') {
            DB::unprepared("
                CREATE OR REPLACE FUNCTION prevent_ledger_tampering()
                RETURNS TRIGGER AS $$
                BEGIN
                    RAISE EXCEPTION '% is append-only', TG_TABLE_NAME;
                END;
                $$ LANGUAGE plpgsql;
            ");

            DB::unprepared('
                CREATE TRIGGER prevent_bank_ledger_entries_update
                BEFORE UPDATE ON bank_ledger_entries
                FOR EACH ROW EXECUTE FUNCTION prevent_ledger_tampering();
            ');

            DB::unprepared('
                CREATE TRIGGER prevent_bank_ledger_entries_delete
                BEFORE DELETE ON bank_ledger_entries
                FOR EACH ROW EXECUTE FUNCTION prevent_ledger_tampering();
            ');

            DB::unprepared('
                CREATE TRIGGER prevent_bank_ledger_transactions_update
                BEFORE UPDATE ON bank_ledger_transactions
                FOR EACH ROW EXECUTE FUNCTION prevent_ledger_tampering();
            ');

            DB::unprepared('
                CREATE TRIGGER prevent_bank_ledger_transactions_delete
                BEFORE DELETE ON bank_ledger_transactions
                FOR EACH ROW EXECUTE FUNCTION prevent_ledger_tampering();
            ');
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite' || $driver === 'mysql' || $driver === 'mariadb') {
            DB::unprepared('DROP TRIGGER IF EXISTS prevent_bank_ledger_entries_update');
            DB::unprepared('DROP TRIGGER IF EXISTS prevent_bank_ledger_entries_delete');
            DB::unprepared('DROP TRIGGER IF EXISTS prevent_bank_ledger_transactions_update');
            DB::unprepared('DROP TRIGGER IF EXISTS prevent_bank_ledger_transactions_delete');
        } elseif ($driver === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS prevent_bank_ledger_entries_update ON bank_ledger_entries');
            DB::unprepared('DROP TRIGGER IF EXISTS prevent_bank_ledger_entries_delete ON bank_ledger_entries');
            DB::unprepared('DROP TRIGGER IF EXISTS prevent_bank_ledger_transactions_update ON bank_ledger_transactions');
            DB::unprepared('DROP TRIGGER IF EXISTS prevent_bank_ledger_transactions_delete ON bank_ledger_transactions');
            DB::unprepared('DROP FUNCTION IF EXISTS prevent_ledger_tampering()');
        }
    }
};
