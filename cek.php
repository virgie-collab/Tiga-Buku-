<?php
define('DATA_FILE', __DIR__ . '/data.json');
define('APP_NAME', 'CARSOTOCARE');

$id = isset($_GET['id']) ? preg_replace('/[^A-Za-z0-9\-]/', '', $_GET['id']) : '';
$pesanan = null;
if ($id && file_exists(DATA_FILE)) {
    $d = json_decode(file_get_contents(DATA_FILE), true);
    if (is_array($d) && isset($d['pesanan'])) {
        foreach ($d['pesanan'] as $p) {
            if ($p['id'] === $id || (isset($p['no_order']) && $p['no_order'] === $id)) {
                $pesanan = $p;
                break;
            }
        }
    }
}
function rp($n){ return 'Rp ' . number_format((float)$n, 0, ',', '.'); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cek Status - <?= APP_NAME ?></title>
<link rel="icon" type="image/webp" href="https://carsotocare.com/icon.webp">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<style>
body{font-family:'Inter',sans-serif;background:linear-gradient(135deg,#0a0a0a,#1a1a1a);min-height:100vh;color:#fff;padding:16px;display:flex;align-items:center;justify-content:center;}
.wrap{max-width:420px;width:100%;}
.header{text-align:center;margin-bottom:20px;}
.header img{width:70px;height:70px;border-radius:50%;border:3px solid #FFD700;padding:3px;background:#000;}
.header h1{color:#FFD700;font-weight:900;font-size:22px;margin:10px 0 2px;letter-spacing:1px;}
.header p{color:#b0b0b0;font-size:13px;margin:0;}
.card{background:#181818;border:1px solid #3a3a3a;border-radius:16px;padding:18px;box-shadow:0 8px 24px rgba(0,0,0,.4);}
.badge-st{display:inline-block;padding:6px 14px;border-radius:20px;font-size:12px;font-weight:800;margin-bottom:12px;}
.st-antri{background:#ffe0e0;color:#c92a2a;}
.st-proses{background:#fff3cd;color:#856404;}
.st-selesai{background:#d4edda;color:#155724;}
.badge-byr{display:inline-block;padding:6px 14px;border-radius:20px;font-size:12px;font-weight:800;margin-left:6px;margin-bottom:12px;}
.b-lunas{background:#d4edda;color:#155724;}
.b-utang{background:#f8d7da;color:#721c24;}
.oc-no{font-family:'Courier New',monospace;color:#FFD700;font-weight:800;font-size:13px;margin-bottom:6px;}
.nm{font-size:18px;font-weight:800;color:#fff;margin:6px 0;}
.meta{font-size:12.5px;color:#b0b0b0;margin-bottom:12px;}
.items{background:#101010;border-radius:10px;padding:12px;margin:10px 0;font-size:12.5px;}
.item-line{display:flex;justify-content:space-between;padding:4px 0;color:#ddd;border-bottom:1px dashed #333;}
.item-line:last-child{border-bottom:none;}
.total{display:flex;justify-content:space-between;padding:12px 0 0;border-top:2px solid #FFD700;margin-top:8px;font-size:18px;font-weight:900;color:#FFD700;}
.est{background:rgba(77,171,247,.1);border:1px solid rgba(77,171,247,.4);border-radius:10px;padding:10px;font-size:12.5px;color:#4dabf7;margin:10px 0;}
.notfound{text-align:center;padding:40px 20px;}
.notfound i{font-size:50px;color:#ff6b6b;display:block;margin-bottom:12px;}
</style>
</head>
<body>
<div class="wrap">
    <div class="header">
        <img src="https://carsotocare.com/icon.webp" alt="Logo">
        <h1><?= APP_NAME ?></h1>
        <p>Cek Status Pesanan</p>
    </div>

    <?php if (!$pesanan): ?>
        <div class="card notfound">
            <i class="bi bi-exclamation-triangle"></i>
            <h4 style="color:#fff;">Pesanan Tidak Ditemukan</h4>
            <p style="color:#b0b0b0;font-size:13px;">Pastikan QR / link yang kamu scan benar.</p>
        </div>
    <?php else:
        $sp = isset($pesanan['status_pekerjaan']) ? $pesanan['status_pekerjaan'] : 'antri';
        $sbyr = isset($pesanan['status_bayar']) ? $pesanan['status_bayar'] : 'belum_bayar';
        $stLabel = $sp === 'antri' ? 'Menunggu' : ($sp === 'proses' ? 'Sedang Dikerjakan' : 'Selesai');
        $stClass = $sp === 'antri' ? 'st-antri' : ($sp === 'proses' ? 'st-proses' : 'st-selesai');
    ?>
        <div class="card">
            <div style="text-align:center;">
                <span class="badge-st <?= $stClass ?>"><i class="bi bi-<?= $sp === 'antri' ? 'hourglass' : ($sp === 'proses' ? 'gear' : 'check-circle') ?>"></i> <?= $stLabel ?></span>
                <span class="badge-byr <?= $sbyr === 'lunas' ? 'b-lunas' : 'b-utang' ?>">
                    <i class="bi bi-<?= $sbyr === 'lunas' ? 'cash-coin' : 'exclamation-circle' ?>"></i> <?= $sbyr === 'lunas' ? 'LUNAS' : 'Belum Bayar' ?>
                </span>
            </div>
            <div style="text-align:center;">
                <div class="oc-no"><?= htmlspecialchars($pesanan['no_order']) ?></div>
                <div class="nm"><i class="bi bi-person-fill"></i> <?= htmlspecialchars($pesanan['nama_customer']) ?></div>
                <div class="meta">
                    <?php if (!empty($pesanan['jenis_kendaraan'])): ?><i class="bi bi-car-front"></i> <?= htmlspecialchars($pesanan['jenis_kendaraan']) ?><?php endif; ?>
                    <?php if (!empty($pesanan['tipe'])): ?> <?= htmlspecialchars($pesanan['tipe']) ?><?php endif; ?>
                    <?php if (!empty($pesanan['no_polisi'])): ?> &bull; <?= htmlspecialchars($pesanan['no_polisi']) ?><?php endif; ?>
                </div>
            </div>

            <?php if (!empty($pesanan['estimasi_selesai']) && $sp !== 'selesai'): 
                $est = strtotime($pesanan['estimasi_selesai']);
                $diff = $est - time();
            ?>
            <div class="est">
                <i class="bi bi-hourglass-split"></i>
                <strong>Estimasi selesai:</strong> <?= date('d M Y, H:i', $est) ?> WIB
                <?php if ($diff > 0): ?>
                    <br><small>(sekitar <?= floor($diff/3600) ?> jam <?= floor(($diff%3600)/60) ?> menit lagi)</small>
                <?php else: ?>
                    <br><small style="color:#ff6b6b;">(terlambat <?= floor(abs($diff)/3600) ?> jam <?= floor((abs($diff)%3600)/60) ?> menit)</small>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="items">
                <?php foreach ((array)$pesanan['items'] as $it): ?>
                    <div class="item-line"><span><?= htmlspecialchars($it['nama']) ?> x<?= $it['qty'] ?></span><strong><?= rp($it['subtotal']) ?></strong></div>
                <?php endforeach; ?>
                <?php if (!empty($pesanan['tambahan']) && is_array($pesanan['tambahan'])): foreach ($pesanan['tambahan'] as $it): ?>
                    <div class="item-line" style="color:#51cf66;"><span>+ <?= htmlspecialchars($it['nama']) ?> x<?= $it['qty'] ?></span><strong><?= rp($it['subtotal']) ?></strong></div>
                <?php endforeach; endif; ?>
                <?php if (!empty($pesanan['servis']['jenis'])): ?>
                    <div class="item-line" style="color:#ffd43b;"><span>Servis: <?= htmlspecialchars($pesanan['servis']['jenis']) ?></span><strong><?= rp($pesanan['servis']['biaya']) ?></strong></div>
                <?php endif; ?>
                <?php if (!empty($pesanan['doorsmeer']['paket'])): ?>
                    <div class="item-line" style="color:#4dabf7;"><span>Cuci: <?= htmlspecialchars($pesanan['doorsmeer']['paket']) ?></span><strong><?= rp($pesanan['doorsmeer']['harga']) ?></strong></div>
                <?php endif; ?>
                <?php if (!empty($pesanan['diskon']) && $pesanan['diskon'] > 0): ?>
                    <div class="item-line" style="color:#ff6b6b;"><span>Diskon</span><strong>-<?= rp($pesanan['diskon']) ?></strong></div>
                <?php endif; ?>
            </div>
            <div class="total"><span>TOTAL</span><span><?= rp($pesanan['total']) ?></span></div>

            <?php if (!empty($pesanan['note'])): ?>
                <div style="background:#fffbea;color:#856404;border-radius:10px;padding:10px;margin-top:12px;font-size:12px;border-left:4px solid #B8860B;">
                    <strong><i class="bi bi-sticky-fill"></i> Catatan:</strong><br><?= nl2br(htmlspecialchars($pesanan['note'])) ?>
                </div>
            <?php endif; ?>
        </div>

        <div style="text-align:center;margin-top:16px;color:#666;font-size:11px;">
            <i class="bi bi-shield-check"></i> Halaman cek status aman &bull; <?= APP_NAME ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
