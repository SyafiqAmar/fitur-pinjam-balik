    <?php
        require_once "../../config/redirect_login.php";
        require_once "../../config/config.php";
        require_once "../../config/functions.php";
        require_once "../../vendor/autoload.php";
        require_once "../../asset/AdminLTE-3.2.0/plugins/dompdf/autoload.inc.php";
        
        use Dompdf\Dompdf;
        use Dompdf\Options;
        
        // Redirect ke halaman pengembalian jika type=pengembalian
        if (isset($_GET['type']) && $_GET['type'] === 'pengembalian') {
            header('Location: ../pengembalian/index.php?type=pengembalian');
            exit;
        }
        
        // Hapus kode view_pdf - sudah dipindah ke download_sj.php
        // Cek jika ini request untuk view PDF (legacy - redirect ke download_sj.php)
        if (isset($_GET['action']) && $_GET['action'] == 'view_pdf') {
            $nomor_peminjaman = isset($_GET['nomor_peminjaman']) ? $_GET['nomor_peminjaman'] : '';
            if (!empty($nomor_peminjaman)) {
                header('Location: download_sj.php?nomor_peminjaman=' . urlencode($nomor_peminjaman));
                exit;
            }
        }

        // Data Web
        $namaHalaman = "Peminjaman Stok";

        $title  = $basetitle." - ".$namaHalaman;

        require_once "../../template/header.php";
        require_once "../../template/navbar.php";
        require_once "../../template/sidebar.php";

        // Cek apakah ada parameter start_date dan end_date pada URL
        if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
            $start_date_default2    = $_GET['start_date'];
            $end_date_default1      = $_GET['end_date'];
        } else {
            $start_date_default2    = date('Y-m-d', strtotime('-29 days'));
            $end_date_default1      = date('Y-m-d');
        }

        if (isset($_GET['msg'])) {
            $msg = $_GET['msg'];
        }else{
            $msg = '';
        }

        if (isset($_GET['data'])) {
            $data = $_GET['data'];
        }else{
            $data = '';
        }

        $idEntitasPeminjam = isset($_GET['entitas_peminjam']) ? $_GET['entitas_peminjam'] : '';
        $idEntitasDipinjam = isset($_GET['entitas_dipinjam']) ? $_GET['entitas_dipinjam'] : '';
        $gudangAsal = isset($_GET['gudang_asal']) ? $_GET['gudang_asal'] : '';
        $gudangTujuan = isset($_GET['gudang_tujuan']) ? $_GET['gudang_tujuan'] : '';

        // Notifikasi
        $alert = '';
        if ($msg == 'added') {
            $alert = '<div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-circle-check"></i>'.$data.' Data '.$namaHalaman.' berhasil ditambah!
        </div>';
        }
        if ($msg == 'updated') {
            $alert = '<div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-circle-check"></i> Data '.$namaHalaman.' berhasil di-update!
        </div>';
        }
        if ($msg == 'deleted') {
            $alert = '<div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-circle-check"></i> Data '.$namaHalaman.' berhasil dihapus!
        </div>';
        }
        if ($msg == 'error') {
            $alert = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-circle-exclamation"></i> Proses error!
        </div>';
        }

        // Query untuk mendapatkan produk dari omni_stok_akhir
        $product = getDataDc("SELECT DISTINCT nama FROM omni_stok_akhir ORDER BY nama");

        // Generate opsi untuk dropdown produk
        $productOptions = "<option value=''>-- Pilih Produk --</option>";
        foreach ($product as $produk) {
            $productOptions .= "<option value='{$produk['nama']}'>{$produk['nama']}</option>";
        }
        
        // Query untuk mendapatkan gudang
        $gudang = getDataDc("SELECT DISTINCT nama_gudang FROM gudang_omni ORDER BY nama_gudang");
        
        // Generate opsi untuk dropdown gudang
        $gudangOptions = "<option value=''>-- Pilih Gudang --</option>";
        foreach ($gudang as $gud) {
            $gudangOptions .= "<option value='{$gud['nama_gudang']}'>{$gud['nama_gudang']}</option>";
        }

        // Query untuk mendapatkan data entitas
        $entitasList = getDataDc("SELECT DISTINCT be.inisial as entitas, be.id as id_entitas 
                                FROM base_entitas be 
                                INNER JOIN base_tim bt ON bt.id_entitas = be.id 
                                INNER JOIN gudang_omni go ON go.tim = bt.tim 
                                WHERE be.inisial IS NOT NULL AND TRIM(be.inisial) <> '' 
                                ORDER BY be.inisial ASC");

        // Generate opsi untuk dropdown entitas peminjam
        $entitasPeminjamOptions = "<option value=''>-- Pilih Entitas Peminjam --</option>";
        foreach ($entitasList as $ent) {
            $entitasPeminjamOptions .= "<option value='{$ent['entitas']}'>{$ent['entitas']}</option>";
        }
        
        // Generate opsi untuk dropdown entitas dipinjam
        $entitasDipinjamOptions = "<option value=''>-- Pilih Entitas Dipinjam --</option>";
        foreach ($entitasList as $ent) {
            $entitasDipinjamOptions .= "<option value='{$ent['entitas']}'>{$ent['entitas']}</option>";
        }
    ?>

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="ml-3 text-bold"><?= $namaHalaman ?></h1>
                    </div><!-- /.col -->
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="<?= $dashboard ?>"><i class="fas fa-home"></i> Home</a></li>
                            <li class="breadcrumb-item active"><?= $namaHalaman ?></li>
                        </ol>
                    </div><!-- /.col -->

                        <div class="col-12 px-4 mt-2">
                            <div class="alert alert-info alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle"></i> Menampilkan data <?= $namaHalaman ?> Tanggal <strong><?= indoTgl($start_date_default2) ?></strong> s/d <strong><?= indoTgl($end_date_default1) ?></strong>
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                        </div>
                </div><!-- /.row -->
            </div><!-- /.container-fluid -->
        </div>
        <!-- /.content-header -->

        <?php // Notifikasi
            if ($msg !== '') {
                echo $alert;
            ?>
            <script>
                setTimeout(function() {
                    document.querySelector('.alert').style.display = 'none';
                }, 3500);
            </script>
            <?php
            }
        ?>

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <div class="row mr-3 mb-3"> <!-- Tombol Tambah -->
                    <div class="col-12 d-flex justify-content-end">
                        <a class="btn btn-success float-right" data-toggle="modal" data-target="#tambahModal" id="btnTambahData">
                            <i class="fas fa-plus-square"></i> Tambah Data
                        </a>
                    </div>
                </div>
            </div>

            <!-- Card Peminjaman dan Pengembalian -->
            <div class="row ml-3 mr-3 mb-3">
                <div class="col-md-6">
                    <div class="card type-card" data-type="peminjaman" style="cursor: pointer; border-radius: 8px; transition: all 0.3s;">
                        <div class="card-body text-center p-4" style="background-color: #007bff; color: white;">
                            <h6 class="mb-2" style="font-weight: bold; text-transform: uppercase; font-size: 14px;">PEMINJAMAN</h6>
                            <h3 class="mb-0" style="font-weight: bold; font-size: 36px;" id="countPeminjaman">0</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card type-card" data-type="pengembalian" style="cursor: pointer; border-radius: 8px; transition: all 0.3s;">
                        <div class="card-body text-center p-4" style="background-color: #ffffff; color: #333;">
                            <h6 class="mb-2" style="font-weight: bold; text-transform: uppercase; font-size: 14px;">PENGEMBALIAN</h6>
                            <h3 class="mb-0" style="font-weight: bold; font-size: 36px;" id="countPengembalian">0</h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Formulir Filter -->
            <div class="row ml-3 mr-3">
                <div class="col-12">
                    <div class="card mb-3">
                        <div class="card-header d-flex justify-content-between align-items-center" id="headingOne" data-toggle="collapse" data-target="#filterFormContainer" aria-expanded="true" aria-controls="filterFormContainer" style="cursor: pointer;">
                            <h5 class="card-title mb-0 text-bold"><i class="fas fa-filter"></i> Filter Data</h5>
                            <i class="fas fa-chevron-down ml-auto" id="toggleIcon"></i>
                        </div>
                        <div id="filterFormContainer" class="collapse">
                            <div class="card-body">
                                <form id="filterForm" class="form">
                                    <div class="form-row">
                                        <div class="form-group col-md-3">
                                            <label for="entitas_peminjam">Pilih Entitas Peminjam:</label>
                                            <select name="entitas_peminjam[]" id="entitas_peminjam" class="form-control select2" multiple="multiple" style="width: 100%;">
                                                <?php
                                                    foreach ($entitasList as $ent) {
                                                        $selected = (in_array($ent['entitas'], explode(',', $idEntitasPeminjam))) ? 'selected' : '';
                                                        echo '<option value="' . htmlspecialchars($ent['entitas']) . '" ' . $selected . '>' . htmlspecialchars($ent['entitas']) . '</option>';
                                                    }
                                                ?>
                                            </select>
                                        </div>
                                        
                                        <div class="form-group col-md-3">
                                            <label for="entitas_dipinjam">Pilih Entitas Dipinjam:</label>
                                            <select name="entitas_dipinjam[]" id="entitas_dipinjam" class="form-control select2" multiple="multiple" style="width: 100%;">
                                                <?php
                                                    foreach ($entitasList as $ent) {
                                                        $selected = (in_array($ent['entitas'], explode(',', $idEntitasDipinjam))) ? 'selected' : '';
                                                        echo '<option value="' . htmlspecialchars($ent['entitas']) . '" ' . $selected . '>' . htmlspecialchars($ent['entitas']) . '</option>';
                                                    }
                                                ?>
                                            </select>
                                        </div>

                                        <div class="form-group col-md-3">
                                            <label for="daterange">Pilih Tanggal:</label>
                                            <div id="daterange" class="form-control" style="background: #fff; cursor: pointer; padding: 5px 10px; border: 1px solid #ccc;">
                                                <i class="fa fa-calendar"></i>&nbsp;
                                                <span></span> <i class="fa fa-caret-down"></i>
                                            </div>
                                            <input type="hidden" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date_default2) ?>" />
                                            <input type="hidden" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date_default1) ?>" />
                                        </div>
                                    </div>
                                    <div class="form-row align-items-end">
                                        <div class="form-group col-md-2">
                                            <button type="button" class="btn btn-primary btn-block" id="filterButton"><i class="fas fa-search"></i> Filter</button>
                                        </div>
                                        <div class="form-group col-md-2">
                                            <button type="button" class="btn btn-outline-secondary btn-block" id="resetButton"><i class="fas fa-trash"></i> Reset</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table Data -->
            <div class="row ml-3 mr-3">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="table-peminjaman" class="table table-hover table-bordered" style="width: 100%;">
                                    <thead>
                                        <tr class="text-center">
                                        <th scope="col">Tanggal</th>
                                        <th scope="col">Nomor Peminjaman</th>
                                        <th scope="col">Entitas Dipinjam</th>
                                        <th scope="col">Entitas Peminjam</th>
                                        <th scope="col">Jumlah Item</th>
                                        <th scope="col">Total Qty</th>
                                        <th scope="col" width="10%">Status Transaksi</th>
                                        <th scope="col" width="10%">Status Peminjaman</th>
                                        <th scope="col" width="5%">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <?php
        require_once "../../template/footer.php";
    ?>

    <!-- Modal -->
    <div class="modal fade" id="tambahModal" data-backdrop="static" data-keyboard="false" tabindex="-1" aria-labelledby="tambahModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form id="tambahForm" method="post" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 class="modal-title font-weight-bold" id="tambahModalLabel">Tambah <?= $namaHalaman ?></h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        
                        <!--hidden input for edit-->
                        <input type="hidden" name="edit_id" id="edit_id">

                        <div class="form-row align-items-end">
                            <div class="form-group col-md-6">
                                <label for="in_tanggal">Tanggal Peminjaman</label>
                                <input type="date" name="in_tanggal" class="form-control" id="in_tanggal" value="<?= date("Y-m-d") ?>" required>
                            </div>

                            <div class="form-group col-md-6">
                                <label class="mb-1" for="in_nomor_peminjaman">Nomor Peminjaman</label>
                                <input type="text" id="in_nomor_peminjaman" name="in_nomor_peminjaman" class="form-control in_nomor_peminjaman" readonly>
                            </div>
                            
                            <div class="form-group col-md-6">
                                <label for="in_entitas_dipinjam">Entitas Dipinjam</label>
                                <select class="form-control select2" name="in_entitas_dipinjam" id="in_entitas_dipinjam" required>
                                    <?php echo $entitasDipinjamOptions; ?>
                                </select>
                            </div>

                            <div class="form-group col-md-6">
                                <label for="in_entitas_peminjam">Entitas Peminjam</label>
                                <select class="form-control select2" name="in_entitas_peminjam" id="in_entitas_peminjam" required>
                                    <?php echo $entitasPeminjamOptions; ?>
                                </select>
                            </div>

                            <div class="form-group col-md-6">
                                <label for="in_gudang_tujuan">Gudang Dipinjam</label>
                                <select class="form-control select2" name="in_gudang_tujuan" id="in_gudang_tujuan" required disabled>
                                    <option value="">-- Pilih Entitas Dipinjam dulu --</option>
                                </select>
                            </div>

                            <div class="form-group col-md-6">
                                <label for="in_gudang_asal">Gudang Peminjam</label>
                                <select class="form-control select2" name="in_gudang_asal" id="in_gudang_asal" required disabled>
                                    <option value="">-- Pilih Entitas Peminjam dulu --</option>
                                </select>
                            </div>

                        </div>
                        
                        <!-- Tombol Tambah Row -->
                        <button type="button" id="addRowButton" class="btn btn-primary mb-3">Tambah Data Produk</button>
                        
                        <table class="table table-striped table-bordered table-hover mb-0 mt-3" id="detailTable">
                            <thead>
                                <tr class="text-center">
                                    <th width="5%">No</th>
                                    <th width="30%">Produk</th>
                                    <th width="15%">Stok</th>
                                    <th width="15%">Jumlah</th>
                                    <th width="5%">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyListData">
                                <tr id="emptyRow">
                                    <td colspan="5" class="text-center">Klik tombol 'Tambah Data Produk' untuk menambahkan produk</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="modal-footer actionAddData">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-dismiss="modal"><i class="fas fa-times-circle"></i> Tutup</button>
                        <button type="submit" class="btn btn-warning" name="in_action_button" value="Draft" id="draftButton"><i class="fas fa-file-alt"></i> Simpan sebagai Draft</button>
                        <button type="submit" class="btn btn-primary" name="in_action_button" value="Final" id="submitButton"><i class="fas fa-check-circle"></i> Simpan sebagai Final</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Detail Peminjaman -->
    <div class="modal fade" id="viewDetailModal" data-backdrop="static" data-keyboard="false" tabindex="-1" aria-labelledby="viewDetailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl" style="max-width: 95%;">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title font-weight-bold" id="viewDetailModalLabel" style="font-size: 1.5rem;">Detail Data Peminjaman</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" style="padding: 20px;">
                    <div class="row mb-4">
                        <!-- Kolom Kiri: Detail Peminjaman -->
                        <div class="col-md-6">
                            <div class="card" style="border: none; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                <div class="card-body">
                                    <h6 class="font-weight-bold mb-3">Detail Peminjaman</h6>
                                    <div class="mb-2"><strong>Tanggal Peminjaman:</strong> <span id="detailTanggalPeminjaman">-</span></div>
                                    <div class="mb-2"><strong>Nomor Peminjaman:</strong> <span id="detailNomorPeminjaman">-</span></div>
                                    <div class="mb-2"><strong>Entitas Peminjam:</strong> <span id="detailEntitasPeminjam">-</span></div>
                                    <div class="mb-2"><strong>Entitas Dipinjam:</strong> <span id="detailEntitasDipinjam">-</span></div>
                                    <div class="mb-2"><strong>Gudang Peminjam:</strong> <span id="detailGudangAsal">-</span></div>
                                    <div class="mb-2"><strong>Gudang Dipinjam:</strong> <span id="detailGudangTujuan">-</span></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Kolom Kanan: Warehouse Identity -->
                        <div class="col-md-6">
                            <div class="card" style="border: none; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                <div class="card-body">
                                    <h6 class="font-weight-bold mb-3">
                                        <i class="fas fa-warehouse mr-2"></i>Warehouse Identity
                                    </h6>
                                    <div class="mb-2">
                                        <i class="fas fa-user mr-2 text-muted"></i>
                                        <strong>Name:</strong> <span id="detailPjAsal">-</span>
                                    </div>
                                    <div class="mb-2">
                                        <i class="fas fa-map-marker-alt mr-2 text-muted"></i>
                                        <strong>Address:</strong> <span id="detailAlamatAsal">-</span>
                                    </div>
                                    <div class="mb-2">
                                        <i class="fas fa-phone mr-2 text-muted"></i>
                                        <strong>Phone:</strong> <span id="detailNoTelpAsal">-</span>
                                    </div>
                                    <div class="mb-2">
                                        <i class="fas fa-envelope mr-2 text-muted"></i>
                                        <strong>Email:</strong> 
                                        <a href="#" id="detailEmailAsal" style="color: #007bff;">-</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tabel Produk -->
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="detailProductsTable">
                            <thead class="thead-light">
                                <tr>
                                    <th>No</th>
                                    <th>Gudang</th>
                                    <th>Produk</th>
                                    <th>Jumlah</th>
                                    <th>Jml. Terkirim</th>
                                    <th>Jml. Diterima</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="detailProductsBody">
                                <!-- Data akan diisi via JavaScript -->
                            </tbody>
                            <tfoot>
                                <tr class="font-weight-bold">
                                    <td colspan="3" class="text-right">Total</td>
                                    <td id="detailTotalJumlah">0</td>
                                    <td id="detailTotalTerkirim">0</td>
                                    <td id="detailTotalDiterima">0</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    
                </div>
                <div class="modal-footer">
                    <a id="downloadSjBtn" href="#" class="btn btn-success" target="_blank" style="display:none;">
                        <i class="fas fa-file-pdf"></i> Download SJ
                    </a>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times"></i> Tutup
                    </button>
                    <button type="button" class="btn btn-primary simpanPerubahanPeminjaman" data-nomor-peminjaman="" style="display:none;">
                        <i class="fas fa-save"></i> Simpan Perubahan
                    </button>
                </div>
            </div>
        </div>
    </div>

    <style>
        .modal {
            overflow: visible !important;
        }
        .modal-body {
            max-height: 70vh;
            overflow-y: auto;
            overflow-x: auto;
            white-space: nowrap;
        }
        
        /* Styling untuk tabel peminjaman agar sesuai dengan card */
        #table-peminjaman_wrapper {
            width: 100%;
            overflow: hidden;
        }
        
        #table-peminjaman {
            width: 100% !important;
            table-layout: auto;
        }
        
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        /* Pastikan card body tidak overflow */
        .card-body {
            padding: 1rem;
            overflow-x: hidden;
        }
        
        /* DataTables wrapper */
        .dataTables_wrapper {
            width: 100%;
            position: relative;
        }
        
        .dataTables_scroll {
            width: 100% !important;
        }
        
        .dataTables_scrollHead,
        .dataTables_scrollBody {
            width: 100% !important;
        }
        
        /* Pastikan Select2 memiliki lebar yang konsisten */
        #detailTable select.select2 {
            width: 100% !important;
        }
        
        .select2-container {
            width: 100% !important;
        }
        
        /* Pastikan kolom produk memiliki lebar yang sama */
        #detailTable td:nth-child(2) {
            width: 35%;
        }
        
        #detailTable td:nth-child(2) .select2-container {
            width: 100% !important;
        }
        
        /* Styling untuk input invalid */
        input.is-invalid {
            border-color: #dc3545;
            background-color: #fff5f5;
        }
        
        input.is-invalid:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }
    </style>

    <script type="text/javascript">
        $(document).ready(function() {

            window.isEditMode = false;

            window.updateSubmitButtonState = function() {
                // Skip validasi jika tombol sudah disabled karena status bukan Draft
                if ($('#draftButton').prop('disabled') && $('#submitButton').prop('disabled')) {
                    const isDraftDisabled = $('#draftButton').data('disabled-by-status');
                    const isSubmitDisabled = $('#submitButton').data('disabled-by-status');
                    if (isDraftDisabled || isSubmitDisabled) {
                        return; // Jangan update jika disabled karena status
                    }
                }
                
                let hasInvalid = false;
                let hasData = false;
                let rowCount = 0;
                
                $('#tbodyListData tr').each(function() {
                    const row = $(this);
                    // Skip empty row
                    if (row.attr('id') === 'emptyRow') {
                        return true; // continue
                    }
                    
                    rowCount++;
                    const jumlahInput = row.find('input[name="in_jumlah[]"]');
                    const produkSelect = row.find('select[name="in_produk[]"]');
                    const stokText = row.find('.stok-display').text();
                    const stok = stokText !== '-' ? parseFloat(stokText) || 0 : 0;
                    
                    if (jumlahInput.length && produkSelect.length) {
                        const jumlah = parseFloat(jumlahInput.val()) || 0;
                        const produk = produkSelect.val();
                        
                        if (produk && jumlah > 0) {
                            hasData = true;
                        }
                        
                        // Validasi: jumlah harus > 0
                        if (produk && jumlah <= 0) {
                            hasInvalid = true;
                            return false; // break loop
                        }
                        
                        // Validasi: jumlah tidak boleh > stok
                        if (produk && stok > 0 && jumlah > stok) {
                            hasInvalid = true;
                            return false; // break loop
                        }
                    }
                });
                
                // Disable tombol submit jika ada jumlah <= 0 atau tidak ada data sama sekali (dan ada row)
                if (rowCount > 0) {
                    $('#draftButton, #submitButton').prop('disabled', hasInvalid || !hasData);
                }
            };
        
            $(document).on("click", ".edit_btn", function () {
                $("#submitButton").text("Update sebagai Final");
                $("#draftButton").text("Update sebagai Draft");
                $("#tambahModalLabel").text("Update Peminjaman Stok");
                window.isEditMode = true;
            
                let nomor_peminjaman = $(this).data('nomor_peminjaman') || '';
                let statusPeminjaman = $(this).data('status') || 'Draft';
                let minId = parseInt($(this).data('min-id')) || 0;
                let isDraftNoNomor = $(this).data('is-draft-no-nomor') == '1';
                
                // Simpan status untuk digunakan di dalam modal
                window.currentStatusPeminjaman = statusPeminjaman;
                
                // Simpan identifier di window untuk digunakan di event handler
                window.editNomorPeminjaman = nomor_peminjaman;
                window.editMinId = minId;
                window.editIsDraftNoNomor = isDraftNoNomor;
            
                // Tampilkan modal dulu
                $("#tambahModal").modal("show");
                
                // Ketika modal sudah selesai ditampilkan, baru jalankan AJAX
                $("#tambahModal").off("shown.bs.modal").on("shown.bs.modal", function() {
                    let tbody = $("#tbodyListData");
                    
                    // Gunakan variabel dari window untuk memastikan data tersedia
                    let currentNomorPeminjaman = window.editNomorPeminjaman || '';
                    let currentMinId = window.editMinId || 0;
                    let currentIsDraftNoNomor = window.editIsDraftNoNomor || false;
                    
                    if (!window.isEditMode) {
                        tbody.empty().append(`
                            <tr id="emptyRow">
                                <td colspan="5" class="text-center">Tidak ada data detail</td>
                            </tr>
                        `);
                        return;
                    }
                    tbody.empty();
                
                    // Untuk Draft tanpa nomor, gunakan min_id
                    let ajaxData = {};
                    if (currentIsDraftNoNomor && currentMinId > 0) {
                        ajaxData = { min_id: currentMinId };
                    } else if (currentNomorPeminjaman) {
                        ajaxData = { nomor_peminjaman: currentNomorPeminjaman };
                    } else {
                        tbody.empty().append(`
                            <tr id="emptyRow">
                                <td colspan="5" class="text-center text-danger">Error: Tidak ada identifier yang valid</td>
                            </tr>
                        `);
                        return;
                    }
                
                    $.ajax({
                        url: "get_data.php?action=get_peminjaman_detail",
                        type: "POST",
                        data: ajaxData,
                        dataType: "json",
                        success: function(response) {
                            tbody.empty();
                            if (response.length > 0) {
                                
                                response.forEach((item, index) => {
                                    const isDraft = window.currentStatusPeminjaman === 'Draft';
                                    // Untuk Draft: hanya produk dan jumlah yang bisa diedit
                                    const produkDisabledAttr = isDraft ? '' : 'disabled';
                                    const jumlahReadonlyAttr = isDraft ? '' : 'readonly';
                                    const deleteDisabledAttr = ''; // Tombol hapus selalu enabled
                                    
                                    let row = $(`
                                        <tr>
                                            <td class="text-center">${index + 1}</td>
                                            <td>
                                                <select name="in_produk[]" class="form-control select-produk" ${produkDisabledAttr}>
                                                    <?php echo $productOptions; ?>
                                                </select>
                                            </td>
                                            <td class="text-center">
                                                <span class="stok-display" style="display: inline-block; padding: 6px 12px; font-weight: bold; color: #495057;">-</span>
                                            </td>
                                            <td class="text-center">
                                                <input type="number" name="in_jumlah[]" value="${item.qty}" class="form-control text-center" min="0" required ${jumlahReadonlyAttr}>
                                                <input type="hidden" name="in_id[]" value="${item.id}" class="form-control text-center">
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-danger btn-sm btnDeleteRow" data-id="${item.id}" ${deleteDisabledAttr}>
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    `);
                                    tbody.append(row);
                    
                                    // Inisialisasi Select2 setelah elemen ditambahkan ke DOM
                                    row.find(".select-produk").select2({
                                        dropdownParent: $('#tambahModal'),
                                        width: '100%'
                                    });
                    
                                    // Set nilai dari database jika ada - pastikan produk muncul
                                    if (item.produk) {
                                        // Set nilai produk dengan delay untuk memastikan Select2 sudah terinisialisasi
                                        setTimeout(function() {
                                            row.find(".select-produk").val(item.produk).trigger('change');
                                            // Ambil stok setelah produk dipilih
                                            // PENTING: Stok diambil dari Gudang Dipinjam (in_gudang_tujuan), bukan Gudang Peminjam
                                            const gudangDipinjam = $('#in_gudang_tujuan').val(); // Stok dari gudang dipinjam
                                            if (gudangDipinjam) {
                                                $.post("get_data.php?action=get_stok_by_produk", {
                                                    produk: item.produk,
                                                    gudang: gudangDipinjam
                                                }, function(response) {
                                                    try {
                                                        const data = typeof response === 'string' ? JSON.parse(response) : response;
                                                        if (data.status === 'success') {
                                                            const stok = data.stok || 0;
                                                            const $stokDisplay = row.find('.stok-display');
                                                            $stokDisplay.text(stok).css('color', stok > 0 ? '#28a745' : '#dc3545');
                                                            
                                                            // Re-validate jumlah setelah stok berubah
                                                            const jumlahInput = row.find('input[name="in_jumlah[]"]');
                                                            const jumlah = parseFloat(jumlahInput.val()) || 0;
                                                            
                                                            // Hapus error sebelumnya
                                                            jumlahInput.removeClass('is-invalid');
                                                            $stokDisplay.removeClass('text-danger');
                                                            row.find('.invalid-feedback').remove();
                                                            
                                                            // Validasi ulang: jumlah tidak boleh > stok
                                                            if (stok > 0 && jumlah > stok) {
                                                                jumlahInput.addClass('is-invalid');
                                                                $stokDisplay.addClass('text-danger');
                                                                if (!row.find('.invalid-feedback').length) {
                                                                    $stokDisplay.after('<div class="invalid-feedback d-block" style="font-size: 0.875rem;">Jumlah tidak boleh melebihi stok!</div>');
                                                                }
                                                            }
                                                            
                                                            updateSubmitButtonState();
                                                        }
                                                    } catch (e) {
                                                        // Ignore error
                                                    }
                                                }, 'json');
                                            }
                                        }, 200);
                                    }
                                    
                                    // Event handler untuk mengambil stok ketika produk dipilih
                                    // PENTING: Stok diambil dari Gudang Dipinjam (in_gudang_tujuan), bukan Gudang Peminjam
                                    row.find('select[name="in_produk[]"]').on('change', function() {
                                        const produk = $(this).val();
                                        const gudangDipinjam = $('#in_gudang_tujuan').val(); // Stok dari gudang dipinjam
                                        const $stokDisplay = $(this).closest('tr').find('.stok-display');
                                        
                                        if (produk && gudangDipinjam) {
                                            // Ambil stok dari server - menggunakan gudang dipinjam
                                            $.post("get_data.php?action=get_stok_by_produk", {
                                                produk: produk,
                                                gudang: gudangDipinjam
                                            }, function(response) {
                                                try {
                                                    const data = typeof response === 'string' ? JSON.parse(response) : response;
                                                    if (data.status === 'success') {
                                                        const stok = data.stok || 0;
                                                        $stokDisplay.text(stok).css('color', stok > 0 ? '#28a745' : '#dc3545');
                                                        
                                                        // Re-validate jumlah setelah stok berubah
                                                        const jumlahInput = $row.find('input[name="in_jumlah[]"]');
                                                        const jumlah = parseFloat(jumlahInput.val()) || 0;
                                                        
                                                        // Hapus error sebelumnya
                                                        jumlahInput.removeClass('is-invalid');
                                                        $stokDisplay.removeClass('text-danger');
                                                        $row.find('.invalid-feedback').remove();
                                                        
                                                        // Validasi ulang: jumlah tidak boleh > stok
                                                        if (stok > 0 && jumlah > stok) {
                                                            jumlahInput.addClass('is-invalid');
                                                            $stokDisplay.addClass('text-danger');
                                                            if (!$row.find('.invalid-feedback').length) {
                                                                $stokDisplay.after('<div class="invalid-feedback d-block" style="font-size: 0.875rem;">Jumlah tidak boleh melebihi stok!</div>');
                                                            }
                                                        }
                                                    } else {
                                                        $stokDisplay.text('-').css('color', '#495057');
                                                    }
                                                } catch (e) {
                                                    $stokDisplay.text('-').css('color', '#495057');
                                                }
                                            }, 'json').fail(function() {
                                                $stokDisplay.text('-').css('color', '#495057');
                                            });
                                        } else {
                                            $stokDisplay.text('-').css('color', '#495057');
                                        }
                                        
                                        updateSubmitButtonState();
                                    });
                                    
                                    // Event handler untuk validasi real-time - cek jumlah tidak boleh > stok
                                    row.find('input[name="in_jumlah[]"]').on('input change', function() {
                                        const $input = $(this);
                                        const $row = $input.closest('tr');
                                        const jumlah = parseFloat($input.val()) || 0;
                                        const stokText = $row.find('.stok-display').text();
                                        const stok = stokText !== '-' ? parseFloat(stokText) || 0 : 0;
                                        
                                        // Hapus class error sebelumnya
                                        $input.removeClass('is-invalid');
                                        $row.find('.stok-display').removeClass('text-danger');
                                        
                                        // Validasi: jumlah tidak boleh > stok
                                        if (stok > 0 && jumlah > stok) {
                                            $input.addClass('is-invalid');
                                            $row.find('.stok-display').addClass('text-danger');
                                            // Tampilkan tooltip atau pesan
                                            if (!$row.find('.invalid-feedback').length) {
                                                $row.find('.stok-display').after('<div class="invalid-feedback d-block" style="font-size: 0.875rem;">Jumlah tidak boleh melebihi stok!</div>');
                                            }
                                        } else {
                                            $row.find('.invalid-feedback').remove();
                                        }
                                        
                                        updateSubmitButtonState();
                                    });
                                });
                                setTimeout(updateSubmitButtonState, 300);
                            } else {
                                tbody.append(`
                                    <tr id="emptyRow">
                                        <td colspan="5" class="text-center">Tidak ada data detail</td>
                                    </tr>
                                `);
                            }
                        },
                        error: function(xhr, status, error) {
                            tbody.empty().append(`
                                <tr id="emptyRow">
                                    <td colspan="5" class="text-center text-danger">Error: Gagal mengambil data detail. Status: ${xhr.status}</td>
                                </tr>
                            `);
                            alert("Gagal mengambil data detail! Status: " + xhr.status + ". Cek console untuk detail error.");
                        }
                    });

                });
                
                $("#edit_id").val($(this).data('id'));
                $("#in_tanggal").val($(this).data('tanggal'));
                const entitasPeminjamVal = $(this).data('entitas_peminjam');
                const entitasDipinjamVal = $(this).data('entitas_dipinjam');
                const gudangAsalVal = $(this).data('gudang_asal');
                const gudangTujuanVal = $(this).data('gudang_tujuan');
                
                // Set entitas peminjam dan dipinjam, lalu load gudang
                $("#in_entitas_peminjam").val(entitasPeminjamVal).trigger('change');
                $("#in_entitas_dipinjam").val(entitasDipinjamVal).trigger('change');
                
                // Load gudang setelah entitas dipilih, lalu set nilai
                setTimeout(function() {
                    loadGudangByEntitas(entitasPeminjamVal, $("#in_gudang_asal"), function() {
                        $("#in_gudang_asal").val(gudangAsalVal).trigger('change');
                    });
                    loadGudangByEntitas(entitasDipinjamVal, $("#in_gudang_tujuan"), function() {
                        $("#in_gudang_tujuan").val(gudangTujuanVal).trigger('change');
                        // Update stok setelah gudang dipinjam dipilih
                        setTimeout(function() {
                            updateAllStok();
                        }, 300);
                    });
                }, 500);
                
                // Set nomor peminjaman (bisa kosong untuk Draft)
                if (nomor_peminjaman && nomor_peminjaman !== '') {
                    $("#in_nomor_peminjaman").val(nomor_peminjaman);
                } else {
                    $("#in_nomor_peminjaman").val("");
                }
                
                // Disable form fields jika status bukan Draft
                if (statusPeminjaman !== 'Draft') {
                    $("#in_tanggal, #in_entitas_peminjam, #in_entitas_dipinjam, #in_gudang_asal, #in_gudang_tujuan, #in_nomor_peminjaman").prop('disabled', true);
                    $("#addRowButton").prop('disabled', true);
                    $("#draftButton, #submitButton").prop('disabled', true);
                    // Disable semua field di tabel detail
                    $("#tbodyListData select, #tbodyListData input[type='number']").prop('disabled', true);
                    alert("Dokumen dengan status '" + statusPeminjaman + "' tidak dapat diedit. Hanya dokumen dengan status 'Draft' yang dapat diedit.");
                } else {
                    // Untuk Draft: hanya produk dan jumlah yang bisa diedit
                    // Disable field header (tanggal, entitas, gudang peminjam, gudang dipinjam, nomor peminjaman)
                    $("#in_tanggal, #in_entitas_peminjam, #in_entitas_dipinjam, #in_gudang_asal, #in_gudang_tujuan, #in_nomor_peminjaman").prop('disabled', true);
                    // Enable tombol dan field detail (hanya produk dan jumlah)
                    $("#addRowButton").prop('disabled', false);
                    $("#draftButton, #submitButton").prop('disabled', false);
                    // Enable hanya produk (select) dan jumlah (input number)
                    $("#tbodyListData select[name='in_produk[]'], #tbodyListData input[name='in_jumlah[]']").prop('disabled', false);
                    // Untuk Draft, tombol hapus enabled (tidak perlu disable karena sudah di dalam blok Draft)
                }
            });

        // reset
        $("#tambahModal").on("hidden.bs.modal", function() {
            window.isEditMode = false;
            window.editNomorPeminjaman = '';
            window.editMinId = 0;
            window.editIsDraftNoNomor = false;
            let tbody = $("#tbodyListData");
            tbody.empty();
            $("#tambahModalLabel").text("Tambah Peminjaman Stok");
            $("#tambahForm")[0].reset();
            $("#in_entitas_peminjam, #in_entitas_dipinjam, #in_gudang_asal, #in_gudang_tujuan").val(null).trigger("change");
            $("#in_gudang_asal").prop('disabled', true).html('<option value="">-- Pilih Entitas Peminjam dulu --</option>');
            $("#in_gudang_tujuan").prop('disabled', true).html('<option value="">-- Pilih Entitas Dipinjam dulu --</option>');
            $("#edit_id").val("");
            $("#in_nomor_peminjaman").val("");
            $("#submitButton").text("Simpan sebagai Final").removeClass("btn-warning").addClass("btn-primary");
            $("#draftButton").text("Simpan sebagai Draft");
            // Enable semua field saat reset
            $("#in_tanggal, #in_entitas_peminjam, #in_entitas_dipinjam, #in_gudang_asal, #in_gudang_tujuan, #in_nomor_peminjaman").prop('disabled', false);
            $("#addRowButton, #draftButton, #submitButton").prop('disabled', false);
        });
        
        // Caching selector
        const $tbody         = $("#tbodyListData"),
            $detailTable   = $("#detailTable"),
            $addRowButton  = $("#addRowButton");

        // Fungsi untuk menambahkan satu row ke tabel
        function addRow() {
            // Jika ada baris pesan, hapus dulu
            $("#emptyRow").remove();
            const count = $tbody.find("tr").length + 1;
            // Cek apakah sedang edit draft (field header disabled)
            const isEditDraft = $("#in_tanggal").prop('disabled') && window.currentStatusPeminjaman === 'Draft';
            // Tombol hapus selalu enabled
            const deleteDisabledAttr = '';
            const $newRow = $(`
                <tr>
                    <td class="text-center">${count}</td>
                    <td>
                        <select name="in_produk[]" class="form-control select-produk select2" required>
                            <?php echo $productOptions; ?>
                        </select>
                    </td>
                    <td class="text-center">
                        <span class="stok-display" style="display: inline-block; padding: 6px 12px; font-weight: bold; color: #495057;">-</span>
                    </td>
                    <td class="text-center">
                        <input type="number" class="form-control" name="in_jumlah[]" min="0" value="0" required>
                        <input type="hidden" name="in_id[]" value="">
                    </td>
                    <td width="5%">
                        <button type="button" class="btn btn-sm btn-danger btnDeleteRow" ${deleteDisabledAttr}>
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </td>
                </tr>
            `);
            $tbody.append($newRow);
            
            $newRow.find('.select2').select2({
                dropdownParent: $('#tambahModal'),
                width: '100%'
            }).on('select2:open', function () {
                setTimeout(() => {
                    let searchField = document.querySelector('.select2-container--open .select2-search__field');
                    if (searchField) {
                        searchField.focus();
                    }
                }, 100);
            });
            
            // Event handler untuk mengambil stok ketika produk dipilih
            // PENTING: Stok diambil dari Gudang Dipinjam (in_gudang_tujuan), bukan Gudang Peminjam
            $newRow.find('select[name="in_produk[]"]').on('change', function() {
                const produk = $(this).val();
                const gudangDipinjam = $('#in_gudang_tujuan').val(); // Stok dari gudang dipinjam
                const $stokDisplay = $(this).closest('tr').find('.stok-display');
                
                if (produk && gudangDipinjam) {
                    // Ambil stok dari server - menggunakan gudang dipinjam
                    $.post("get_data.php?action=get_stok_by_produk", {
                        produk: produk,
                        gudang: gudangDipinjam
                    }, function(response) {
                        try {
                            const data = typeof response === 'string' ? JSON.parse(response) : response;
                            if (data.status === 'success') {
                                const stok = data.stok || 0;
                                $stokDisplay.text(stok).css('color', stok > 0 ? '#28a745' : '#dc3545');
                                
                                // Re-validate jumlah setelah stok berubah
                                const $row = $stokDisplay.closest('tr');
                                const jumlahInput = $row.find('input[name="in_jumlah[]"]');
                                const jumlah = parseFloat(jumlahInput.val()) || 0;
                                
                                // Hapus error sebelumnya
                                jumlahInput.removeClass('is-invalid');
                                $stokDisplay.removeClass('text-danger');
                                $row.find('.invalid-feedback').remove();
                                
                                // Validasi ulang: jumlah tidak boleh > stok
                                if (stok > 0 && jumlah > stok) {
                                    jumlahInput.addClass('is-invalid');
                                    $stokDisplay.addClass('text-danger');
                                    if (!$row.find('.invalid-feedback').length) {
                                        $stokDisplay.after('<div class="invalid-feedback d-block" style="font-size: 0.875rem;">Jumlah tidak boleh melebihi stok!</div>');
                                    }
                                }
                            } else {
                                $stokDisplay.text('-').css('color', '#495057');
                            }
                        } catch (e) {
                            $stokDisplay.text('-').css('color', '#495057');
                        }
                    }, 'json').fail(function() {
                        $stokDisplay.text('-').css('color', '#495057');
                    });
                } else {
                    $stokDisplay.text('-').css('color', '#495057');
                }
                
                updateSubmitButtonState();
            });
            
            // Event handler untuk validasi real-time - cek jumlah tidak boleh > stok
            $newRow.find('input[name="in_jumlah[]"]').on('input change', function() {
                const $input = $(this);
                const $row = $input.closest('tr');
                const jumlah = parseFloat($input.val()) || 0;
                const stokText = $row.find('.stok-display').text();
                const stok = stokText !== '-' ? parseFloat(stokText) || 0 : 0;
                
                // Hapus class error sebelumnya
                $input.removeClass('is-invalid');
                $row.find('.stok-display').removeClass('text-danger');
                
                // Validasi: jumlah tidak boleh > stok
                if (stok > 0 && jumlah > stok) {
                    $input.addClass('is-invalid');
                    $row.find('.stok-display').addClass('text-danger');
                    // Tampilkan tooltip atau pesan
                    if (!$row.find('.invalid-feedback').length) {
                        $row.find('.stok-display').after('<div class="invalid-feedback d-block" style="font-size: 0.875rem;">Jumlah tidak boleh melebihi stok!</div>');
                    }
                } else {
                    $row.find('.invalid-feedback').remove();
                }
                
                updateSubmitButtonState();
            });
        }
        
        // Fungsi untuk update status tombol submit berdasarkan validasi
        
        // Tambah row saat tombol diklik
        $addRowButton.click(function(){
            addRow();
            setTimeout(updateSubmitButtonState, 100);
        });
        
        // Tambahkan satu row default
        // addRow();

        // Hapus row dan perbarui nomor urut
        $(document).on("click", ".btnDeleteRow", function(){
            let rowsCount = $tbody.find("tr").length;
            if(rowsCount <= 1){
                alert("Minimal harus ada 1 baris.");
                return;
            }
            $(this).closest("tr").remove();
            $tbody.find("tr").each(function(i){
                $(this).find("td:first").text(i + 1);
            });
            // Update status tombol submit setelah menghapus row
            updateSubmitButtonState();
        });

        // Load gudang berdasarkan entitas yang dipilih (global function)
        window.loadGudangByEntitas = function(entitas, targetSelect, callback) {
            if (!entitas) {
                targetSelect.prop('disabled', true).html('<option value="">-- Pilih Entitas dulu --</option>').trigger('change');
                if (callback) callback();
                return;
            }
            
            $.post("get_data.php?action=get_gudang_by_entitas", { entitas: entitas }, function (response) {
                try {
                    // Handle response yang sudah berupa object atau masih string
                    const data = typeof response === 'string' ? JSON.parse(response) : response;
                    let options = '<option value="">-- Pilih Gudang --</option>';
                    if (data.gudang && data.gudang.length > 0) {
                        data.gudang.forEach(function(g) {
                            options += '<option value="' + g.nama_gudang + '">' + g.nama_gudang + '</option>';
                        });
                    }
                    targetSelect.prop('disabled', false).html(options).trigger('change');
                    if (callback) callback();
                } catch (e) {
                    targetSelect.prop('disabled', true).html('<option value="">Error loading gudang</option>');
                    if (callback) callback();
                }
            }, 'json').fail(function (xhr, status, error) {
                targetSelect.prop('disabled', true).html('<option value="">Error loading gudang</option>');
                if (callback) callback();
            });
        }

        // Load gudang berdasarkan multiple entitas (untuk filter) - global function
        window.loadGudangByMultipleEntitas = function(entitasArray, targetSelect, callback) {
            if (!entitasArray || entitasArray.length === 0) {
                targetSelect.prop('disabled', true).html('<option value="">-- Pilih Entitas dulu --</option>').trigger('change');
                if (callback) callback();
                return;
            }
            
            // Jika hanya satu entitas, gunakan fungsi yang sudah ada
            if (entitasArray.length === 1) {
                window.loadGudangByEntitas(entitasArray[0], targetSelect, callback);
                return;
            }
            
            // Untuk multiple entitas, kirim sebagai array
            $.post("get_data.php?action=get_gudang_by_entitas", { entitas: entitasArray }, function (response) {
                try {
                    const data = typeof response === 'string' ? JSON.parse(response) : response;
                    let options = '<option value="">-- Pilih Gudang --</option>';
                    if (data.gudang && data.gudang.length > 0) {
                        // Hapus duplikat berdasarkan nama_gudang
                        const uniqueGudang = [];
                        const seen = {};
                        data.gudang.forEach(function(g) {
                            if (!seen[g.nama_gudang]) {
                                seen[g.nama_gudang] = true;
                                uniqueGudang.push(g);
                            }
                        });
                        uniqueGudang.forEach(function(g) {
                            options += '<option value="' + g.nama_gudang + '">' + g.nama_gudang + '</option>';
                        });
                    }
                    targetSelect.prop('disabled', false).html(options).trigger('change');
                    if (callback) callback();
                } catch (e) {
                    targetSelect.prop('disabled', true).html('<option value="">Error loading gudang</option>');
                    if (callback) callback();
                }
            }, 'json').fail(function (xhr, status, error) {
                targetSelect.prop('disabled', true).html('<option value="">Error loading gudang</option>');
                if (callback) callback();
            });
        }

        // Handler ketika entitas peminjam dipilih di form tambah/edit
        $("#in_entitas_peminjam").change(function() {
            const entitasPeminjam = $(this).val();
            const entitasDipinjam = $("#in_entitas_dipinjam").val();
            
            // Validasi: entitas peminjam dan entitas dipinjam tidak boleh sama
            if (entitasPeminjam && entitasDipinjam && entitasPeminjam === entitasDipinjam) {
                alert("Entitas Peminjam dan Entitas Dipinjam tidak boleh sama!");
                $(this).val('').trigger('change');
                $("#in_gudang_asal").prop('disabled', true).html('<option value="">-- Pilih Entitas Peminjam dulu --</option>').trigger('change');
                return;
            }
            
            if (entitasPeminjam) {
                loadGudangByEntitas(entitasPeminjam, $("#in_gudang_asal"));
            } else {
                $("#in_gudang_asal").prop('disabled', true).html('<option value="">-- Pilih Entitas Peminjam dulu --</option>').trigger('change');
            }
            
            // Clear nomor peminjaman jika entitas berubah (karena gudang akan di-reset)
            $("#in_nomor_peminjaman").val("");
            // Nomor peminjaman akan di-generate otomatis ketika gudang peminjam dipilih
        });
        
        // Handler ketika entitas dipinjam dipilih di form tambah/edit
        $("#in_entitas_dipinjam").change(function() {
            const entitasDipinjam = $(this).val();
            const entitasPeminjam = $("#in_entitas_peminjam").val();
            
            // Validasi: entitas peminjam dan entitas dipinjam tidak boleh sama
            if (entitasPeminjam && entitasDipinjam && entitasPeminjam === entitasDipinjam) {
                alert("Entitas Peminjam dan Entitas Dipinjam tidak boleh sama!");
                $(this).val('').trigger('change');
                $("#in_gudang_tujuan").prop('disabled', true).html('<option value="">-- Pilih Entitas Dipinjam dulu --</option>').trigger('change');
                return;
            }
            
            if (entitasDipinjam) {
                loadGudangByEntitas(entitasDipinjam, $("#in_gudang_tujuan"));
            } else {
                $("#in_gudang_tujuan").prop('disabled', true).html('<option value="">-- Pilih Entitas Dipinjam dulu --</option>').trigger('change');
            }
        });


        // Fungsi untuk update stok semua produk yang sudah dipilih
        // PENTING: Stok diambil dari Gudang Dipinjam (in_gudang_tujuan), bukan Gudang Peminjam
        function updateAllStok() {
            const gudangDipinjam = $('#in_gudang_tujuan').val(); // Stok dari gudang dipinjam
            if (!gudangDipinjam) {
                $('#tbodyListData tr').each(function() {
                    $(this).find('.stok-display').text('-').css('color', '#495057');
                });
                return;
            }
            
            $('#tbodyListData tr').each(function() {
                const $row = $(this);
                const produk = $row.find('select[name="in_produk[]"]').val();
                const $stokDisplay = $row.find('.stok-display');
                
                if (produk) {
                    $.post("get_data.php?action=get_stok_by_produk", {
                        produk: produk,
                        gudang: gudangDipinjam
                    }, function(response) {
                        try {
                            const data = typeof response === 'string' ? JSON.parse(response) : response;
                            if (data.status === 'success') {
                                const stok = data.stok || 0;
                                $stokDisplay.text(stok).css('color', stok > 0 ? '#28a745' : '#dc3545');
                                
                                // Re-validate jumlah setelah stok berubah
                                const $row = $stokDisplay.closest('tr');
                                const jumlahInput = $row.find('input[name="in_jumlah[]"]');
                                const jumlah = parseFloat(jumlahInput.val()) || 0;
                                
                                // Hapus error sebelumnya
                                jumlahInput.removeClass('is-invalid');
                                $stokDisplay.removeClass('text-danger');
                                $row.find('.invalid-feedback').remove();
                                
                                // Validasi ulang: jumlah tidak boleh > stok
                                if (stok > 0 && jumlah > stok) {
                                    jumlahInput.addClass('is-invalid');
                                    $stokDisplay.addClass('text-danger');
                                    if (!$row.find('.invalid-feedback').length) {
                                        $stokDisplay.after('<div class="invalid-feedback d-block" style="font-size: 0.875rem;">Jumlah tidak boleh melebihi stok!</div>');
                                    }
                                }
                                
                                updateSubmitButtonState();
                            } else {
                                $stokDisplay.text('-').css('color', '#495057');
                            }
                        } catch (e) {
                            $stokDisplay.text('-').css('color', '#495057');
                        }
                    }, 'json').fail(function() {
                        $stokDisplay.text('-').css('color', '#495057');
                    });
                } else {
                    $stokDisplay.text('-').css('color', '#495057');
                }
            });
        }
        
        $("#in_gudang_asal").change(function() {
            const gudangAsal = $(this).val();
            const gudangTujuan = $("#in_gudang_tujuan").val();
            if (gudangAsal && gudangTujuan && gudangAsal === gudangTujuan) {
                alert("Gudang Peminjam dan Gudang Dipinjam tidak boleh sama!");
                $(this).val('').trigger('change');
            }
            // Jika gudang peminjam dikosongkan, clear nomor peminjaman
            if (!gudangAsal) {
                $("#in_nomor_peminjaman").val("");
            }
            generateNomorPeminjaman();
        });
        
        // Handler ketika gudang dipinjam berubah - update stok semua produk
        $("#in_gudang_tujuan").change(function() {
            const gudangAsal = $("#in_gudang_asal").val();
            const gudangTujuan = $(this).val();
            if (gudangAsal && gudangTujuan && gudangAsal === gudangTujuan) {
                alert("Gudang Peminjam dan Gudang Dipinjam tidak boleh sama!");
                $(this).val('').trigger('change');
                return;
            }
            generateNomorPeminjaman();
            // Update stok semua produk yang sudah dipilih
            updateAllStok();
        });
        
        function generateNomorPeminjaman() {
            if (window.isEditMode) { 
                return; 
            }
            const in_entitas_peminjam = $('#in_entitas_peminjam').val();
            const in_gudang_asal = $('#in_gudang_asal').val();
            const in_gudang_tujuan = $('#in_gudang_tujuan').val();
            
            // Nomor peminjaman hanya muncul jika gudang peminjam diisi
            if (!in_gudang_asal) {
                $("#in_nomor_peminjaman").val("");
                return;
            }
            
            // Untuk Draft, nomor peminjaman tidak di-generate (kosong)
            // Nomor peminjaman hanya di-generate saat Final
            // Jadi kita tidak generate otomatis di sini, biarkan kosong
            // Nomor peminjaman akan di-generate di server side saat Final
            
            // Clear nomor peminjaman untuk Draft
            $("#in_nomor_peminjaman").val("");
            return;
        }

    });

    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Fungsi untuk inisialisasi Select2
            function initializeSelect2() {
                $('.select2').select2({
                    placeholder: "Pilih",
                    allowClear: true,
                    dropdownParent: $('#tambahModal'),
                    width: '100%'
                });
            }

            initializeSelect2();

            // Pastikan semua field di-enable saat tombol Tambah Data diklik
            $('#btnTambahData').on('click', function() {
                // Reset form dan enable semua field
                $("#edit_id").val("");
                $("#in_tanggal, #in_entitas_peminjam, #in_entitas_dipinjam, #in_gudang_asal, #in_gudang_tujuan, #in_nomor_peminjaman").prop('disabled', false);
                $("#addRowButton").prop('disabled', false);
                $("#draftButton, #submitButton").prop('disabled', false);
                // Enable semua field di tabel detail yang sudah ada
                $("#tbodyListData select, #tbodyListData input[type='number']").prop('disabled', false);
            });
            
            // Pastikan semua field di-enable saat modal dibuka untuk tambah baru
            $('#tambahModal').on('show.bs.modal', function () {
                // Jika bukan mode edit (tidak ada edit_id), enable semua field
                if (!$("#edit_id").val() || $("#edit_id").val() === '') {
                    $("#in_tanggal, #in_entitas_peminjam, #in_entitas_dipinjam, #in_gudang_asal, #in_gudang_tujuan, #in_nomor_peminjaman").prop('disabled', false);
                    $("#addRowButton").prop('disabled', false);
                    $("#draftButton, #submitButton").prop('disabled', false);
                    // Enable semua field di tabel detail yang sudah ada
                    $("#tbodyListData select, #tbodyListData input[type='number']").prop('disabled', false);
                }
            });
            
            $('#tambahModal').on('shown.bs.modal', function () {
                initializeSelect2();
            });
        });
        
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.actionAddData button[type="submit"]').forEach(button => {
                button.addEventListener('click', function (e) {
                    e.preventDefault();

                    const actionType = this.value;

                    // Validasi entitas peminjam dan entitas dipinjam tidak boleh sama
                    const entitasPeminjam = $('#in_entitas_peminjam').val();
                    const entitasDipinjam = $('#in_entitas_dipinjam').val();
                    
                    if (entitasPeminjam && entitasDipinjam && entitasPeminjam === entitasDipinjam) {
                        alert('Validasi Gagal:\n\nEntitas Peminjam dan Entitas Dipinjam tidak boleh sama!');
                        $('#in_entitas_peminjam, #in_entitas_dipinjam').addClass('is-invalid');
                        return false;
                    } else {
                        $('#in_entitas_peminjam, #in_entitas_dipinjam').removeClass('is-invalid');
                    }

                    // Validasi form
                    let isValid = true;
                    let errorMessage = '';
                    $('#tbodyListData tr').each(function() {
                        const row = $(this);
                        const jumlahInput = row.find('input[name="in_jumlah[]"]');
                        const produkSelect = row.find('select[name="in_produk[]"]');
                        const stokText = row.find('.stok-display').text();
                        const stok = stokText !== '-' ? parseFloat(stokText) || 0 : 0;
                        
                        if (jumlahInput.length && produkSelect.length) {
                            const jumlah = parseFloat(jumlahInput.val()) || 0;
                            const produk = produkSelect.val();
                            const produkNama = produkSelect.find('option:selected').text();
                            
                            // Validasi: jumlah harus > 0
                            if (produk && jumlah <= 0) {
                                isValid = false;
                                errorMessage += `Jumlah untuk produk "${produkNama}" harus lebih dari 0.\n`;
                                jumlahInput.addClass('is-invalid');
                            }
                            // Validasi: jumlah tidak boleh > stok
                            else if (produk && stok > 0 && jumlah > stok) {
                                isValid = false;
                                errorMessage += `Jumlah untuk produk "${produkNama}" (${jumlah}) tidak boleh melebihi stok yang tersedia (${stok}).\n`;
                                jumlahInput.addClass('is-invalid');
                                row.find('.stok-display').addClass('text-danger');
                            } else {
                                jumlahInput.removeClass('is-invalid');
                                row.find('.stok-display').removeClass('text-danger');
                            }
                        }
                    });

                    if (!isValid) {
                        alert('Validasi Gagal:\n\n' + errorMessage);
                        return false;
                    }

                    // Pastikan semua field tidak disabled sebelum submit
                    // Field yang disabled tidak akan dikirim ke server saat form disubmit
                    const fieldsToEnable = ['in_tanggal', 'in_entitas_peminjam', 'in_entitas_dipinjam', 'in_gudang_asal', 'in_gudang_tujuan'];
                    fieldsToEnable.forEach(function(fieldId) {
                        const $field = $('#' + fieldId);
                        if ($field.length && $field.prop('disabled')) {
                            $field.prop('disabled', false);
                            // Jika field menggunakan Select2, trigger update
                            if ($field.hasClass('select2-hidden-accessible')) {
                                $field.trigger('change.select2');
                            }
                        }
                    });
                    
                    // Tunggu sebentar untuk memastikan Select2 ter-update (gunakan setTimeout dengan callback)
                    // Note: Ini dilakukan secara synchronous dengan delay minimal
                    
                    // Jika update sebagai Final dan tanggal kosong, set tanggal hari ini
                    // Lakukan ini SEBELUM membuat FormData agar nilai tanggal ikut terkirim
                    if (actionType === 'Final' && window.isEditMode) {
                        const $tanggalField = $('#in_tanggal');
                        if (!$tanggalField.val() || $tanggalField.val().trim() === '') {
                            const today = new Date();
                            const year = today.getFullYear();
                            const month = String(today.getMonth() + 1).padStart(2, '0');
                            const day = String(today.getDate()).padStart(2, '0');
                            const todayStr = `${year}-${month}-${day}`;
                            $tanggalField.val(todayStr);
                            // Trigger change event untuk memastikan nilai ter-update
                            $tanggalField.trigger('change');
                        }
                    }
                    
                    const formData = new FormData(document.getElementById('tambahForm'));
                    formData.append('in_action_button', actionType);
                    
                    // Pastikan nilai gudang terkirim (backup jika masih ada masalah dengan disabled field)
                    const gudangAsal = $('#in_gudang_asal').val();
                    const gudangTujuan = $('#in_gudang_tujuan').val();
                    if (gudangAsal) {
                        formData.append('in_gudang_asal', gudangAsal);
                    }
                    if (gudangTujuan) {
                        formData.append('in_gudang_tujuan', gudangTujuan);
                    }
                    
                    // Debug: log data yang akan dikirim (hapus di production)
                    console.log('Form data being sent:', {
                        in_gudang_asal: gudangAsal,
                        in_gudang_tujuan: gudangTujuan,
                        in_entitas_peminjam: $('#in_entitas_peminjam').val(),
                        in_entitas_dipinjam: $('#in_entitas_dipinjam').val(),
                        in_tanggal: $('#in_tanggal').val(),
                        produk_count: $('select[name="in_produk[]"]').length,
                        jumlah_count: $('input[name="in_jumlah[]"]').length,
                        action_button: actionType
                    });

                    const editIdEl = document.getElementById('edit_id');
                    const editIdVal = editIdEl ? editIdEl.value : '';
                    const endpointUrl = (window.isEditMode === true) ? 'action.php?act=update' : 'action.php?act=add';

                    $.ajax({
                        type: 'POST',
                        url: endpointUrl,
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function (response) {
                            try {
                                // Clean response jika ada whitespace atau karakter tidak valid
                                let cleanResponse = typeof response === 'string' ? response.trim() : response;
                                
                                // Parse JSON
                                const result = typeof cleanResponse === 'object' ? cleanResponse : JSON.parse(cleanResponse);
                                
                                if (result.status === 'success') {
                                    // Hapus focus dari elemen yang terfokus sebelum menutup modal
                                    if (document.activeElement) {
                                        document.activeElement.blur();
                                    }
                                    
                                    // Tutup modal dan tunggu sampai benar-benar ditutup
                                    $('#tambahModal').one('hidden.bs.modal', function() {
                                        const statusMessage = actionType === 'Draft' ? 'Data berhasil disimpan sebagai Draft. Anda masih dapat mengedit dokumen ini.' : 'Data berhasil disimpan sebagai Final. Dokumen tidak dapat diedit lagi.';
                                        alert(statusMessage);
                                        
                                        // Refresh DataTables tanpa reload halaman
                                        if (typeof tablePeminjaman !== 'undefined' && tablePeminjaman) {
                                            try {
                                                tablePeminjaman.ajax.reload(null, false); // false = keep current page
                                            } catch (reloadError) {
                                                console.error('Error reloading DataTables:', reloadError);
                                                // Fallback: reload halaman jika DataTables error
                                                location.reload();
                                            }
                                        } else {
                                            location.reload();
                                        }
                                    });
                                    
                                    $('#tambahModal').modal('hide');
                                } else {
                                    alert(result.message || 'Terjadi kesalahan saat menyimpan data.');
                                }
                            } catch (e) {
                                console.error('Error parsing response:', e, response);
                                alert('Gagal memproses respons dari server. Silakan refresh halaman dan coba lagi.');
                                // Fallback: reload halaman jika parsing error
                                setTimeout(function() {
                                    location.reload();
                                }, 2000);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX Error:', {
                                status: xhr.status,
                                statusText: xhr.statusText,
                                responseText: xhr.responseText,
                                error: error
                            });
                            
                            let errorMessage = 'Gagal mengirim data! Data Peminjaman belum lengkap, mohon cek kembali & lengkapi data dengan benar!';
                            
                            // Coba parse error response jika ada
                            if (xhr.responseText) {
                                try {
                                    const errorResponse = JSON.parse(xhr.responseText);
                                    if (errorResponse.message) {
                                        errorMessage = errorResponse.message;
                                    }
                                } catch (e) {
                                    // Jika bukan JSON, gunakan responseText langsung (potong jika terlalu panjang)
                                    if (xhr.responseText.length < 200) {
                                        errorMessage = xhr.responseText;
                                    }
                                }
                            }
                            
                            alert(errorMessage);
                        },
                        dataType: 'json' // Explicitly set dataType to JSON
                    });
                });
            });

        });

        $(function() {
            $('.select2').select2();
            
            // Inisialisasi validasi tombol submit saat halaman dimuat
            setTimeout(function() {
                updateSubmitButtonState();
            }, 500);

            // Handler untuk card Peminjaman dan Pengembalian
            let selectedType = 'peminjaman'; // Default: peminjaman
            
            // Inisialisasi card berdasarkan URL parameter atau default
            const urlParams = new URLSearchParams(window.location.search);
            const typeParam = urlParams.get('type');
            if (typeParam === 'pengembalian') {
                selectedType = 'pengembalian';
            }
            
            // Update tampilan card berdasarkan selectedType
            function updateCardDisplay() {
                $('.type-card').each(function() {
                    const card = $(this);
                    const type = card.data('type');
                    const cardBody = card.find('.card-body');
                    
                    if (type === selectedType) {
                        // Card terpilih: biru
                        cardBody.css({
                            'background-color': '#007bff',
                            'color': 'white'
                        });
                    } else {
                        // Card tidak terpilih: putih
                        cardBody.css({
                            'background-color': '#ffffff',
                            'color': '#333'
                        });
                    }
                });
            }
            
            // Fungsi untuk load count untuk kedua card
            function loadCardCounts() {
                // Load count peminjaman
                $.get('get_data.php', {
                    action: 'get_data',
                    type: 'peminjaman',
                    start_date: '<?= $start_date_default2 ?>',
                    end_date: '<?= $end_date_default1 ?>',
                    draw: 1,
                    start: 0,
                    length: 1
                }, function(response) {
                    if (response && response.recordsTotal !== undefined) {
                        $('#countPeminjaman').text(response.recordsTotal);
                    }
                }, 'json');
                
                // Load count pengembalian
                $.get('get_data.php', {
                    action: 'get_data',
                    type: 'pengembalian',
                    start_date: '<?= $start_date_default2 ?>',
                    end_date: '<?= $end_date_default1 ?>',
                    draw: 1,
                    start: 0,
                    length: 1
                }, function(response) {
                    if (response && response.recordsTotal !== undefined) {
                        $('#countPengembalian').text(response.recordsTotal);
                    }
                }, 'json');
            }
            
            // Handler click pada card
            $('.type-card').on('click', function() {
                const clickedType = $(this).data('type');
                
                // Jika klik card pengembalian, redirect ke halaman pengembalian
                if (clickedType === 'pengembalian') {
                    window.location.href = '../pengembalian/index.php?type=pengembalian';
                    return;
                }
                
                // Jika klik card peminjaman, tetap di halaman ini dan tampilkan data peminjaman
                selectedType = 'peminjaman';
                updateCardDisplay();
                
                // Update URL tanpa reload
                const url = new URL(window.location);
                url.searchParams.set('type', 'peminjaman');
                window.history.pushState({}, '', url);
                
                // Reload DataTables dengan filter type peminjaman
                if (typeof tablePeminjaman !== 'undefined' && tablePeminjaman) {
                    // Destroy table yang lama
                    tablePeminjaman.destroy();
                    tablePeminjaman = null;
                }
                
                // Reinitialize dengan parameter type peminjaman
                initDataTable();
                
                // Reload count untuk kedua card
                loadCardCounts();
            });
            
            // Inisialisasi tampilan card saat halaman dimuat
            updateCardDisplay();
            
            // Load count untuk kedua card saat halaman dimuat
            loadCardCounts();

            var start       = moment().subtract(29, 'days');
            var end         = moment();
            var startDate   = "<?= $start_date_default2?>";
            var endDate     = "<?= $end_date_default1 ?>";

            if (startDate && endDate) {
                start       = moment(startDate);
                end         = moment(endDate);
            }

            function cb(start, end) {
                $('#daterange span').html(start.format('D MMMM YYYY') + ' - ' + end.format('D MMMM YYYY'));
                var startInGMT7 = start.utcOffset(420);
                var endInGMT7 = end.utcOffset(420);

                $('#start_date').val(startInGMT7.startOf('day').format('YYYY-MM-DD'));
                $('#end_date').val(endInGMT7.endOf('day').format('YYYY-MM-DD'));
            }

            $('#daterange').daterangepicker({
                startDate: start,
                endDate: end,
                ranges: {
                    'Today': [moment(), moment()],
                    'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                    'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                    'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                    'This Month': [moment().startOf('month'), moment().endOf('month')],
                    'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
                }
            }, cb);

            cb(start, end);
        });

        function formatDate(date) {
            var d       = new Date(date),
                month   = '' + (d.getMonth() + 1),
                day     = '' + d.getDate(),
                year    = d.getFullYear();

            if (month.length < 2) 
                month   = '0' + month;
            if (day.length < 2) 
                day     = '0' + day;

            return [year, month, day].join('-');
        }

        // Function to filter data
        $('#filterButton').on('click', function() {
            var selectedEntitasPeminjam = $('#entitas_peminjam').val();
            var selectedEntitasDipinjam = $('#entitas_dipinjam').val();
            var dateRange            = $('#daterange span').html().split(' - ');
            var startDate            = formatDate(dateRange[0]);
            var endDate              = formatDate(dateRange[1]);
            
            var newUrl = 'index.php?start_date=' + startDate + '&end_date=' + endDate;
            if (selectedEntitasPeminjam && selectedEntitasPeminjam.length > 0) {
                newUrl += '&entitas_peminjam=' + selectedEntitasPeminjam.join(',');
            }
            if (selectedEntitasDipinjam && selectedEntitasDipinjam.length > 0) {
                newUrl += '&entitas_dipinjam=' + selectedEntitasDipinjam.join(',');
            }
            
            window.history.pushState({}, '', newUrl);
            initDataTable();
        });

        $('#filterFormContainer').on('show.bs.collapse', function () {
            $('#toggleIcon').removeClass('fa-chevron-down').addClass('fa-chevron-up');
        });
        $('#filterFormContainer').on('hide.bs.collapse', function () {
            $('#toggleIcon').removeClass('fa-chevron-up').addClass('fa-chevron-down');
        });

        $('#resetButton').on('click', function() {
            window.history.pushState({}, '', 'index.php');
            
            $('#filterForm')[0].reset();
            $('#entitas_peminjam, #entitas_dipinjam').val(null).trigger('change');
            
            var defaultStartDate = '<?= $start_date_default2 ?>';
            var defaultEndDate = '<?= $end_date_default1 ?>';
            $('#daterange span').html(moment(defaultStartDate).format('DD/MM/YYYY') + ' - ' + moment(defaultEndDate).format('DD/MM/YYYY'));
            $('#start_date').val(defaultStartDate);
            $('#end_date').val(defaultEndDate);
            
            initDataTable();
        });

        function deletepeminjaman(identifier) {
            if (confirm("Apakah Anda yakin ingin menghapus Peminjaman " + identifier + "?")) {
                $.ajax({
                    type: 'POST',
                    url: 'action.php?act=delete',
                    data: { identifier: identifier },
                    success: function(response) {
                        try {
                            var result = typeof response === 'object' ? response : JSON.parse(response);
                            if (result.status === 'success') {
                                // Jika Draft, reload DataTables tanpa refresh halaman
                                if (result.is_draft) {
                                    // Tampilkan notifikasi
                                    alert(result.message);
                                    // Reload DataTables untuk update data
                                    if (typeof tablePeminjaman !== 'undefined' && tablePeminjaman) {
                                        tablePeminjaman.ajax.reload(null, false); // false = keep current page
                                    } else {
                                        location.reload();
                                    }
                                } else {
                                    // Untuk Final, reload halaman seperti biasa
                                    alert(result.message);
                                    location.reload();
                                }
                            } else {
                                alert("Gagal menghapus Peminjaman: " + result.message);
                            }
                        } catch (e) {
                            alert("Terjadi kesalahan saat memproses data.");
                        }
                    },
                    error: function(xhr, status, error) {
                        alert("Gagal mengirim data.");
                    }
                });
            }
        }

        // Variable untuk menyimpan instance DataTable
        let tablePeminjaman;

        // Function untuk inisialisasi DataTable
        function initDataTable() {
            // Destroy existing table if exists
            if (tablePeminjaman) {
                tablePeminjaman.destroy();
            }

            // Ambil parameter dari URL
            const urlParams = new URLSearchParams(window.location.search);
            const startDate = urlParams.get('start_date') || '<?= $start_date_default2 ?>';
            const endDate = urlParams.get('end_date') || '<?= $end_date_default1 ?>';
            const entitasPeminjam = urlParams.get('entitas_peminjam') || '';
            const entitasDipinjam = urlParams.get('entitas_dipinjam') || '';
            const type = urlParams.get('type') || 'peminjaman';

            tablePeminjaman = $('#table-peminjaman').DataTable({
                processing: true,
                serverSide: true,
                "scrollY": "600px",
                "scrollCollapse": true,
                "autoWidth": false,
                "responsive": false,
                "fixedColumns": false,
                ajax: {
                    url: 'get_data.php',
                    type: 'GET',
                    data: function(d) {
                        // Ambil type dari URL parameter (bisa berubah saat card diklik)
                        const currentUrlParams = new URLSearchParams(window.location.search);
                        const currentType = currentUrlParams.get('type') || 'peminjaman';
                        
                        d.start_date = startDate;
                        d.end_date = endDate;
                        d.entitas_peminjam = entitasPeminjam;
                        d.entitas_dipinjam = entitasDipinjam;
                        d.type = currentType; // Gunakan type dari URL (bisa berubah)
                        d.action = 'get_data';
                        
                        // Debug: log parameter yang dikirim
                        console.log('Peminjaman DataTables - Sending type:', d.type);
                        return d;
                    }
                },
                columns: [
                    { data: 0, width: "10%" }, // Tanggal
                    { data: 1, width: "15%" }, // Nomor Peminjaman
                    { data: 2, width: "12%" }, // Entitas Dipinjam
                    { data: 3, width: "12%" }, // Entitas Peminjam
                    { data: 4, width: "8%" }, // Jumlah Item
                    { data: 5, width: "10%" }, // Total Qty
                    { data: 6, width: "10%" }, // Status Transaksi
                    { data: 7, width: "10%" }, // Status Peminjaman
                    { data: 8, width: "11%" }  // Aksi
                ],
                pageLength: 10,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
                order: [[0, 'desc']],
                language: {
                    processing: '<div class="spinner-border" role="status"><span class="sr-only">Loading...</span></div>',
                    search: "Cari:",
                    lengthMenu: "Tampilkan _MENU_ data per halaman",
                    zeroRecords: "Tidak ada data yang ditemukan",
                    info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                    infoEmpty: "Menampilkan 0 sampai 0 dari 0 data",
                    infoFiltered: "(difilter dari _MAX_ total data)",
                    paginate: {
                        first: "Pertama",
                        last: "Terakhir",
                        next: "Selanjutnya",
                        previous: "Sebelumnya"
                    }
                },
                drawCallback: function(settings) {
                    $('[data-toggle="tooltip"]').tooltip();
                    
                    // Update count di card berdasarkan recordsTotal dan type yang sedang aktif
                    const recordsTotal = settings.json ? settings.json.recordsTotal : 0;
                    // Ambil type dari URL parameter (bisa berubah saat card diklik)
                    const currentUrlParams = new URLSearchParams(window.location.search);
                    const currentType = currentUrlParams.get('type') || 'peminjaman';
                    
                    if (currentType === 'peminjaman') {
                        $('#countPeminjaman').text(recordsTotal);
                    } else if (currentType === 'pengembalian') {
                        $('#countPengembalian').text(recordsTotal);
                    }
                }
            });
        }

    $(document).ready(function() {
        initDataTable();
        
        // PERBAIKAN: Handler untuk tombol View PDF
        $(document).on('click', '.view_pdf_btn', function() {
            const nomorPeminjaman = $(this).data('nomor-peminjaman');
            if (!nomorPeminjaman) {
                alert('Nomor peminjaman tidak ditemukan!');
                return;
            }
            
            // Tampilkan modal
            $('#viewDetailModal').modal('show');
            
            // Ambil data detail peminjaman dari get_data.php?action=get_peminjaman_details
            $.ajax({
                url: 'get_data.php?action=get_peminjaman_details',
                method: 'POST',
                data: { 
                    nomor_peminjaman: nomorPeminjaman
                },
                beforeSend: function() {
                    // Show loading spinner in modal body
                    $('#viewDetailModal .modal-body').html(`
                        <div class="text-center">
                            <div class="spinner-border" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                        </div>
                    `);
                    // Sembunyikan tombol download SJ saat loading
                    $('#downloadSjBtn').hide();
                },
                success: function(response) {
                    // Populate modal content dengan HTML dari get_data.php?action=get_peminjaman_details
                    $('#viewDetailModal .modal-body').html(response);
                    
                    // Tampilkan dan set URL tombol download SJ di footer modal hanya untuk peminjaman
                    const viewType = $('#viewType').val();
                    const viewNomorPeminjaman = $('#viewNomorPeminjaman').val();
                    const downloadSjBtn = $('#downloadSjBtn');
                    
                    if (viewType === 'peminjaman' && viewNomorPeminjaman && viewNomorPeminjaman !== '-') {
                        downloadSjBtn.attr('href', 'download_sj.php?nomor_peminjaman=' + encodeURIComponent(viewNomorPeminjaman));
                        downloadSjBtn.show();
                    } else {
                        downloadSjBtn.hide();
                    }
                },
                error: function(xhr, status, error) {
                    $('#viewDetailModal .modal-body').html(`
                        <div class="alert alert-danger">
                            <h5>Error</h5>
                            <p>Gagal memuat data detail peminjaman.</p>
                            <p class="mb-0"><small>Status: ${xhr.status} | Error: ${error}</small></p>
                        </div>
                    `);
                    // Sembunyikan tombol download SJ jika error
                    $('#downloadSjBtn').hide();
                }
            });
        });
        
        // Sembunyikan tombol download SJ saat modal ditutup
        $('#viewDetailModal').on('hidden.bs.modal', function() {
            $('#downloadSjBtn').hide();
        });
        
        // Handler untuk tombol View Detail (jika masih diperlukan untuk fungsi lain)
        $(document).on('click', '.view_detail_btn', function() {
            const nomorMutasi = $(this).data('nomor-mutasi');
            if (!nomorMutasi) {
                alert('Nomor mutasi tidak ditemukan!');
                return;
            }
            
            // Tampilkan modal
            $('#viewDetailModal').modal('show');
            
            // ... kode untuk load detail data ...
        });
        
        // Handler untuk tombol Tambah Rincian di detail peminjaman
        $(document).on('click', '.addRowPeminjaman', function() {
            const nomorPeminjaman = $(this).data('nomor-peminjaman');
            const tbodyListData = $('#viewDetailModal').find('.tbodyListDataPeminjaman').filter(function() {
                return $(this).data('nomor-peminjaman') === nomorPeminjaman;
            })[0];
            
            if (tbodyListData) {
                // Hapus baris "Tidak ada data detail" jika ada
                const $tbody = $(tbodyListData);
                const emptyRow = $tbody.find('tr td[colspan]');
                if (emptyRow.length) {
                    emptyRow.closest('tr').remove();
                }
                
                const rowCount = $tbody.find('tr').length + 1;
                const newRow = $('<tr></tr>');
                
                // Ambil gudang peminjam dari data attribute tbody
                const gudangAsal = $tbody.data('gudang-asal') || '';
                
                // Gunakan opsi dari index.php (yang sudah tersedia di halaman)
                // json_encode akan menghasilkan string JSON yang valid, yang kemudian akan di-parse oleh JavaScript
                const gudangOpts = <?= json_encode($gudangOptions ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?>;
                const productOpts = <?= json_encode($productOptions ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?>;
                
                newRow.html(`
                    <td class="text-center">${rowCount}</td>
                    <td>
                        <select name="in_gudang[]" class="form-control form-control-sm select2" required>
                            ${gudangOpts || '<option value="">-- Pilih Gudang --</option>'}
                        </select>
                    </td>
                    <td>
                        <select name="in_produk[]" class="form-control form-control-sm select2" required>
                            ${productOpts || '<option value="">-- Pilih Produk --</option>'}
                        </select>
                    </td>
                    <td class="text-center">
                        <input type="number" name="in_jumlah[]" class="form-control form-control-sm text-center" min="0" value="0" required>
                        <input type="hidden" name="in_id[]" value="">
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-danger btnDeleteRowPeminjaman">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </td>
                `);
                
                // Tandai sebagai data baru (bisa diedit)
                newRow.addClass('data-new');
                
                // Tambahkan row ke tbody
                $tbody.append(newRow);
                
                // Inisialisasi Select2 untuk dropdown baru
                newRow.find('.select2').select2({
                    width: '100%',
                    dropdownParent: $('#viewDetailModal')
                });
                
                // Set gudang peminjam sebagai default jika tersedia
                if (gudangAsal) {
                    const gudangSelect = newRow.find('select[name="in_gudang[]"]');
                    if (gudangSelect.find(`option[value="${gudangAsal}"]`).length > 0) {
                        gudangSelect.val(gudangAsal).trigger('change');
                    }
                }
                
                // Tampilkan tombol Simpan Perubahan di footer
                $('#viewDetailModal').find('.modal-footer .simpanPerubahanPeminjaman').attr('data-nomor-peminjaman', nomorPeminjaman).show();
            }
        });
        
        // Handler untuk tombol hapus baris peminjaman
        $(document).on('click', '.btnDeleteRowPeminjaman', function() {
            const row = $(this).closest('tr');
            const tbody = row.closest('tbody');
            const nomorPeminjaman = tbody.data('nomor-peminjaman');
            
            // Cek status peminjaman dari modal atau dari data attribute
            const isDraft = $('#viewDetailModal').find('.formEditPeminjaman[data-nomor-peminjaman="' + nomorPeminjaman + '"]').length > 0;
            
            // Untuk Draft, semua data bisa dihapus (termasuk existing)
            // Untuk Final, hanya data baru yang bisa dihapus
            if (!isDraft && row.hasClass('data-existing')) {
                alert('Data existing tidak dapat dihapus. Hanya data baru yang dapat dihapus.');
                return;
            }
            
            // Cek apakah masih ada baris lain
            const totalRows = tbody.find('tr').length;
            if (totalRows <= 1) {
                alert('Minimal harus ada 1 baris data.');
                return;
            }
            
            row.remove();
            
            // Renumber rows
            tbody.find('tr').each(function(index) {
                $(this).find('td:first').text(index + 1);
            });
            
            // Tombol Simpan Perubahan tetap tampil meskipun tidak ada data baru
            // Tidak perlu menyembunyikan tombol
        });
        
        // Handler untuk tombol Simpan Perubahan Peminjaman
        $(document).on('click', '.simpanPerubahanPeminjaman', function(e) {
            e.preventDefault();
            const nomorPeminjaman = $(this).attr('data-nomor-peminjaman');
            if (!nomorPeminjaman || nomorPeminjaman === '') {
                alert('Nomor peminjaman tidak ditemukan!');
                return;
            }
            const form = $('#viewDetailModal').find('.formEditPeminjaman').filter(function() {
                return $(this).data('nomor-peminjaman') === nomorPeminjaman;
            });
            
            if (!form.length) {
                alert('Form tidak ditemukan!');
                return;
            }
            
            // Validasi form (hanya validasi data baru, skip data existing yang disabled)
            let isValid = true;
            form.find('tr.data-new select[name="in_gudang[]"], tr.data-new select[name="in_produk[]"], tr.data-new input[name="in_jumlah[]"]').each(function() {
                if ($(this).is(':visible') && !$(this).is(':disabled') && !$(this).val()) {
                    isValid = false;
                    $(this).addClass('is-invalid');
                } else {
                    $(this).removeClass('is-invalid');
                }
            });
            
            if (!isValid) {
                alert('Mohon lengkapi semua field yang wajib diisi!');
                return;
            }
            
            // Konfirmasi
            if (!confirm('Apakah Anda yakin ingin menyimpan perubahan detail peminjaman?')) {
                return;
            }
            
            // Kirim data dengan AJAX
            // Pastikan data existing (disabled) tetap dikirim
            const formData = new FormData();
            
            // Kumpulkan data untuk debugging
            const debugData = {
                gudang: [],
                produk: [],
                jumlah: [],
                id: []
            };
            
            // Ambil semua input dari form, termasuk yang disabled
            // Untuk disabled fields, kita perlu enable sementara untuk mendapatkan value
            form.find('input, select').each(function() {
                const $field = $(this);
                const name = $field.attr('name');
                if (name) {
                    let value = '';
                    let wasDisabled = false;
                    
                    // Jika disabled, enable sementara untuk mendapatkan value
                    if ($field.is(':disabled')) {
                        wasDisabled = true;
                        $field.prop('disabled', false);
                    }
                    
                    // Ambil value
                    if ($field.is('select')) {
                        // Untuk select, ambil value langsung
                        value = $field.val() || '';
                    } else {
                        // Untuk input, ambil value langsung
                        value = $field.val() || '';
                    }
                    
                    // Kembalikan disabled state jika sebelumnya disabled
                    if (wasDisabled) {
                        $field.prop('disabled', true);
                    }
                    
                    // Append ke FormData
                    if (name) {
                        // Untuk array fields, selalu append
                        if (name.endsWith('[]')) {
                            formData.append(name, value || '');
                            
                            // Debug: simpan ke array untuk logging
                            if (name === 'in_gudang[]') {
                                debugData.gudang.push(value || '');
                            } else if (name === 'in_produk[]') {
                                debugData.produk.push(value || '');
                            } else if (name === 'in_jumlah[]') {
                                debugData.jumlah.push(value || '');
                            } else if (name === 'in_id[]') {
                                debugData.id.push(value || '');
                            }
                        } else if (name === 'nomor_peminjaman') {
                            formData.append(name, value);
                        } else if (value !== '') {
                            formData.append(name, value);
                        }
                    }
                }
            });
            
            // Debug logging
            console.log('Form Data to Send:', {
                gudang: debugData.gudang,
                produk: debugData.produk,
                jumlah: debugData.jumlah,
                id: debugData.id,
                nomor_peminjaman: formData.get('nomor_peminjaman')
            });
            
            formData.append('act', 'edit_detail');
            
            $.ajax({
                type: 'POST',
                url: 'action.php?act=edit_detail',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert('Data detail peminjaman berhasil disimpan!');
                        // Destroy semua Select2 di modal sebelum reload
                        $('#viewDetailModal .select2').each(function() {
                            const $select = $(this);
                            if ($select.hasClass('select2-hidden-accessible')) {
                                try {
                                    $select.select2('destroy');
                                } catch(e) {
                                    // Ignore error
                                }
                            }
                        });
                        // Reload DataTables untuk update qty di dashboard tanpa refresh halaman
                        if (typeof tablePeminjaman !== 'undefined' && tablePeminjaman) {
                            tablePeminjaman.ajax.reload(null, false); // false = keep current page
                        }
                        // Reload view detail untuk menampilkan data terbaru
                        setTimeout(function() {
                            $('.view_pdf_btn[data-nomor-peminjaman="' + nomorPeminjaman + '"]').click();
                        }, 100);
                    } else {
                        alert('Error: ' + (response.error || 'Gagal menyimpan data'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error, xhr.responseText);
                    alert('Gagal mengirim data. Pastikan semua form terisi dengan benar!');
                }
            });
        });
    });
    </script>