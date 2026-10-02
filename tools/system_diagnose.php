<?php
/**
 * GlowDrinks - System Diagnostic & Auto-Fix Tool
 * Cong cu tu dong kiem tra va sua loi he thong, database, cong mang va ma nguon.
 */

define('BASE_DIR', dirname(__DIR__));
$configFile = BASE_DIR . '/Trang chinh/config/config.php';
$targetPort = isset($argv[2]) ? (int)$argv[2] : 8000;

function printMsg($status, $title, $detail = '') {
    $symbols = [
        'SUCCESS' => '[OK]',
        'ERROR'   => '[LOI]',
        'WARNING' => '[CANH BAO]',
        'INFO'    => '[THONG TIN]'
    ];
    $tag = $symbols[$status] ?? "[$status]";
    echo "  $tag $title\n";
    if (!empty($detail)) {
        echo "        -> $detail\n";
    }
}

// 1. Giai phong cong mang (Kill port conflict)
function freePort($port) {
    printMsg('INFO', "Kiem tra trang thai cong mang $port...");
    $output = [];
    exec("netstat -ano -p tcp", $output);

    $pidsToKill = [];
    foreach ($output as $line) {
        $line = trim($line);
        if (preg_match('/TCP\s+[\d\.]+:'.$port.'\s+[\d\.]+:0\s+LISTENING\s+(\d+)/i', $line, $matches) ||
            preg_match('/TCP\s+\[::\]:'.$port.'\s+\[::\]:0\s+LISTENING\s+(\d+)/i', $line, $matches)) {
            $pidsToKill[] = (int)$matches[1];
        }
    }

    $pidsToKill = array_unique($pidsToKill);

    if (empty($pidsToKill)) {
        printMsg('SUCCESS', "Cong $port hoan toan trong va san sang su dung.");
        return true;
    }

    foreach ($pidsToKill as $pid) {
        // Lay ten process
        $procOutput = [];
        exec("tasklist /FI \"PID eq $pid\" /FO CSV /NH", $procOutput);
        $procName = 'Unknown';
        if (!empty($procOutput[0])) {
            $parts = str_getcsv($procOutput[0]);
            $procName = $parts[0] ?? 'Unknown';
        }

        if (stripos($procName, 'php') !== false) {
            printMsg('WARNING', "Cong $port dang bi chiem boi tien trinh PHP cu (PID: $pid). Dang giai phong...");
            exec("taskkill /F /PID $pid >nul 2>&1");
            printMsg('SUCCESS', "Da dong tien trinh PHP (PID: $pid) thanh cong.");
        } else {
            printMsg('WARNING', "Cong $port dang bi chiem boi ung dung: $procName (PID: $pid).");
        }
    }

    return true;
}

// 2. Kiem tra Extension PHP
function checkExtensions() {
    $required = ['pdo', 'pdo_mysql', 'mbstring', 'session', 'json'];
    $missing = [];
    foreach ($required as $ext) {
        if (!extension_loaded($ext)) {
            $missing[] = $ext;
        }
    }
    if (!empty($missing)) {
        printMsg('ERROR', 'Thieu Extension PHP: ' . implode(', ', $missing), 'Vui long mo php.ini va bo dau ";" truoc extension tuong ung.');
        return false;
    }
    printMsg('SUCCESS', 'Cac Extension PHP can thiet da san sang (' . implode(', ', $required) . ')');
    return true;
}

// 3. Lay cau hinh Database
function getDbConfig($configFile) {
    $host = 'localhost';
    $user = 'root';
    $pass = '';
    $dbname = 'drink_shop';

    if (file_exists($configFile)) {
        $content = file_get_contents($configFile);
        if (preg_match("/define\s*\(\s*['\"]DB_HOST['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/i", $content, $m)) $host = $m[1];
        if (preg_match("/define\s*\(\s*['\"]DB_USER['\"]\s*,\s*['\"]([^'\"]*)['\"]\s*\)/i", $content, $m)) $user = $m[1];
        if (preg_match("/define\s*\(\s*['\"]DB_PASS['\"]\s*,\s*['\"]([^'\"]*)['\"]\s*\)/i", $content, $m)) $pass = $m[1];
        if (preg_match("/define\s*\(\s*['\"]DB_NAME['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/i", $content, $m)) $dbname = $m[1];
    }
    return [$host, $user, $pass, $dbname];
}

