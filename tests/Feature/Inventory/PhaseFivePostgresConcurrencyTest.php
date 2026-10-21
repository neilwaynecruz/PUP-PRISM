<?php

use App\Enums\PurchaseOrderStatus;
use App\Models\User;
use App\Services\Procurement\PurchaseOrderGenerator;
use Illuminate\Support\Facades\DB;
use PgSql\Connection;
use PgSql\Result;
use PHPUnit\Framework\Assert;

test('postgresql product locks prevent concurrent duplicate draft recommendations', function () {
    if (! function_exists('pg_send_query')) {
        Assert::markTestSkipped('The PostgreSQL extension with asynchronous query support is unavailable.');
    }

    try {
        $pdo = DB::connection('pgsql_testing')->getPdo();
    } catch (Throwable $exception) {
        Assert::markTestSkipped('PostgreSQL testing connection unavailable: '.$exception->getMessage());
    }

    if (! $pdo instanceof PDO) {
        Assert::markTestSkipped('The PostgreSQL testing connection did not provide a PDO instance.');
    }

    $config = DB::connection('pgsql_testing')->getConfig();
    $connectionString = sprintf(
        'host=%s port=%s dbname=%s user=%s password=%s',
        (string) ($config['host'] ?? ''),
        (string) ($config['port'] ?? ''),
        (string) ($config['database'] ?? ''),
        (string) ($config['username'] ?? ''),
        (string) ($config['password'] ?? ''),
    );
    $asyncConnection = pg_connect($connectionString, PGSQL_CONNECT_FORCE_NEW);

    if (! $asyncConnection instanceof Connection) {
        Assert::markTestSkipped('Unable to open the second PostgreSQL test connection.');
    }

    $suffix = bin2hex(random_bytes(6));
    $now = now()->toDateTimeString();

    $insertId = static function (string $sql, array $parameters) use ($pdo): int {
        $statement = $pdo->prepare($sql);

        if (! $statement instanceof PDOStatement) {
            throw new RuntimeException('Unable to prepare the PostgreSQL test statement.');
        }

        $statement->execute($parameters);

        return (int) $statement->fetchColumn();
    };

    $supplierId = $insertId(
        'INSERT INTO suppliers (name, email, is_active, lead_time_days, created_at, updated_at) VALUES (?, ?, true, 7, ?, ?) RETURNING id',
        ["Phase 5 Supplier {$suffix}", "p5-supplier-{$suffix}@test.local", $now, $now],
    );
    $userId = $insertId(
        'INSERT INTO users (name, email, password, is_active, created_at, updated_at) VALUES (?, ?, ?, true, ?, ?) RETURNING id',
        ["Phase 5 User {$suffix}", "p5-user-{$suffix}@test.local", 'secret', $now, $now],
    );
    $productId = $insertId(
        "INSERT INTO products (name, sku, type, supplier_id, unit_price, is_active, reorder_threshold, created_at, updated_at) VALUES (?, ?, 'consumable', ?, 25, true, 15, ?, ?) RETURNING id",
        ["Phase 5 Product {$suffix}", "P5-PG-{$suffix}", $supplierId, $now, $now],
    );
    $insertId(
        'INSERT INTO product_stocks (product_id, on_hand_qty, created_at, updated_at) VALUES (?, 5, ?, ?) RETURNING id',
        [$productId, $now, $now],
    );

    $competingPoNumber = "P5-RACE-{$suffix}";
    $concurrentSql = <<<SQL
        BEGIN;
        SELECT id FROM products WHERE id = {$productId} FOR UPDATE;
        SELECT pg_sleep(0.25);
        WITH purchase_order AS (
            INSERT INTO purchase_orders
                (supplier_id, po_number, status, subtotal, tax, total_amount, requested_by, created_at, updated_at)
            VALUES
                ({$supplierId}, '{$competingPoNumber}', 'draft', 625, 0, 625, {$userId}, '{$now}', '{$now}')
            RETURNING id
        )
        INSERT INTO purchase_order_lines
            (purchase_order_id, product_id, qty_ordered, qty_received, unit_price, subtotal, created_at, updated_at)
        SELECT id, {$productId}, 25, 0, 25, 625, '{$now}', '{$now}' FROM purchase_order;
        COMMIT;
        SQL;

    expect(pg_send_query($asyncConnection, $concurrentSql))->toBeTrue();
    usleep(100_000);
    pg_consume_input($asyncConnection);
    expect(pg_connection_busy($asyncConnection))->toBeTrue();

    $originalConnection = DB::getDefaultConnection();
    DB::setDefaultConnection('pgsql_testing');

    try {
        $requestedBy = User::query()->findOrFail($userId);
        $generated = app(PurchaseOrderGenerator::class)->generateFromAlerts($requestedBy);

        expect($generated)->toBeEmpty();
    } finally {
        DB::setDefaultConnection($originalConnection);
    }

    while (($result = pg_get_result($asyncConnection)) instanceof Result) {
        expect(pg_result_status($result))->toBeIn([PGSQL_COMMAND_OK, PGSQL_TUPLES_OK]);
    }

    pg_close($asyncConnection);

    $activeOrderStatement = $pdo->prepare(
        'SELECT COUNT(*)
         FROM purchase_order_lines pol
         JOIN purchase_orders po ON po.id = pol.purchase_order_id
         WHERE pol.product_id = ?
           AND po.status IN (?, ?, ?)
           AND po.deleted_at IS NULL',
    );

    if (! $activeOrderStatement instanceof PDOStatement) {
        throw new RuntimeException('Unable to prepare the active order count statement.');
    }

    $activeOrderStatement->execute([
        $productId,
        PurchaseOrderStatus::Draft->value,
        PurchaseOrderStatus::Sent->value,
        PurchaseOrderStatus::Partial->value,
    ]);
    $activeOrderCount = (int) $activeOrderStatement->fetchColumn();

    $statusStatement = $pdo->prepare('SELECT status FROM purchase_orders WHERE po_number = ?');

    if (! $statusStatement instanceof PDOStatement) {
        throw new RuntimeException('Unable to prepare the purchase order status statement.');
    }

    $statusStatement->execute([$competingPoNumber]);

    expect($activeOrderCount)->toBe(1)
        ->and((string) $statusStatement->fetchColumn())->toBe(PurchaseOrderStatus::Draft->value);
});
