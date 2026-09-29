<?php

$testRoot = sys_get_temp_dir() . '/secureit-report-access-' . bin2hex(random_bytes(8));
$tenantRoot = $testRoot . '/ncvo/latest/assets';
if (!mkdir($tenantRoot, 0775, true) && !is_dir($tenantRoot)) {
    throw new RuntimeException('Unable to create the report access test fixture.');
}

file_put_contents($testRoot . '/ncvo/latest/index.html', '<!doctype html><title>SecureIT test</title>');
file_put_contents($tenantRoot . '/report.js', 'window.secureitTest = true;');
file_put_contents($testRoot . '/outside.txt', 'must not be served');
@symlink($testRoot . '/outside.txt', $tenantRoot . '/outside-link.txt');

putenv('SECUREIT_REPORTS_ROOT=' . $testRoot);
require __DIR__ . '/../app/lib.php';

function secureit_report_access_test_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function secureit_report_access_test_remove_tree(string $path): void {
    if (!file_exists($path) && !is_link($path)) {
        return;
    }
    if (is_file($path) || is_link($path)) {
        @unlink($path);
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() && !$item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($path);
}

try {
    $indexPath = secureit_tenant_report_asset_path('ncvo', 'latest/index.html');
    secureit_report_access_test_assert(
        $indexPath === realpath($testRoot . '/ncvo/latest/index.html'),
        'A valid in-tenant report asset should resolve.'
    );
    secureit_report_access_test_assert(
        secureit_tenant_report_asset_path('ncvo', '../outside.txt') === null,
        'Parent-directory traversal must be rejected.'
    );
    secureit_report_access_test_assert(
        secureit_tenant_report_asset_path('ncvo', 'latest//index.html') === null,
        'Ambiguous empty path segments must be rejected.'
    );
    secureit_report_access_test_assert(
        secureit_tenant_report_asset_path('ncvo', 'latest/assets/outside-link.txt') === null,
        'Symlinks escaping the tenant report root must be rejected.'
    );
    secureit_report_access_test_assert(
        secureit_tenant_report_asset_path('../ncvo', 'latest/index.html') === null,
        'Invalid tenant keys must be rejected.'
    );
    secureit_report_access_test_assert(
        secureit_report_asset_content_type('report.html') === 'text/html; charset=UTF-8'
        && secureit_report_asset_content_type('summary.json') === 'application/json; charset=UTF-8'
        && secureit_report_asset_content_type('unknown.bin') === 'application/octet-stream',
        'Report assets must use the expected content types.'
    );
    $policy = secureit_report_asset_content_security_policy();
    secureit_report_access_test_assert(
        str_contains($policy, 'sandbox allow-scripts allow-downloads')
        && !str_contains($policy, 'allow-same-origin'),
        'Imported HTML must be sandboxed away from the application origin.'
    );
} finally {
    secureit_report_access_test_remove_tree($testRoot);
}

echo "SecureIT report access test passed.\n";
