<?php
session_start();
require 'config.php';

// Proteksi Halaman: Jika belum login, tendang ke login.php
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$user_aktif = $_SESSION['user'];

// PROSES SIMPAN PESANAN (Jika Form di-submit)
if (isset($_POST['action']) && $_POST['action'] == 'save_order') {
    $pelanggan = mysqli_real_escape_string($conn, $_POST['customer_name']);
    $no_meja = intval($_POST['table_no']);
    $id_karyawan = intval($_POST['employee_id']);
    $cart_data = json_decode($_POST['cart_json'], true);
    $total_harga = intval($_POST['total_harga']);
    $tanggal = date('Y-m-d');

    if (!empty($cart_data)) {
        // Mulai Transaksi SQL aman
        mysqli_begin_transaction($conn);

        // 1. Insert Pelanggan
        $insert_pl = mysqli_query($conn, "INSERT INTO PELANGGAN (nama) VALUES ('$pelanggan')");
        $id_pelanggan = mysqli_insert_id($conn);

        // 2. Insert Pesanan
        $insert_ps = mysqli_query($conn, "INSERT INTO PESANAN (id_pelanggan, id_karyawan, no_meja, tanggal, total_harga) VALUES ($id_pelanggan, $id_karyawan, $no_meja, '$tanggal', $total_harga)");
        $id_pesanan = mysqli_insert_id($conn);

        // 3. Insert Detail Pesanan
        $detail_success = true;
        foreach ($cart_data as $item) {
            $id_menu = intval($item['id']);
            $qty = intval($item['qty']);
            $subtotal = intval($item['subtotal']);
            
            $insert_dp = mysqli_query($conn, "INSERT INTO DETAIL_PESANAN (id_pesanan, id_menu, jumlah, subtotal) VALUES ($id_pesanan, $id_menu, $qty, $subtotal)");
            if (!$insert_dp) {
                $detail_success = false;
            }
        }

        if ($insert_pl && $insert_ps && $detail_success) {
            mysqli_commit($conn);
            echo "<script>alert('Pesanan berhasil disimpan ke database!'); window.location='index.php';</script>";
        } else {
            mysqli_rollback($conn);
            echo "<script>alert('Gagal menyimpan pesanan.');</script>";
        }
    }
}

// AMBIL DATA MASTER UNTUK DROPDOWN
$meja_res = mysqli_query($conn, "SELECT * FROM MEJA");
$karyawan_res = mysqli_query($conn, "SELECT * FROM KARYAWAN");
$menu_res = mysqli_query($conn, "SELECT * FROM MENU");

