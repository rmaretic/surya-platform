<?php

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * Commits fixtures on the isolated test DB so independent connections can see them.
 * Both workers reach the tenant lock while the parent holds it; no timing sleeps.
 * The following test rebuilds the test DB through the existing RefreshDatabase hook.
 *
 * @param  list<array<string, mixed>>  $operations
 * @param  list<int>  $lockedTenants
 * @return list<array<string, mixed>>
 */
function bookingRace(array $operations, array $lockedTenants, ?int $independentWorker = null): array
{
    expect(DB::connection()->getDriverName())->toBe('mysql');
    expect(DB::connection()->getDatabaseName())->toBe('surya_testing');
    expect((int) config('database.connections.mysql.port'))->toBe(3308);
    DB::commit();
    RefreshDatabaseState::$migrated = false;
    $server = stream_socket_server('tcp://127.0.0.1:0');
    $address = 'tcp://'.stream_socket_get_name($server, false);
    $processes = [];
    $sockets = [];
    $connectionIds = [];
    DB::beginTransaction();
    try {
        Tenant::query()->whereIn('id', $lockedTenants)->orderBy('id')->lockForUpdate()->get();
        foreach ($operations as $operation) {
            $process = new Process([PHP_BINARY, base_path('tests/BookingRaceWorker.php')], base_path(), ['APP_ENV' => 'testing'], timeout: 30);
            $process->setInput(json_encode([
                ...$operation, 'barrier' => $address, 'database' => config('database.connections.mysql'),
            ], JSON_THROW_ON_ERROR));
            $process->start();
            $processes[] = $process;
            $socket = stream_socket_accept($server, 15);
            expect($socket, $process->getErrorOutput())->not->toBeFalse();
            stream_set_timeout($socket, 15);
            $sockets[] = $socket;
            $connectionIds[] = json_decode((string) fgets($socket), true, flags: JSON_THROW_ON_ERROR)['connection'];
        }
        expect(count(array_unique($connectionIds)))->toBe(count($operations));
        foreach ($sockets as $socket) {
            fwrite($socket, "GO\n");
        }
        foreach ($sockets as $socket) {
            expect(trim((string) fgets($socket)))->toBe('ATTEMPT');
        }
        if ($independentWorker !== null) {
            $processes[$independentWorker]->wait();
            expect($processes[$independentWorker]->isSuccessful(), $processes[$independentWorker]->getErrorOutput())->toBeTrue();
            expect(json_decode($processes[$independentWorker]->getOutput(), true, flags: JSON_THROW_ON_ERROR)['status'])->toBe('ok');
        }
        DB::commit();
        $results = [];
        foreach ($processes as $process) {
            $process->wait();
            expect($process->isSuccessful(), $process->getErrorOutput())->toBeTrue();
            $results[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        }

        return $results;
    } finally {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        foreach ($processes as $process) {
            if ($process->isRunning()) {
                $process->stop();
            }
        }
        foreach ($sockets as $socket) {
            fclose($socket);
        }
        fclose($server);
    }
}
