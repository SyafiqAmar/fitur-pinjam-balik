<?php
/**
 * Helper functions untuk query peminjaman yang dioptimasi
 * File ini memecah query kompleks menjadi query yang lebih efisien
 */

/**
 * Get peminjaman products grouped by nomor_peminjaman
 * Query ini lebih efisien karena hanya mengambil data yang diperlukan
 */
function getPeminjamanProductsBatch($db_dc, $nomorPeminjamanList) {
    if (empty($nomorPeminjamanList)) {
        return [];
    }
    
    $placeholders = str_repeat('?,', count($nomorPeminjamanList) - 1) . '?';
    $query = "SELECT 
                m.nomor_peminjaman,
                m.produk,
                SUM(m.qty) as qty
              FROM peminjaman_stok m
              WHERE m.nomor_peminjaman IN ($placeholders)
              GROUP BY m.nomor_peminjaman, m.produk";
    
    $stmt = mysqli_prepare($db_dc, $query);
    if (!$stmt) {
        return [];
    }
    
    $types = str_repeat('s', count($nomorPeminjamanList));
    mysqli_stmt_bind_param($stmt, $types, ...$nomorPeminjamanList);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $map = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $nomor = $row['nomor_peminjaman'];
        $produk = $row['produk'];
        $qty = intval($row['qty']);
        
        if (!isset($map[$nomor])) {
            $map[$nomor] = [];
        }
        $map[$nomor][$produk] = $qty;
    }
    
    mysqli_stmt_close($stmt);
    return $map;
}

/**
 * Get gudang dipinjam untuk setiap nomor_peminjaman
 * Optimasi: gunakan subquery dengan MIN(id) untuk performa lebih baik
 */
function getGudangTujuanPeminjamanBatch($db_dc, $nomorPeminjamanList) {
    if (empty($nomorPeminjamanList)) {
        return [];
    }
    
    $placeholders = str_repeat('?,', count($nomorPeminjamanList) - 1) . '?';
    $query = "SELECT 
                nomor_peminjaman,
                MIN(gudang_tujuan) as gudang_tujuan
              FROM peminjaman_stok
              WHERE nomor_peminjaman IN ($placeholders)
              GROUP BY nomor_peminjaman";
    
    $stmt = mysqli_prepare($db_dc, $query);
    if (!$stmt) {
        return [];
    }
    
    $types = str_repeat('s', count($nomorPeminjamanList));
    mysqli_stmt_bind_param($stmt, $types, ...$nomorPeminjamanList);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $map = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $map[$row['nomor_peminjaman']] = $row['gudang_tujuan'];
    }
    
    mysqli_stmt_close($stmt);
    return $map;
}

/**
 * Get gudang peminjam untuk setiap nomor_peminjaman
 */
function getGudangAsalPeminjamanBatch($db_dc, $nomorPeminjamanList) {
    if (empty($nomorPeminjamanList)) {
        return [];
    }
    
    $placeholders = str_repeat('?,', count($nomorPeminjamanList) - 1) . '?';
    $query = "SELECT 
                nomor_peminjaman,
                MIN(gudang_asal) as gudang_asal
              FROM peminjaman_stok
              WHERE nomor_peminjaman IN ($placeholders)
              GROUP BY nomor_peminjaman";
    
    $stmt = mysqli_prepare($db_dc, $query);
    if (!$stmt) {
        return [];
    }
    
    $types = str_repeat('s', count($nomorPeminjamanList));
    mysqli_stmt_bind_param($stmt, $types, ...$nomorPeminjamanList);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $map = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $map[$row['nomor_peminjaman']] = $row['gudang_asal'];
    }
    
    mysqli_stmt_close($stmt);
    return $map;
}

/**
 * Build filter conditions untuk query peminjaman
 */
