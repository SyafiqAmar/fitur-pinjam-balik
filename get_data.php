<?php
// Start output buffering untuk mencegah output sebelum JSON
ob_start();

// Disable error display untuk mencegah output error sebelum JSON
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Set error handler untuk menangkap semua error
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Log error tapi jangan output
    error_log("PHP Error [$errno]: $errstr in $errfile on line $errline");
    return true; // Suppress default error handler
});

session_start();

if (!isset($_SESSION["ssLogin"])) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    ob_end_flush();
    exit;
}

require_once "../../config/config.php";
require_once "../../config/functions.php";
require_once "query_helper.php";

// Ambil action dari GET atau POST
$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : 'get_data');

// Routing berdasarkan action
switch ($action) {
    case 'get_peminjaman_detail':
        // handleGetPeminjamanDetail() sudah menangani output buffer dan exit sendiri
        handleGetPeminjamanDetail();
        break;
    
    case 'get_peminjaman_details':
        // Handler untuk get_peminjaman_details (HTML output)
        handleGetPeminjamanDetails();
        break;
    
    case 'get_stok_by_produk':
        handleGetStokByProduk();
        break;
    
    case 'get_stok':
        handleGetStokByProduk();
        break;
    
    case 'get_entitas_code':
        handleGetEntitasCode();
        break;
    
    case 'get_gudang_by_entitas':
        handleGetGudangByEntitas();
        break;
    
    case 'get_list_nomor_peminjaman':
        handleGetListNomorPeminjaman();
        break;
    
    case 'get_sisa_qty_pengembalian':
        handleGetSisaQtyPengembalian();
        break;
    
    case 'get_data':
    default:
        // handleGetData() sudah mengurus output buffering dan exit()
        try {
            handleGetData();
        } catch (Throwable $e) {
            // Tangkap semua error termasuk fatal error
            while (ob_get_level()) {
                ob_end_clean();
            }
            header('Content-Type: application/json; charset=utf-8');
            error_log("Fatal error in get_data.php: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
            echo json_encode([
                "draw" => isset($_GET['draw']) ? intval($_GET['draw']) : 1,
                "recordsTotal" => 0,
                "recordsFiltered" => 0,
                "data" => [],
                "error" => "Fatal error: " . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }
        break;
}

// Function ini tidak digunakan lagi, dihapus untuk optimasi

// Function untuk get data peminjaman (DataTables)
function handleGetData() {
    global $db_dc;
    
    // Pastikan database connection ada
    if (!isset($db_dc) || !$db_dc) {
        throw new Exception("Database connection not available");
    }
    
    // Test database connection
    if (!mysqli_ping($db_dc)) {
        throw new Exception("Database connection lost");
    }
    
    // Bersihkan output buffer di awal untuk memastikan tidak ada output sebelum JSON
    // Pastikan semua output buffer dibersihkan
    while (ob_get_level()) {
        ob_end_clean();
    }
    ob_start();
    
    try {
        // Ambil parameter dari request
        $start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
        $end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';
        $entitas_peminjam = isset($_GET['entitas_peminjam']) ? $_GET['entitas_peminjam'] : '';
        $entitas_dipinjam = isset($_GET['entitas_dipinjam']) ? $_GET['entitas_dipinjam'] : '';
        $gudang_asal = isset($_GET['gudang_asal']) ? $_GET['gudang_asal'] : '';
        $gudang_tujuan = isset($_GET['gudang_tujuan']) ? $_GET['gudang_tujuan'] : '';
        // Ambil type dari GET atau POST (DataTables bisa kirim via GET)
        $type = isset($_GET['type']) ? trim($_GET['type']) : (isset($_POST['type']) ? trim($_POST['type']) : 'peminjaman');
        
        // Validasi type - hanya terima 'peminjaman' atau 'pengembalian'
        if ($type !== 'peminjaman' && $type !== 'pengembalian') {
            $type = 'peminjaman'; // Default ke peminjaman jika tidak valid
        }
        

        // Validasi dan set default untuk tanggal jika kosong
        if (empty($start_date)) {
            $start_date = date('Y-m-d', strtotime('-29 days'));
        }
        if (empty($end_date)) {
            $end_date = date('Y-m-d');
        }
        
        // Validasi format tanggal
        $start_date_obj = DateTime::createFromFormat('Y-m-d', $start_date);
        $end_date_obj = DateTime::createFromFormat('Y-m-d', $end_date);
        
        if (!$start_date_obj || !$end_date_obj) {
            // Jika format salah, gunakan default
            $start_date = date('Y-m-d', strtotime('-29 days'));
            $end_date = date('Y-m-d');
        }
        
        // Pastikan format tanggal valid sebelum digunakan di query
        $start_date = $start_date_obj ? $start_date_obj->format('Y-m-d') : date('Y-m-d', strtotime('-29 days'));
        $end_date = $end_date_obj ? $end_date_obj->format('Y-m-d') : date('Y-m-d');
        
        // Escape untuk SQL injection prevention
        $start_date = mysqli_real_escape_string($db_dc, $start_date);
        $end_date = mysqli_real_escape_string($db_dc, $end_date);

        // DataTables parameters
        $draw = isset($_GET['draw']) ? intval($_GET['draw']) : 1;
        $start = isset($_GET['start']) ? intval($_GET['start']) : 0;
        $length = isset($_GET['length']) ? intval($_GET['length']) : 10;
        $search = isset($_GET['search']['value']) ? $_GET['search']['value'] : '';

        // Tentukan tabel berdasarkan type - PASTIKAN menggunakan tabel yang benar
        if ($type === 'pengembalian') {
            $table_name = 'pengembalian_stok';
            $tanggal_field = 'tanggal_pengembalian';
            $nomor_field = 'nomor_pengembalian';
            $status_field = 'status_pengembalian';
            $nomor_peminjaman_field = 'nomor_peminjaman'; // Kolom nomor peminjaman untuk pengembalian
        } else {
            $table_name = 'peminjaman_stok';
            $tanggal_field = 'tanggal_peminjaman';
            $nomor_field = 'nomor_peminjaman';
            $status_field = 'status_peminjaman';
            $nomor_peminjaman_field = null;
        }
        
        // PASTIKAN: Force table untuk pengembalian - tidak boleh salah
        if ($type === 'pengembalian' && $table_name !== 'pengembalian_stok') {
            $table_name = 'pengembalian_stok';
        }
        
        // PERBAIKAN: Query yang lebih aman untuk handle tanggal - TANPA DATE() atau CAST untuk menghindari error
        // Untuk pengembalian, ambil juga nomor_peminjaman asli
        $nomor_peminjaman_select = ($type === 'pengembalian' && isset($nomor_peminjaman_field)) 
            ? ", m.$nomor_peminjaman_field as nomor_peminjaman_original" 
            : "";
        $nomor_peminjaman_group = ($type === 'pengembalian' && isset($nomor_peminjaman_field)) 
            ? ", MIN(pg.nomor_peminjaman_original) as nomor_peminjaman_original" 
            : "";
        
        $query = "
            WITH data_grouped AS (
            SELECT 
                CASE WHEN m.$nomor_field IS NULL OR m.$nomor_field = '' OR TRIM(m.$nomor_field) = '' 
                        THEN CONCAT(COALESCE(m.entitas_peminjam, ''), '|', m.gudang_asal, '|', m.gudang_tujuan, '|', COALESCE(DATE(m.$tanggal_field), 'NULL'), '|', m.$status_field)
                    ELSE m.$nomor_field 
                END as nomor_peminjaman,
                m.$tanggal_field as tanggal_peminjaman,
                m.entitas_peminjam,
                m.entitas_dipinjam,
                m.gudang_asal,
                m.gudang_tujuan,
                m.$status_field as status_peminjaman,
                m.id,
                m.produk,
                m.qty
                $nomor_peminjaman_select
            FROM `$table_name` m
            WHERE (m.$tanggal_field >= '$start_date 00:00:00' 
                AND m.$tanggal_field < DATE_ADD('$end_date', INTERVAL 1 DAY))
                OR (m.$tanggal_field IS NULL AND m.$status_field = 'Draft')
            )
            SELECT 
                pg.nomor_peminjaman,
                MIN(pg.tanggal_peminjaman) as tanggal_peminjaman,
                MIN(pg.entitas_peminjam) as entitas_peminjam,
                MIN(pg.entitas_dipinjam) as entitas_dipinjam,
                MIN(pg.gudang_asal) as gudang_asal,
                MIN(pg.gudang_tujuan) as gudang_tujuan,
                COUNT(DISTINCT pg.produk) as jumlah_item,
                SUM(pg.qty) as total_qty,
                MIN(pg.status_peminjaman) as status_peminjaman,
                MIN(pg.id) as min_id
                $nomor_peminjaman_group
            FROM data_grouped pg
            WHERE 1=1
        ";

        // Optimasi: Gunakan helper function untuk build filter conditions
        if ($type === 'pengembalian') {
            require_once "../pengembalian/query_helper.php";
            $filterConditions = buildFilterConditionsPengembalian($db_dc, $entitas_peminjam, $entitas_dipinjam, $gudang_asal, $gudang_tujuan, $search, true);
        } else {
            $filterConditions = buildFilterConditionsPeminjaman($db_dc, $entitas_peminjam, $entitas_dipinjam, $gudang_asal, $gudang_tujuan, $search, true);
        }
        
        // Tambahkan filter ke query utama - filter diterapkan pada CTE
        if (!empty($filterConditions)) {
            // Filter diterapkan pada CTE data_grouped (masih menggunakan alias m.)
            $filterConditionsStr = implode(" AND ", $filterConditions);
            // Ganti alias pg. menjadi m. untuk filter di CTE
            $filterConditionsStr = str_replace('pg.', 'm.', $filterConditionsStr);
            // Tambahkan filter ke WHERE clause CTE
            $query = str_replace(
                "WHERE (m.$tanggal_field >= '$start_date 00:00:00'",
                "WHERE (" . $filterConditionsStr . ") AND (m.$tanggal_field >= '$start_date 00:00:00'",
                $query
            );
        }

        // Count query - gunakan helper function yang sesuai dengan type
        if ($type === 'pengembalian') {
            require_once "../pengembalian/query_helper.php";
            $countQueryTotal = getCountQueryPengembalian($db_dc, $start_date, $end_date, $entitas_peminjam, $entitas_dipinjam, $gudang_asal, $gudang_tujuan, $search, false);
            $countQueryFiltered = getCountQueryPengembalian($db_dc, $start_date, $end_date, $entitas_peminjam, $entitas_dipinjam, $gudang_asal, $gudang_tujuan, $search, true);
        } else {
            $countQueryTotal = getCountQueryPeminjaman($db_dc, $start_date, $end_date, $entitas_peminjam, $entitas_dipinjam, $gudang_asal, $gudang_tujuan, $search, false);
            $countQueryFiltered = getCountQueryPeminjaman($db_dc, $start_date, $end_date, $entitas_peminjam, $entitas_dipinjam, $gudang_asal, $gudang_tujuan, $search, true);
        }

        // Eksekusi count query untuk total records
        $countResultTotal = @mysqli_query($db_dc, $countQueryTotal);
        $recordsTotal = 0;
        if ($countResultTotal) {
            $countRowTotal = mysqli_fetch_assoc($countResultTotal);
            $recordsTotal = intval($countRowTotal['total']);
        } else {
            // Jika query error, gunakan fallback dan log error
            $error = mysqli_error($db_dc);
            error_log("Count query total error: " . $error);
            $recordsTotal = 0;
        }

        // Eksekusi count query untuk filtered records
        $countResultFiltered = @mysqli_query($db_dc, $countQueryFiltered);
        $recordsFiltered = 0;
        if ($countResultFiltered) {
            $countRowFiltered = mysqli_fetch_assoc($countResultFiltered);
            $recordsFiltered = intval($countRowFiltered['total']);
        } else {
            // Jika query error, gunakan fallback dan log error
            $error = mysqli_error($db_dc);
            error_log("Count query filtered error: " . $error);
            $recordsFiltered = 0;
        }

        // Tambahkan GROUP BY dan ORDER BY untuk query utama
        // Untuk pengembalian, tambahkan nomor_peminjaman_original di GROUP BY
        $nomor_peminjaman_group_by = ($type === 'pengembalian' && isset($nomor_peminjaman_field)) 
            ? ", pg.nomor_peminjaman_original" 
            : "";
        $query .= " GROUP BY 
            pg.nomor_peminjaman
            $nomor_peminjaman_group_by";
        $query .= " ORDER BY 
            CASE WHEN pg.tanggal_peminjaman IS NULL THEN 0 ELSE 1 END DESC,
            pg.tanggal_peminjaman DESC, 
            CASE WHEN pg.nomor_peminjaman IS NULL OR pg.nomor_peminjaman = '' OR TRIM(pg.nomor_peminjaman) = '' 
                THEN '' 
                ELSE pg.nomor_peminjaman 
            END DESC";

        // Tambahkan LIMIT
        $query .= " LIMIT $start, $length";

        // Eksekusi query dengan error handling sederhana
        $result = @mysqli_query($db_dc, $query);
        
        if (!$result) {
            $error_message = mysqli_error($db_dc);
            error_log("Query error in get_data.php: " . $error_message . " | Table: `$table_name` | Type: $type");
            throw new Exception("Query error: " . $error_message);
        }

        // Optimasi: Ambil semua data dulu, lalu hitung status secara batch
        $rowsData = [];
        $validNomorPeminjaman = [];
        
        if ($result && mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                // Normalisasi tanggal_peminjaman (disederhanakan)
                if (isset($row['tanggal_peminjaman'])) {
                    $tanggal_raw = $row['tanggal_peminjaman'];
                    if (empty($tanggal_raw) || $tanggal_raw === '0000-00-00' || $tanggal_raw === '0000-00-00 00:00:00') {
                        $row['tanggal_peminjaman'] = null;
                    } else {
                        // Ambil hanya bagian tanggal (YYYY-MM-DD)
                        $tanggal_clean = substr(trim($tanggal_raw), 0, 10);
                        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_clean)) {
                            $row['tanggal_peminjaman'] = $tanggal_clean;
                        } else {
                            $row['tanggal_peminjaman'] = null;
                        }
                    }
                }
                $rowsData[] = $row;
                $nomor_peminjaman_original = $row['nomor_peminjaman'];
                if (!empty($nomor_peminjaman_original) && trim($nomor_peminjaman_original) != '') {
                    $validNomorPeminjaman[] = mysqli_real_escape_string($db_dc, $nomor_peminjaman_original);
                }
            }
        }
        
        // Optimasi: Gunakan helper functions untuk batch query yang lebih efisien
        $statusMap = [];
        $stokDipinjamMap = [];
        if (!empty($validNomorPeminjaman)) {
            $uniqueNomorPeminjaman = array_unique($validNomorPeminjaman);
            
            if ($type === 'pengembalian') {
                require_once "../pengembalian/query_helper.php";
                $peminjamanProductsMap = getPengembalianProductsBatch($db_dc, $uniqueNomorPeminjaman);
                $gudangTujuanMap = getGudangTujuanPengembalianBatch($db_dc, $uniqueNomorPeminjaman);
                $gudangAsalMap = getGudangAsalPengembalianBatch($db_dc, $uniqueNomorPeminjaman);
                list($logStokProductsMap, $logStokProductsMapMismatch) = getLogStokPengembalianBatch($db_dc, $uniqueNomorPeminjaman, $gudangTujuanMap);
                
                // Ambil nomor_peminjaman_original untuk mendapatkan stok dipinjam
                $nomorPeminjamanOriginalList = [];
                foreach ($rowsData as $row) {
                    if (isset($row['nomor_peminjaman_original']) && !empty($row['nomor_peminjaman_original']) && trim($row['nomor_peminjaman_original']) != '') {
                        $nomorPeminjamanOriginalList[] = mysqli_real_escape_string($db_dc, $row['nomor_peminjaman_original']);
                    }
                }
                if (!empty($nomorPeminjamanOriginalList)) {
                    $uniqueNomorPeminjamanOriginal = array_unique($nomorPeminjamanOriginalList);
                    $stokDipinjamMap = getStokDipinjamBatch($db_dc, $uniqueNomorPeminjamanOriginal);
                }
                
                // Ambil data log_stok untuk menentukan status transaksi (menggunakan nama_file dan nama_gudang)
                $logStokForStatusTransaksiMap = getLogStokForStatusTransaksiPengembalian($db_dc, $uniqueNomorPeminjaman, $gudangAsalMap, $gudangTujuanMap);
                
                // Hitung status transaksi
                $statusMap = calculateStatusTransaksiPengembalianBatch($rowsData, $peminjamanProductsMap, $logStokProductsMap, $logStokProductsMapMismatch, $logStokForStatusTransaksiMap);
                // Untuk pengembalian, statusPeminjamanMap tidak digunakan
                $statusPeminjamanMap = [];
            } else {
                $peminjamanProductsMap = getPeminjamanProductsBatch($db_dc, $uniqueNomorPeminjaman);
                $gudangTujuanMap = getGudangTujuanPeminjamanBatch($db_dc, $uniqueNomorPeminjaman);
                $gudangAsalMap = getGudangAsalPeminjamanBatch($db_dc, $uniqueNomorPeminjaman);
                list($logStokProductsMap, $logStokProductsMapMismatch) = getLogStokPeminjamanBatch($db_dc, $uniqueNomorPeminjaman, $gudangTujuanMap);
                
                // Ambil data log_stok untuk menentukan status transaksi (menggunakan nama_file dan nama_gudang)
                $logStokForStatusTransaksiMap = getLogStokForStatusTransaksi($db_dc, $uniqueNomorPeminjaman, $gudangAsalMap, $gudangTujuanMap);
                
                // Ambil data log_stok untuk menentukan status peminjaman (menggunakan no_pj)
                $logStokForStatusPeminjamanMap = getLogStokForStatusPeminjaman($db_dc, $uniqueNomorPeminjaman, $gudangAsalMap, $gudangTujuanMap);
                
                // Debug logging
                error_log("get_data.php: logStokForStatusPeminjamanMap = " . json_encode($logStokForStatusPeminjamanMap));
                error_log("get_data.php: gudangAsalMap = " . json_encode($gudangAsalMap));
                error_log("get_data.php: uniqueNomorPeminjaman = " . json_encode($uniqueNomorPeminjaman));
                
                // Hitung status transaksi
                $statusTransaksiMap = calculateStatusTransaksiBatch($rowsData, $peminjamanProductsMap, $logStokProductsMap, $logStokProductsMapMismatch, $logStokForStatusTransaksiMap);
                
                // Hitung status peminjaman
                $statusPeminjamanMap = calculateStatusPeminjamanBatch($rowsData, $peminjamanProductsMap, $logStokProductsMap, $logStokProductsMapMismatch, $logStokForStatusPeminjamanMap);
                
                // Gabungkan kedua status map (untuk kompatibilitas dengan kode yang ada)
                $statusMap = $statusTransaksiMap; // Status Transaksi digunakan untuk badge utama
            }
        }
        
        // Inisialisasi statusPeminjamanMap jika belum didefinisikan
        if (!isset($statusPeminjamanMap)) {
            $statusPeminjamanMap = [];
        }

        // Process rows dengan status yang sudah dihitung
        $data = [];
        foreach ($rowsData as $row) {
            // Format tanggal - untuk Draft bisa NULL atau empty string
            $tanggal_peminjaman = $row['tanggal_peminjaman'] ?? null;
            
            // PERBAIKAN: Validasi yang lebih ketat untuk memastikan tanggal valid sebelum diproses
            $isValidDate = false;
            if (!empty($tanggal_peminjaman) && is_string($tanggal_peminjaman) && trim($tanggal_peminjaman) !== '') {
                $tanggal_peminjaman = trim($tanggal_peminjaman);
                // Cek format YYYY-MM-DD dan pastikan bukan nilai invalid
                if (strlen($tanggal_peminjaman) >= 10 && 
                    $tanggal_peminjaman !== '0000-00-00' && 
                    $tanggal_peminjaman !== '0000-00-00 00:00:00' &&
                    preg_match('/^\d{4}-\d{2}-\d{2}/', $tanggal_peminjaman)) {
                    // Validasi dengan DateTime untuk memastikan tanggal valid
                    $date = DateTime::createFromFormat('Y-m-d', substr($tanggal_peminjaman, 0, 10));
                    if ($date && $date->format('Y-m-d') === substr($tanggal_peminjaman, 0, 10)) {
                        $isValidDate = true;
                    }
                }
            }
            
            $tanggal = $isValidDate ? indoTgl(substr($tanggal_peminjaman, 0, 10)) : '<span class="text-muted">-</span>';
            
            // Status badge
            $statusBadge = '';
            // Untuk pengembalian, gunakan status_pengembalian; untuk peminjaman, gunakan status_peminjaman
            if ($type === 'pengembalian' && isset($row['status_pengembalian'])) {
                $status_peminjaman = $row['status_pengembalian'];
            } else {
                $status_peminjaman = isset($row['status_peminjaman']) ? $row['status_peminjaman'] : '';
            }
            
            $calculatedStatus = 'final';
            // Untuk pengembalian, gunakan nomor_pengembalian (yang ada di nomor_peminjaman karena alias); untuk peminjaman, gunakan nomor_peminjaman
            if ($type === 'pengembalian') {
                $nomor_key = isset($row['nomor_peminjaman']) ? $row['nomor_peminjaman'] : ''; // Ini sebenarnya nomor_pengembalian karena alias
            } else {
                $nomor_key = isset($row['nomor_peminjaman']) ? $row['nomor_peminjaman'] : '';
            }
            
            // Status Transaksi: menggunakan calculatedStatus dari statusMap
            // Aturan: Draft, Final, Belum Selesai, Selesai
            if (!empty($nomor_key) && trim($nomor_key) != '' && isset($statusMap[$nomor_key])) {
                $calculatedStatus = $statusMap[$nomor_key]['status'];
                
                if ($calculatedStatus == 'draft') {
                    $statusBadge = '<span class="badge badge-warning">Draft</span>';
                } elseif ($calculatedStatus == 'final') {
                    $statusBadge = '<span class="badge badge-primary">Final</span>';
                } elseif ($calculatedStatus == 'belum_selesai') {
                    $statusBadge = '<span class="badge badge-danger">Belum Selesai</span>';
                } elseif ($calculatedStatus == 'selesai') {
                    $statusBadge = '<span class="badge badge-success">Selesai</span>';
                } else {
                    $statusBadge = '<span class="badge badge-secondary">' . htmlspecialchars($calculatedStatus) . '</span>';
                }
            } else {
                // Fallback jika tidak ada calculatedStatus
                if ($status_peminjaman == 'Draft') {
                    $statusBadge = '<span class="badge badge-warning">Draft</span>';
                } elseif ($status_peminjaman == 'Final' || $status_peminjaman == 'Aktif') {
                    $statusBadge = '<span class="badge badge-primary">Final</span>';
                } else {
                    $statusBadge = '<span class="badge badge-secondary">' . htmlspecialchars($status_peminjaman) . '</span>';
                }
            }
            
            // Status Peminjaman badge (dihitung berdasarkan no_pj di log_stok dan nama_gudang = gudang_asal)
            $statusPeminjamanBadge = '';
            $calculatedStatusPeminjaman = 'final';
            if (!empty($nomor_peminjaman_original) && trim($nomor_peminjaman_original) != '' && isset($statusPeminjamanMap[$nomor_peminjaman_original])) {
                $calculatedStatusPeminjaman = $statusPeminjamanMap[$nomor_peminjaman_original]['status'];
                
                if ($calculatedStatusPeminjaman == 'draft') {
                    $statusPeminjamanBadge = '<span class="badge badge-warning">Draft</span>';
                } elseif ($calculatedStatusPeminjaman == 'final') {
                    // Status Final ditampilkan sebagai tanda "-"
                    $statusPeminjamanBadge = '<span class="text-muted">-</span>';
                } elseif ($calculatedStatusPeminjaman == 'belum_selesai') {
                    $statusPeminjamanBadge = '<span class="badge badge-danger">Belum Selesai</span>';
                } elseif ($calculatedStatusPeminjaman == 'selesai') {
                    $statusPeminjamanBadge = '<span class="badge badge-success">Selesai</span>';
                } else {
                    $statusPeminjamanBadge = '<span class="badge badge-secondary">' . htmlspecialchars($calculatedStatusPeminjaman) . '</span>';
                }
            } else {
                // Fallback jika tidak ada calculatedStatusPeminjaman
                if ($status_peminjaman == 'Draft') {
                    $statusPeminjamanBadge = '<span class="badge badge-warning">Draft</span>';
                } elseif ($status_peminjaman == 'Final') {
                    // Status Final ditampilkan sebagai tanda "-"
                    $statusPeminjamanBadge = '<span class="text-muted">-</span>';
                } elseif ($status_peminjaman == 'Aktif') {
                    $statusPeminjamanBadge = '<span class="badge badge-info">Aktif</span>';
                } elseif ($status_peminjaman == 'Selesai') {
                    $statusPeminjamanBadge = '<span class="badge badge-success">Selesai</span>';
                } else {
                    $statusPeminjamanBadge = '<span class="badge badge-secondary">' . htmlspecialchars($status_peminjaman) . '</span>';
                }
            }
            
            // Handle nomor_peminjaman yang NULL (untuk Draft)
            // Ambil nomor_peminjaman dari row saat ini, bukan dari variabel yang di-set di loop sebelumnya
            $nomor_peminjaman_original = isset($row['nomor_peminjaman']) ? $row['nomor_peminjaman'] : '';
            $isDraftNoNomor = empty($nomor_peminjaman_original) || $nomor_peminjaman_original === null || $status_peminjaman == 'Draft';
            if (!$isDraftNoNomor && strpos($nomor_peminjaman_original, '|') !== false) {
                $isDraftNoNomor = true;
            }
            $nomor_peminjaman_display = $isDraftNoNomor ? '<span class="text-muted">-</span>' : $nomor_peminjaman_original;
            
            // Untuk pengembalian, ambil nomor_peminjaman_original (nomor peminjaman yang dipilih saat tambah data)
            $nomor_peminjaman_original_display = '';
            if ($type === 'pengembalian' && isset($row['nomor_peminjaman_original']) && !empty($row['nomor_peminjaman_original'])) {
                $nomor_peminjaman_original_display = htmlspecialchars($row['nomor_peminjaman_original']);
            } else {
                $nomor_peminjaman_original_display = '<span class="text-muted">-</span>';
            }
            
            $deleteIdentifier = $isDraftNoNomor ? 'DRAFT-ID-' . $row['min_id'] : $nomor_peminjaman_original;
            
            // Tombol aksi
            $aksi = '<div class="btn-group" role="group">';
            if ($status_peminjaman == 'Draft') {
                $editIdentifier = $isDraftNoNomor ? '' : htmlspecialchars($nomor_peminjaman_original);
                $editTanggal = '';
                if (isset($isValidDate) && $isValidDate) {
                    $editTanggal = substr($tanggal_peminjaman, 0, 10);
                }
                $aksi .= '<button type="button" class="btn btn-sm btn-info edit_btn" 
                            data-toggle="modal" 
                            data-target="#tambahModal"
                            data-id="' . ($isDraftNoNomor ? $row['min_id'] : $nomor_peminjaman_original) . '"
                            data-nomor_peminjaman="' . $editIdentifier . '"
                            data-tanggal="' . htmlspecialchars($editTanggal) . '"
                            data-entitas_peminjam="' . htmlspecialchars($row['entitas_peminjam']) . '"
                            data-entitas_dipinjam="' . htmlspecialchars($row['entitas_dipinjam']) . '"
                            data-gudang_asal="' . htmlspecialchars($row['gudang_asal']) . '"
                            data-gudang_tujuan="' . htmlspecialchars($row['gudang_tujuan']) . '"
                            data-status="' . htmlspecialchars($status_peminjaman) . '"
                            data-min-id="' . $row['min_id'] . '"
                            data-is-draft-no-nomor="' . ($isDraftNoNomor ? '1' : '0') . '"
                            title="Edit">
                            <i class="fas fa-edit"></i>
                          </button>';
            }
            
            // Tampilkan tombol view untuk Final/Aktif (peminjaman) atau Final/Selesai (pengembalian)
            $canView = false;
            $viewNomor = '';
            
            if ($type === 'pengembalian') {
                // Untuk pengembalian, tampilkan view jika status Final atau Selesai
                // Gunakan nomor_pengembalian (nomor_peminjaman dari row) untuk view, bukan nomor_peminjaman_original
                $canView = ($status_peminjaman == 'Final' || $status_peminjaman == 'Selesai') 
                    && !empty($row['nomor_peminjaman']) 
                    && $row['nomor_peminjaman'] !== null;
                $viewNomor = $row['nomor_peminjaman']; // Ini adalah nomor_pengembalian untuk pengembalian
            } else {
                // Untuk peminjaman, tampilkan view jika status Final atau Aktif
                $canView = ($status_peminjaman == 'Final' || $status_peminjaman == 'Aktif') 
                    && !empty($nomor_peminjaman_original) 
                    && $nomor_peminjaman_original !== null;
                $viewNomor = $nomor_peminjaman_original;
            }
            
            if ($canView && !empty($viewNomor)) {
                $aksi .= '<button type="button" class="btn btn-sm btn-primary view_pdf_btn" 
                            data-nomor-peminjaman="' . htmlspecialchars($viewNomor, ENT_QUOTES) . '"
                            title="View Detail">
                            <i class="fas fa-eye"></i>
                          </button>';
            }
            
            $deleteDisabled = ''; // Tombol hapus selalu enabled
            // Gunakan fungsi yang sesuai berdasarkan type
            $deleteFunction = ($type === 'pengembalian') ? 'deletepengembalian' : 'deletepeminjaman';
            $deleteOnClick = 'onclick="' . $deleteFunction . '(\'' . htmlspecialchars($deleteIdentifier, ENT_QUOTES) . '\')"';
            $aksi .= '<button type="button" class="btn btn-sm btn-danger" 
                        ' . $deleteOnClick . '
                        title="Hapus">
                        <i class="fas fa-trash"></i>
                      </button>';
            $aksi .= '</div>';
            
            // Untuk pengembalian, tidak mengirim kolom gudang (10 kolom: Tanggal, Nomor Peminjaman, Nomor Pengembalian, Entitas Pengembali, Entitas Penerima, Jumlah Item, Stok Dipinjam, Total Qty, Status, Aksi)
            // Urutan entitas: Entitas Pengembali = entitas peminjam, Entitas Penerima = entitas dipinjam
            // Untuk peminjaman, tidak mengirim kolom gudang (9 kolom: Tanggal, Nomor Peminjaman, Entitas Dipinjam, Entitas Peminjam, Jumlah Item, Total Qty, Status Transaksi, Status Peminjaman, Aksi)
            if ($type === 'pengembalian') {
                // Ambil stok dipinjam dari map berdasarkan nomor_peminjaman_original
                $stokDipinjam = 0;
                if (isset($row['nomor_peminjaman_original']) && !empty($row['nomor_peminjaman_original']) && isset($stokDipinjamMap[$row['nomor_peminjaman_original']])) {
                    $stokDipinjam = $stokDipinjamMap[$row['nomor_peminjaman_original']];
                }
                
                $data[] = [
                    $tanggal,
                    $nomor_peminjaman_original_display, // Nomor Peminjaman
                    $nomor_peminjaman_display, // Nomor Pengembalian
                    $row['entitas_peminjam'], // Entitas Pengembali (entitas peminjam ketika peminjaman)
                    $row['entitas_dipinjam'], // Entitas Penerima (entitas dipinjam ketika peminjaman)
                    $row['jumlah_item'],
                    number_format($stokDipinjam, 0, ',', '.'), // Stok Dipinjam
                    number_format($row['total_qty'], 0, ',', '.'), // Total Qty
                    $statusBadge,
                    $aksi
                ];
            } else {
                $data[] = [
                    $tanggal,
                    $nomor_peminjaman_display,
                    $row['entitas_dipinjam'],
                    $row['entitas_peminjam'],
                    $row['jumlah_item'],
                    number_format($row['total_qty'], 0, ',', '.'),
                    $statusBadge, // Status Transaksi
                    $statusPeminjamanBadge, // Status Peminjaman
                    $aksi
                ];
            }
        }

        // Response untuk DataTables
        // Pastikan tidak ada output sebelum JSON
        while (ob_get_level() > 1) {
            ob_end_clean();
        }
        ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        
        echo json_encode([
            "draw" => intval($draw),
            "recordsTotal" => $recordsTotal,
            "recordsFiltered" => $recordsFiltered,
            "data" => $data
        ], JSON_UNESCAPED_UNICODE);
        ob_end_flush();
        exit();
    } catch (Exception $e) {
        // Jika terjadi error, kirim response error dalam format JSON
        // Pastikan tidak ada output sebelum JSON
        while (ob_get_level() > 1) {
            ob_end_clean();
        }
        ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        error_log("handleGetData Exception: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
        echo json_encode([
            "draw" => isset($draw) ? intval($draw) : 1,
            "recordsTotal" => 0,
            "recordsFiltered" => 0,
            "data" => [],
            "error" => "Error: " . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
        ob_end_flush();
        exit();
    }
}

// ... (fungsi-fungsi lainnya tetap sama seperti kode asli)
// Function untuk get detail peminjaman
// Function untuk get detail peminjaman - REVISI UNTUK DRAFT
function handleGetPeminjamanDetail() {
    global $db_dc;
    
    try {
        // Ambil type dari POST atau default ke peminjaman
        $type = isset($_POST['type']) ? trim($_POST['type']) : 'peminjaman';
        $table_name = ($type === 'pengembalian') ? 'pengembalian_stok' : 'peminjaman_stok';
        $tanggal_field = ($type === 'pengembalian') ? 'tanggal_pengembalian' : 'tanggal_peminjaman';
        $nomor_field = ($type === 'pengembalian') ? 'nomor_pengembalian' : 'nomor_peminjaman';
        $status_field = ($type === 'pengembalian') ? 'status_pengembalian' : 'status_peminjaman';
        
        if (isset($_POST['nomor_peminjaman']) || isset($_POST['min_id']) || isset($_POST['nomor_pengembalian'])) {
            $nomor_peminjaman = isset($_POST['nomor_peminjaman']) ? $_POST['nomor_peminjaman'] : '';
            $nomor_pengembalian = isset($_POST['nomor_pengembalian']) ? $_POST['nomor_pengembalian'] : '';
            $min_id = isset($_POST['min_id']) ? intval($_POST['min_id']) : 0;
            
            // Untuk pengembalian dengan nomor_pengembalian, gunakan sebagai nomor_field
            if ($type === 'pengembalian' && !empty($nomor_pengembalian) && empty($nomor_peminjaman)) {
                $nomor_peminjaman = $nomor_pengembalian; // Gunakan nomor_pengembalian sebagai identifier
            }
            
            if (empty($nomor_peminjaman) && $min_id > 0) {
                // Untuk draft, ambil berdasarkan ID
                $nomor_peminjaman_select = ($type === 'pengembalian') 
                    ? ", nomor_peminjaman" 
                    : "";
                $getRefQuery = "SELECT entitas_peminjam, gudang_asal, gudang_tujuan, $tanggal_field as tanggal_peminjaman, $status_field as status_peminjaman $nomor_peminjaman_select FROM $table_name WHERE id = ? LIMIT 1";
                $getRefStmt = mysqli_prepare($db_dc, $getRefQuery);
                if ($getRefStmt) {
                    mysqli_stmt_bind_param($getRefStmt, "i", $min_id);
                    mysqli_stmt_execute($getRefStmt);
                    $refResult = mysqli_stmt_get_result($getRefStmt);
                    $refRow = mysqli_fetch_assoc($refResult);
                    mysqli_stmt_close($getRefStmt);
                    
                    if ($refRow) {
                        $gudang_asal = mysqli_real_escape_string($db_dc, $refRow['gudang_asal']);
                        $gudang_tujuan = mysqli_real_escape_string($db_dc, $refRow['gudang_tujuan']);
                        $tanggal_peminjaman = $refRow['tanggal_peminjaman'];
                        $status_peminjaman = mysqli_real_escape_string($db_dc, $refRow['status_peminjaman']);
                        $nomorPeminjamanOriginal = ($type === 'pengembalian' && isset($refRow['nomor_peminjaman'])) ? $refRow['nomor_peminjaman'] : '';
                        
                        // Untuk pengembalian draft, ambil semua data dengan kriteria yang sama
                        $nomor_peminjaman_select_query = ($type === 'pengembalian') 
                            ? ", nomor_peminjaman, gudang_asal" 
                            : "";
                        if ($tanggal_peminjaman === null || $tanggal_peminjaman === '') {
                            $query = "SELECT id, produk, qty, catatan $nomor_peminjaman_select_query FROM $table_name
                                      WHERE gudang_asal = ? AND gudang_tujuan = ? AND $tanggal_field IS NULL
                                      AND $status_field = ? AND ($nomor_field IS NULL OR $nomor_field = '' OR TRIM($nomor_field) = '')
                                      ORDER BY id ASC";
                            $stmt = mysqli_prepare($db_dc, $query);
                            if (!$stmt) {
                                throw new Exception("Error preparing statement: " . mysqli_error($db_dc));
                            }
                            mysqli_stmt_bind_param($stmt, "sss", $gudang_asal, $gudang_tujuan, $status_peminjaman);
                        } else {
                            $query = "SELECT id, produk, qty, catatan $nomor_peminjaman_select_query FROM $table_name
                                      WHERE gudang_asal = ? AND gudang_tujuan = ? AND $tanggal_field = ?
                                      AND $status_field = ? AND ($nomor_field IS NULL OR $nomor_field = '' OR TRIM($nomor_field) = '')
                                      ORDER BY id ASC";
                            $stmt = mysqli_prepare($db_dc, $query);
                            if (!$stmt) {
                                throw new Exception("Error preparing statement: " . mysqli_error($db_dc));
                            }
                            mysqli_stmt_bind_param($stmt, "ssss", $gudang_asal, $gudang_tujuan, $tanggal_peminjaman, $status_peminjaman);
                        }
                    } else {
                        throw new Exception("Data dengan ID $min_id tidak ditemukan");
                    }
                } else {
                    throw new Exception("Error preparing reference query: " . mysqli_error($db_dc));
                }
            } else {
                // Untuk data dengan nomor (non-draft)
                $nomor_peminjaman_select = ($type === 'pengembalian') 
                    ? ", nomor_peminjaman, gudang_asal" 
                    : "";
                $query = "SELECT id, produk, qty, catatan $nomor_peminjaman_select FROM $table_name WHERE $nomor_field = ? ORDER BY id ASC";
                $stmt = mysqli_prepare($db_dc, $query);
                if (!$stmt) {
                    throw new Exception("Error preparing statement: " . mysqli_error($db_dc));
                }
                mysqli_stmt_bind_param($stmt, "s", $nomor_peminjaman);
            }
            
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            $data = [];
            $gudangAsal = null;
            
            while ($row = mysqli_fetch_assoc($result)) {
                $itemData = [
                    'id' => $row['id'],
                    'produk' => $row['produk'],
                    'qty' => $row['qty'] ?? 0,
                    'catatan' => $row['catatan'] ?? ''
                ];
                
                // UNTUK PENGEMBALIAN: Ambil jumlah dipinjam dan stok
                if ($type === 'pengembalian') {
                    $nomorPeminjamanOriginal = isset($row['nomor_peminjaman']) ? $row['nomor_peminjaman'] : (isset($nomorPeminjamanOriginal) ? $nomorPeminjamanOriginal : '');
                    $gudangAsalRow = isset($row['gudang_asal']) ? $row['gudang_asal'] : null;
                    $gudangAsal = $gudangAsalRow ? $gudangAsalRow : $gudangAsal;
                    
                    // PERBAIKAN: Untuk DRAFT, jumlah dipinjam = qty dari data pengembalian itu sendiri
                    $jumlahDipinjam = $row['qty']; // Default: gunakan qty sendiri untuk draft
                    
                    // Jika ada nomor peminjaman original, ambil dari peminjaman_stok
                    if (!empty($nomorPeminjamanOriginal) && !empty($row['produk'])) {
                        $queryPeminjaman = "SELECT SUM(qty) as total_qty FROM peminjaman_stok 
                                           WHERE nomor_peminjaman = ? AND produk = ?";
                        $stmtPeminjaman = mysqli_prepare($db_dc, $queryPeminjaman);
                        if ($stmtPeminjaman) {
                            mysqli_stmt_bind_param($stmtPeminjaman, "ss", $nomorPeminjamanOriginal, $row['produk']);
                            mysqli_stmt_execute($stmtPeminjaman);
                            $resultPeminjaman = mysqli_stmt_get_result($stmtPeminjaman);
                            if ($rowPeminjaman = mysqli_fetch_assoc($resultPeminjaman)) {
                                $jumlahDipinjamAsli = floatval($rowPeminjaman['total_qty'] ?? 0);
                                // Hanya gunakan dari peminjaman jika lebih besar dari 0
                                if ($jumlahDipinjamAsli > 0) {
                                    $jumlahDipinjam = $jumlahDipinjamAsli;
                                }
                            }
                            mysqli_stmt_close($stmtPeminjaman);
                        }
                    }
                    
                    // Ambil stok dari omni_stok_akhir
                    $stok = 0;
                    if (!empty($gudangAsal) && !empty($row['produk'])) {
                        $gudangEscaped = mysqli_real_escape_string($db_dc, $gudangAsal);
                        $produkEscaped = mysqli_real_escape_string($db_dc, $row['produk']);
                        $queryStok = "SELECT SUM(osa.qty) as stok 
                                     FROM omni_stok_akhir osa
                                     INNER JOIN gudang_omni go ON go.gudang COLLATE utf8mb4_unicode_ci = osa.gudang COLLATE utf8mb4_unicode_ci 
                                     AND go.tim COLLATE utf8mb4_unicode_ci = osa.tim COLLATE utf8mb4_unicode_ci
                                     WHERE osa.nama = ? AND go.nama_gudang COLLATE utf8mb4_unicode_ci = ? COLLATE utf8mb4_unicode_ci";
                        $stmtStok = mysqli_prepare($db_dc, $queryStok);
                        if ($stmtStok) {
                            mysqli_stmt_bind_param($stmtStok, "ss", $produkEscaped, $gudangEscaped);
                            mysqli_stmt_execute($stmtStok);
                            $resultStok = mysqli_stmt_get_result($stmtStok);
                            if ($resultStok && $rowStok = mysqli_fetch_assoc($resultStok)) {
                                $stok = floatval($rowStok['stok'] ?? 0);
                            }
                            mysqli_stmt_close($stmtStok);
                        }
                    }
                    
                    $itemData['jumlah_dipinjam'] = $jumlahDipinjam;
                    $itemData['stok'] = $stok;
                    // Untuk DRAFT, jumlah dikembalikan = qty dari data itu sendiri
                    $itemData['jumlah_dikembalikan'] = $row['qty'];
                    
                    if (!empty($nomorPeminjamanOriginal)) {
                        $itemData['nomor_peminjaman'] = $nomorPeminjamanOriginal;
                    }
                }
                
                $data[] = $itemData;
            }
            
            mysqli_stmt_close($stmt);
            
            // Debug logging (hanya ke error log, tidak ke output)
            error_log("get_peminjaman_detail - Type: $type, Data count: " . count($data));
            if (count($data) > 0 && $type === 'pengembalian') {
                error_log("get_peminjaman_detail - First item: " . json_encode($data[0]));
            }
            
            // Bersihkan output buffer (hanya konten, jangan hapus buffer)
            while (ob_get_level() > 1) {
                ob_end_clean();
            }
            if (ob_get_level() > 0) {
                ob_clean();
            }
            
            // Set header JSON - pastikan tidak ada output sebelumnya
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            
            // Encode dan kirim JSON
            $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            
            // Validasi JSON sebelum output
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("get_peminjaman_detail - JSON encode error: " . json_last_error_msg());
                $json = json_encode(['error' => 'Error encoding JSON: ' . json_last_error_msg()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            
            // Log untuk debugging
            error_log("get_peminjaman_detail - JSON length: " . strlen($json) . ", First 100 chars: " . substr($json, 0, 100));
            
            // Output JSON
            echo $json;
            
            // Flush dan exit
            if (ob_get_level() > 0) {
                ob_end_flush();
            } else {
                flush();
            }
            exit;
        } else {
            error_log("get_peminjaman_detail - Error: Nomor peminjaman atau ID tidak ditemukan. POST data: " . json_encode($_POST));
            
            // Bersihkan output buffer (hanya konten, jangan hapus buffer)
            while (ob_get_level() > 1) {
                ob_end_clean();
            }
            if (ob_get_level() > 0) {
                ob_clean();
            }
            
            // Set header JSON
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            
            echo json_encode(['error' => 'Nomor peminjaman atau ID tidak ditemukan'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            
            if (ob_get_level() > 0) {
                ob_end_flush();
            } else {
                flush();
            }
            exit;
        }
    } catch (Exception $e) {
        // Bersihkan output buffer (hanya konten, jangan hapus buffer)
        while (ob_get_level() > 1) {
            ob_end_clean();
        }
        if (ob_get_level() > 0) {
            ob_clean();
        }
        
        // Set header JSON
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        
        echo json_encode(['error' => 'Error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        if (ob_get_level() > 0) {
            ob_end_flush();
        } else {
            flush();
        }
        exit;
    }
}

// Function untuk get stok by produk (sama seperti mutasi)
function handleGetStokByProduk() {
    global $db_dc;
    
    header('Content-Type: application/json');
    
    if (isset($_POST['produk']) && isset($_POST['gudang'])) {
        $produk = $_POST['produk'];
        $gudang = $_POST['gudang'];
        
        $query = "SELECT SUM(osa.qty) as stok 
                  FROM omni_stok_akhir osa
                  INNER JOIN gudang_omni go ON go.gudang COLLATE utf8mb4_unicode_ci = osa.gudang COLLATE utf8mb4_unicode_ci 
                  AND go.tim COLLATE utf8mb4_unicode_ci = osa.tim COLLATE utf8mb4_unicode_ci
                  WHERE osa.nama = ? AND go.nama_gudang COLLATE utf8mb4_unicode_ci = ? COLLATE utf8mb4_unicode_ci";
        
        $stmt = mysqli_prepare($db_dc, $query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ss", $produk, $gudang);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            $stok = 0;
            if ($row = mysqli_fetch_assoc($result)) {
                $stok = intval($row['stok'] ?? 0);
            }
            
            mysqli_stmt_close($stmt);
            
            echo json_encode([
                'status' => 'success',
                'stok' => $stok
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Gagal mengambil data stok: ' . mysqli_error($db_dc),
                'stok' => 0
            ]);
        }
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Parameter tidak lengkap',
            'stok' => 0
        ]);
    }
}

// Function untuk get entitas code (sama seperti mutasi)
function handleGetEntitasCode() {
    global $db_dc;
    
    ob_start();
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    header('Content-Type: application/json');
    
    try {
        if (isset($_POST['gudang'])) {
            $gudang = $_POST['gudang'];
            
            $query = "SELECT bt.kode_tim, be.inisial as entitas
                      FROM gudang_omni go
                      INNER JOIN base_tim bt ON bt.tim COLLATE utf8mb4_unicode_ci = go.tim COLLATE utf8mb4_unicode_ci
                      INNER JOIN base_entitas be ON be.id = bt.id_entitas
                      WHERE go.nama_gudang COLLATE utf8mb4_unicode_ci = ? COLLATE utf8mb4_unicode_ci
                      LIMIT 1";
            
            $stmt = mysqli_prepare($db_dc, $query);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "s", $gudang);
                if (mysqli_stmt_execute($stmt)) {
                    $result = mysqli_stmt_get_result($stmt);
                    
                    $code_entitas = '';
                    $entitas = '';
                    if ($row = mysqli_fetch_assoc($result)) {
                        $code_entitas = isset($row['kode_tim']) ? $row['kode_tim'] : '';
                        $entitas = isset($row['entitas']) ? $row['entitas'] : '';
                    }
                    mysqli_stmt_close($stmt);
                    
                    if (empty($code_entitas)) {
                        $code_entitas = strtoupper(substr($gudang, 0, 3));
                    }
                    
                    ob_clean();
                    echo json_encode([
                        'code_entitas' => $code_entitas,
                        'entitas' => $entitas
                    ]);
                    ob_end_flush();
                    exit;
                }
            }
        }
        
        ob_clean();
        echo json_encode(['error' => 'Parameter tidak ditemukan']);
        ob_end_flush();
        exit;
    } catch (Exception $e) {
        ob_clean();
        echo json_encode([
            'error' => 'Error: ' . $e->getMessage()
        ]);
        ob_end_flush();
        exit;
    }
}

// Function untuk get gudang by entitas (sama seperti mutasi)
function handleGetGudangByEntitas() {
    global $db_dc;
    
    header('Content-Type: application/json');
    
    if (isset($_POST['entitas'])) {
        $entitas = $_POST['entitas'];
        
        $entitasArray = is_array($entitas) ? $entitas : [$entitas];
        $entitasArray = array_filter(array_map('trim', $entitasArray));
        
        if (empty($entitasArray)) {
            echo json_encode(['gudang' => []]);
            return;
        }
        
        $placeholders = str_repeat('?,', count($entitasArray) - 1) . '?';
        $query = "SELECT DISTINCT go.nama_gudang 
                  FROM gudang_omni go
                  LEFT JOIN base_tim bt ON bt.tim = go.tim
                  LEFT JOIN base_entitas be ON be.id = bt.id_entitas
                  WHERE be.inisial IN ($placeholders)
                  ORDER BY go.nama_gudang ASC";
        
        $stmt = mysqli_prepare($db_dc, $query);
        if (!$stmt) {
            echo json_encode(['error' => 'Error preparing query: ' . mysqli_error($db_dc)]);
            return;
        }
        
        $types = str_repeat('s', count($entitasArray));
        mysqli_stmt_bind_param($stmt, $types, ...$entitasArray);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $gudang = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $gudang[] = [
                'nama_gudang' => $row['nama_gudang']
            ];
        }
        
        mysqli_stmt_close($stmt);
        echo json_encode(['gudang' => $gudang]);
    } else {
        echo json_encode(['error' => 'Entitas tidak ditemukan', 'gudang' => []]);
    }
}

/**
 * Function untuk get list nomor peminjaman yang bisa dikembalikan
 * Mengambil nomor peminjaman yang statusnya Final (bukan Draft)
 */
function handleGetListNomorPeminjaman() {
    global $db_dc;
    
    // Pastikan output buffer bersih
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    header('Content-Type: application/json');
    
    try {
        // Validasi koneksi database
        if (!isset($db_dc) || !$db_dc) {
            throw new Exception("Database connection not available");
        }
        
        if (!mysqli_ping($db_dc)) {
            throw new Exception("Database connection lost");
        }
        
        // Query untuk mendapatkan list nomor peminjaman yang statusnya Final
        // Hanya ambil yang memiliki nomor_peminjaman (bukan Draft tanpa nomor)
        $query = "SELECT DISTINCT 
                    m.nomor_peminjaman,
                    m.tanggal_peminjaman,
                    m.entitas_peminjam,
                    m.entitas_dipinjam,
                    m.gudang_asal,
                    m.gudang_tujuan
                  FROM peminjaman_stok m
                  WHERE m.nomor_peminjaman IS NOT NULL 
                    AND m.nomor_peminjaman != '' 
                    AND TRIM(m.nomor_peminjaman) != ''
                    AND m.status_peminjaman = 'Final'
                  ORDER BY m.tanggal_peminjaman DESC, m.nomor_peminjaman DESC
                  LIMIT 1000";
        
        error_log("get_list_nomor_peminjaman - Query: " . $query);
        
        $result = mysqli_query($db_dc, $query);
        
        if (!$result) {
            $error = mysqli_error($db_dc);
            error_log("get_list_nomor_peminjaman - Query error: " . $error);
            throw new Exception("Query error: " . $error);
        }
        
        // Kumpulkan semua nomor peminjaman terlebih dahulu
        $allRows = [];
        $nomorPeminjamanList = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $nomorPeminjaman = $row['nomor_peminjaman'] ?? '';
            if (!empty($nomorPeminjaman)) {
                $allRows[$nomorPeminjaman] = $row;
                $nomorPeminjamanList[] = mysqli_real_escape_string($db_dc, $nomorPeminjaman);
            }
        }
        
        if (empty($nomorPeminjamanList)) {
            echo json_encode([
                'success' => true, 
                'data' => [],
                'count' => 0
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // Batch query: Ambil semua qty dipinjam per produk untuk semua nomor peminjaman sekaligus
        $placeholders = str_repeat('?,', count($nomorPeminjamanList) - 1) . '?';
        $queryPeminjamanBatch = "SELECT 
                            nomor_peminjaman,
                            produk,
                            SUM(qty) as total_qty_dipinjam
                          FROM peminjaman_stok
                          WHERE nomor_peminjaman IN ($placeholders)
                          GROUP BY nomor_peminjaman, produk";
        
        $stmtPeminjamanBatch = mysqli_prepare($db_dc, $queryPeminjamanBatch);
        $qtyDipinjamMap = []; // [nomor_peminjaman][produk] = qty
        
        if ($stmtPeminjamanBatch) {
            $types = str_repeat('s', count($nomorPeminjamanList));
            mysqli_stmt_bind_param($stmtPeminjamanBatch, $types, ...$nomorPeminjamanList);
            mysqli_stmt_execute($stmtPeminjamanBatch);
            $resultPeminjamanBatch = mysqli_stmt_get_result($stmtPeminjamanBatch);
            
            while ($rowPeminjaman = mysqli_fetch_assoc($resultPeminjamanBatch)) {
                $nomor = $rowPeminjaman['nomor_peminjaman'];
                $produk = $rowPeminjaman['produk'];
                $qtyDipinjam = floatval($rowPeminjaman['total_qty_dipinjam']);
                if ($qtyDipinjam > 0) {
                    if (!isset($qtyDipinjamMap[$nomor])) {
                        $qtyDipinjamMap[$nomor] = [];
                    }
                    $qtyDipinjamMap[$nomor][$produk] = $qtyDipinjam;
                }
            }
            mysqli_stmt_close($stmtPeminjamanBatch);
        }
        
        // Batch query: Ambil semua qty dikembalikan per produk untuk semua nomor peminjaman sekaligus
        $queryPengembalianBatch = "SELECT 
                                nomor_peminjaman,
                                produk,
                                SUM(qty) as total_qty_dikembalikan
                              FROM pengembalian_stok
                              WHERE nomor_peminjaman IN ($placeholders)
                              GROUP BY nomor_peminjaman, produk";
        
        $stmtPengembalianBatch = mysqli_prepare($db_dc, $queryPengembalianBatch);
        $qtyDikembalikanMap = []; // [nomor_peminjaman][produk] = qty
        
        if ($stmtPengembalianBatch) {
            $types = str_repeat('s', count($nomorPeminjamanList));
            mysqli_stmt_bind_param($stmtPengembalianBatch, $types, ...$nomorPeminjamanList);
            mysqli_stmt_execute($stmtPengembalianBatch);
            $resultPengembalianBatch = mysqli_stmt_get_result($stmtPengembalianBatch);
            
            while ($rowPengembalian = mysqli_fetch_assoc($resultPengembalianBatch)) {
                $nomor = $rowPengembalian['nomor_peminjaman'];
                $produk = $rowPengembalian['produk'];
                $qtyDikembalikan = floatval($rowPengembalian['total_qty_dikembalikan']);
                if ($qtyDikembalikan > 0) {
                    if (!isset($qtyDikembalikanMap[$nomor])) {
                        $qtyDikembalikanMap[$nomor] = [];
                    }
                    $qtyDikembalikanMap[$nomor][$produk] = $qtyDikembalikan;
                }
            }
            mysqli_stmt_close($stmtPengembalianBatch);
        }
        
        // Proses setiap nomor peminjaman untuk cek apakah sudah selesai
        $list = [];
        $count = 0;
        foreach ($allRows as $nomorPeminjaman => $row) {
            $qtyDipinjamPerProduk = isset($qtyDipinjamMap[$nomorPeminjaman]) ? $qtyDipinjamMap[$nomorPeminjaman] : [];
            $qtyDikembalikanPerProduk = isset($qtyDikembalikanMap[$nomorPeminjaman]) ? $qtyDikembalikanMap[$nomorPeminjaman] : [];
            
            // Cek apakah semua produk sudah selesai dikembalikan
            $isSelesai = true;
            if (empty($qtyDipinjamPerProduk)) {
                // Jika tidak ada data peminjaman, skip
                continue;
            }
            
            foreach ($qtyDipinjamPerProduk as $produk => $qtyDipinjam) {
                $qtyDikembalikan = isset($qtyDikembalikanPerProduk[$produk]) ? floatval($qtyDikembalikanPerProduk[$produk]) : 0;
                
                // Gunakan toleransi untuk perbandingan floating point
                if (abs($qtyDikembalikan - $qtyDipinjam) >= 0.01) {
                    $isSelesai = false;
                    break;
                }
            }
            
            // Hanya tambahkan ke list jika belum selesai dikembalikan
            if (!$isSelesai) {
                $list[] = [
                    'nomor_peminjaman' => $nomorPeminjaman,
                    'tanggal_peminjaman' => $row['tanggal_peminjaman'] ?? '',
                    'entitas_peminjam' => $row['entitas_peminjam'] ?? '',
                    'entitas_dipinjam' => $row['entitas_dipinjam'] ?? '',
                    'gudang_asal' => $row['gudang_asal'] ?? '',
                    'gudang_tujuan' => $row['gudang_tujuan'] ?? ''
                ];
                $count++;
            }
        }
        
        error_log("get_list_nomor_peminjaman - Found " . $count . " records");
        
        echo json_encode([
            'success' => true, 
            'data' => $list,
            'count' => $count
        ], JSON_UNESCAPED_UNICODE);
        
        exit;
    } catch (Exception $e) {
        error_log("get_list_nomor_peminjaman - Exception: " . $e->getMessage());
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Error: ' . $e->getMessage(),
            'data' => []
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

/**
 * Function untuk get sisa qty yang belum dikembalikan per produk untuk nomor peminjaman tertentu
 */
function handleGetSisaQtyPengembalian() {
    global $db_dc;
    
    try {
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        
        $nomor_peminjaman = isset($_POST['nomor_peminjaman']) ? trim($_POST['nomor_peminjaman']) : '';
        
        if (empty($nomor_peminjaman)) {
            echo json_encode([
                'success' => false,
                'error' => 'Nomor peminjaman tidak boleh kosong',
                'data' => []
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // Ambil total qty per produk dari peminjaman_stok
        $queryPeminjaman = "SELECT 
                            produk,
                            SUM(qty) as total_qty_dipinjam
                          FROM peminjaman_stok
                          WHERE nomor_peminjaman = ?
                          GROUP BY produk";
        
        $stmtPeminjaman = mysqli_prepare($db_dc, $queryPeminjaman);
        if (!$stmtPeminjaman) {
            throw new Exception("Error preparing query: " . mysqli_error($db_dc));
        }
        
        mysqli_stmt_bind_param($stmtPeminjaman, "s", $nomor_peminjaman);
        mysqli_stmt_execute($stmtPeminjaman);
        $resultPeminjaman = mysqli_stmt_get_result($stmtPeminjaman);
        
        $qtyDipinjamPerProduk = [];
        while ($rowPeminjaman = mysqli_fetch_assoc($resultPeminjaman)) {
            $produk = $rowPeminjaman['produk'];
            $qtyDipinjam = floatval($rowPeminjaman['total_qty_dipinjam']);
            if ($qtyDipinjam > 0) {
                $qtyDipinjamPerProduk[$produk] = $qtyDipinjam;
            }
        }
        mysqli_stmt_close($stmtPeminjaman);
        
        // Ambil total qty per produk dari pengembalian_stok (yang sudah Final)
        $queryPengembalian = "SELECT 
                                produk,
                                SUM(qty) as total_qty_dikembalikan
                              FROM pengembalian_stok
                              WHERE nomor_peminjaman = ?
                                AND status_pengembalian = 'Final'
                              GROUP BY produk";
        
        $stmtPengembalian = mysqli_prepare($db_dc, $queryPengembalian);
        $qtyDikembalikanPerProduk = [];
        if ($stmtPengembalian) {
            mysqli_stmt_bind_param($stmtPengembalian, "s", $nomor_peminjaman);
            mysqli_stmt_execute($stmtPengembalian);
            $resultPengembalian = mysqli_stmt_get_result($stmtPengembalian);
            
            while ($rowPengembalian = mysqli_fetch_assoc($resultPengembalian)) {
                $produk = $rowPengembalian['produk'];
                $qtyDikembalikan = floatval($rowPengembalian['total_qty_dikembalikan']);
                if ($qtyDikembalikan > 0) {
                    $qtyDikembalikanPerProduk[$produk] = $qtyDikembalikan;
                }
            }
            mysqli_stmt_close($stmtPengembalian);
        }
        
        // Hitung sisa qty per produk
        $sisaQtyPerProduk = [];
        foreach ($qtyDipinjamPerProduk as $produk => $qtyDipinjam) {
            $qtyDikembalikan = isset($qtyDikembalikanPerProduk[$produk]) ? floatval($qtyDikembalikanPerProduk[$produk]) : 0;
            $sisaQty = $qtyDipinjam - $qtyDikembalikan;
            if ($sisaQty > 0) {
                $sisaQtyPerProduk[$produk] = $sisaQty;
            } else {
                $sisaQtyPerProduk[$produk] = 0;
            }
        }
        
        echo json_encode([
            'success' => true,
            'data' => $sisaQtyPerProduk
        ], JSON_UNESCAPED_UNICODE);
        exit;
        
    } catch (Exception $e) {
        error_log("get_sisa_qty_pengembalian - Exception: " . $e->getMessage());
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Error: ' . $e->getMessage(),
            'data' => []
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

/**
 * Function untuk get peminjaman details (HTML output)
 * Menampilkan HTML detail peminjaman dengan catatan penyesuaian
 */
function handleGetPeminjamanDetails() {
    global $db_dc;
    
    ob_clean();
    header('Content-Type: text/html; charset=utf-8');
    
    try {
        if (!isset($_POST['nomor_peminjaman'])) {
            throw new Exception('Nomor peminjaman tidak ditemukan');
        }
        
        // Ambil type dari POST atau default ke peminjaman
        $type = isset($_POST['type']) ? trim($_POST['type']) : 'peminjaman';
        $table_name = ($type === 'pengembalian') ? 'pengembalian_stok' : 'peminjaman_stok';
        $tanggal_field = ($type === 'pengembalian') ? 'tanggal_pengembalian' : 'tanggal_peminjaman';
        $nomor_field = ($type === 'pengembalian') ? 'nomor_pengembalian' : 'nomor_peminjaman';
        $status_field = ($type === 'pengembalian') ? 'status_pengembalian' : 'status_peminjaman';
        
        $nomor_peminjaman = mysqli_real_escape_string($db_dc, $_POST['nomor_peminjaman']);
        
        // Query untuk mengambil data peminjaman/pengembalian dengan catatan
        // Untuk pengembalian, ambil juga nomor_peminjaman (kolom yang menyimpan nomor peminjaman asli)
        $nomor_peminjaman_original_select = ($type === 'pengembalian') 
            ? ", m.nomor_peminjaman as nomor_peminjaman_original" 
            : "";
        
        $query = "SELECT 
                        m.$nomor_field as nomor_peminjaman,
                        m.$tanggal_field as tanggal_peminjaman,
                        m.entitas_peminjam,
                        m.entitas_dipinjam,
                        m.gudang_asal,
                        m.gudang_tujuan,
                        m.produk,
                        m.qty,
                        m.catatan,
                        m.$status_field as status_peminjaman,
                        m.id
                        $nomor_peminjaman_original_select
                      FROM $table_name m
                      WHERE m.$nomor_field = ?
                      ORDER BY 
                        CASE WHEN m.catatan IS NULL OR m.catatan = '' OR m.catatan NOT LIKE '%Penyesuaian%' THEN 0 ELSE 1 END,
                        m.id ASC";
        
        $stmt = mysqli_prepare($db_dc, $query);
        if (!$stmt) {
            throw new Exception("Error preparing query: " . mysqli_error($db_dc));
        }
        
        mysqli_stmt_bind_param($stmt, "s", $nomor_peminjaman);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $headerData = null;
        $peminjamanData = [];
        $totalQty = 0;
        
        while ($row = mysqli_fetch_assoc($result)) {
            if ($headerData === null) {
                $headerData = [
                    'nomor_peminjaman' => $row['nomor_peminjaman'],
                    'tanggal_peminjaman' => $row['tanggal_peminjaman'],
                    'entitas_peminjam' => $row['entitas_peminjam'],
                    'entitas_dipinjam' => $row['entitas_dipinjam'],
                    'gudang_asal' => $row['gudang_asal'],
                    'gudang_tujuan' => $row['gudang_tujuan'],
                    'status_peminjaman' => $row['status_peminjaman']
                ];
                // Untuk pengembalian, simpan nomor_peminjaman_original
                if ($type === 'pengembalian' && isset($row['nomor_peminjaman_original'])) {
                    $headerData['nomor_peminjaman_original'] = $row['nomor_peminjaman_original'];
                }
            }
            
            // Include semua data, termasuk penyesuaian
            $isPenyesuaian = !empty($row['catatan']) && stripos($row['catatan'], 'Penyesuaian') !== false;
            
            $qtyValue = intval($row['qty']);
            if (!empty($row['produk']) && $qtyValue != 0) {
                $productData = [
                    'id' => $row['id'],
                    'produk' => $row['produk'],
                    'qty' => $qtyValue,
                    'catatan' => $row['catatan'] ?? '',
                    'is_penyesuaian' => $isPenyesuaian
                ];
                
                $peminjamanData[] = $productData;
                if ($qtyValue > 0) {
                    $totalQty += $qtyValue;
                }
            }
        }
        
        mysqli_stmt_close($stmt);
        
        if ($headerData === null) {
            throw new Exception('Data peminjaman tidak ditemukan');
        }
        
        // Hitung jumlah terkirim dan diterima per produk dari log_stok (hanya untuk Final, bukan Draft)
        // Untuk pengembalian, jumlah dikembalikan diambil dari grouping total qty di tabel pengembalian_stok
        $gudangTujuan = $headerData['gudang_tujuan'] ?? '';
        $gudangAsal = $headerData['gudang_asal'] ?? '';
        $nomor_peminjaman = $headerData['nomor_peminjaman'] ?? '';
        $jumlahTerkirimPerProduk = [];
        $jumlahDiterimaPerProduk = []; // Untuk status (dari log_stok)
        $jumlahDikembalikanPerProduk = []; // Untuk kolom Jml. Dikembalikan (dari pengembalian_stok)
        $overallStatus = 'final';
        
        // Ambil jumlah dikembalikan dari grouping total qty di tabel pengembalian_stok
        // Untuk pengembalian: berdasarkan nomor_pengembalian (mengambil qty dari pengembalian_stok)
        // Untuk peminjaman: berdasarkan nomor_peminjaman (mencari di kolom nomor_peminjaman di tabel pengembalian_stok)
        if ($type === 'pengembalian') {
            // Untuk pengembalian, ambil qty dari pengembalian_stok berdasarkan nomor_pengembalian
            $queryJumlahDikembalikan = "SELECT 
                                    produk,
                                    SUM(qty) as jumlah_dikembalikan
                                 FROM pengembalian_stok
                                 WHERE nomor_pengembalian = ?
                                 GROUP BY produk";
            $stmtJumlahDikembalikan = mysqli_prepare($db_dc, $queryJumlahDikembalikan);
            if ($stmtJumlahDikembalikan) {
                mysqli_stmt_bind_param($stmtJumlahDikembalikan, "s", $nomor_peminjaman);
                mysqli_stmt_execute($stmtJumlahDikembalikan);
                $resultJumlahDikembalikan = mysqli_stmt_get_result($stmtJumlahDikembalikan);
            } else {
                $resultJumlahDikembalikan = false;
            }
        } else {
            // Untuk peminjaman, ambil dari log_stok berdasarkan no_pj = nomor_peminjaman
            // Format sudah konsisten (dash sudah diganti spasi saat generate), jadi hanya perlu exact match
            $queryJumlahDikembalikan = "SELECT 
                                    ls.varian as produk,
                                    SUM(ls.jumlah) as jumlah_dikembalikan
                                 FROM log_stok ls
                                 WHERE ls.kategori = 'Peminjaman'
                                 AND ls.no_pj = ?
                                 GROUP BY ls.varian";
            $stmtJumlahDikembalikan = mysqli_prepare($db_dc, $queryJumlahDikembalikan);
            if ($stmtJumlahDikembalikan) {
                mysqli_stmt_bind_param($stmtJumlahDikembalikan, "s", $nomor_peminjaman);
                mysqli_stmt_execute($stmtJumlahDikembalikan);
                $resultJumlahDikembalikan = mysqli_stmt_get_result($stmtJumlahDikembalikan);
            } else {
                $resultJumlahDikembalikan = false;
            }
        }
        if ($resultJumlahDikembalikan) {
            while ($rowJml = mysqli_fetch_assoc($resultJumlahDikembalikan)) {
                $produk = $rowJml['produk'];
                $jumlahDikembalikan = floatval($rowJml['jumlah_dikembalikan']);
                if ($jumlahDikembalikan > 0) {
                    $jumlahDikembalikanPerProduk[$produk] = $jumlahDikembalikan;
                    // Untuk pengembalian, jumlah dikembalikan juga digunakan untuk status
                    if ($type === 'pengembalian') {
                        $jumlahDiterimaPerProduk[$produk] = $jumlahDikembalikan;
                    }
                }
            }
            if (isset($stmtJumlahDikembalikan)) {
                mysqli_stmt_close($stmtJumlahDikembalikan);
            }
        }
        
        // Tentukan kategori log_stok berdasarkan type
        $log_stok_kategori = ($type === 'pengembalian') ? 'Pengembalian' : 'Peminjaman';
        $log_stok_prefix = ($type === 'pengembalian') ? 'PB ' : 'PJ ';
        
        // Untuk peminjaman, ambil jumlah terkirim dan diterima dari log_stok (untuk status, bukan untuk jml dikembalikan)
        // Untuk pengembalian, jumlah dikembalikan sudah diambil di atas
        if ($type !== 'pengembalian' && !empty($gudangTujuan) && !empty($gudangAsal) && !empty($nomor_peminjaman) && $headerData['status_peminjaman'] !== 'Draft') {
            // Query untuk jumlah terkirim (dari gudang asal/peminjam, nama_file mengandung nomor_peminjaman)
            // Format sudah konsisten, jadi hanya perlu exact match dan LIKE sederhana
            $pmPrefix = $log_stok_prefix . $nomor_peminjaman;
            $likePattern = $log_stok_prefix . $nomor_peminjaman . '%';
            $queryLogTerkirim = "SELECT 
                            ls.varian as produk,
                            SUM(ls.jumlah) as jumlah_terkirim
                         FROM log_stok ls
                         WHERE ls.kategori = ?
                         AND ls.nama_gudang = ?
                         AND (
                             ls.nama_file = ?
                             OR ls.nama_file = ?
                             OR ls.nama_file LIKE ?
                         )
                         GROUP BY ls.varian";
            
            $stmtLogTerkirim = mysqli_prepare($db_dc, $queryLogTerkirim);
            if ($stmtLogTerkirim) {
                mysqli_stmt_bind_param($stmtLogTerkirim, "sssss", $log_stok_kategori, $gudangAsal, $nomor_peminjaman, $pmPrefix, $likePattern);
                mysqli_stmt_execute($stmtLogTerkirim);
                $resultLogTerkirim = mysqli_stmt_get_result($stmtLogTerkirim);
                
                if ($resultLogTerkirim) {
                    while ($rowLog = mysqli_fetch_assoc($resultLogTerkirim)) {
                        $produk = $rowLog['produk'];
                        $jumlahTerkirimRaw = floatval($rowLog['jumlah_terkirim']);
                        $jumlahTerkirimPerProduk[$produk] = $jumlahTerkirimRaw;
                    }
                    mysqli_stmt_close($stmtLogTerkirim);
                }
            } else {
                error_log("Query Jml Terkirim prepare error: " . mysqli_error($db_dc));
            }
            
            // Query untuk jumlah diterima (di gudang tujuan/dipinjam, nama_file mengandung nomor_peminjaman)
            // Format sudah konsisten, jadi hanya perlu exact match dan LIKE sederhana
            $queryLogDiterima = "SELECT 
                            ls.varian as produk,
                            SUM(ls.jumlah) as jumlah_diterima
                         FROM log_stok ls
                         WHERE ls.kategori = ?
                         AND ls.nama_gudang = ?
                         AND ls.jumlah > 0
                         AND (
                             ls.nama_file = ?
                             OR ls.nama_file = ?
                             OR ls.nama_file LIKE ?
                         )
                         GROUP BY ls.varian";
            
            $stmtLogDiterima = mysqli_prepare($db_dc, $queryLogDiterima);
            if ($stmtLogDiterima) {
                mysqli_stmt_bind_param($stmtLogDiterima, "sssss", $log_stok_kategori, $gudangTujuan, $nomor_peminjaman, $pmPrefix, $likePattern);
                mysqli_stmt_execute($stmtLogDiterima);
                $resultLogDiterima = mysqli_stmt_get_result($stmtLogDiterima);
                
                if ($resultLogDiterima) {
                    while ($rowLog = mysqli_fetch_assoc($resultLogDiterima)) {
                        $produk = $rowLog['produk'];
                        $jumlahDiterima = floatval($rowLog['jumlah_diterima']);
                        if ($jumlahDiterima > 0) {
                            $jumlahDiterimaPerProduk[$produk] = $jumlahDiterima;
                        }
                    }
                    mysqli_stmt_close($stmtLogDiterima);
                }
            } else {
                error_log("Query Jml Diterima prepare error: " . mysqli_error($db_dc));
            }
        }
        
        // Hitung status keseluruhan
        $status_peminjaman = $headerData['status_peminjaman'] ?? '';
        if ($status_peminjaman == 'Draft') {
            $overallStatus = 'draft';
        } else if (!empty($peminjamanData)) {
            $peminjamanProductsMap = [];
            foreach ($peminjamanData as $product) {
                $produk = $product['produk'];
                if (!isset($peminjamanProductsMap[$produk])) {
                    $peminjamanProductsMap[$produk] = 0;
                }
                $peminjamanProductsMap[$produk] += $product['qty'];
            }
            
            // Untuk pengembalian, ambil jumlah dipinjam dari peminjaman_stok berdasarkan nomor_peminjaman yang sama
            // nomor_peminjaman_original adalah nomor peminjaman yang tersimpan di pengembalian_stok
            // Data ini akan digunakan untuk menampilkan di kolom "Jml. Dipinjam" di tabel detail
            $jumlahDipinjamPerProduk = [];
            if ($type === 'pengembalian') {
                $nomorPeminjamanOriginal = isset($headerData['nomor_peminjaman_original']) ? $headerData['nomor_peminjaman_original'] : null;
                
                if (!empty($nomorPeminjamanOriginal)) {
                    // Query mengambil jumlah dipinjam dari peminjaman_stok berdasarkan nomor_peminjaman yang sama
                    $queryJumlahDipinjam = "SELECT 
                                    produk,
                                    SUM(qty) as jumlah_dipinjam
                                 FROM peminjaman_stok
                                 WHERE nomor_peminjaman = ?
                                 GROUP BY produk";
                    
                    $stmtJumlahDipinjam = mysqli_prepare($db_dc, $queryJumlahDipinjam);
                    if ($stmtJumlahDipinjam) {
                        mysqli_stmt_bind_param($stmtJumlahDipinjam, "s", $nomorPeminjamanOriginal);
                        mysqli_stmt_execute($stmtJumlahDipinjam);
                        $resultJumlahDipinjam = mysqli_stmt_get_result($stmtJumlahDipinjam);
                        
                        if ($resultJumlahDipinjam) {
                            while ($rowDipinjam = mysqli_fetch_assoc($resultJumlahDipinjam)) {
                                $produk = $rowDipinjam['produk'];
                                $jumlahDipinjam = floatval($rowDipinjam['jumlah_dipinjam']);
                                if ($jumlahDipinjam > 0) {
                                    $jumlahDipinjamPerProduk[$produk] = $jumlahDipinjam;
                                }
                            }
                            mysqli_stmt_close($stmtJumlahDipinjam);
                        }
                    }
                }
            }
            
            // Bandingkan jumlah dipinjam dengan jumlah dikembalikan untuk menentukan status
            // Untuk pengembalian dan peminjaman, hanya ada 2 status: Belum Selesai dan Selesai
            if ($type === 'pengembalian') {
                // Bandingkan jumlah dipinjam dengan jumlah dikembalikan
                $allMatch = true;
                $hasAnyData = false;
                
                if (!empty($jumlahDipinjamPerProduk)) {
                    foreach ($jumlahDipinjamPerProduk as $produk => $qtyDipinjam) {
                        $hasAnyData = true;
                        $jumlahDikembalikan = isset($jumlahDiterimaPerProduk[$produk]) ? floatval($jumlahDiterimaPerProduk[$produk]) : 0;
                        $qtyDipinjamFloat = floatval($qtyDipinjam);
                        
                        if (abs($jumlahDikembalikan - $qtyDipinjamFloat) >= 0.01) {
                            $allMatch = false;
                            break;
                        }
                    }
                }
                
                if ($hasAnyData && $allMatch) {
                    $overallStatus = 'selesai';
                } else {
                    $overallStatus = 'belum_selesai';
                }
            } else {
                // Untuk peminjaman, hanya ada 2 status: Belum Selesai dan Selesai
                // Selesai jika jml dipinjam = jml dikembalikan untuk semua produk
                // Belum Selesai jika jml dipinjam ≠ jml dikembalikan (atau tidak ada data dikembalikan)
                $allMatch = true;
                $hasAnyData = false;
                
                foreach ($peminjamanProductsMap as $produk => $qty) {
                    $hasAnyData = true;
                    $jumlahDikembalikan = isset($jumlahDikembalikanPerProduk[$produk]) ? floatval($jumlahDikembalikanPerProduk[$produk]) : 0;
                    $qtyFloat = floatval($qty);
                    
                    // Bandingkan jumlah dipinjam dengan jumlah dikembalikan
                    if (abs($jumlahDikembalikan - $qtyFloat) >= 0.01) {
                        $allMatch = false;
                        break;
                    }
                }
                
                if ($hasAnyData && $allMatch) {
                    $overallStatus = 'selesai';
                } else {
                    $overallStatus = 'belum_selesai';
                }
            }
        }
        
        // Grouping produk
        $groupedProducts = [];
        foreach ($peminjamanData as $product) {
            $key = $product['produk'];
            $jumlahTerkirim = isset($jumlahTerkirimPerProduk[$product['produk']]) ? $jumlahTerkirimPerProduk[$product['produk']] : null;
            // Untuk kolom Jml. Dikembalikan, gunakan data dari pengembalian_stok
            $jumlahDiterima = isset($jumlahDikembalikanPerProduk[$product['produk']]) ? $jumlahDikembalikanPerProduk[$product['produk']] : null;
            // Untuk pengembalian, jumlah dipinjam diambil dari peminjaman_stok; untuk peminjaman, gunakan qty
            $jumlahDipinjam = null;
            if ($type === 'pengembalian') {
                $jumlahDipinjam = isset($jumlahDipinjamPerProduk[$product['produk']]) ? $jumlahDipinjamPerProduk[$product['produk']] : null;
            } else {
                // Untuk peminjaman, jumlah dipinjam = qty dari data peminjaman
                $jumlahDipinjam = $product['qty'];
            }
            
            if (!isset($groupedProducts[$key])) {
                $groupedProducts[$key] = [
                    'produk' => $product['produk'],
                    'gudang' => [$headerData['gudang_asal'] ?? ''],
                    'qty' => $product['qty'],
                    'jumlah_dipinjam' => $jumlahDipinjam,
                    'jumlah_terkirim' => $jumlahTerkirim,
                    'jumlah_diterima' => $jumlahDiterima,
                    'has_penyesuaian' => $product['is_penyesuaian'] ?? false
                ];
            } else {
                if (!in_array($headerData['gudang_asal'] ?? '', $groupedProducts[$key]['gudang'])) {
                    $groupedProducts[$key]['gudang'][] = $headerData['gudang_asal'] ?? '';
                }
                $groupedProducts[$key]['qty'] += $product['qty'];
                // Untuk jumlah dipinjam, jika belum ada atau untuk pengembalian, update dari jumlahDipinjamPerProduk
                if ($type === 'pengembalian' && isset($jumlahDipinjamPerProduk[$product['produk']])) {
                    $groupedProducts[$key]['jumlah_dipinjam'] = $jumlahDipinjamPerProduk[$product['produk']];
                } elseif ($type !== 'pengembalian') {
                    // Untuk peminjaman, jumlah dipinjam = total qty
                    $groupedProducts[$key]['jumlah_dipinjam'] = $groupedProducts[$key]['qty'];
                }
                if ($jumlahTerkirim !== null) {
                    $groupedProducts[$key]['jumlah_terkirim'] = $jumlahTerkirim;
                }
                if ($jumlahDiterima !== null) {
                    $groupedProducts[$key]['jumlah_diterima'] = $jumlahDiterima;
                }
                if ($product['is_penyesuaian'] ?? false) {
                    $groupedProducts[$key]['has_penyesuaian'] = true;
                }
            }
        }
        
        foreach ($groupedProducts as &$product) {
            if (is_array($product['gudang'])) {
                $product['gudang'] = implode(', ', $product['gudang']);
            }
        }
        unset($product);
        
        $allProducts = array_values($groupedProducts);
        
        // Query untuk mengambil data company identity dari base_entitas
        // Untuk pengembalian: menggunakan entitas_peminjam (entitas pengembali)
        // Untuk peminjaman: menggunakan entitas_peminjam (entitas peminjam)
        $companyData = null;
        $entitasForCompany = $headerData['entitas_peminjam'] ?? '';
        
        if (!empty($entitasForCompany)) {
            $entitasEscaped = mysqli_real_escape_string($db_dc, $entitasForCompany);
            $queryCompany = "SELECT 
                                be.nama as nama_company,
                                be.alamat as alamat_company,
                                be.no_hp as telp_company,
                                be.email as email_company
                             FROM base_entitas be
                             WHERE be.inisial = ? OR be.nama = ?
                             LIMIT 1";
            
            $stmtCompany = mysqli_prepare($db_dc, $queryCompany);
            if ($stmtCompany) {
                mysqli_stmt_bind_param($stmtCompany, "ss", $entitasEscaped, $entitasEscaped);
                mysqli_stmt_execute($stmtCompany);
                $resultCompany = mysqli_stmt_get_result($stmtCompany);
                if ($rowCompany = mysqli_fetch_assoc($resultCompany)) {
                    $companyData = [
                        'nama' => $rowCompany['nama_company'] ?? '-',
                        'alamat' => $rowCompany['alamat_company'] ?? '-',
                        'telp' => $rowCompany['telp_company'] ?? '-',
                        'email' => $rowCompany['email_company'] ?? '-'
                    ];
                }
                mysqli_stmt_close($stmtCompany);
            }
        }
        
        // Format tanggal
        $tanggalFormatted = $headerData['tanggal_peminjaman'] ? date('d/m/Y', strtotime($headerData['tanggal_peminjaman'])) : '-';
        $nomor_peminjaman_display = $headerData['nomor_peminjaman'] ?: '-';
        $nomor_peminjaman_original_display = ($type === 'pengembalian' && isset($headerData['nomor_peminjaman_original'])) 
            ? $headerData['nomor_peminjaman_original'] 
            : '-';
        
        // Mulai output HTML
        // Tambahkan hidden input untuk menentukan apakah tombol download SJ harus ditampilkan
        ?>
        <input type="hidden" id="viewType" value="<?= htmlspecialchars($type) ?>">
        <input type="hidden" id="viewNomorPeminjaman" value="<?= htmlspecialchars($nomor_peminjaman_display) ?>">
        
<div class="row mb-4">
    <div class="col-md-6">
        <table class="table border-0" border="0">
            <?php if ($type === 'pengembalian'): ?>
            <tr class="text-left">
                <th width="40%">Tanggal Pengembalian</th>
                <td width="5%" class="text-center">:</td>
                <td><?= htmlspecialchars($tanggalFormatted) ?></td>
            </tr>
            <tr class="text-left">
                <th width="40%">Nomor Peminjaman</th>
                <td width="5%" class="text-center">:</td>
                <td><?= htmlspecialchars($nomor_peminjaman_original_display) ?></td>
            </tr>
            <tr class="text-left">
                <th width="40%">Nomor Pengembalian</th>
                <td width="5%" class="text-center">:</td>
                <td><?= htmlspecialchars($nomor_peminjaman_display) ?></td>
            </tr>
            <tr class="text-left">
                <th width="40%">Entitas Pengembali</th>
                <td width="5%" class="text-center">:</td>
                <td><?= htmlspecialchars($headerData['entitas_peminjam'] ?? '-') ?></td>
            </tr>
            <tr class="text-left">
                <th width="40%">Entitas Penerima</th>
                <td width="5%" class="text-center">:</td>
                <td><?= htmlspecialchars($headerData['entitas_dipinjam'] ?? '-') ?></td>
            </tr>
            <tr class="text-left">
                <th width="40%">Gudang Pengembali</th>
                <td width="5%" class="text-center">:</td>
                <td><?= htmlspecialchars($headerData['gudang_asal']) ?></td>
            </tr>
            <tr class="text-left">
                <th width="40%">Gudang Penerima</th>
                <td width="5%" class="text-center">:</td>
                <td><?= htmlspecialchars($headerData['gudang_tujuan']) ?></td>
            </tr>
            <?php else: ?>
            <tr class="text-left">
                <th width="40%">Tanggal Peminjaman</th>
                <td width="5%" class="text-center">:</td>
                <td><?= htmlspecialchars($tanggalFormatted) ?></td>
            </tr>
            <tr class="text-left">
                <th width="40%">Nomor Peminjaman</th>
                <td width="5%" class="text-center">:</td>
                <td><?= htmlspecialchars($nomor_peminjaman_display) ?></td>
            </tr>
            <tr class="text-left">
                <th width="40%">Entitas Peminjam</th>
                <td width="5%" class="text-center">:</td>
                <td><?= htmlspecialchars($headerData['entitas_peminjam'] ?? '-') ?></td>
            </tr>
            <tr class="text-left">
                <th width="40%">Entitas Dipinjam</th>
                <td width="5%" class="text-center">:</td>
                <td><?= htmlspecialchars($headerData['entitas_dipinjam'] ?? '-') ?></td>
            </tr>
            <tr class="text-left">
                <th width="40%">Gudang Peminjam</th>
                <td width="5%" class="text-center">:</td>
                <td><?= htmlspecialchars($headerData['gudang_asal']) ?></td>
            </tr>
            <tr class="text-left">
                <th width="40%">Gudang Dipinjam</th>
                <td width="5%" class="text-center">:</td>
                <td><?= htmlspecialchars($headerData['gudang_tujuan']) ?></td>
            </tr>
            <?php endif; ?>
            <tr class="text-left">
                <th width="40%">Status</th>
                <td width="5%" class="text-center">:</td>
                <td>
                    <?php
                    // Untuk peminjaman dan pengembalian, hanya ada 2 status: Belum Selesai dan Selesai
                    if ($overallStatus == 'selesai') {
                        echo '<span class="badge badge-success">Selesai</span>';
                    } else {
                        echo '<span class="badge badge-danger">Belum Selesai</span>';
                    }
                    ?>
                </td>
            </tr>
        </table>
    </div>
    
    <!-- Kolom Kanan: Company Identity -->
    <div class="col-md-6">
        <div class="card" style="border: none; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div class="card-body">
                <h6 class="font-weight-bold mb-3">
                    <i class="fas fa-building mr-2"></i>Company Identity
                </h6>
                <div class="mb-2 d-flex align-items-start">
                    <i class="fas fa-user text-muted mr-2 mt-1" style="width: 20px;"></i>
                    <div>
                        <strong>Name:</strong> <span><?= htmlspecialchars($companyData['nama'] ?? '-') ?></span>
                    </div>
                </div>
                <div class="mb-2 d-flex align-items-start">
                    <i class="fas fa-map-marker-alt text-muted mr-2 mt-1" style="width: 20px;"></i>
                    <div>
                        <strong>Address:</strong> <span><?= htmlspecialchars($companyData['alamat'] ?? '-') ?></span>
                    </div>
                </div>
                <div class="mb-2 d-flex align-items-start">
                    <i class="fas fa-phone text-muted mr-2 mt-1" style="width: 20px;"></i>
                    <div>
                        <strong>Phone Number:</strong> <span><?= htmlspecialchars($companyData['telp'] ?? '-') ?></span>
                    </div>
                </div>
                <div class="mb-2 d-flex align-items-start">
                    <i class="fas fa-envelope text-muted mr-2 mt-1" style="width: 20px;"></i>
                    <div>
                        <strong>Email Address:</strong> 
                        <?php if (!empty($companyData['email']) && $companyData['email'] != '-'): ?>
                            <a href="mailto:<?= htmlspecialchars($companyData['email']) ?>" style="color: #007bff;"><?= htmlspecialchars($companyData['email']) ?></a>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabel Read-only dengan Jml Dipinjam dan Dikembalikan -->
<table class="table table-striped table-bordered table-hover">
    <thead>
        <tr class="text-center">
            <th width="5%">No</th>
            <th width="15%">Gudang</th>
            <th width="25%">Produk</th>
            <th width="15%" class="text-nowrap">Jml. Dipinjam</th>
            <th width="15%" class="text-nowrap">Jml. Dikembalikan</th>
            <th width="15%">Status</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $noList = 1;
        $total_jumlah_peminjaman = 0;
        $total_jumlah_diterima = 0;
        
        foreach ($allProducts as $product) {
            // Untuk pengembalian, jumlah dipinjam dari peminjaman_stok; untuk peminjaman, dari qty
            $jumlahDipinjamDisplay = ($type === 'pengembalian' && isset($product['jumlah_dipinjam']) && $product['jumlah_dipinjam'] !== null) 
                ? $product['jumlah_dipinjam'] 
                : ($type === 'pengembalian' ? 0 : $product['qty']);
            
            $total_jumlah_peminjaman += $jumlahDipinjamDisplay;
            
            if ($product['jumlah_diterima'] !== null) {
                $total_jumlah_diterima += $product['jumlah_diterima'];
            }
            
            $statusBadge = '';
            
            // Untuk pengembalian dan peminjaman, hanya ada 2 status: Belum Selesai dan Selesai
            // Selesai jika jml dipinjam = jml dikembalikan
            // Belum Selesai jika jml dipinjam ≠ jml dikembalikan (atau tidak ada data dikembalikan)
            if ($type === 'pengembalian') {
                $jumlahDipinjam = floatval($jumlahDipinjamDisplay);
                $jumlahDikembalikan = $product['jumlah_diterima'] !== null ? floatval($product['jumlah_diterima']) : 0;
                
                if (abs($jumlahDikembalikan - $jumlahDipinjam) < 0.01) {
                    $statusBadge = '<span class="badge badge-success"><i class="far fa-check-circle"></i> Selesai</span>';
                } else {
                    $statusBadge = '<span class="badge badge-danger"><i class="fas fa-times-circle"></i> Belum Selesai</span>';
                }
            } else {
                // Untuk peminjaman, hanya ada 2 status: Belum Selesai dan Selesai
                // Selesai jika jml dipinjam = jml dikembalikan
                // Belum Selesai jika jml dipinjam ≠ jml dikembalikan (atau tidak ada data dikembalikan)
                $jumlahDikembalikan = $product['jumlah_diterima'] !== null ? floatval($product['jumlah_diterima']) : 0;
                $jumlahDipinjam = floatval($product['qty']);
                
                if (abs($jumlahDikembalikan - $jumlahDipinjam) < 0.01) {
                    $statusBadge = '<span class="badge badge-success"><i class="far fa-check-circle"></i> Selesai</span>';
                } else {
                    $statusBadge = '<span class="badge badge-danger"><i class="fas fa-times-circle"></i> Belum Selesai</span>';
                }
            }
        ?>
        <tr>
            <td><?= $noList ?></td>
            <td class="text-left"><?= htmlspecialchars($product['gudang']) ?></td>
            <td class="text-left">
                <?= htmlspecialchars($product['produk']) ?>
                <?php if (!empty($product['has_penyesuaian'])): ?>
                    <br class="my-0 mb-1" />
                    <small class="badge bg-warning"><i class="fas fa-exclamation-circle"></i> Penyesuaian</small>
                <?php endif ?>
            </td>
            <td class="text-center">
                <?php 
                if ($type === 'pengembalian') {
                    // Untuk pengembalian, tampilkan jumlah dipinjam dari peminjaman_stok
                    if (isset($product['jumlah_dipinjam']) && $product['jumlah_dipinjam'] !== null) {
                        echo number_format($product['jumlah_dipinjam'], 0);
                    } else {
                        echo '-';
                    }
                } else {
                    // Untuk peminjaman, tampilkan qty
                    echo number_format($product['qty'], 0);
                }
                ?>
            </td>
            <td class="text-center">
                <?php 
                if ($product['jumlah_diterima'] !== null) {
                    echo number_format($product['jumlah_diterima'], 0);
                } else {
                    echo '-';
                }
                ?>
            </td>
            <td class="text-center"><?= $statusBadge ?></td>
        </tr>
        <?php 
            $noList++;
        } 
        ?>
    </tbody>
    <tfoot>
        <tr>
            <th colspan="3" class="text-center">Total</th>
            <th class="text-center"><?= number_format($total_jumlah_peminjaman, 0) ?></th>
            <th class="text-center"><?= $total_jumlah_diterima != 0 ? number_format($total_jumlah_diterima, 0) : '-' ?></th>
            <th></th>
        </tr>
    </tfoot>
</table>

<!-- Form Editable (Accordion) - Hanya tampilkan untuk peminjaman, bukan untuk pengembalian -->
<?php if ($type === 'peminjaman'): ?>
<div class="accordion col-12 mt-4" id="accordionDetailPeminjaman">
    <div class="card">
        <div class="card-header p-0 border-0" id="headingDetailPeminjaman">
            <button class="btn btn-info btn-block text-left p-2" type="button" data-toggle="collapse" data-target="#collapseDetailPeminjaman" aria-expanded="true" aria-controls="collapseDetailPeminjaman">
                <div class="d-flex justify-content-between">
                    <h4 class="mb-0 my-auto"><i class="fas fa-file-alt"></i> Detail Data Peminjaman</h4>
                    <i class="fas fa-chevron-down my-auto"></i>
                </div>
            </button>
        </div>

        <div id="collapseDetailPeminjaman" class="collapse show" aria-labelledby="headingDetailPeminjaman" data-parent="#accordionDetailPeminjaman">
            <div class="card-body">
                <div class="text-left mb-3">
                    <button type="button" class="btn btn-sm btn-success addRowPeminjaman" data-nomor-peminjaman="<?= htmlspecialchars($nomor_peminjaman, ENT_QUOTES) ?>">
                        <i class="fas fa-plus-circle"></i> Tambah Rincian
                    </button>
                </div>

                <form class="formEditPeminjaman" data-nomor-peminjaman="<?= htmlspecialchars($nomor_peminjaman, ENT_QUOTES) ?>" method="post">
                    <input type="hidden" name="nomor_peminjaman" value="<?= htmlspecialchars($nomor_peminjaman) ?>">
                    <table class="datatables table table-striped table-bordered table-hover">
                        <thead>
                            <tr class="text-center">
                                <th width="5%">No</th>
                                <th width="25%">Gudang</th>
                                <th width="50%">Produk</th>
                                <th width="15%">Jumlah</th>
                                <th width="5%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="tbodyListDataPeminjaman" data-nomor-peminjaman="<?= htmlspecialchars($nomor_peminjaman, ENT_QUOTES) ?>" data-gudang-asal="<?= htmlspecialchars($headerData['gudang_asal'] ?? '', ENT_QUOTES) ?>">
                        <?php
                        $noDetailPeminjaman = 1;
                        // Query untuk mengambil semua detail peminjaman/pengembalian
                        $queryDetailPeminjaman = "SELECT 
                                                    m.id,
                                                    m.gudang_asal as gudang,
                                                    m.produk,
                                                    m.qty,
                                                    m.catatan
                                                  FROM $table_name m
                                                  WHERE m.$nomor_field = ?
                                                  ORDER BY 
                                                    CASE WHEN m.catatan IS NULL OR m.catatan = '' OR m.catatan NOT LIKE '%Penyesuaian%' THEN 0 ELSE 1 END,
                                                    m.id ASC";
                        $stmtDetailPeminjaman = mysqli_prepare($db_dc, $queryDetailPeminjaman);
                        if ($stmtDetailPeminjaman) {
                            mysqli_stmt_bind_param($stmtDetailPeminjaman, "s", $nomor_peminjaman);
                            mysqli_stmt_execute($stmtDetailPeminjaman);
                            $resultDetailPeminjaman = mysqli_stmt_get_result($stmtDetailPeminjaman);
                            
                            while ($rowDetail = mysqli_fetch_assoc($resultDetailPeminjaman)) {
                                $isPenyesuaianDetail = !empty($rowDetail['catatan']) && stripos($rowDetail['catatan'], 'Penyesuaian') !== false;
                                $rowClassDetail = $isPenyesuaianDetail ? 'table-warning' : '';
                                $isDraft = $headerData['status_peminjaman'] === 'Draft';
                                $produkDisabledAttr = $isDraft ? '' : 'disabled';
                                $jumlahReadonlyAttr = $isDraft ? '' : 'readonly';
                                $deleteDisabledAttr = ''; // Tombol hapus selalu enabled
                                
                                // Query untuk mendapatkan opsi gudang dan produk
                                // (Ini akan diisi dengan JavaScript, tapi kita buat struktur HTML dulu)
                                ?>
                                <tr class="<?= $rowClassDetail ?> data-existing" data-id="<?= $rowDetail['id'] ?>">
                                    <td class="text-center"><?= $noDetailPeminjaman ?></td>
                                    <td>
                                        <select name="in_gudang[]" class="form-control select-gudang" <?= $produkDisabledAttr ?>>
                                            <option value="<?= htmlspecialchars($rowDetail['gudang']) ?>" selected><?= htmlspecialchars($rowDetail['gudang']) ?></option>
                                        </select>
                                    </td>
                                    <td>
                                        <select name="in_produk[]" class="form-control select-produk" <?= $produkDisabledAttr ?>>
                                            <option value="<?= htmlspecialchars($rowDetail['produk']) ?>" selected><?= htmlspecialchars($rowDetail['produk']) ?></option>
                                        </select>
                                    </td>
                                    <td class="text-center">
                                        <input type="number" class="form-control" name="in_jumlah[]" value="<?= $rowDetail['qty'] ?>" <?= $jumlahReadonlyAttr ?>>
                                        <input type="hidden" name="in_id[]" value="<?= $rowDetail['id'] ?>">
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-danger btnDeleteRowPeminjaman" <?= $deleteDisabledAttr ?>>
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php
                                $noDetailPeminjaman++;
                            }
                            mysqli_stmt_close($stmtDetailPeminjaman);
                        }
                        ?>
                        </tbody>
                    </table>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
// Hanya tampilkan table pengembalian jika type adalah peminjaman (bukan pengembalian)
if ($type === 'peminjaman' && !empty($nomor_peminjaman)) {
    // Query untuk mengambil data pengembalian berdasarkan nomor peminjaman
    // Hanya ambil data yang memiliki nomor_pengembalian (tidak NULL) untuk menghindari data draft
    $queryPengembalian = "SELECT 
                            ps.nomor_pengembalian,
                            ps.tanggal_pengembalian,
                            ps.status_pengembalian,
                            ps.entitas_peminjam,
                            ps.entitas_dipinjam,
                            ps.gudang_asal,
                            ps.gudang_tujuan,
                            COUNT(DISTINCT ps.produk) as jumlah_item,
                            SUM(ps.qty) as total_qty
                         FROM pengembalian_stok ps
                         WHERE ps.nomor_peminjaman = ?
                         AND ps.nomor_pengembalian IS NOT NULL
                         AND ps.nomor_pengembalian != ''
                         AND TRIM(ps.nomor_pengembalian) != ''
                         GROUP BY ps.nomor_pengembalian, ps.tanggal_pengembalian, ps.status_pengembalian, 
                                  ps.entitas_peminjam, ps.entitas_dipinjam, ps.gudang_asal, ps.gudang_tujuan
                         ORDER BY ps.tanggal_pengembalian DESC, ps.nomor_pengembalian DESC";
    
    $stmtPengembalian = mysqli_prepare($db_dc, $queryPengembalian);
    $pengembalianData = [];
    
    if ($stmtPengembalian) {
        mysqli_stmt_bind_param($stmtPengembalian, "s", $nomor_peminjaman);
        mysqli_stmt_execute($stmtPengembalian);
        $resultPengembalian = mysqli_stmt_get_result($stmtPengembalian);
        
        if ($resultPengembalian) {
            while ($rowPengembalian = mysqli_fetch_assoc($resultPengembalian)) {
            $pengembalianData[] = [
                'nomor_pengembalian' => $rowPengembalian['nomor_pengembalian'] ?? '-',
                'tanggal_pengembalian' => $rowPengembalian['tanggal_pengembalian'] ?? null,
                'status_pengembalian' => $rowPengembalian['status_pengembalian'] ?? '-',
                'entitas_peminjam' => $rowPengembalian['entitas_peminjam'] ?? '-',
                'entitas_dipinjam' => $rowPengembalian['entitas_dipinjam'] ?? '-',
                'gudang_asal' => $rowPengembalian['gudang_asal'] ?? '-',
                'gudang_tujuan' => $rowPengembalian['gudang_tujuan'] ?? '-',
                'jumlah_item' => intval($rowPengembalian['jumlah_item'] ?? 0),
                'total_qty' => floatval($rowPengembalian['total_qty'] ?? 0)
            ];
            }
            mysqli_stmt_close($stmtPengembalian);
        }
    }
    ?>
    
    <!-- Table Data Pengembalian -->
    <div class="col-12 mt-4">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-undo-alt mr-2"></i>Data Pengembalian</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-striped mb-0">
                        <thead class="thead-light">
                            <tr class="text-center">
                                <th width="5%">No</th>
                                <th width="12%">Tanggal</th>
                                <th width="15%">Nomor Pengembalian</th>
                                <th width="12%">Entitas Pengembali</th>
                                <th width="12%">Entitas Penerima</th>
                                <th width="12%">Gudang Pengembali</th>
                                <th width="12%">Gudang Penerima</th>
                                <th width="8%">Jumlah Item</th>
                                <th width="10%">Total Qty</th>
                                <th width="10%">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if (empty($pengembalianData)) {
                                ?>
                                <tr>
                                    <td colspan="10" class="text-center text-muted">
                                        <i class="fas fa-info-circle mr-2"></i>Belum ada data pengembalian
                                    </td>
                                </tr>
                                <?php
                            } else {
                                $noPengembalian = 1;
                                foreach ($pengembalianData as $pengembalian) {
                                    $tanggalFormatted = $pengembalian['tanggal_pengembalian'] 
                                        ? date('d/m/Y', strtotime($pengembalian['tanggal_pengembalian'])) 
                                        : '-';
                                    
                                    $statusBadge = '';
                                    if ($pengembalian['status_pengembalian'] === 'Draft') {
                                        $statusBadge = '<span class="badge badge-warning">Draft</span>';
                                    } elseif ($pengembalian['status_pengembalian'] === 'Selesai') {
                                        $statusBadge = '<span class="badge badge-success">Selesai</span>';
                                    } elseif ($pengembalian['status_pengembalian'] === 'Final') {
                                        $statusBadge = '<span class="badge badge-primary">Final</span>';
                                    } else {
                                        $statusBadge = '<span class="badge badge-secondary">' . htmlspecialchars($pengembalian['status_pengembalian']) . '</span>';
                                    }
                                    ?>
                                    <tr>
                                        <td class="text-center"><?= $noPengembalian ?></td>
                                        <td class="text-center"><?= htmlspecialchars($tanggalFormatted) ?></td>
                                        <td class="text-center">
                                            <strong><?= htmlspecialchars($pengembalian['nomor_pengembalian']) ?></strong>
                                        </td>
                                        <td class="text-left"><?= htmlspecialchars($pengembalian['entitas_peminjam']) ?></td>
                                        <td class="text-left"><?= htmlspecialchars($pengembalian['entitas_dipinjam']) ?></td>
                                        <td class="text-left"><?= htmlspecialchars($pengembalian['gudang_asal']) ?></td>
                                        <td class="text-left"><?= htmlspecialchars($pengembalian['gudang_tujuan']) ?></td>
                                        <td class="text-center"><?= number_format($pengembalian['jumlah_item'], 0) ?></td>
                                        <td class="text-center"><strong><?= number_format($pengembalian['total_qty'], 0) ?></strong></td>
                                        <td class="text-center"><?= $statusBadge ?></td>
                                    </tr>
                                    <?php
                                    $noPengembalian++;
                                }
                            }
                            ?>
                        </tbody>
                        <?php if (!empty($pengembalianData)): ?>
                        <tfoot>
                            <tr class="font-weight-bold">
                                <td colspan="7" class="text-right">Total</td>
                                <td class="text-center"><?= count($pengembalianData) ?></td>
                                <td class="text-center">
                                    <?= number_format(array_sum(array_column($pengembalianData, 'total_qty')), 0) ?>
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php
    }
    } catch (Exception $e) {
        ob_clean();
        header('Content-Type: text/html; charset=utf-8');
        echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
    ob_end_flush();
}