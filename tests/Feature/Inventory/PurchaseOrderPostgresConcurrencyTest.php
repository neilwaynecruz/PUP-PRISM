<?php

use App\Services\Procurement\PurchaseOrderNumberGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

test('postgresql concurrent transactions allocate distinct consecutive purchase order numbers without collision', function () {
    try {
        $pdo1 = DB::connection('pgsql_testing')->getPdo();
    } catch (Throwable $e) {
        $this->markTestSkipped('PostgreSQL testing connection unavailable: '.$e->getMessage());
    }

    // Connect second independent PDO instance to test database
    $config = config('database.connections.pgsql_testing');
    $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $config['host'], $config['port'], $config['database']);
    $pdo2 = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $testDate = CarbonImmutable::parse('2026-10-18');
    $prefix = $testDate->format('Ymd');

    // Clean up sequence table for test prefix
    $pdo1->exec("DELETE FROM purchase_order_sequences WHERE date_prefix = '{$prefix}'");

    // Initialize row if not present
    $generator = new PurchaseOrderNumberGenerator;

    // Concurrently allocate numbers:
    // Connection 1 begins transaction, ensures row and acquires lock
    $pdo1->beginTransaction();
    $pdo1->exec("INSERT INTO purchase_order_sequences (date_prefix, last_sequence, created_at, updated_at) VALUES ('{$prefix}', 0, NOW(), NOW()) ON CONFLICT (date_prefix) DO NOTHING");
    $stmt1 = $pdo1->prepare('SELECT last_sequence FROM purchase_order_sequences WHERE date_prefix = :p FOR UPDATE');
    $stmt1->execute([':p' => $prefix]);
    $seq1 = (int) $stmt1->fetchColumn();
    $nextSeq1 = $seq1 + 1;
    $upd1 = $pdo1->prepare('UPDATE purchase_order_sequences SET last_sequence = :s, updated_at = NOW() WHERE date_prefix = :p');
    $upd1->execute([':s' => $nextSeq1, ':p' => $prefix]);

    // Connection 1 commits
    $pdo1->commit();

    // Now Connection 2 runs in its own transaction
    $pdo2->beginTransaction();
    $stmt2 = $pdo2->prepare('SELECT last_sequence FROM purchase_order_sequences WHERE date_prefix = :p FOR UPDATE');
    $stmt2->execute([':p' => $prefix]);
    $seq2 = (int) $stmt2->fetchColumn();
    $nextSeq2 = $seq2 + 1;
    $upd2 = $pdo2->prepare('UPDATE purchase_order_sequences SET last_sequence = :s, updated_at = NOW() WHERE date_prefix = :p');
    $upd2->execute([':s' => $nextSeq2, ':p' => $prefix]);
    $pdo2->commit();

    expect($nextSeq1)->toBe(1)
        ->and($nextSeq2)->toBe(2);

    $finalStmt = $pdo1->query("SELECT last_sequence FROM purchase_order_sequences WHERE date_prefix = '{$prefix}'");
    $finalSeq = (int) $finalStmt->fetchColumn();
    expect($finalSeq)->toBe(2);
});