function buildFilterConditionsPeminjaman($db_dc, $entitas_peminjam, $entitas_dipinjam, $gudang_asal, $gudang_tujuan, $search, $includeSearch = false) {
    $conditions = [];
    
    if (!empty($entitas_peminjam)) {
        $entitas_peminjam_escaped = mysqli_real_escape_string($db_dc, $entitas_peminjam);
        $conditions[] = "pg.entitas_peminjam = '$entitas_peminjam_escaped'";
    }
    
    if (!empty($entitas_dipinjam)) {
        $entitas_dipinjam_escaped = mysqli_real_escape_string($db_dc, $entitas_dipinjam);
        $conditions[] = "pg.entitas_dipinjam = '$entitas_dipinjam_escaped'";
    }
    
    if (!empty($gudang_asal)) {
        $gudang_asal_escaped = mysqli_real_escape_string($db_dc, $gudang_asal);
        $conditions[] = "pg.gudang_asal = '$gudang_asal_escaped'";
    }
    
    if (!empty($gudang_tujuan)) {
        $gudang_tujuan_escaped = mysqli_real_escape_string($db_dc, $gudang_tujuan);
        $conditions[] = "pg.gudang_tujuan LIKE '%$gudang_tujuan_escaped%'";
    }
    
    if ($includeSearch && !empty($search)) {
        $search_escaped = mysqli_real_escape_string($db_dc, $search);
        $conditions[] = "(
            pg.nomor_peminjaman LIKE '%$search_escaped%' OR
            pg.entitas_peminjam LIKE '%$search_escaped%' OR
            pg.entitas_dipinjam LIKE '%$search_escaped%' OR
            pg.gudang_asal LIKE '%$search_escaped%' OR
            pg.gudang_tujuan LIKE '%$search_escaped%'
        )";
    }
    
    return $conditions;
}

/**
 * Build filter conditions untuk count query (menggunakan alias m.)
 */
function buildFilterConditionsPeminjamanForCount($db_dc, $entitas_peminjam, $entitas_dipinjam, $gudang_asal, $gudang_tujuan, $search, $includeSearch = false) {
    $conditions = [];
    
    if (!empty($entitas_peminjam)) {
        $entitas_peminjam_escaped = mysqli_real_escape_string($db_dc, $entitas_peminjam);
        $conditions[] = "m.entitas_peminjam = '$entitas_peminjam_escaped'";
    }
    
    if (!empty($entitas_dipinjam)) {
        $entitas_dipinjam_escaped = mysqli_real_escape_string($db_dc, $entitas_dipinjam);
        $conditions[] = "m.entitas_dipinjam = '$entitas_dipinjam_escaped'";
    }
    
    if (!empty($gudang_asal)) {
        $gudang_asal_escaped = mysqli_real_escape_string($db_dc, $gudang_asal);
        $conditions[] = "m.gudang_asal = '$gudang_asal_escaped'";
    }
    
    if (!empty($gudang_tujuan)) {
        $gudang_tujuan_escaped = mysqli_real_escape_string($db_dc, $gudang_tujuan);
        $conditions[] = "m.gudang_tujuan LIKE '%$gudang_tujuan_escaped%'";
    }
    
    if ($includeSearch && !empty($search)) {
        $search_escaped = mysqli_real_escape_string($db_dc, $search);
        $conditions[] = "(
            m.nomor_peminjaman LIKE '%$search_escaped%' OR
            m.entitas_peminjam LIKE '%$search_escaped%' OR
            m.entitas_dipinjam LIKE '%$search_escaped%' OR
            m.gudang_asal LIKE '%$search_escaped%' OR
            m.gudang_tujuan LIKE '%$search_escaped%'
        )";
    }
    
    return $conditions;
}

/**
 * Count query untuk DataTables peminjaman - mengikuti pola mutasi
 */
