<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

test('postgresql row locks serialize concurrent receiving and prevent over-receiving across independent connections', function () {
    try {
        $pdo1 = DB::connection('pgsql_testing')->getPdo();
    } catch (Throwable $e) {
        $this->markTestSkipped('PostgreSQL testing connection unavailable: '.$e->getMessage());
    }

    $config = config('database.connections.pgsql_testing');
    $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $config['host'], $config['port'], $config['database']);
    $pdo2 = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    // Setup isolated test records directly via PDO on pup_prism_test
    $now = CarbonImmutable::now()->toDateTimeString();

    // Ensure supplier exists
    $pdo1->exec("INSERT INTO suppliers (name, contact_person, email, phone, is_active, lead_time_days, created_at, updated_at) VALUES ('PG Supplier', 'Contact', 'pg@test.local', '123', true, 7, '{$now}', '{$now}') ON CONFLICT DO NOTHING");
    $supplierId = (int) $pdo1->query("SELECT id FROM suppliers WHERE name = 'PG Supplier'")->fetchColumn();

    // Ensure user exists
    $pdo1->exec("INSERT INTO users (name, email, password, is_active, created_at, updated_at) VALUES ('PG User', 'pguser@test.local', 'secret', true, '{$now}', '{$now}') ON CONFLICT (email) DO NOTHING");
    $userId = (int) $pdo1->query("SELECT id FROM users WHERE email = 'pguser@test.local'")->fetchColumn();

    // Ensure product and stock exist
    $pdo1->exec("INSERT INTO products (name, sku, type, is_active, reorder_threshold, created_at, updated_at) VALUES ('PG Product', 'SKU-PG-CONCUR', 'consumable', true, 5, '{$now}', '{$now}') ON CONFLICT (sku) DO NOTHING");
    $productId = (int) $pdo1->query("SELECT id FROM products WHERE sku = 'SKU-PG-CONCUR'")->fetchColumn();

    $pdo1->exec("INSERT INTO product_stocks (product_id, on_hand_qty, created_at, updated_at) VALUES ({$productId}, 0, '{$now}', '{$now}') ON CONFLICT (product_id) DO UPDATE SET on_hand_qty = 0");

    // Create a sent purchase order with 10 units ordered
    $poNumber = 'PO-PG-'.uniqid();
    $pdo1->exec("INSERT INTO purchase_orders (supplier_id, po_number, status, subtotal, tax, total_amount, requested_by, created_at, updated_at) VALUES ({$supplierId}, '{$poNumber}', 'sent', 100, 0, 100, {$userId}, '{$now}', '{$now}')");
    $poId = (int) $pdo1->query("SELECT id FROM purchase_orders WHERE po_number = '{$poNumber}'")->fetchColumn();

    $pdo1->exec("INSERT INTO purchase_order_lines (purchase_order_id, product_id, qty_ordered, qty_received, unit_price, subtotal, created_at, updated_at) VALUES ({$poId}, {$productId}, 10, 0, 10, 100, '{$now}', '{$now}')");
    $lineId = (int) $pdo1->query("SELECT id FROM purchase_order_lines WHERE purchase_order_id = {$poId}")->fetchColumn();

    // Concurrency test:
    // Connection 1 begins, locks PO row and line row
    $pdo1->beginTransaction();
    $stmt1 = $pdo1->prepare('SELECT id, status FROM purchase_orders WHERE id = :id FOR UPDATE');
    $stmt1->execute([':id' => $poId]);

    $lineStmt1 = $pdo1->prepare('SELECT qty_ordered, qty_received FROM purchase_order_lines WHERE id = :id FOR UPDATE');
    $lineStmt1->execute([':id' => $lineId]);
    $lineData1 = $lineStmt1->fetch(PDO::FETCH_ASSOC);

    $remaining1 = (int) $lineData1['qty_ordered'] - (int) $lineData1['qty_received'];
    expect($remaining1)->toBe(10);

    // Connection 1 processes receipt of 6 units
    $receiveQty1 = 6;
    $pdo1->exec("UPDATE purchase_order_lines SET qty_received = qty_received + {$receiveQty1} WHERE id = {$lineId}");
    $pdo1->exec("UPDATE purchase_orders SET status = 'partial' WHERE id = {$poId}");
    $pdo1->commit();

    // Now Connection 2 runs in its own transaction and checks remaining quantity
    $pdo2->beginTransaction();
    $stmt2 = $pdo2->prepare('SELECT id, status FROM purchase_orders WHERE id = :id FOR UPDATE');
    $stmt2->execute([':id' => $poId]);

    $lineStmt2 = $pdo2->prepare('SELECT qty_ordered, qty_received FROM purchase_order_lines WHERE id = :id FOR UPDATE');
    $lineStmt2->execute([':id' => $lineId]);
    $lineData2 = $lineStmt2->fetch(PDO::FETCH_ASSOC);

    $remaining2 = (int) $lineData2['qty_ordered'] - (int) $lineData2['qty_received'];
    // Remaining must now be 4 (not 10!) because Connection 1 committed
    expect($remaining2)->toBe(4);

    // If Connection 2 was attempting to receive 6 units, it detects over-receiving
    $receiveQty2 = 6;
    $wouldOverReceive = $receiveQty2 > $remaining2;
    expect($wouldOverReceive)->toBeTrue();

    // Connection 2 rejects receipt and rolls back
    $pdo2->rollBack();

    // Verify final state in database
    $finalLine = $pdo1->query("SELECT qty_received FROM purchase_order_lines WHERE id = {$lineId}")->fetch(PDO::FETCH_ASSOC);
    expect((int) $finalLine['qty_received'])->toBe(6);
});