// 4. Tu dong kiem tra va tao/nap Database
function checkAndFixDatabase($configFile, $forceReset = false) {
    list($host, $user, $pass, $dbname) = getDbConfig($configFile);

    printMsg('INFO', "Ket noi toi MySQL Server ($host:3306)...");

    try {
        $pdoServer = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 3
        ]);
        printMsg('SUCCESS', "Da ket noi thanh cong toi MySQL Server ($user@$host)");
    } catch (PDOException $e) {
        printMsg('ERROR', 'Khong the ket noi toi MySQL Server!', $e->getMessage());
        printMsg('WARNING', 'Vui long khoi dong dich vu MySQL (WampServer, XAMPP hoac Laragon) truoc!');
        return false;
    }

    if ($forceReset) {
        printMsg('WARNING', "Dang xoa Database cu '$dbname' va thiet lap lai...");
        $pdoServer->exec("DROP DATABASE IF EXISTS `$dbname`");
    }

    $stmt = $pdoServer->query("SHOW DATABASES LIKE '$dbname'");
    $dbExists = (bool)$stmt->fetch();

    if (!$dbExists) {
        printMsg('WARNING', "Database '$dbname' chua ton tai. Dang tu dong tao moi...");
        $pdoServer->exec("CREATE DATABASE `$dbname` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        printMsg('SUCCESS', "Da tao Database '$dbname' thanh cong.");
    }

    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($tables) || $forceReset) {
        printMsg('WARNING', "Database chua co bang du lieu. Dang tu dong import file SQL...");
        $sqlFile = BASE_DIR . '/dulieu/drink_shop.sql';
        if (!file_exists($sqlFile)) {
            $sqlFile = BASE_DIR . '/dulieu/drink_shop_full.sql';
        }

        if (file_exists($sqlFile)) {
            $sqlContent = file_get_contents($sqlFile);
            $sqlContent = preg_replace('/CREATE\s+DATABASE\s+IF\s+NOT\s+EXISTS\s+`?[^;]+;?/i', '', $sqlContent);
            $sqlContent = preg_replace('/USE\s+`?[^;]+;?/i', '', $sqlContent);

            $pdo->exec($sqlContent);
            printMsg('SUCCESS', 'Da nap cau truc va du lieu tu: ' . basename($sqlFile));

            $seedFile = BASE_DIR . '/dulieu/seed_updater.php';
            if (file_exists($seedFile)) {
                printMsg('INFO', 'Dang cap nhat hinh anh va mon an snack...');
                try {
                    include $seedFile;
                    printMsg('SUCCESS', 'Da dong bo du lieu hinh anh va menu thanh cong.');
                } catch (\Throwable $th) {
                    printMsg('WARNING', 'Luu y seed: ' . $th->getMessage());
                }
            }
        } else {
            printMsg('ERROR', 'Khong tim thay file SQL trong thu muc dulieu/ de import!');
            return false;
        }
    } else {
        try {
            $countProd = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
            $countUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
            printMsg('SUCCESS', "Database '$dbname' san sang! (" . count($tables) . " bang, $countProd san pham, $countUsers user)");
        } catch (\Throwable $th) {
            printMsg('SUCCESS', "Database '$dbname' da co " . count($tables) . " bang.");
        }
    }

    return true;
}

// 5. Quet loi cu phap PHP
function checkPhpSyntax($dir) {
    printMsg('INFO', "Dang quet kiem tra cu phap PHP trong '$dir'...");
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    $errorCount = 0;
    $checkedCount = 0;

    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
            $path = $file->getRealPath();
            $checkedCount++;
            $output = [];
            $returnVar = 0;
            exec("php -l \"$path\" 2>&1", $output, $returnVar);

            if ($returnVar !== 0) {
                $errorCount++;
                $relPath = str_replace(BASE_DIR . DIRECTORY_SEPARATOR, '', $path);
                printMsg('ERROR', "Phat hien loi cu phap: $relPath", implode("\n        ", $output));
            }
        }
    }

    if ($errorCount === 0) {
        printMsg('SUCCESS', "Toan bo $checkedCount file PHP deu hop le, khong co loi cu phap!");
    } else {
        printMsg('WARNING', "Co $errorCount file PHP bi loi cu phap, vui long kiem tra!");
    }
    return $errorCount === 0;
}

// =================== RUN ===================
$command = $argv[1] ?? 'check';

switch ($command) {
    case 'check':
        echo "\n=== TIEN HANH KIEM TRA VA TU DONG SUA LOI ===\n";
        freePort($targetPort);
        $extOk = checkExtensions();
        $dbOk = checkAndFixDatabase($configFile, false);
        echo "==============================================\n\n";
        exit(($extOk && $dbOk) ? 0 : 1);

    case 'free-port':
        echo "\n=== GIAI PHONG CONG MANG $targetPort ===\n";
        freePort($targetPort);
        echo "=========================================\n\n";
        exit(0);

    case 'reset-db':
        echo "\n=== KHOI PHUC / IMPORT LAI DATABASE ===\n";
        $ok = checkAndFixDatabase($configFile, true);
        echo "========================================\n\n";
        exit($ok ? 0 : 1);

    case 'lint':
        echo "\n=== QUET LOI CU PHAP MA NGUON PHP ===\n";
        $ok = checkPhpSyntax(BASE_DIR . '/Trang chinh');
        echo "=======================================\n\n";
        exit($ok ? 0 : 1);

    default:
        echo "Lenh ho tro: check, free-port, reset-db, lint\n";
        exit(1);
}