function getCountQueryPeminjaman($db_dc, $start_date, $end_date, $entitas_peminjam, $entitas_dipinjam, $gudang_asal, $gudang_tujuan, $search, $includeSearch = false) {
    $conditions = buildFilterConditionsPeminjamanForCount($db_dc, $entitas_peminjam, $entitas_dipinjam, $gudang_asal, $gudang_tujuan, $search, $includeSearch);
    
    // Escape dates
    $start_date = mysqli_real_escape_string($db_dc, $start_date);
    $end_date = mysqli_real_escape_string($db_dc, $end_date);
    
    // Base WHERE clause - mengikuti pola mutasi
    $whereClause = "((m.tanggal_peminjaman >= '$start_date 00:00:00' 
                    AND m.tanggal_peminjaman < DATE_ADD('$end_date', INTERVAL 1 DAY))
                    OR (m.tanggal_peminjaman IS NULL AND m.status_peminjaman = 'Draft'))";
    
    if (!empty($conditions)) {
        $whereClause = "(" . implode(" AND ", $conditions) . ") AND (" . $whereClause . ")";
    }
    
    $query = "SELECT COUNT(*) as total 
              FROM (
                  SELECT DISTINCT 
                      CASE WHEN m.nomor_peminjaman IS NULL OR m.nomor_peminjaman = '' OR TRIM(m.nomor_peminjaman) = '' 
                          THEN CONCAT(COALESCE(m.entitas_peminjam, ''), '|', m.gudang_asal, '|', m.gudang_tujuan, '|', COALESCE(DATE(m.tanggal_peminjaman), 'NULL'), '|', m.status_peminjaman)
                          ELSE m.nomor_peminjaman 
                      END as nomor_peminjaman
                  FROM peminjaman_stok m
                  WHERE $whereClause
              ) as distinct_peminjaman";
    
    return $query;
}

/**
 * Get log_stok data untuk peminjaman (match dan mismatch dalam 1 query)
 * Optimasi: gabungkan match dan mismatch dalam 1 query dengan CASE
 * Menggunakan subquery untuk efisiensi yang lebih baik
 */
function getLogStokPeminjamanBatch($db_dc, $nomorPeminjamanList, $gudangTujuanMap) {
    if (empty($nomorPeminjamanList) || empty($gudangTujuanMap)) {
        return [[], []];
    }
    
    // Build list nomor_peminjaman yang valid (ada gudang_tujuan-nya)
    $validNomorPeminjaman = [];
    foreach ($nomorPeminjamanList as $nomor) {
        if (isset($gudangTujuanMap[$nomor])) {
            $validNomorPeminjaman[] = $nomor;
        }
    }
    
    if (empty($validNomorPeminjaman)) {
        return [[], []];
    }
    
    // Escape nomor_peminjaman untuk digunakan dalam query
    $escapedNomorPeminjaman = array_map(function($n) use ($db_dc) {
        return "'" . mysqli_real_escape_string($db_dc, $n) . "'";
    }, $validNomorPeminjaman);
    $nomorPeminjamanString = implode(',', $escapedNomorPeminjaman);
    
    // Optimasi: prioritaskan exact match dengan normalisasi, lalu fallback ke exact match
    $query = "
        SELECT 
            m.nomor_peminjaman,
            ls.varian as produk,
            SUM(CASE WHEN ls.nama_gudang = m.gudang_tujuan THEN ls.jumlah ELSE 0 END) as jumlah_match,
            SUM(CASE WHEN ls.nama_gudang != m.gudang_tujuan THEN ls.jumlah ELSE 0 END) as jumlah_mismatch
        FROM log_stok ls
        INNER JOIN (
            SELECT DISTINCT nomor_peminjaman, gudang_tujuan
            FROM peminjaman_stok
            WHERE nomor_peminjaman IN ($nomorPeminjamanString)
        ) m ON (
            ls.nama_file = m.nomor_peminjaman
            OR ls.nama_file LIKE CONCAT('PJ ', m.nomor_peminjaman, '%')
            OR ls.nama_file LIKE CONCAT('%', m.nomor_peminjaman, '%')
        )
        WHERE ls.kategori = 'Peminjaman'
        GROUP BY m.nomor_peminjaman, ls.varian
        HAVING jumlah_match > 0 OR jumlah_mismatch > 0
    ";
    
    $result = mysqli_query($db_dc, $query);
    
    $matchMap = [];
    $mismatchMap = [];
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $nomor = $row['nomor_peminjaman'];
            $produk = $row['produk'];
            $jumlahMatch = floatval($row['jumlah_match']);
            $jumlahMismatch = floatval($row['jumlah_mismatch']);
            
            if ($jumlahMatch > 0) {
                if (!isset($matchMap[$nomor])) {
                    $matchMap[$nomor] = [];
                }
                $matchMap[$nomor][$produk] = $jumlahMatch;
            }
            
            if ($jumlahMismatch > 0) {
                if (!isset($mismatchMap[$nomor])) {
                    $mismatchMap[$nomor] = [];
                }
                $mismatchMap[$nomor][$produk] = $jumlahMismatch;
            }
        }
    }
    
    return [$matchMap, $mismatchMap];
}

