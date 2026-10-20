<?php

use Illuminate\Support\Facades\DB;
use PgSql\Connection;
use PgSql\Result;

/** @return array{0: PDO, 1: Connection} */
function phaseFourPostgresConnections(): array
{
    $pdo = DB::connection('pgsql_testing')->getPdo();
    $config = config('database.connections.pgsql_testing');
    $connectionString = sprintf(
        'host=%s port=%s dbname=%s user=%s password=%s',
        $config['host'],
        $config['port'],
        $config['database'],
        $config['username'],
        $config['password'],
    );
    $asyncConnection = pg_connect($connectionString, PGSQL_CONNECT_FORCE_NEW);

    if (! $asyncConnection instanceof Connection) {
        throw new RuntimeException('Unable to open the second PostgreSQL test connection.');
    }

    return [$pdo, $asyncConnection];
}

/** @param array<string, mixed> $parameters */
function phaseFourInsertId(PDO $pdo, string $sql, array $parameters): int
{
    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);

    return (int) $statement->fetchColumn();
}

/** @return array<string, string|null> */
function phaseFourAwaitRow(Connection $connection): array
{
    $deadline = microtime(true) + 5;

    while (pg_connection_busy($connection)) {
        pg_consume_input($connection);
        if (microtime(true) >= $deadline) {
            throw new RuntimeException('The concurrent PostgreSQL query did not finish after the blocking transaction committed.');
        }
        usleep(10_000);
    }

    $result = pg_get_result($connection);
    if (! $result instanceof Result || pg_result_status($result) !== PGSQL_TUPLES_OK) {
        throw new RuntimeException('The concurrent PostgreSQL query failed.');
    }

    return pg_fetch_assoc($result) ?: [];
}

