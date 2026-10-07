<?php

use App\Jobs\Middleware\UseTenantContext;
use App\Models\Tenant;
use App\Models\TenantProfile;
use App\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Worker;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

class TenantIsolationProbeJob implements ShouldQueue
{
    /** @var list<array{tenant: int, names: array<int, string>}> */
    public static array $observations = [];

    public function __construct(public int $tenantId, public bool $fail = false) {}

    /** @return list<UseTenantContext> */
    public function middleware(): array
    {
        return [new UseTenantContext($this->tenantId)];
    }

    public function handle(): void
    {
        self::$observations[] = [
            'tenant' => app(TenantContext::class)->requireTenant()->id,
            'names' => TenantProfile::query()->pluck('name')->all(),
        ];
        if ($this->fail) {
            throw new RuntimeException('Intentional isolation test failure.');
        }
    }
}

test('one database queue worker isolates consecutive tenants and cleans up a failed job', function () {
    $lotus = Tenant::factory()->active()->create();
    $balance = Tenant::factory()->active()->create();
    foreach ([[$lotus, 'Lotus'], [$balance, 'Balance']] as [$tenant, $name]) {
        app(TenantContext::class)->setTenant($tenant);
        TenantProfile::factory()->for($tenant)->create(['name' => $name]);
    }
    app(TenantContext::class)->clear();
    TenantIsolationProbeJob::$observations = [];
    $failures = [];
    Event::listen(JobFailed::class, function (JobFailed $event) use (&$failures): void {
        $failures[] = $event->exception->getMessage();
        expect(app(TenantContext::class)->tenant())->toBeNull();
    });
    $queue = Queue::connection('database');
    $queue->push(new TenantIsolationProbeJob($lotus->id), '', 'isolation');
    $queue->push(new TenantIsolationProbeJob($balance->id, true), '', 'isolation');
    $queue->push(new TenantIsolationProbeJob($lotus->id), '', 'isolation');
    $queue->push(new TenantIsolationProbeJob($balance->id), '', 'isolation');
    /** @var Worker $worker */
    $worker = app('queue.worker');
    for ($i = 0; $i < 4; $i++) {
        $worker->runNextJob('database', 'isolation', new WorkerOptions(sleep: 0, maxTries: 1));
        expect(app(TenantContext::class)->tenant())->toBeNull();
        expect(fn () => TenantProfile::query()->count())->toThrow(LogicException::class);
    }
    expect(TenantIsolationProbeJob::$observations)->toBe([
        ['tenant' => $lotus->id, 'names' => ['Lotus']],
        ['tenant' => $balance->id, 'names' => ['Balance']],
        ['tenant' => $lotus->id, 'names' => ['Lotus']],
        ['tenant' => $balance->id, 'names' => ['Balance']],
    ]);
    expect($failures)->toBe(['Intentional isolation test failure.']);
    expect($queue->size('isolation'))->toBe(0);
});

test('missing or inactive queued studios cannot execute and stale context is cleared', function (string $status) {
    $previous = Tenant::factory()->active()->create();
    $target = $status === 'missing' ? null : Tenant::factory()->create(['status' => $status]);
    app(TenantContext::class)->setTenant($previous);
    $called = false;
    expect(fn () => (new UseTenantContext($target?->id ?? 999999))->handle(new stdClass, function () use (&$called): void {
        $called = true;
    }))->toThrow($status === 'missing' ? ModelNotFoundException::class : LogicException::class);
    expect($called)->toBeFalse();
    expect(app(TenantContext::class)->tenant())->toBeNull();
    expect(app(TenantContext::class)->isPlatform())->toBeFalse();
})->with(['missing', 'pending', 'suspended']);