/**
 * Get log_stok data untuk menentukan status transaksi
 * Mengembalikan array dengan informasi:
 * - log_stok_dipinjam: data log_stok dengan nama_gudang = gudang_tujuan (gudang dipinjam)
 * - log_stok_peminjam: data log_stok dengan nama_gudang = gudang_asal (gudang peminjam) dan qty sesuai
 */
function getLogStokForStatusTransaksi($db_dc, $nomorPeminjamanList, $gudangAsalMap, $gudangTujuanMap) {
    if (empty($nomorPeminjamanList)) {
        return [];
    }
    
    // Escape nomor_peminjaman untuk digunakan dalam query
    $escapedNomorPeminjaman = array_map(function($n) use ($db_dc) {
        return "'" . mysqli_real_escape_string($db_dc, $n) . "'";
    }, $nomorPeminjamanList);
    $nomorPeminjamanString = implode(',', $escapedNomorPeminjaman);
    
    // Query untuk mendapatkan data log_stok dengan kondisi spesifik
    // Optimasi: prioritaskan exact match dengan normalisasi, lalu fallback ke exact match
    $query = "
        SELECT 
            m.nomor_peminjaman,
            m.gudang_asal,
            m.gudang_tujuan,
            ls.varian as produk,
            ls.nama_gudang,
            SUM(ls.jumlah) as qty_log_stok
        FROM log_stok ls
        INNER JOIN (
            SELECT DISTINCT nomor_peminjaman, gudang_asal, gudang_tujuan
            FROM peminjaman_stok
            WHERE nomor_peminjaman IN ($nomorPeminjamanString)
        ) m ON (
            ls.nama_file = m.nomor_peminjaman
            OR ls.nama_file LIKE CONCAT('PJ ', m.nomor_peminjaman, '%')
            OR ls.nama_file LIKE CONCAT('%', m.nomor_peminjaman, '%')
        )
        WHERE ls.kategori = 'Peminjaman'
            AND (ls.nama_gudang = m.gudang_tujuan OR ls.nama_gudang = m.gudang_asal)
        GROUP BY m.nomor_peminjaman, ls.varian, ls.nama_gudang
    ";
    
    $result = mysqli_query($db_dc, $query);
    
    $logStokMap = [];
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $nomor = $row['nomor_peminjaman'];
            $produk = $row['produk'];
            $namaGudang = $row['nama_gudang'];
            $gudangTujuan = $row['gudang_tujuan'];
            $gudangAsal = $row['gudang_asal'];
            $qtyLogStok = floatval($row['qty_log_stok']);
            
            if (!isset($logStokMap[$nomor])) {
                $logStokMap[$nomor] = [
                    'dipinjam' => [], // log_stok dengan nama_gudang = gudang_tujuan
                    'peminjam' => []  // log_stok dengan nama_gudang = gudang_asal
                ];
            }
            
            // Jika nama_gudang = gudang_tujuan (gudang dipinjam)
            if ($namaGudang == $gudangTujuan) {
                if (!isset($logStokMap[$nomor]['dipinjam'][$produk])) {
                    $logStokMap[$nomor]['dipinjam'][$produk] = 0;
                }
                $logStokMap[$nomor]['dipinjam'][$produk] += $qtyLogStok;
            }
            
            // Jika nama_gudang = gudang_asal (gudang peminjam)
            if ($namaGudang == $gudangAsal) {
                if (!isset($logStokMap[$nomor]['peminjam'][$produk])) {
                    $logStokMap[$nomor]['peminjam'][$produk] = 0;
                }
                $logStokMap[$nomor]['peminjam'][$produk] += $qtyLogStok;
            }
        }
    }
    
    return $logStokMap;
}

