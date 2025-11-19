<?php
    // Start output buffering untuk mencegah output sebelum JSON
    ob_start();

    // Disable error display untuk mencegah output error sebelum JSON
    ini_set('display_errors', 0);
    error_reporting(E_ALL);

    session_start();

    // Periksa apakah sesi login ada
    if (!isset($_SESSION["ssLogin"])) {
        ob_clean();
        header("location:../../auth/login.php");
        ob_end_flush();
        exit();
    }

    require_once "../../config/config.php";
    require_once "../../config/functions.php";

    // Fungsi untuk generate nomor pengembalian berdasarkan gudang peminjam dan tanggal
    function generateNomorPengembalian($gudang_asal, $entitas_peminjam, $tanggal_pengembalian, $pdo) {
        global $db_dc;
        
        // Query untuk mendapatkan kode_tim dan tim dari base_tim berdasarkan gudang
        $query = "SELECT bt.kode_tim, bt.tim as nama_tim
                  FROM gudang_omni go
                  INNER JOIN base_tim bt ON bt.tim COLLATE utf8mb4_unicode_ci = go.tim COLLATE utf8mb4_unicode_ci
                  WHERE go.nama_gudang COLLATE utf8mb4_unicode_ci = ? COLLATE utf8mb4_unicode_ci
                  LIMIT 1";
        
        $stmt = mysqli_prepare($db_dc, $query);
        $nama_tim = '';
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $gudang_asal);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            $code_tim = '';
            if ($row = mysqli_fetch_assoc($result)) {
                $code_tim = isset($row['kode_tim']) ? $row['kode_tim'] : '';
                $nama_tim = isset($row['nama_tim']) ? $row['nama_tim'] : '';
            }
            mysqli_stmt_close($stmt);
            
            // Jika code_tim kosong, gunakan 3 karakter pertama dari gudang
            if (empty($code_tim)) {
                $code_tim = strtoupper(substr($gudang_asal, 0, 3));
            }
            
            // Format tanggal untuk nomor pengembalian
            if (empty($tanggal_pengembalian)) {
                throw new Exception("Tanggal pengembalian tidak boleh kosong");
            }
            $dateObj = new DateTime($tanggal_pengembalian);
            $month = $dateObj->format('m');
            $year = $dateObj->format('y');
            $formattedDate = $month . $year;
            
            // Cari nomor pengembalian terakhir berdasarkan code_tim (tim) yang sama dan bulan
            // Format sudah konsisten, tidak perlu normalisasi lagi
            $expectedPrefix = "PB/" . $code_tim . "/" . $formattedDate . "/";
            $nextNo = 1;
            
            $queryCount = "SELECT m.nomor_pengembalian,
                                  CAST(SUBSTRING_INDEX(m.nomor_pengembalian, '/', -1) AS UNSIGNED) as nomor_urut,
                                  SUBSTRING_INDEX(SUBSTRING_INDEX(m.nomor_pengembalian, '/', 2), '/', -1) as code_tim_from_nomor,
                                  SUBSTRING_INDEX(SUBSTRING_INDEX(m.nomor_pengembalian, '/', 3), '/', -1) as date_from_nomor
                          FROM pengembalian_stok m
                          INNER JOIN gudang_omni go ON go.nama_gudang COLLATE utf8mb4_unicode_ci = m.gudang_asal COLLATE utf8mb4_unicode_ci
                          INNER JOIN base_tim bt ON bt.tim COLLATE utf8mb4_unicode_ci = go.tim COLLATE utf8mb4_unicode_ci
                          WHERE bt.kode_tim = ?
                          AND m.tanggal_pengembalian >= DATE_FORMAT(?, '%Y-%m-01')
                          AND m.tanggal_pengembalian < DATE_ADD(DATE_FORMAT(?, '%Y-%m-01'), INTERVAL 1 MONTH)
                          AND m.nomor_pengembalian IS NOT NULL
                          AND m.nomor_pengembalian != ''
                          AND TRIM(m.nomor_pengembalian) != ''
                          AND m.nomor_pengembalian LIKE 'PB/%'
                          HAVING code_tim_from_nomor = ?
                          AND date_from_nomor = ?
                          ORDER BY nomor_urut DESC, m.nomor_pengembalian DESC
                          LIMIT 1";
            
            $stmtCount = mysqli_prepare($db_dc, $queryCount);
            if ($stmtCount) {
                mysqli_stmt_bind_param($stmtCount, "sssss", $code_tim, $tanggal_pengembalian, $tanggal_pengembalian, $code_tim, $formattedDate);
                mysqli_stmt_execute($stmtCount);
                $resultCount = mysqli_stmt_get_result($stmtCount);
                
                if ($rowCount = mysqli_fetch_assoc($resultCount)) {
                    $nomor_urut = intval($rowCount['nomor_urut']);
                    $nextNo = $nomor_urut + 1;
                }
                mysqli_stmt_close($stmtCount);
            }
            
            // Format nomor pengembalian: PB/CODE_TIM/MMYY/XXX
            $nomor_pengembalian = "PB/" . $code_tim . "/" . $formattedDate . "/" . str_pad($nextNo, 3, '0', STR_PAD_LEFT);
            
            return $nomor_pengembalian;
        } else {
            throw new Exception("Error preparing statement untuk generate nomor pengembalian");
        }
    }

    // Fungsi untuk generate nomor peminjaman berdasarkan gudang peminjam dan tanggal
    function generateNomorPeminjaman($gudang_asal, $entitas_peminjam, $tanggal_peminjaman, $pdo) {
        global $db_dc;
        
        // Query untuk mendapatkan kode_tim dan tim dari base_tim berdasarkan gudang
        $query = "SELECT bt.kode_tim, bt.tim as nama_tim
                  FROM gudang_omni go
                  INNER JOIN base_tim bt ON bt.tim COLLATE utf8mb4_unicode_ci = go.tim COLLATE utf8mb4_unicode_ci
                  WHERE go.nama_gudang COLLATE utf8mb4_unicode_ci = ? COLLATE utf8mb4_unicode_ci
                  LIMIT 1";
        
        $stmt = mysqli_prepare($db_dc, $query);
        $nama_tim = '';
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $gudang_asal);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            $code_tim = '';
            if ($row = mysqli_fetch_assoc($result)) {
                $code_tim = isset($row['kode_tim']) ? $row['kode_tim'] : '';
                $nama_tim = isset($row['nama_tim']) ? $row['nama_tim'] : '';
            }
            mysqli_stmt_close($stmt);
            
            // Jika code_tim kosong, gunakan 3 karakter pertama dari gudang
            if (empty($code_tim)) {
                $code_tim = strtoupper(substr($gudang_asal, 0, 3));
            }
            
            // Format tanggal untuk nomor peminjaman
            if (empty($tanggal_peminjaman)) {
                throw new Exception("Tanggal peminjaman tidak boleh kosong");
            }
            $dateObj = new DateTime($tanggal_peminjaman);
            $month = $dateObj->format('m');
            $year = $dateObj->format('y');
            $formattedDate = $month . $year;
            
            // Cari nomor peminjaman terakhir berdasarkan code_tim (tim) yang sama dan bulan
            // Gunakan code_tim yang sudah dinormalisasi untuk pencarian
            $expectedPrefix = "PJ/" . $code_tim . "/" . $formattedDate . "/";
            $nextNo = 1;
            
            // Format sudah konsisten, tidak perlu normalisasi lagi
            $queryCount = "SELECT m.nomor_peminjaman,
                                  CAST(SUBSTRING_INDEX(m.nomor_peminjaman, '/', -1) AS UNSIGNED) as nomor_urut,
                                  SUBSTRING_INDEX(SUBSTRING_INDEX(m.nomor_peminjaman, '/', 2), '/', -1) as code_tim_from_nomor,
                                  SUBSTRING_INDEX(SUBSTRING_INDEX(m.nomor_peminjaman, '/', 3), '/', -1) as date_from_nomor
                          FROM peminjaman_stok m
                          INNER JOIN gudang_omni go ON go.nama_gudang COLLATE utf8mb4_unicode_ci = m.gudang_asal COLLATE utf8mb4_unicode_ci
                          INNER JOIN base_tim bt ON bt.tim COLLATE utf8mb4_unicode_ci = go.tim COLLATE utf8mb4_unicode_ci
                          WHERE bt.kode_tim = ?
                          AND m.tanggal_peminjaman >= DATE_FORMAT(?, '%Y-%m-01')
                          AND m.tanggal_peminjaman < DATE_ADD(DATE_FORMAT(?, '%Y-%m-01'), INTERVAL 1 MONTH)
                          AND m.nomor_peminjaman IS NOT NULL
                          AND m.nomor_peminjaman != ''
                          AND TRIM(m.nomor_peminjaman) != ''
                          AND m.nomor_peminjaman LIKE 'PJ/%'
                          HAVING code_tim_from_nomor = ?
                          AND date_from_nomor = ?
                          ORDER BY nomor_urut DESC, m.nomor_peminjaman DESC
                          LIMIT 1";
            
            $stmtCount = mysqli_prepare($db_dc, $queryCount);
            if ($stmtCount && !empty($code_tim)) {
                mysqli_stmt_bind_param($stmtCount, "sssss", $code_tim, $tanggal_peminjaman, $tanggal_peminjaman, $code_tim, $formattedDate);
                mysqli_stmt_execute($stmtCount);
                $resultCount = mysqli_stmt_get_result($stmtCount);
                
                if ($rowCount = mysqli_fetch_assoc($resultCount)) {
                    $lastNomor = $rowCount['nomor_peminjaman'];
                    $parts = explode('/', $lastNomor);
                    if (count($parts) >= 4) {
                        $lastNo = intval($parts[3]);
                        $nextNo = $lastNo + 1;
                    } else {
                        $lastNo = intval(substr($lastNomor, strrpos($lastNomor, '/') + 1));
                        if ($lastNo > 0) {
                            $nextNo = $lastNo + 1;
                        }
                    }
                }
                mysqli_stmt_close($stmtCount);
            }
            
            // Fallback: Jika code_tim kosong atau query gagal, gunakan gudang_asal
            if ($nextNo == 1 && empty($code_tim) && !empty($gudang_asal)) {
                $queryCountFallback = "SELECT nomor_peminjaman,
                                             CAST(SUBSTRING_INDEX(nomor_peminjaman, '/', -1) AS UNSIGNED) as nomor_urut
                                          FROM peminjaman_stok 
                                          WHERE gudang_asal = ? 
                                          AND YEAR(tanggal_peminjaman) = YEAR(?)
                                          AND MONTH(tanggal_peminjaman) = MONTH(?)
                                          AND nomor_peminjaman IS NOT NULL
                                          AND nomor_peminjaman != ''
                                          AND TRIM(nomor_peminjaman) != ''
                                      AND nomor_peminjaman LIKE ?
                                      ORDER BY nomor_urut DESC, nomor_peminjaman DESC
                                      LIMIT 1";
                
                $stmtCountFallback = mysqli_prepare($db_dc, $queryCountFallback);
                if ($stmtCountFallback) {
                    $likePattern = $expectedPrefix . '%';
                    mysqli_stmt_bind_param($stmtCountFallback, "ssss", $gudang_asal, $tanggal_peminjaman, $tanggal_peminjaman, $likePattern);
                    mysqli_stmt_execute($stmtCountFallback);
                    $resultCountFallback = mysqli_stmt_get_result($stmtCountFallback);
                
                    if ($rowCountFallback = mysqli_fetch_assoc($resultCountFallback)) {
                        $lastNomor = $rowCountFallback['nomor_peminjaman'];
                        $parts = explode('/', $lastNomor);
                        if (count($parts) >= 4) {
                            $lastNo = intval($parts[3]);
                            $nextNo = $lastNo + 1;
                        }
                    }
                    mysqli_stmt_close($stmtCountFallback);
                }
            }
            
            // Pastikan nextNo minimal 1
            if ($nextNo < 1) {
                $nextNo = 1;
            }
            
            // Generate nomor peminjaman: PJ/CODE_TIM/DATE/NO
            return "PJ/" . $code_tim . "/" . $formattedDate . "/" . str_pad($nextNo, 3, '0', STR_PAD_LEFT);
        }
        
        // Fallback jika query gagal
        $code_tim = strtoupper(substr($gudang_asal, 0, 3));
        $dateObj = new DateTime($tanggal_peminjaman);
        $formattedDate = $dateObj->format('my');
        return "PJ/" . $code_tim . "/" . $formattedDate . "/001";
    }

    // Fungsi untuk mengecek dan menambahkan kolom jika belum ada
    // Function ini tidak digunakan lagi, dihapus untuk optimasi

    // Pastikan tabel peminjaman_stok menggunakan CHARACTER SET utf8mb4
    $tablesToCheck = ['peminjaman_stok', 'gudang_omni', 'base_tim'];
    foreach ($tablesToCheck as $tableName) {
        $checkTableQuery = "SELECT TABLE_COLLATION 
                            FROM information_schema.TABLES 
                            WHERE TABLE_SCHEMA = DATABASE() 
                            AND TABLE_NAME = ?";
        $stmt = mysqli_prepare($db_dc, $checkTableQuery);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $tableName);
            mysqli_stmt_execute($stmt);
            $tableResult = mysqli_stmt_get_result($stmt);
            if ($tableResult && $tableRow = mysqli_fetch_assoc($tableResult)) {
                $currentCollation = $tableRow['TABLE_COLLATION'];
                if ($currentCollation && strpos($currentCollation, 'utf8mb4') === false) {
                    $alterTableQuery = "ALTER TABLE `$tableName` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
                    @mysqli_query($db_dc, $alterTableQuery);
                }
            }
            mysqli_stmt_close($stmt);
        }
    }
    
    // Pastikan kolom tanggal_peminjaman bisa NULL untuk status Draft
    $checkTanggalQuery = "SHOW COLUMNS FROM `peminjaman_stok` WHERE Field = 'tanggal_peminjaman'";
    $checkTanggalResult = mysqli_query($db_dc, $checkTanggalQuery);
    if ($checkTanggalResult && $row = mysqli_fetch_assoc($checkTanggalResult)) {
        if (strtoupper($row['Null']) === 'NO') {
            $alterTanggalQuery = "ALTER TABLE `peminjaman_stok` MODIFY COLUMN `tanggal_peminjaman` DATE NULL";
            mysqli_query($db_dc, $alterTanggalQuery);
        }
    }

    // Clean output buffer sebelum mengirim JSON
    ob_clean();
    // Atur header JSON
    header('Content-Type: application/json');

    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        if ($_GET['act'] === "add_pengembalian") {
            // Handler untuk tambah data pengembalian
            try {
                while (ob_get_level()) { ob_end_clean(); }
                header('Content-Type: application/json');
                
                // Validasi input
                if (!isset($_POST['in_nomor_peminjaman']) || empty($_POST['in_nomor_peminjaman'])) {
                    throw new Exception("Nomor Peminjaman harus dipilih");
                }
                
                if (!isset($_POST['in_tanggal']) || empty($_POST['in_tanggal'])) {
                    throw new Exception("Tanggal Pengembalian harus diisi");
                }
                
                if (!isset($_POST['in_produk']) || !is_array($_POST['in_produk']) || empty($_POST['in_produk'])) {
                    throw new Exception("Minimal harus ada 1 produk yang dikembalikan");
                }
                
                $nomor_peminjaman = trim($_POST['in_nomor_peminjaman']);
                $tanggal_pengembalian = $_POST['in_tanggal'];
                // Ambil dari hidden field (name sudah benar di form)
                $entitas_peminjam = isset($_POST['in_entitas_peminjam']) ? trim($_POST['in_entitas_peminjam']) : '';
                $entitas_dipinjam = isset($_POST['in_entitas_dipinjam']) ? trim($_POST['in_entitas_dipinjam']) : '';
                $gudang_asal = isset($_POST['in_gudang_asal']) ? trim($_POST['in_gudang_asal']) : '';
                $gudang_tujuan = isset($_POST['in_gudang_tujuan']) ? trim($_POST['in_gudang_tujuan']) : '';
                $action_button = isset($_POST['in_action_button']) ? $_POST['in_action_button'] : 'Draft';
                $status_pengembalian = $action_button;
                
                $produk_array = $_POST['in_produk'];
                $jumlah_kembali_array = isset($_POST['in_jumlah_kembali']) ? $_POST['in_jumlah_kembali'] : [];
                
                // Validasi jumlah array harus sama
                if (count($produk_array) !== count($jumlah_kembali_array)) {
                    throw new Exception("Data produk dan jumlah tidak sesuai");
                }
                
                // Generate nomor pengembalian
                $nomor_pengembalian = generateNomorPengembalian($gudang_asal, $entitas_peminjam, $tanggal_pengembalian, $pdo);
                
                // Mulai transaksi
                $pdo->beginTransaction();
                
                try {
                    // Insert data pengembalian untuk setiap produk
                    $insertQuery = "INSERT INTO pengembalian_stok (
                        nomor_pengembalian,
                        nomor_peminjaman,
                        tanggal_pengembalian,
                        entitas_peminjam,
                        entitas_dipinjam,
                        gudang_asal,
                        gudang_tujuan,
                        produk,
                        qty,
                        status_pengembalian,
                        catatan
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '')";
                    
                    $stmt = $pdo->prepare($insertQuery);
                    
                    for ($i = 0; $i < count($produk_array); $i++) {
                        $produk = trim($produk_array[$i]);
                        $qty = intval($jumlah_kembali_array[$i]);
                        
                        if (empty($produk) || $qty <= 0) {
                            continue; // Skip jika produk kosong atau qty <= 0
                        }
                        
                        $stmt->execute([
                            $nomor_pengembalian,
                            $nomor_peminjaman,
                            $tanggal_pengembalian,
                            $entitas_peminjam,
                            $entitas_dipinjam,
                            $gudang_asal,
                            $gudang_tujuan,
                            $produk,
                            $qty,
                            $status_pengembalian
                        ]);
                    }
                    
                    // Commit transaksi
                    $pdo->commit();
                    
                    $message = $status_pengembalian === 'Final' 
                        ? "Data pengembalian berhasil disimpan sebagai Final. Dokumen tidak dapat diedit lagi." 
                        : "Data pengembalian berhasil disimpan sebagai Draft. Anda masih dapat mengedit dokumen ini.";
                    
                    echo json_encode([
                        'success' => true,
                        'message' => $message,
                        'nomor_pengembalian' => $nomor_pengembalian
                    ], JSON_UNESCAPED_UNICODE);
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw new Exception("Error saat menyimpan data: " . $e->getMessage());
                }
                
            } catch (Exception $e) {
                while (ob_get_level()) { ob_end_clean(); }
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ], JSON_UNESCAPED_UNICODE);
            }
            exit();
        } else if ($_GET['act'] === "add") {
            try {
                $pdo->beginTransaction();
        
                // Ambil data dari form
                $nomor_peminjaman       = $_POST["in_nomor_peminjaman"] ?? '';
                $tanggal_peminjaman     = $_POST["in_tanggal"] ?? '';
                $entitas_peminjam       = $_POST["in_entitas_peminjam"] ?? '';
                $entitas_dipinjam       = $_POST["in_entitas_dipinjam"] ?? '';
                $gudang_asal            = $_POST["in_gudang_asal"] ?? '';
                $gudang_tujuan          = $_POST["in_gudang_tujuan"] ?? '';
                $produk                 = $_POST["in_produk"] ?? [];
                $jumlah                 = $_POST["in_jumlah"] ?? [];
                $action_button          = $_POST["in_action_button"] ?? "Final";
                
                // Debug log (hapus di production jika tidak diperlukan)
                error_log("Add Peminjaman - Action: " . $action_button . ", Gudang Asal: " . $gudang_asal . ", Gudang Tujuan: " . $gudang_tujuan . ", Produk count: " . count($produk) . ", Jumlah count: " . count($jumlah));
                
                // Validasi: entitas peminjam dan entitas dipinjam tidak boleh sama
                if (!empty($entitas_peminjam) && !empty($entitas_dipinjam) && $entitas_peminjam === $entitas_dipinjam) {
                    throw new Exception("Entitas Peminjam dan Entitas Dipinjam tidak boleh sama!");
                }
                
                // Set status berdasarkan tombol yang diklik
                if ($action_button == "Draft") {
                    $status_peminjaman = "Draft";
                    $nomor_peminjaman = '';
                    $tanggal_peminjaman = null;
                } else {
                    $status_peminjaman = "Final";
                    if (empty($tanggal_peminjaman)) {
                        throw new Exception("Tanggal peminjaman harus diisi untuk status Final!");
                    }
                    if (empty($nomor_peminjaman)) {
                        $nomor_peminjaman = generateNomorPeminjaman($gudang_asal, $entitas_peminjam, $tanggal_peminjaman, $pdo);
                    }
                }
        
                // Cek kelengkapan data
                if ($status_peminjaman == "Final") {
                    if (empty($tanggal_peminjaman) || empty($entitas_peminjam) || empty($gudang_asal) || empty($gudang_tujuan)) {
                        throw new Exception("Data Peminjaman belum lengkap, mohon cek kembali & lengkapi data dengan benar!");
                    }
                } else {
                    if (empty($gudang_asal) || empty($gudang_tujuan)) {
                        throw new Exception("Gudang Peminjam dan Gudang Dipinjam harus diisi!");
                    }
                }
                
                if ($status_peminjaman == "Final" && (empty($nomor_peminjaman) || trim($nomor_peminjaman) == '')) {
                    throw new Exception("Gagal generate nomor peminjaman. Silakan coba lagi.");
                }

                // Validasi gudang peminjam dan gudang dipinjam tidak boleh sama
                if ($gudang_asal === $gudang_tujuan) {
                    throw new Exception("Gudang Peminjam dan Gudang Dipinjam tidak boleh sama!");
                }

                // Validasi produk dan jumlah tidak boleh kosong
                // Pastikan produk dan jumlah adalah array
                if (!is_array($produk)) {
                    $produk = [];
                }
                if (!is_array($jumlah)) {
                    $jumlah = [];
                }
                
                // Filter produk dan jumlah yang valid (pasangan produk-jumlah yang valid)
                $produkFiltered = [];
                $jumlahFiltered = [];
                $maxCount = max(count($produk), count($jumlah));
                
                for ($i = 0; $i < $maxCount; $i++) {
                    $p = isset($produk[$i]) ? trim($produk[$i]) : '';
                    $j = isset($jumlah[$i]) ? floatval($jumlah[$i]) : 0;
                    
                    // Hanya tambahkan jika produk tidak kosong dan jumlah > 0
                    if (!empty($p) && $j > 0) {
                        $produkFiltered[] = $p;
                        $jumlahFiltered[] = $j;
                    }
                }
                
                if (empty($produkFiltered) || empty($jumlahFiltered) || count($produkFiltered) === 0 || count($jumlahFiltered) === 0) {
                    throw new Exception("Minimal harus ada 1 produk dengan jumlah > 0 untuk peminjaman!");
                }
                
                // Update array produk dan jumlah dengan yang sudah di-filter
                $produk = $produkFiltered;
                $jumlah = $jumlahFiltered;

                // Cek apakah nomor peminjaman sudah ada (hanya untuk Final)
                if ($status_peminjaman == "Final" && !empty($nomor_peminjaman) && trim($nomor_peminjaman) != '') {
                    $maxRetries = 10;
                    $retryCount = 0;
                    while ($retryCount < $maxRetries) {
                        $checkQuery = $pdo->prepare("SELECT COUNT(*) FROM peminjaman_stok WHERE nomor_peminjaman = :nomor_peminjaman");
                        $checkQuery->execute([":nomor_peminjaman" => $nomor_peminjaman]);
                        if ($checkQuery->fetchColumn() > 0) {
                            $retryCount++;
                            $parts = explode('/', $nomor_peminjaman);
                            if (count($parts) >= 4) {
                                $currentNo = intval($parts[3]);
                                $newNo = $currentNo + 1;
                                $nomor_peminjaman = $parts[0] . '/' . $parts[1] . '/' . $parts[2] . '/' . str_pad($newNo, 3, '0', STR_PAD_LEFT);
                            } else {
                                $nomor_peminjaman = generateNomorPeminjaman($gudang_asal, $entitas_peminjam, $tanggal_peminjaman, $pdo);
                            }
                        } else {
                            break;
                        }
                    }
                    if ($retryCount >= $maxRetries) {
                        throw new Exception("Gagal generate nomor peminjaman yang unik setelah beberapa kali percobaan. Silakan coba lagi.");
                    }
                }
        
                // Insert data peminjaman
                $insertQuery = $pdo->prepare("INSERT INTO peminjaman_stok 
                    (nomor_peminjaman, tanggal_peminjaman, entitas_peminjam, entitas_dipinjam, gudang_asal, gudang_tujuan, produk, qty, status_peminjaman, catatan, created_at) 
                    VALUES 
                    (:nomor_peminjaman, :tanggal_peminjaman, :entitas_peminjam, :entitas_dipinjam, :gudang_asal, :gudang_tujuan, :produk, :qty, :status_peminjaman, :catatan, NOW())");
        
                for ($i = 0; $i < count($produk); $i++) {
                    if (!empty($produk[$i]) && !empty($jumlah[$i])) {
                        $insertQuery->execute([
                            ":nomor_peminjaman" => $nomor_peminjaman,
                            ":tanggal_peminjaman" => ($tanggal_peminjaman === null || $tanggal_peminjaman === '') ? null : $tanggal_peminjaman,
                            ":entitas_peminjam" => $entitas_peminjam ?: '',
                            ":entitas_dipinjam" => $entitas_dipinjam ?: '',
                            ":gudang_asal" => $gudang_asal,
                            ":gudang_tujuan" => $gudang_tujuan,
                            ":produk" => $produk[$i],
                            ":qty" => $jumlah[$i],
                            ":status_peminjaman" => $status_peminjaman,
                            ":catatan" => '' // Default empty untuk catatan
                        ]);
                    }
                }
        
                $pdo->commit();
                
                // Pastikan tidak ada output sebelum JSON
                while (ob_get_level()) {
                    ob_end_clean();
                }
                
                header('Content-Type: application/json; charset=utf-8');
                $message = $status_peminjaman == "Draft" ? "Data Peminjaman berhasil disimpan sebagai Draft" : "Data Peminjaman berhasil disimpan sebagai Final";
                echo json_encode(["status" => "success", "message" => $message], JSON_UNESCAPED_UNICODE);
                exit();
            } catch (Exception $e) {
                $pdo->rollBack();
                
                // Log error untuk debugging
                error_log("Error in add peminjaman: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
                
                // Pastikan tidak ada output sebelum JSON
                while (ob_get_level()) {
                    ob_end_clean();
                }
                
                header('Content-Type: application/json; charset=utf-8');
                $errorMessage = $e->getMessage();
                // Pastikan error message tidak kosong
                if (empty($errorMessage)) {
                    $errorMessage = "Terjadi kesalahan saat menyimpan data. Silakan cek kembali data yang diinput.";
                }
                echo json_encode(["status" => "error", "message" => $errorMessage], JSON_UNESCAPED_UNICODE);
                exit();
            }
        }
        elseif ($_GET['act'] === "delete") {
            
            // Terima parameter identifier atau nomor_peminjaman (untuk backward compatibility)
            $nomor_peminjaman = isset($_POST['identifier']) ? $_POST['identifier'] : (isset($_POST['nomor_peminjaman']) ? $_POST['nomor_peminjaman'] : '');
            
            if (empty($nomor_peminjaman)) {
                echo json_encode(array(
                    "status"  => "error",
                    "message" => "Nomor Peminjaman tidak ditemukan."
                ));
                exit();
            }
            
            mysqli_begin_transaction($db_dc);
            
            try {
                $ids = [];
                $isDraft = false;
                if (strpos($nomor_peminjaman, 'DRAFT-ID-') === 0) {
                    $isDraft = true;
                    $draftId = str_replace('DRAFT-ID-', '', $nomor_peminjaman);
                    $querySelect = "SELECT m1.id FROM peminjaman_stok m1 
                                   INNER JOIN peminjaman_stok m2 ON m1.gudang_asal = m2.gudang_asal 
                                   AND m1.gudang_tujuan = m2.gudang_tujuan 
                                   AND (m1.tanggal_peminjaman IS NULL AND m2.tanggal_peminjaman IS NULL OR (m1.tanggal_peminjaman IS NOT NULL AND m2.tanggal_peminjaman IS NOT NULL AND m1.tanggal_peminjaman = m2.tanggal_peminjaman))
                                   AND m1.status_peminjaman = m2.status_peminjaman
                                   WHERE m2.id = ? AND (m1.nomor_peminjaman IS NULL OR m1.nomor_peminjaman = '' OR TRIM(m1.nomor_peminjaman) = '') AND m1.status_peminjaman = 'Draft'";
                    $stmtSelect = mysqli_prepare($db_dc, $querySelect);
                    mysqli_stmt_bind_param($stmtSelect, "i", $draftId);
                    mysqli_stmt_execute($stmtSelect);
                    $resultSelect = mysqli_stmt_get_result($stmtSelect);
                    while ($qp = mysqli_fetch_assoc($resultSelect)) {
                        $ids[] = $qp['id'];
                    }
                    
                    if (!empty($ids)) {
                        $idsString = implode(',', array_map('intval', $ids));
                        $queryDelete = "DELETE FROM peminjaman_stok WHERE id IN ($idsString)";
                        $executeDelete = mysqli_query($db_dc, $queryDelete);
                    } else {
                        throw new Exception("Data tidak ditemukan untuk dihapus.");
                    }
                } else {
                    $querySelect = "SELECT id FROM peminjaman_stok WHERE nomor_peminjaman = ?";
                    $stmtSelect = mysqli_prepare($db_dc, $querySelect);
                    mysqli_stmt_bind_param($stmtSelect, "s", $nomor_peminjaman);
                    mysqli_stmt_execute($stmtSelect);
                    $resultSelect = mysqli_stmt_get_result($stmtSelect);
                    
                    while ($qp = mysqli_fetch_assoc($resultSelect)) {
                        $ids[] = $qp['id'];
                    }
                    
                    $queryDelete = "DELETE FROM peminjaman_stok WHERE nomor_peminjaman = ?";
                    $stmtDelete = mysqli_prepare($db_dc, $queryDelete);
                    mysqli_stmt_bind_param($stmtDelete, "s", $nomor_peminjaman);
                    $executeDelete = mysqli_stmt_execute($stmtDelete);
                }
                
                if (!$executeDelete) {
                    throw new Exception("Error saat menghapus Peminjaman: " . mysqli_error($db_dc));
                }
                
                if (!empty($ids)) {
                    $queryInsertLog = "INSERT INTO log_hapus (akun, id) VALUES (?, ?)";
                    $stmtInsertLog = mysqli_prepare($db_dc, $queryInsertLog);
                    foreach ($ids as $id) {
                        mysqli_stmt_bind_param($stmtInsertLog, "si", $akun, $id);
                        $akun = "PEMINJAMAN";
                        mysqli_stmt_execute($stmtInsertLog);
                    }
                    mysqli_stmt_close($stmtInsertLog);
                }
                
                mysqli_commit($db_dc);
                
                if (isset($stmtDelete) && $stmtDelete) {
                    mysqli_stmt_close($stmtDelete);
                }
                if (isset($stmtSelect) && $stmtSelect) {
                    mysqli_stmt_close($stmtSelect);
                }
                
                ob_clean();
                header('Content-Type: application/json');
                $message = $isDraft ? "Data draft berhasil dihapus" : "Peminjaman berhasil dihapus.";
                echo json_encode(array(
                    "status"  => "success",
                    "message" => $message,
                    "is_draft" => $isDraft
                ));
                ob_end_flush();
            } catch (Exception $e) {
                mysqli_rollback($db_dc);
                ob_clean();
                header('Content-Type: application/json');
                echo json_encode(array(
                    "status"  => "error",
                    "message" => $e->getMessage()
                ));
                ob_end_flush();
            }
            exit();
        }
        elseif ($_GET['act'] === "update") {
            try {
                $pdo->beginTransaction();

                $edit_id                = $_POST["edit_id"] ?? NULL;
                $existingIds            = $_POST["in_id"] ?? [];
                $nomor_peminjaman       = $_POST["in_nomor_peminjaman"] ?? '';
                $tanggal_peminjaman     = $_POST["in_tanggal"] ?? '';
                $entitas_peminjam       = $_POST["in_entitas_peminjam"] ?? '';
                $entitas_dipinjam       = $_POST["in_entitas_dipinjam"] ?? '';
                $gudang_asal            = $_POST["in_gudang_asal"] ?? '';
                $gudang_tujuan          = $_POST["in_gudang_tujuan"] ?? '';
                $produk                 = $_POST["in_produk"] ?? [];
                $jumlah                 = $_POST["in_jumlah"] ?? [];
                $action_button          = $_POST["in_action_button"] ?? "Final";
                
                // Validasi: entitas peminjam dan entitas dipinjam tidak boleh sama
                if (!empty($entitas_peminjam) && !empty($entitas_dipinjam) && $entitas_peminjam === $entitas_dipinjam) {
                    throw new Exception("Entitas Peminjam dan Entitas Dipinjam tidak boleh sama!");
                }
                
                // Cek status peminjaman saat ini
                $currentStatus = null;
                $currentNomorPeminjaman = null;
                if (!empty($existingIds) && !empty($existingIds[0])) {
                    $checkStatus = $pdo->prepare("SELECT DISTINCT status_peminjaman, nomor_peminjaman, tanggal_peminjaman, entitas_peminjam, entitas_dipinjam, gudang_asal, gudang_tujuan FROM peminjaman_stok WHERE id = :id LIMIT 1");
                    $checkStatus->execute([":id" => $existingIds[0]]);
                    $statusRow = $checkStatus->fetch(PDO::FETCH_ASSOC);
                    if ($statusRow) {
                        $currentStatus = $statusRow['status_peminjaman'];
                        $currentNomorPeminjaman = $statusRow['nomor_peminjaman'];
                        if (empty($tanggal_peminjaman)) {
                            $tanggal_peminjaman = $statusRow['tanggal_peminjaman'];
                        }
                        if (empty($entitas_peminjam)) {
                            $entitas_peminjam = $statusRow['entitas_peminjam'];
                        }
                        if (empty($entitas_dipinjam)) {
                            $entitas_dipinjam = $statusRow['entitas_dipinjam'];
                        }
                        if (empty($gudang_asal)) {
                            $gudang_asal = $statusRow['gudang_asal'];
                        }
                        if (empty($gudang_tujuan)) {
                            $gudang_tujuan = $statusRow['gudang_tujuan'];
                        }
                    }
                } elseif (!empty($nomor_peminjaman)) {
                    $checkStatus = $pdo->prepare("SELECT DISTINCT status_peminjaman, nomor_peminjaman, tanggal_peminjaman, entitas_peminjam, entitas_dipinjam, gudang_asal, gudang_tujuan FROM peminjaman_stok WHERE nomor_peminjaman = :nomor_peminjaman LIMIT 1");
                    $checkStatus->execute([":nomor_peminjaman" => $nomor_peminjaman]);
                    $statusRow = $checkStatus->fetch(PDO::FETCH_ASSOC);
                    if ($statusRow) {
                        $currentStatus = $statusRow['status_peminjaman'];
                        $currentNomorPeminjaman = $statusRow['nomor_peminjaman'];
                        if (empty($tanggal_peminjaman)) {
                            $tanggal_peminjaman = $statusRow['tanggal_peminjaman'];
                        }
                        if (empty($entitas_peminjam)) {
                            $entitas_peminjam = $statusRow['entitas_peminjam'];
                        }
                        if (empty($entitas_dipinjam)) {
                            $entitas_dipinjam = $statusRow['entitas_dipinjam'];
                        }
                        if (empty($gudang_asal)) {
                            $gudang_asal = $statusRow['gudang_asal'];
                        }
                        if (empty($gudang_tujuan)) {
                            $gudang_tujuan = $statusRow['gudang_tujuan'];
                        }
                    }
                }
                
                if ($currentStatus && $currentStatus != 'Draft') {
                    throw new Exception("Dokumen dengan status '" . $currentStatus . "' tidak dapat diedit. Hanya dokumen dengan status 'Draft' yang dapat diedit.");
                }
                
                // Set status berdasarkan tombol yang diklik
                if ($action_button == "Draft") {
                    $status_peminjaman = "Draft";
                    $nomor_peminjaman = '';
                    $tanggal_peminjaman = null;
                    if (empty($gudang_asal) || empty($gudang_tujuan)) {
                        throw new Exception("Data header tidak lengkap. Gudang Peminjam: " . ($gudang_asal ?: 'kosong') . ", Gudang Dipinjam: " . ($gudang_tujuan ?: 'kosong'));
                    }
                } else {
                    $status_peminjaman = "Final";
                    if (empty($tanggal_peminjaman) || empty($entitas_peminjam) || empty($gudang_asal) || empty($gudang_tujuan)) {
                        throw new Exception("Data header tidak lengkap. Tanggal: " . ($tanggal_peminjaman ?: 'kosong') . ", Entitas Peminjam: " . ($entitas_peminjam ?: 'kosong') . ", Gudang Peminjam: " . ($gudang_asal ?: 'kosong') . ", Gudang Dipinjam: " . ($gudang_tujuan ?: 'kosong'));
                    }
                    if (empty($currentNomorPeminjaman) || $currentStatus == 'Draft' || empty($nomor_peminjaman) || trim($nomor_peminjaman) == '') {
                        $nomor_peminjaman = generateNomorPeminjaman($gudang_asal, $entitas_peminjam, $tanggal_peminjaman, $pdo);
                    }
                }
                
                if ($status_peminjaman == "Final" && (empty($nomor_peminjaman) || trim($nomor_peminjaman) == '')) {
                    throw new Exception("Gagal generate nomor peminjaman. Silakan coba lagi.");
                }

                if ($gudang_asal === $gudang_tujuan) {
                    throw new Exception("Gudang Peminjam dan Gudang Dipinjam tidak boleh sama!");
                }

                if (empty($produk) || empty($jumlah) || count($produk) === 0 || count($jumlah) === 0) {
                    throw new Exception("Minimal harus ada 1 produk untuk peminjaman!");
                }

                // Snapshot ID lama sebelum modifikasi
                $oldIdsBefore = [];
                if (!empty($existingIds) && !empty($existingIds[0])) {
                    if (empty($currentNomorPeminjaman)) {
                        $stmtOldBefore = $pdo->prepare("SELECT m1.id FROM peminjaman_stok m1 
                                                       INNER JOIN peminjaman_stok m2 ON m1.gudang_asal = m2.gudang_asal 
                                                       AND m1.gudang_tujuan = m2.gudang_tujuan 
                                                       AND (m1.tanggal_peminjaman IS NULL AND m2.tanggal_peminjaman IS NULL OR (m1.tanggal_peminjaman IS NOT NULL AND m2.tanggal_peminjaman IS NOT NULL AND m1.tanggal_peminjaman = m2.tanggal_peminjaman))
                                                       AND m1.status_peminjaman = m2.status_peminjaman
                                                       WHERE m2.id = :id AND (m1.nomor_peminjaman IS NULL OR m1.nomor_peminjaman = '' OR TRIM(m1.nomor_peminjaman) = '') AND m1.status_peminjaman = 'Draft'");
                        $stmtOldBefore->execute([":id" => $existingIds[0]]);
                        $oldIdsBefore = $stmtOldBefore->fetchAll(PDO::FETCH_COLUMN);
                    } else {
                        $stmtOldBefore = $pdo->prepare("SELECT id FROM peminjaman_stok WHERE nomor_peminjaman = :nomor_peminjaman");
                        $stmtOldBefore->execute([":nomor_peminjaman" => $currentNomorPeminjaman]);
                        $oldIdsBefore = $stmtOldBefore->fetchAll(PDO::FETCH_COLUMN);
                    }
                } elseif (!empty($nomor_peminjaman)) {
                    $stmtOldBefore = $pdo->prepare("SELECT id FROM peminjaman_stok WHERE nomor_peminjaman = :nomor_peminjaman");
                    $stmtOldBefore->execute([":nomor_peminjaman" => $nomor_peminjaman]);
                    $oldIdsBefore = $stmtOldBefore->fetchAll(PDO::FETCH_COLUMN);
                }

                // Update header fields
                $updateHeader = $pdo->prepare("UPDATE peminjaman_stok SET 
                    nomor_peminjaman = :nomor_peminjaman,
                    tanggal_peminjaman = :tanggal_peminjaman,
                    entitas_peminjam = :entitas_peminjam,
                    entitas_dipinjam = :entitas_dipinjam,
                    gudang_asal = :gudang_asal,
                    gudang_tujuan = :gudang_tujuan,
                    status_peminjaman = :status_peminjaman,
                    updated_at = NOW()
                    WHERE id = :id");

                // Update detail (produk dan qty)
                $updateDetail = $pdo->prepare("UPDATE peminjaman_stok SET 
                    produk = :produk,
                    qty = :qty
                    WHERE id = :id");

                // Update existing rows berdasarkan existingIds yang dikirim dari form
                for ($i = 0; $i < count($existingIds); $i++) {
                    if (!empty($existingIds[$i])) {
                        $existingId = intval($existingIds[$i]);
                        
                        // Update header
                        $updateHeader->execute([
                            ":id" => $existingId,
                            ":nomor_peminjaman" => $nomor_peminjaman,
                            ":tanggal_peminjaman" => ($tanggal_peminjaman === null || $tanggal_peminjaman === '') ? null : $tanggal_peminjaman,
                            ":entitas_peminjam" => $entitas_peminjam,
                            ":entitas_dipinjam" => $entitas_dipinjam,
                            ":gudang_asal" => $gudang_asal,
                            ":gudang_tujuan" => $gudang_tujuan,
                            ":status_peminjaman" => $status_peminjaman
                        ]);

                        // Update detail (produk dan qty) jika ada
                        if (isset($produk[$i]) && isset($jumlah[$i])) {
                            $updateDetail->execute([
                                ":id" => $existingId,
                                ":produk" => $produk[$i] ?? '',
                                ":qty" => $jumlah[$i] ?? 0
                            ]);
                        }
                    }
                }
                
                // Update header untuk semua row di oldIdsBefore yang tidak ada di existingIds
                // Ini penting untuk memastikan semua row di-update saat perubahan dari Draft ke Final
                $existingIdsInt = array_map('intval', array_filter($existingIds, function($id) { return !empty($id); }));
                $toUpdateFromOld = array_diff($oldIdsBefore, $existingIdsInt);
                
                if (!empty($toUpdateFromOld)) {
                    foreach ($toUpdateFromOld as $oldId) {
                        $updateHeader->execute([
                            ":id" => $oldId,
                            ":nomor_peminjaman" => $nomor_peminjaman,
                            ":tanggal_peminjaman" => ($tanggal_peminjaman === null || $tanggal_peminjaman === '') ? null : $tanggal_peminjaman,
                            ":entitas_peminjam" => $entitas_peminjam,
                            ":entitas_dipinjam" => $entitas_dipinjam,
                            ":gudang_asal" => $gudang_asal,
                            ":gudang_tujuan" => $gudang_tujuan,
                            ":status_peminjaman" => $status_peminjaman
                        ]);
                    }
                }

                // Insert new rows hanya jika ada produk baru yang tidak ada di existingIds
                $insertRow = $pdo->prepare("INSERT INTO peminjaman_stok 
                    (nomor_peminjaman, tanggal_peminjaman, entitas_peminjam, entitas_dipinjam, gudang_asal, gudang_tujuan, produk, qty, status_peminjaman, catatan, created_at) 
                    VALUES (:nomor_peminjaman, :tanggal_peminjaman, :entitas_peminjam, :entitas_dipinjam, :gudang_asal, :gudang_tujuan, :produk, :qty, :status_peminjaman, :catatan, NOW())");
                
                for ($i = 0; $i < count($produk); $i++) {
                    $idVal = isset($existingIds[$i]) ? intval($existingIds[$i]) : null;
                    // Hanya insert jika tidak ada existingId dan produk/jumlah valid
                    if (empty($idVal) && !empty($produk[$i]) && !empty($jumlah[$i])) {
                        $insertRow->execute([
                            ":nomor_peminjaman" => $nomor_peminjaman,
                            ":tanggal_peminjaman" => ($tanggal_peminjaman === null || $tanggal_peminjaman === '') ? null : $tanggal_peminjaman,
                            ":entitas_peminjam" => $entitas_peminjam,
                            ":entitas_dipinjam" => $entitas_dipinjam,
                            ":gudang_asal" => $gudang_asal,
                            ":gudang_tujuan" => $gudang_tujuan,
                            ":produk" => $produk[$i],
                            ":qty" => $jumlah[$i],
                            ":status_peminjaman" => $status_peminjaman,
                            ":catatan" => '' // Default empty untuk catatan
                        ]);
                    }
                }

                // Hapus data yang tidak ada lagi - hanya hapus row yang ada di oldIdsBefore tapi tidak ada di existingIds
                $existingIdsFiltered = array_map('intval', array_filter($existingIds, function($id) { return !empty($id) && intval($id) > 0; }));
                $toDelete = array_diff($oldIdsBefore, $existingIdsFiltered);
                if (!empty($toDelete)) {
                    $insertLog = $pdo->prepare("INSERT INTO log_hapus (akun, id) VALUES ('PEMINJAMAN', :id)");
                    $deleteRow = $pdo->prepare("DELETE FROM peminjaman_stok WHERE id = :id");
                    foreach ($toDelete as $delId) {
                        $insertLog->execute([":id" => $delId]);
                        $deleteRow->execute([":id" => $delId]);
                    }
                }

                $pdo->commit();
                // Pastikan tidak ada output sebelum JSON
                while (ob_get_level()) {
                    ob_end_clean();
                }
                
                header('Content-Type: application/json; charset=utf-8');
                $message = $status_peminjaman == "Draft" ? "Peminjaman berhasil diupdate sebagai Draft" : "Peminjaman berhasil diupdate sebagai Final";
                echo json_encode(["status" => "success", "message" => $message], JSON_UNESCAPED_UNICODE);
                exit();
            } catch (Exception $e) {
                $pdo->rollBack();
                
                // Pastikan tidak ada output sebelum JSON
                while (ob_get_level()) {
                    ob_end_clean();
                }
                
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(["status" => "error", "message" => $e->getMessage()], JSON_UNESCAPED_UNICODE);
                exit();
            }
        }
        elseif ($_GET['act'] === "edit_detail") {
            // Handler untuk edit detail peminjaman (menambah rincian baru)
            try {
                $pdo->beginTransaction();
                
                $nomor_peminjaman = $_POST['nomor_peminjaman'] ?? '';
                $gudang = $_POST['in_gudang'] ?? [];
                $produk = $_POST['in_produk'] ?? [];
                $jumlah = $_POST['in_jumlah'] ?? [];
                $id = $_POST['in_id'] ?? [];
                
                // Pastikan array
                if (!is_array($gudang)) $gudang = [];
                if (!is_array($produk)) $produk = [];
                if (!is_array($jumlah)) $jumlah = [];
                if (!is_array($id)) $id = [];
                
                if (empty($nomor_peminjaman)) {
                    throw new Exception('Nomor peminjaman tidak ditemukan');
                }
                
                // Ambil data header peminjaman (ambil dari data pertama yang bukan adjustment)
                $queryHeader = $pdo->prepare("SELECT 
                    nomor_peminjaman, tanggal_peminjaman, entitas_peminjam, entitas_dipinjam, 
                    gudang_asal, gudang_tujuan, status_peminjaman
                    FROM peminjaman_stok 
                    WHERE nomor_peminjaman = ? 
                    AND (catatan IS NULL OR catatan = '' OR catatan NOT LIKE '%Penyesuaian%')
                    LIMIT 1");
                $queryHeader->execute([$nomor_peminjaman]);
                $headerData = $queryHeader->fetch(PDO::FETCH_ASSOC);
                
                if (!$headerData) {
                    throw new Exception('Data peminjaman tidak ditemukan');
                }
                
                // Validasi konsistensi array
                if (count($gudang) !== count($produk) || count($gudang) !== count($jumlah)) {
                    throw new Exception('Data tidak konsisten. Pastikan semua field terisi dengan benar.');
                }
                
                // Insert data detail baru
                $insertRow = $pdo->prepare("INSERT INTO peminjaman_stok 
                    (nomor_peminjaman, tanggal_peminjaman, entitas_peminjam, entitas_dipinjam, 
                     gudang_asal, gudang_tujuan, produk, qty, catatan, status_peminjaman)
                    VALUES 
                    (:nomor_peminjaman, :tanggal_peminjaman, :entitas_peminjam, :entitas_dipinjam,
                     :gudang_asal, :gudang_tujuan, :produk, :qty, :catatan, :status_peminjaman)");
                
                // Prepare statement untuk update (catatan tetap dipertahankan)
                $updateRow = $pdo->prepare("UPDATE peminjaman_stok SET 
                    gudang_asal = :gudang_asal,
                    produk = :produk,
                    qty = :qty
                    WHERE id = :id AND nomor_peminjaman = :nomor_peminjaman
                    AND (catatan IS NOT NULL AND catatan LIKE '%Penyesuaian%')");
                
                $insertedCount = 0;
                $updatedCount = 0;
                $skippedCount = 0;
                
                // Debug: hitung total data
                $totalData = max(count($gudang), count($produk), count($jumlah), count($id));
                
                for ($i = 0; $i < $totalData; $i++) {
                    $gudangValue = trim($gudang[$i] ?? '');
                    $produkValue = trim($produk[$i] ?? '');
                    $jumlahValue = intval($jumlah[$i] ?? 0);
                    $idValue = trim($id[$i] ?? '');
                    
                    // Jika id ada, cek apakah ini data penyesuaian atau data existing
                    if (!empty($idValue) && is_numeric($idValue)) {
                        // Cek apakah data ini adalah penyesuaian
                        $checkPenyesuaian = $pdo->prepare("SELECT catatan FROM peminjaman_stok WHERE id = ? AND nomor_peminjaman = ?");
                        $checkPenyesuaian->execute([$idValue, $nomor_peminjaman]);
                        $penyesuaianData = $checkPenyesuaian->fetch(PDO::FETCH_ASSOC);
                        $isPenyesuaian = !empty($penyesuaianData['catatan']) && stripos($penyesuaianData['catatan'], 'Penyesuaian') !== false;
                        
                        if ($isPenyesuaian) {
                            // Data penyesuaian bisa di-update
                            $updateRow->execute([
                                ":gudang_asal" => $gudangValue,
                                ":produk" => $produkValue,
                                ":qty" => $jumlahValue,
                                ":id" => $idValue,
                                ":nomor_peminjaman" => $nomor_peminjaman
                            ]);
                            $updatedCount++;
                        } else {
                            // Data existing tidak di-update, hanya dipertahankan
                            $skippedCount++;
                            continue;
                        }
                    } else {
                        // Jika id kosong, insert data baru
                        // Validasi data baru (hanya untuk data tanpa id)
                        if (empty($gudangValue) || empty($produkValue) || $jumlahValue == 0) {
                            // Skip data yang tidak valid
                            continue;
                        }
                        
                        $insertRow->execute([
                            ":nomor_peminjaman" => $nomor_peminjaman,
                            ":tanggal_peminjaman" => $headerData['tanggal_peminjaman'],
                            ":entitas_peminjam" => $headerData['entitas_peminjam'],
                            ":entitas_dipinjam" => $headerData['entitas_dipinjam'],
                            ":gudang_asal" => $gudangValue,
                            ":gudang_tujuan" => $headerData['gudang_tujuan'],
                            ":produk" => $produkValue,
                            ":qty" => $jumlahValue,
                            ":catatan" => 'Penyesuaian', // Tandai sebagai penyesuaian untuk data baru dari tambah rincian
                            ":status_peminjaman" => $headerData['status_peminjaman']
                        ]);
                        $insertedCount++;
                    }
                }
                
                // Jika tidak ada data baru yang di-insert, beri pesan yang lebih informatif
                if ($insertedCount == 0) {
                    if ($skippedCount > 0 && $totalData > 0) {
                        // Ada data existing tapi tidak ada data baru
                        throw new Exception('Tidak ada data baru yang valid untuk disimpan. Pastikan produk, gudang, dan jumlah sudah diisi dengan benar.');
                    } else {
                        throw new Exception('Tidak ada data detail yang valid untuk disimpan. Pastikan semua field (gudang, produk, jumlah) sudah diisi dengan benar.');
                    }
                }
                
                $pdo->commit();
                while (ob_get_level()) {
                    ob_end_clean();
                }
                header('Content-Type: application/json');
                echo json_encode(["success" => true, "message" => "Data detail peminjaman berhasil disimpan"], JSON_UNESCAPED_UNICODE);
                ob_end_flush();
            } catch (Exception $e) {
                $pdo->rollBack();
                while (ob_get_level()) {
                    ob_end_clean();
                }
                header('Content-Type: application/json');
                echo json_encode(["success" => false, "error" => $e->getMessage()], JSON_UNESCAPED_UNICODE);
                ob_end_flush();
            }
            exit();
        }
    }
?>