// Ambil data menu ke array JS agar keranjang belanja interaktif
$menu_array = [];
while ($m = mysqli_fetch_assoc($menu_res)) {
    $menu_array[] = $m;
}
mysqli_data_seek($menu_res, 0); // Reset pointer dropdown HTML
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Kasir Kafe</title>
    <link rel="stylesheet" href="style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <header>
        <h1>☕ Cafe Management System</h1>
        <p>Logged in as: <strong><?= htmlspecialchars($user_aktif['nama']); ?> (<?= htmlspecialchars($user_aktif['posisi']); ?>)</strong> | <a href="logout.php" style="color: #c53030; font-weight: bold; text-decoration: none;">Logout</a></p>
    </header>

    <div class="container">
        <div class="card">
            <h2>Buat Pesanan Baru</h2>
            <form id="orderForm" method="POST" action="">
                <input type="hidden" name="action" value="save_order">
                <input type="hidden" id="cart_json" name="cart_json">
                <input type="hidden" id="total_harga" name="total_harga" value="0">

                <div class="form-group">
                    <label>Nama Pelanggan:</label>
                    <input type="text" name="customer_name" required placeholder="Masukkan nama pelanggan">
                </div>
                <div class="form-group">
                    <label>Pilih Meja:</label>
                    <select name="table_no" required>
                        <?php while($meja = mysqli_fetch_assoc($meja_res)): ?>
                            <option value="<?= $meja['no_meja']; ?>">Meja <?= $meja['no_meja']; ?> (Kap: <?= $meja['kapasitas']; ?>)</option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Pelayan (Karyawan):</label>
                    <select name="employee_id" required>
                        <?php while($karyawan = mysqli_fetch_assoc($karyawan_res)): ?>
                            <option value="<?= $karyawan['id_karyawan']; ?>" <?= $karyawan['id_karyawan'] == $user_aktif['id_karyawan'] ? 'selected' : ''; ?>><?= htmlspecialchars($karyawan['nama']); ?> - <?= htmlspecialchars($karyawan['posisi']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <hr>
                <h3>Pilih Menu</h3>
                <div class="form-group">
                    <label>Menu:</label>
                    <select id="menuSelect">
                        <?php foreach($menu_array as $menu): ?>
                            <option value="<?= $menu['id_menu']; ?>"><?= htmlspecialchars($menu['nama_menu']); ?> - Rp <?= number_format($menu['harga']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Jumlah:</label>
                    <input type="number" id="menuQty" value="1" min="1">
                </div>
                <button type="button" id="addToCartBtn" class="btn-secondary">Tambah ke Keranjang</button>

                <div class="cart-section">
                    <h4>Keranjang:</h4>
                    <ul id="cartList"></ul>
                    <p style="display:flex; justify-content:space-between; margin-top:10px;"><strong>Total:</strong> <strong>Rp <span id="cartTotal">0</span></strong></p>
                </div>

                <button type="submit" class="btn-primary">Simpan Transaksi</button>
            </form>
        </div>

        <div class="card">
            <h2>Daftar Pesanan (Database Terintegrasi)</h2>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Pelanggan</th>
                            <th>Meja</th>
                            <th>Pelayan</th>
                            <th>Item (Qty)</th>
                            <th>Total Harga</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql_pesanan = "
                            SELECT p.id_pesanan, pl.nama AS pelanggan, p.no_meja, k.nama AS pelayan, p.total_harga,
                                   GROUP_CONCAT(CONCAT(m.nama_menu, ' (', dp.jumlah, ')') SEPARATOR ', ') AS items
                            FROM PESANAN p
                            LEFT JOIN PELANGGAN pl ON p.id_pelanggan = pl.id_pelanggan
                            LEFT JOIN KARYAWAN k ON p.id_karyawan = k.id_karyawan
                            LEFT JOIN DETAIL_PESANAN dp ON p.id_pesanan = dp.id_pesanan
                            LEFT JOIN MENU m ON dp.id_menu = m.id_menu
                            GROUP BY p.id_pesanan ORDER BY p.id_pesanan DESC;
                        ";
                        $res_pesanan = mysqli_query($conn, $sql_pesanan);
                        while($row = mysqli_fetch_assoc($res_pesanan)):
                        ?>
                        <tr>
                            <td><strong>ORD-<?= $row['id_pesanan']; ?></strong></td>
                            <td><?= htmlspecialchars($row['pelanggan'] ?? ''); ?></td>
                            <td>Meja <?= $row['no_meja']; ?></td>
                            <td><?= htmlspecialchars($row['pelayan'] ?? ''); ?></td>
                            <td><?= $row['items'] ? htmlspecialchars($row['items']) : '-'; ?></td>
                            <td><strong>Rp <?= number_format($row['total_harga']); ?></strong></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        const masterMenu = <?= json_encode($menu_array); ?>;
        let currentCart = [];

        document.getElementById("addToCartBtn").addEventListener("click", () => {
            const menuId = parseInt(document.getElementById("menuSelect").value);
            const qty = parseInt(document.getElementById("menuQty").value);
            const selectedMenu = masterMenu.find(m => parseInt(m.id_menu) === menuId);
            
            if(!selectedMenu) return;

            const existingItem = currentCart.find(item => item.id === menuId);
            if (existingItem) {
                existingItem.qty += qty;
                existingItem.subtotal = existingItem.qty * parseInt(selectedMenu.harga);
            } else {
                currentCart.push({
                    id: parseInt(selectedMenu.id_menu),
                    nama: selectedMenu.nama_menu,
                    harga: parseInt(selectedMenu.harga),
                    qty: qty,
                    subtotal: qty * parseInt(selectedMenu.harga)
                });
            }
            updateCartUI();
        });

        function updateCartUI() {
            const cartList = document.getElementById("cartList");
            const cartTotal = document.getElementById("cartTotal");
            cartList.innerHTML = "";
            let total = 0;

            currentCart.forEach(item => {
                total += item.subtotal;
                cartList.innerHTML += `<li>${item.nama} x${item.qty} <span>Rp ${item.subtotal.toLocaleString()}</span></li>`;
            });

            cartTotal.innerText = total.toLocaleString();
            
            document.getElementById("cart_json").value = JSON.stringify(currentCart);
            document.getElementById("total_harga").value = total;
        }

        document.getElementById("orderForm").addEventListener("submit", (e) => {
            if (currentCart.length === 0) {
                e.preventDefault();
                alert("Keranjang belanja masih kosong!");
            }
        });
    </script>
</body>
</html>