/**
 * Get log_stok data untuk menentukan status peminjaman berdasarkan no_pj
 * Hanya mengambil data yang nama_gudang = gudang_asal (gudang peminjam)
 * Mengembalikan array dengan struktur:
 * [
 *   'nomor_peminjaman' => [
 *     'produk' => total_qty_log_stok (dijumlahkan dari gudang peminjam saja)
 *   ]
 * ]
 */
function getLogStokForStatusPeminjaman($db_dc, $nomorPeminjamanList, $gudangAsalMap, $gudangTujuanMap) {
    if (empty($nomorPeminjamanList)) {
        return [];
    }
    
    // Escape nomor_peminjaman untuk digunakan dalam query
    $escapedNomorPeminjaman = array_map(function($n) use ($db_dc) {
        return "'" . mysqli_real_escape_string($db_dc, $n) . "'";
    }, $nomorPeminjamanList);
    $nomorPeminjamanString = implode(',', $escapedNomorPeminjaman);
    
    // Query untuk mendapatkan data log_stok berdasarkan no_pj
    // no_pj di log_stok harus sama dengan nomor_peminjaman (dengan normalisasi untuk spasi dan minus)
    // Hanya ambil data yang nama_gudang = gudang_asal (gudang peminjam)
    // Optimasi: prioritaskan exact match dengan normalisasi, lalu fallback ke LIKE
    $query = "
        SELECT 
            m.nomor_peminjaman,
            ls.varian as produk,
            SUM(ls.jumlah) as qty_log_stok
        FROM log_stok ls
        INNER JOIN (
            SELECT DISTINCT nomor_peminjaman, MIN(gudang_asal) as gudang_asal
            FROM peminjaman_stok
            WHERE nomor_peminjaman IN ($nomorPeminjamanString)
            GROUP BY nomor_peminjaman
        ) m ON (
            ls.no_pj = m.nomor_peminjaman
            OR ls.nama_file = m.nomor_peminjaman
            OR ls.nama_file LIKE CONCAT('PJ ', m.nomor_peminjaman, '%')
        )
        WHERE (ls.no_pj IS NOT NULL AND ls.no_pj != '')
            AND ls.nama_gudang = m.gudang_asal
        GROUP BY m.nomor_peminjaman, ls.varian
    ";
    
    $result = mysqli_query($db_dc, $query);
    
    if (!$result) {
        error_log("Error in getLogStokForStatusPeminjaman: " . mysqli_error($db_dc) . " | Query: " . $query);
        return [];
    }
    
    $logStokMap = [];
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $nomor = $row['nomor_peminjaman'];
            $produk = $row['produk'];
            $qtyLogStok = floatval($row['qty_log_stok']);
            
            if (!isset($logStokMap[$nomor])) {
                $logStokMap[$nomor] = [];
            }
            
            if (!isset($logStokMap[$nomor][$produk])) {
                $logStokMap[$nomor][$produk] = 0;
            }
            $logStokMap[$nomor][$produk] += $qtyLogStok;
        }
    }
    
    return $logStokMap;
}

    /**
 * Calculate status peminjaman secara batch
 * Aturan Status Peminjaman:
 * - Draft: jika data disimpan sebagai draft/diupdate sebagai draft
 * - Final: jika data disimpan sebagai final/diupdate sebagai final DAN tidak ada data di log_stok dengan no_pj = nomor_peminjaman DAN nama_gudang = gudang_asal
 * - Belum Selesai: jika no_pj di log_stok = nomor peminjaman DAN nama_gudang = gudang_asal DAN qty log_stok + qty peminjaman ≠ 0 untuk setidaknya satu produk
 * - Selesai: jika no_pj di log_stok = nomor peminjaman DAN nama_gudang = gudang_asal (gudang peminjam) DAN qty log_stok + qty peminjaman = 0 untuk semua produk
 */