test('postgresql row locks serialize concurrent count adjustment approvals', function () {
    if (! function_exists('pg_send_query')) {
        $this->markTestSkipped('The PostgreSQL extension with asynchronous query support is unavailable.');
    }

    try {
        [$pdo, $asyncConnection] = phaseFourPostgresConnections();
    } catch (Throwable $exception) {
        $this->markTestSkipped('PostgreSQL testing connection unavailable: '.$exception->getMessage());
    }

    $suffix = bin2hex(random_bytes(6));
    $now = now()->toDateTimeString();
    $counterId = phaseFourInsertId($pdo, 'INSERT INTO users (name, email, password, is_active, created_at, updated_at) VALUES (?, ?, ?, true, ?, ?) RETURNING id', [
        'Phase 4 Counter', "p4-counter-{$suffix}@test.local", 'secret', $now, $now,
    ]);
    $approverId = phaseFourInsertId($pdo, 'INSERT INTO users (name, email, password, is_active, created_at, updated_at) VALUES (?, ?, ?, true, ?, ?) RETURNING id', [
        'Phase 4 Approver', "p4-approver-{$suffix}@test.local", 'secret', $now, $now,
    ]);
    $productId = phaseFourInsertId($pdo, 'INSERT INTO products (name, sku, type, is_active, reorder_threshold, created_at, updated_at) VALUES (?, ?, ?, true, 0, ?, ?) RETURNING id', [
        'Phase 4 PostgreSQL Product', "P4-PG-{$suffix}", 'consumable', $now, $now,
    ]);
    $lotId = phaseFourInsertId($pdo, 'INSERT INTO stock_lots (product_id, reference_no, received_at, qty_received, qty_remaining, created_at, updated_at) VALUES (?, ?, ?, 20, 10, ?, ?) RETURNING id', [
        $productId, "P4-LOT-{$suffix}", $now, $now, $now,
    ]);
    phaseFourInsertId($pdo, 'INSERT INTO product_stocks (product_id, on_hand_qty, created_at, updated_at) VALUES (?, 10, ?, ?) RETURNING id', [$productId, $now, $now]);
    $sessionId = phaseFourInsertId($pdo, "INSERT INTO inventory_count_sessions (reference_no, created_by, assigned_to, counting_date, scope, status, snapshot_at, submitted_at, created_at, updated_at) VALUES (?, ?, ?, CURRENT_DATE, 'PostgreSQL concurrency', 'submitted', ?, ?, ?, ?) RETURNING id", [
        "COUNT-PG-{$suffix}", $counterId, $counterId, $now, $now, $now, $now,
    ]);
    $lineId = phaseFourInsertId($pdo, "INSERT INTO inventory_count_lines (inventory_count_session_id, product_id, stock_lot_id, expected_qty_snapshot, counted_qty, expected_qty_at_count, variance_qty, counted_by, counted_at, review_status, reviewed_by, reviewed_at, created_at, updated_at) VALUES (?, ?, ?, 10, 15, 10, 5, ?, ?, 'approved', ?, ?, ?, ?) RETURNING id", [
        $sessionId, $productId, $lotId, $counterId, $now, $approverId, $now, $now, $now,
    ]);

    $pdo->beginTransaction();
    $pdo->query("SELECT status FROM inventory_count_sessions WHERE id = {$sessionId} FOR UPDATE")->fetchColumn();
    $pdo->query("SELECT id FROM inventory_count_lines WHERE id = {$lineId} FOR UPDATE")->fetchColumn();
    $pdo->query("SELECT id FROM stock_lots WHERE id = {$lotId} FOR UPDATE")->fetchColumn();

    pg_query($asyncConnection, 'BEGIN');
    expect(pg_send_query($asyncConnection, "SELECT status FROM inventory_count_sessions WHERE id = {$sessionId} FOR UPDATE"))->toBeTrue();
    usleep(150_000);
    pg_consume_input($asyncConnection);
    expect(pg_connection_busy($asyncConnection))->toBeTrue();

    $pdo->exec("UPDATE product_stocks SET on_hand_qty = 15 WHERE product_id = {$productId}");
    $adjustmentLotId = phaseFourInsertId($pdo, 'INSERT INTO stock_lots (product_id, reference_no, received_at, qty_received, qty_remaining, created_at, updated_at) VALUES (?, ?, ?, 5, 5, ?, ?) RETURNING id', [
        $productId, "COUNT-PG-{$suffix}-{$lineId}", $now, $now, $now,
    ]);
    $movementId = phaseFourInsertId($pdo, "INSERT INTO stock_movements (movement_type, product_id, stock_lot_id, qty_delta, performed_by, performed_at, notes, qty_before, qty_after, reason_code, counted_qty, variance_qty, created_at, updated_at) VALUES ('cycle_count', ?, ?, 5, ?, ?, ?, 10, 15, 'variance_review', 15, 5, ?, ?) RETURNING id", [
        $productId, $adjustmentLotId, $approverId, $now, "phase-four-concurrency-{$suffix}", $now, $now,
    ]);
    $pdo->exec("UPDATE inventory_count_lines SET adjustment_movement_id = {$movementId} WHERE id = {$lineId}");
    $pdo->exec("UPDATE inventory_count_sessions SET status = 'approved', approved_by = {$approverId}, approved_at = NOW() WHERE id = {$sessionId}");
    $pdo->commit();

    $concurrentRow = phaseFourAwaitRow($asyncConnection);
    expect($concurrentRow['status'] ?? null)->toBe('approved');
    pg_query($asyncConnection, 'ROLLBACK');
    pg_close($asyncConnection);

    expect((int) $pdo->query("SELECT COUNT(*) FROM stock_movements WHERE notes = 'phase-four-concurrency-{$suffix}'")->fetchColumn())->toBe(1)
        ->and((int) $pdo->query("SELECT qty_remaining FROM stock_lots WHERE id = {$lotId}")->fetchColumn())->toBe(10)
        ->and((int) $pdo->query("SELECT SUM(qty_remaining) FROM stock_lots WHERE product_id = {$productId}")->fetchColumn())->toBe(15)
        ->and((int) $pdo->query("SELECT on_hand_qty FROM product_stocks WHERE product_id = {$productId}")->fetchColumn())->toBe(15);
});