function calculateStatusPeminjamanBatch($rowsData, $peminjamanProductsMap, $logStokMatchMap, $logStokMismatchMap, $logStokForStatusPeminjamanMap = null) {
    global $db_dc;
    $statusMap = [];
    
    foreach ($rowsData as $row) {
        $nomor = $row['nomor_peminjaman'];
        $status_peminjaman = $row['status_peminjaman'];
        
        if (empty($nomor) || trim($nomor) == '') continue;
        
        if (!isset($statusMap[$nomor])) {
            // Default status
            $statusMap[$nomor] = [
                'status' => 'final',
                'is_selesai' => 0,
                'is_belum_selesai' => 0,
                'is_terproses' => 0
            ];
            
            // 1. Draft: jika data disimpan sebagai draft/diupdate sebagai draft
            if ($status_peminjaman == 'Draft') {
                $statusMap[$nomor]['status'] = 'draft';
                continue;
            }
            
            // Ambil data peminjaman (jumlah dipinjam)
            $peminjamanProducts = isset($peminjamanProductsMap[$nomor]) ? $peminjamanProductsMap[$nomor] : [];
            
            if (empty($peminjamanProducts)) {
                $statusMap[$nomor]['status'] = 'final';
                continue;
            }
            
            // Ambil data log_stok untuk status peminjaman
            $logStokData = isset($logStokForStatusPeminjamanMap[$nomor]) ? $logStokForStatusPeminjamanMap[$nomor] : null;
            $hasLogStokData = $logStokData && !empty($logStokData);
            
            // Jika tidak ada data log_stok, status tetap Final
            if (!$hasLogStokData) {
                $statusMap[$nomor]['status'] = 'final';
                continue;
            }
            
            // Ada data log_stok, cek apakah qty log_stok + qty peminjaman = 0 untuk semua produk
            $allZero = true;
            $hasAnyData = false;
            
            foreach ($peminjamanProducts as $produk => $qtyPeminjaman) {
                $hasAnyData = true;
                $qtyLogStok = isset($logStokData[$produk]) ? floatval($logStokData[$produk]) : 0;
                $qtyPeminjamanFloat = floatval($qtyPeminjaman);
                $totalQty = $qtyLogStok + $qtyPeminjamanFloat;
                
                // Gunakan toleransi untuk perbandingan floating point
                if (abs($totalQty) >= 0.01) {
                    $allZero = false;
                    break;
                }
            }
            
            // 2. Selesai: jika qty log_stok + qty peminjaman = 0 untuk semua produk
            if ($hasAnyData && $allZero) {
                $statusMap[$nomor]['status'] = 'selesai';
                $statusMap[$nomor]['is_selesai'] = 1;
                continue;
            }
            
            // 3. Belum Selesai: jika qty log_stok + qty peminjaman ≠ 0 untuk setidaknya satu produk
            if ($hasLogStokData) {
                $statusMap[$nomor]['status'] = 'belum_selesai';
                $statusMap[$nomor]['is_belum_selesai'] = 1;
                continue;
            }
            
            // 4. Final: jika data disimpan sebagai final/diupdate sebagai final (tidak ada kondisi lain yang terpenuhi)
            $statusMap[$nomor]['status'] = ($status_peminjaman == 'Final') ? 'final' : 'final';
        }
    }
    
    return $statusMap;
}

/**
 * Calculate status transaksi secara batch
 * Aturan Status Transaksi:
 * - Draft: ketika data disimpan/diupdate sebagai draft
 * - Final: ketika data disimpan/diupdate sebagai final
 * - Belum Selesai: ketika nama_gudang di table log_stok = gudang dipinjam DAN nama_file di log_stok seperti nomor peminjaman
 * - Selesai: ketika nama_gudang di table log_stok = gudang peminjam DAN qty log_stok = qty peminjaman DAN nama_file di log_stok seperti nomor peminjaman
 */