test('postgresql row locks serialize simultaneous asset transfer approvals', function () {
    if (! function_exists('pg_send_query')) {
        $this->markTestSkipped('The PostgreSQL extension with asynchronous query support is unavailable.');
    }

    try {
        [$pdo, $asyncConnection] = phaseFourPostgresConnections();
    } catch (Throwable $exception) {
        $this->markTestSkipped('PostgreSQL testing connection unavailable: '.$exception->getMessage());
    }

    $suffix = bin2hex(random_bytes(6));
    $now = now()->toDateTimeString();
    $userId = phaseFourInsertId($pdo, 'INSERT INTO users (name, email, password, is_active, created_at, updated_at) VALUES (?, ?, ?, true, ?, ?) RETURNING id', [
        'Phase 4 Transfer User', "p4-transfer-{$suffix}@test.local", 'secret', $now, $now,
    ]);
    $productId = phaseFourInsertId($pdo, 'INSERT INTO products (name, sku, type, is_active, reorder_threshold, created_at, updated_at) VALUES (?, ?, ?, true, 0, ?, ?) RETURNING id', [
        'Phase 4 Transfer Asset', "P4-ASSET-{$suffix}", 'asset', $now, $now,
    ]);
    $sourceId = phaseFourInsertId($pdo, 'INSERT INTO locations (code, name, is_active, created_at, updated_at) VALUES (?, ?, true, ?, ?) RETURNING id', ["P4-SRC-{$suffix}", 'Source', $now, $now]);
    $destinationId = phaseFourInsertId($pdo, 'INSERT INTO locations (code, name, is_active, created_at, updated_at) VALUES (?, ?, true, ?, ?) RETURNING id', ["P4-DST-{$suffix}", 'Destination', $now, $now]);
    $assetId = phaseFourInsertId($pdo, "INSERT INTO assets (product_id, tag_code, status, current_location_id, lifecycle_status, condition_status, created_at, updated_at) VALUES (?, ?, 'Available', ?, 'active', 'serviceable', ?, ?) RETURNING id", [
        $productId, "P4-TAG-{$suffix}", $sourceId, $now, $now,
    ]);
    $transferId = phaseFourInsertId($pdo, "INSERT INTO asset_transfers (asset_id, from_location_id, to_location_id, status, reason, transfer_date, initiated_by, created_at, updated_at) VALUES (?, ?, ?, 'pending', 'Concurrent transfer test', CURRENT_DATE, ?, ?, ?) RETURNING id", [
        $assetId, $sourceId, $destinationId, $userId, $now, $now,
    ]);

    expect(fn () => phaseFourInsertId($pdo, "INSERT INTO asset_transfers (asset_id, from_location_id, to_location_id, status, reason, transfer_date, initiated_by, created_at, updated_at) VALUES (?, ?, ?, 'pending', 'Duplicate pending transfer', CURRENT_DATE, ?, ?, ?) RETURNING id", [
        $assetId, $sourceId, $destinationId, $userId, $now, $now,
    ]))->toThrow(PDOException::class);

    $pdo->beginTransaction();
    $pdo->query("SELECT status FROM asset_transfers WHERE id = {$transferId} FOR UPDATE")->fetchColumn();
    $pdo->query("SELECT current_location_id FROM assets WHERE id = {$assetId} FOR UPDATE")->fetchColumn();

    pg_query($asyncConnection, 'BEGIN');
    expect(pg_send_query($asyncConnection, "SELECT status FROM asset_transfers WHERE id = {$transferId} FOR UPDATE"))->toBeTrue();
    usleep(150_000);
    pg_consume_input($asyncConnection);
    expect(pg_connection_busy($asyncConnection))->toBeTrue();

    $pdo->exec("UPDATE assets SET current_location_id = {$destinationId} WHERE id = {$assetId}");
    $pdo->exec("UPDATE asset_transfers SET status = 'approved', approved_by = {$userId}, approved_at = NOW() WHERE id = {$transferId}");
    $pdo->commit();

    $concurrentRow = phaseFourAwaitRow($asyncConnection);
    expect($concurrentRow['status'] ?? null)->toBe('approved');
    pg_query($asyncConnection, 'ROLLBACK');
    pg_close($asyncConnection);

    expect((int) $pdo->query("SELECT current_location_id FROM assets WHERE id = {$assetId}")->fetchColumn())->toBe($destinationId)
        ->and((int) $pdo->query("SELECT COUNT(*) FROM asset_transfers WHERE asset_id = {$assetId} AND status = 'approved'")->fetchColumn())->toBe(1);
});