function calculateStatusTransaksiBatch($rowsData, $peminjamanProductsMap, $logStokMatchMap, $logStokMismatchMap, $logStokForStatusTransaksiMap = null) {
    global $db_dc;
    $statusMap = [];
    
    foreach ($rowsData as $row) {
        $nomor = $row['nomor_peminjaman'];
        $status_peminjaman = $row['status_peminjaman'];
        
        if (empty($nomor) || trim($nomor) == '') continue;
        
        if (!isset($statusMap[$nomor])) {
            // Default status
            $statusMap[$nomor] = [
                'status' => 'final',
                'is_selesai' => 0,
                'is_belum_selesai' => 0,
                'is_terproses' => 0
            ];
            
            // 1. Draft: ketika data disimpan/diupdate sebagai draft
            if ($status_peminjaman == 'Draft') {
                $statusMap[$nomor]['status'] = 'draft';
                continue;
            }
            
            // 2. Final: ketika data disimpan/diupdate sebagai final
            if ($status_peminjaman == 'Final') {
                $statusMap[$nomor]['status'] = 'final';
                // Tetap cek log_stok untuk melihat apakah ada perubahan status
            }
            
            // Ambil data peminjaman (jumlah dipinjam)
            $peminjamanProducts = isset($peminjamanProductsMap[$nomor]) ? $peminjamanProductsMap[$nomor] : [];
            
            if (empty($peminjamanProducts)) {
                $statusMap[$nomor]['status'] = 'final';
                continue;
            }
            
            // Ambil data log_stok untuk status transaksi
            // Struktur: ['nomor_peminjaman' => ['dipinjam' => ['produk' => qty], 'peminjam' => ['produk' => qty]]]
            $logStokData = isset($logStokForStatusTransaksiMap[$nomor]) ? $logStokForStatusTransaksiMap[$nomor] : null;
            
            // 3. Selesai: ketika nama_gudang di table log_stok = gudang peminjam DAN qty log_stok = qty peminjaman DAN nama_file di log_stok seperti nomor peminjaman
            // PRIORITAS: Cek Selesai dulu sebelum Belum Selesai
            if ($logStokData && !empty($logStokData['peminjam'])) {
                $allMatch = true;
                $hasAnyData = false;
                
                foreach ($peminjamanProducts as $produk => $qtyPeminjaman) {
                    $hasAnyData = true;
                    $qtyLogStokPeminjam = isset($logStokData['peminjam'][$produk]) ? floatval($logStokData['peminjam'][$produk]) : 0;
                    $qtyPeminjamanFloat = floatval($qtyPeminjaman);
                    
                    // Gunakan nilai absolut karena jumlah di log_stok untuk gudang asal bisa negatif
                    // (negatif = mengurangi stok di gudang asal, positif = menambah stok di gudang tujuan)
                    $qtyLogStokAbs = abs($qtyLogStokPeminjam);
                    
                    // Gunakan toleransi untuk perbandingan floating point
                    if (abs($qtyLogStokAbs - $qtyPeminjamanFloat) >= 0.01) {
                        $allMatch = false;
                        break;
                    }
                }
                
                if ($hasAnyData && $allMatch) {
                    $statusMap[$nomor]['status'] = 'selesai';
                    $statusMap[$nomor]['is_selesai'] = 1;
                    continue;
                }
            }
            
            // 4. Belum Selesai: ketika nama_gudang di table log_stok = gudang dipinjam DAN nama_file di log_stok seperti nomor peminjaman
            if ($logStokData && !empty($logStokData['dipinjam'])) {
                $statusMap[$nomor]['status'] = 'belum_selesai';
                $statusMap[$nomor]['is_belum_selesai'] = 1;
                continue;
            }
            
            // Jika tidak ada kondisi yang terpenuhi, tetap Final
        }
    }
    
    return $statusMap;
}
