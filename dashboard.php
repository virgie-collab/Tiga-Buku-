<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
session_start();
date_default_timezone_set('Asia/Jakarta');

define('DATA_FILE', __DIR__ . '/data.json');
define('BACKUP_FOLDER', __DIR__ . '/backup');
define('OMSET_FOLDER', __DIR__ . '/omset_harian');
define('UPLOAD_FOLDER', __DIR__ . '/uploads');
define('TELEGRAM_BOT_TOKEN', 'ISI TOKEN');
define('TELEGRAM_CHAT_ID', 'ISI TOKEN');
define('LOGO_URL', 'https://carsotocare.com/icon.webp');
define('APP_NAME', 'CARSOTOCARE');

if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit(); }

// ============================================================
// SIMPLE PDF GENERATOR — pure PHP + Image + Times fonts
// ============================================================
class SimplePDF {
    public $pageW = 595.28;
    public $pageH = 841.89;
    public $margin = 36;
    public $y = 0;
    public $images = [];

    private $pages = [];
    private $content = '';
    private $decorator = '';

    public function __construct() { $this->y = $this->pageH - $this->margin; }
    public function contentWidth() { return $this->pageW - 2 * $this->margin; }
    public function pageCount() { return count($this->pages) + ($this->content !== '' ? 1 : 0); }
    public function setDecorator($s) { $this->decorator = $s; }

    private function esc($s) {
        $s = (string)$s;
        $map = [
            '—'=>'--','–'=>'-','·'=>'.','•'=>'-','●'=>'.','✓'=>'v','✗'=>'x',
            '→'=>'->','←'=>'<-','↑'=>'^','↓'=>'v','…'=>'...','“'=>'"','”'=>'"',
            '‘'=>"'",'’'=>"'",'🙏'=>'','✅'=>'[OK]','⏳'=>'[..]','💰'=>'','📄'=>'',
            '🚗'=>'','🕐'=>'','🔧'=>'','💵'=>'','📊'=>'','🗓'=>'','🛒'=>'','🚿'=>'',
            '📥'=>'','📤'=>'','💸'=>'','⚠️'=>'[!]','✨'=>'','🤖'=>'','🙌'=>'',
            "\xEF\xB8\x8F" => '',
        ];
        $s = strtr($s, $map);
        $s = preg_replace('/[^\x20-\x7E]/', '', $s);
        return str_replace(['\\','(',')',"\r","\n","\t"], ['\\\\','\\(','\\)','',' ',' '], $s);
    }

    public function text($x, $y, $str, $font = 'F1', $size = 10, $color = [0,0,0]) {
        $this->content .= sprintf("%.3f %.3f %.3f rg\n", $color[0]/255, $color[1]/255, $color[2]/255);
        $this->content .= "BT /{$font} {$size} Tf 1 0 0 1 " . sprintf("%.2f %.2f", $x, $y) . " Tm (" . $this->esc($str) . ") Tj ET\n";
    }

    public function textRight($xRight, $y, $str, $font = 'F1', $size = 10, $color = [0,0,0]) {
        $w = $this->measure($str, $font, $size);
        $this->text($xRight - $w, $y, $str, $font, $size, $color);
    }

    public function textCenter($xCenter, $y, $str, $font = 'F1', $size = 10, $color = [0,0,0]) {
        $w = $this->measure($str, $font, $size);
        $this->text($xCenter - $w/2, $y, $str, $font, $size, $color);
    }

    public function measure($str, $font = 'F1', $size = 10) {
        $len = strlen($this->esc($str));
        $factors = [
            'F1' => 0.500,
            'F2' => 0.556,
            'F3' => 0.500,
            'F4' => 0.556,
        ];
        $factor = $factors[$font] ?? 0.500;
        return $len * $size * $factor;
    }

    public function truncate($str, $maxW, $font = 'F1', $size = 10) {
        $str = (string)$str;
        if ($this->measure($str, $font, $size) <= $maxW) return $str;
        $t = $str;
        while (strlen($t) > 0 && $this->measure($t.'...', $font, $size) > $maxW) $t = substr($t, 0, -1);
        return $t . '...';
    }

    public function line($x1, $y1, $x2, $y2, $width = 0.5, $color = [0,0,0]) {
        $this->content .= sprintf("%.2f w\n", $width);
        $this->content .= sprintf("%.3f %.3f %.3f RG\n", $color[0]/255, $color[1]/255, $color[2]/255);
        $this->content .= sprintf("%.2f %.2f m %.2f %.2f l S\n", $x1, $y1, $x2, $y2);
    }

    public function rectFill($x, $y, $w, $h, $color = [255,255,255]) {
        $this->content .= sprintf("%.3f %.3f %.3f rg\n", $color[0]/255, $color[1]/255, $color[2]/255);
        $this->content .= sprintf("%.2f %.2f %.2f %.2f re f\n", $x, $y, $w, $h);
    }

    public function rectStroke($x, $y, $w, $h, $width = 0.5, $color = [0,0,0]) {
        $this->content .= sprintf("%.2f w\n", $width);
        $this->content .= sprintf("%.3f %.3f %.3f RG\n", $color[0]/255, $color[1]/255, $color[2]/255);
        $this->content .= sprintf("%.2f %.2f %.2f %.2f re S\n", $x, $y, $w, $h);
    }

    public function image($x, $y, $dispW, $dispH, $jpegData) {
        if (empty($jpegData) || strlen($jpegData) < 50) return;
        $pxW = 100; $pxH = 100;
        if (function_exists('getimagesizefromstring')) {
            $info = @getimagesizefromstring($jpegData);
            if ($info) { $pxW = $info[0]; $pxH = $info[1]; }
        }
        $name = 'Im' . (count($this->images) + 1);
        $this->images[] = ['name'=>$name,'data'=>$jpegData,'pxW'=>$pxW,'pxH'=>$pxH];
        $this->content .= "q\n";
        $this->content .= sprintf("%.4f 0 0 %.4f %.4f %.4f cm\n", $dispW, $dispH, $x, $y);
        $this->content .= "/{$name} Do\n";
        $this->content .= "Q\n";
    }

    public function addPage() {
        $this->pages[] = $this->decorator . $this->content;
        $this->content = '';
        $this->y = $this->pageH - $this->margin;
    }

    public function output() {
        $this->pages[] = $this->decorator . $this->content;
        $n = count($this->pages);
        $imgCount = count($this->images);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];

        $pageObjStart     = 3;
        $contentObjStart  = 3 + $n;
        $fontRegObj       = 3 + 2 * $n;
        $fontBoldObj      = 4 + 2 * $n;
        $fontTimesObj     = 5 + 2 * $n;
        $fontTimesBoldObj = 6 + 2 * $n;
        $imgObjStart      = 7 + 2 * $n;
        $maxObj           = 6 + 2 * $n + max($imgCount, 0);

        $xobjRefs = '';
        foreach ($this->images as $i => $img) {
            $xobjRefs .= "/{$img['name']} " . ($imgObjStart + $i) . " 0 R ";
        }
        $xobjDict = $xobjRefs ? "/XObject << {$xobjRefs}>> " : '';
        $fonts = "/Font << /F1 {$fontRegObj} 0 R /F2 {$fontBoldObj} 0 R /F3 {$fontTimesObj} 0 R /F4 {$fontTimesBoldObj} 0 R >> ";

        $kids = [];
        for ($i = 0; $i < $n; $i++) $kids[] = ($pageObjStart + $i) . " 0 R";

        $objects = [];
        $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[2] = "<< /Type /Pages /Kids [" . implode(' ', $kids) . "] /Count {$n} >>";

        for ($i = 0; $i < $n; $i++) {
            $pObj = $pageObjStart + $i;
            $cObj = $contentObjStart + $i;
            $objects[$pObj] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$this->pageW} {$this->pageH}] "
                            . "/Contents {$cObj} 0 R /Resources << {$fonts}{$xobjDict}>> >>";
        }
        for ($i = 0; $i < $n; $i++) {
            $cObj = $contentObjStart + $i;
            $stream = $this->pages[$i];
            $objects[$cObj] = "<< /Length " . strlen($stream) . " >>\nstream\n{$stream}\nendstream";
        }

        $objects[$fontRegObj]       = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
        $objects[$fontBoldObj]      = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";
        $objects[$fontTimesObj]     = "<< /Type /Font /Subtype /Type1 /BaseFont /Times-Roman /Encoding /WinAnsiEncoding >>";
        $objects[$fontTimesBoldObj] = "<< /Type /Font /Subtype /Type1 /BaseFont /Times-Bold /Encoding /WinAnsiEncoding >>";

        foreach ($this->images as $i => $img) {
            $objNum = $imgObjStart + $i;
            $objects[$objNum] = "<< /Type /XObject /Subtype /Image /Width {$img['pxW']} /Height {$img['pxH']} "
                              . "/ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode "
                              . "/Length " . strlen($img['data']) . " >>\nstream\n" . $img['data'] . "\nendstream";
        }

        for ($i = 1; $i <= $maxObj; $i++) {
            if (!isset($objects[$i])) continue;
            $offsets[$i] = strlen($pdf);
            $pdf .= "{$i} 0 obj\n{$objects[$i]}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . ($maxObj + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= $maxObj; $i++) {
            $pdf .= isset($offsets[$i]) ? sprintf("%010d 00000 n \n", $offsets[$i]) : "0000000000 65535 f \n";
        }
        $pdf .= "trailer\n<< /Size " . ($maxObj + 1) . " /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF\n";

        return $pdf;
    }
}

function getLogoBase64() {
    $cache = __DIR__ . '/logo_b64_cache.txt';
    if (file_exists($cache) && (time() - filemtime($cache)) < 7*86400) {
        $c = file_get_contents($cache);
        if ($c && strlen($c) > 100) return $c;
    }
    $ctx = stream_context_create(array(
        'http' => array('timeout' => 8, 'user_agent' => 'Mozilla/5.0'),
        'ssl'  => array('verify_peer' => false, 'verify_peer_name' => false)
    ));
    $raw = @file_get_contents(LOGO_URL, false, $ctx);
    if ($raw === false || strlen($raw) < 100) return '';
    if (function_exists('imagecreatefromstring') && function_exists('imagepng')) {
        $img = @imagecreatefromstring($raw);
        if ($img !== false) {
            ob_start(); imagepng($img); $png = ob_get_clean(); imagedestroy($img);
            if ($png) { $b64 = 'data:image/png;base64,' . base64_encode($png); @file_put_contents($cache, $b64); return $b64; }
        }
    }
    $b64 = 'data:image/webp;base64,' . base64_encode($raw);
    @file_put_contents($cache, $b64);
    return $b64;
}

// ============================================================
// SIAPKAN LOGO UNTUK PDF (JPEG bytes, background gelap)
// ============================================================
function prepareLogoJPEG() {
    $cache = __DIR__ . '/logo_pdf_cache.jpg';
    if (file_exists($cache) && filesize($cache) > 200 && (time() - filemtime($cache)) < 7*86400) {
        return file_get_contents($cache);
    }

    $ctx = stream_context_create([
        'http' => ['timeout' => 10, 'user_agent' => 'Mozilla/5.0'],
        'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $raw = @file_get_contents(LOGO_URL, false, $ctx);
    if ($raw === false || strlen($raw) < 100) return '';
    if (!function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) return '';

    $img = @imagecreatefromstring($raw);
    if ($img === false) return '';

    $w = imagesx($img);
    $h = imagesy($img);

    $canvas = imagecreatetruecolor($w, $h);
    imagealphablending($canvas, false);
    $bg = imagecolorallocate($canvas, 8, 8, 8);
    imagefill($canvas, 0, 0, $bg);
    imagealphablending($canvas, true);

    imagecopy($canvas, $img, 0, 0, 0, 0, $w, $h);

    ob_start();
    imagejpeg($canvas, null, 92);
    $jpeg = ob_get_clean();

    imagedestroy($img);
    imagedestroy($canvas);

    if ($jpeg && strlen($jpeg) > 200) {
        @file_put_contents($cache, $jpeg);
        return $jpeg;
    }
    return '';
}

$LOGO_B64 = getLogoBase64();

function readData() {
    $def = array('users'=>array(),'barang'=>array(),'stock_masuk'=>array(),'stock_keluar'=>array(),'servis'=>array(),'doorsmeer'=>array(),'pesanan'=>array(),'omset'=>array(),'pengeluaran'=>array());
    if (file_exists(DATA_FILE)) {
        $d = json_decode(file_get_contents(DATA_FILE), true);
        if (is_array($d)) { foreach ($def as $k=>$v) if (!isset($d[$k]) || !is_array($d[$k])) $d[$k]=$v; return $d; }
    }
    return $def;
}

function writeData($d) {
    $json = json_encode($d, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
    $fp = @fopen(DATA_FILE, 'c+');
    if (!$fp) { @file_put_contents(DATA_FILE, $json); return; }
    if (flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, $json);
        fflush($fp);
        flock($fp, LOCK_UN);
    } else {
        @file_put_contents(DATA_FILE, $json);
    }
    fclose($fp);
}

function genID($p) { return $p . date('YmdHis') . rand(100,999); }
function rp($n) { return 'Rp ' . number_format((float)$n, 0, ',', '.'); }
function rpNum($n) { return number_format((float)$n, 0, ',', '.'); }
function tgl($t) { return date('d M Y, H:i', strtotime($t)); }
function tglS($t) { return date('d/m/Y', strtotime($t)); }
function tglL($t) {
    $h=array('Sunday'=>'Minggu','Monday'=>'Senin','Tuesday'=>'Selasa','Wednesday'=>'Rabu','Thursday'=>'Kamis','Friday'=>'Jumat','Saturday'=>'Sabtu');
    $b=array('January'=>'Januari','February'=>'Februari','March'=>'Maret','April'=>'April','May'=>'Mei','June'=>'Juni','July'=>'Juli','August'=>'Agustus','September'=>'September','October'=>'Oktober','November'=>'November','December'=>'Desember');
    return $h[date('l',strtotime($t))].', '.date('d',strtotime($t)).' '.$b[date('F',strtotime($t))].' '.date('Y',strtotime($t));
}
function ago($t){$d=time()-strtotime($t);if($d<60)return'Baru saja';if($d<3600)return floor($d/60).' menit lalu';if($d<86400)return floor($d/3600).' jam lalu';if($d<604800)return floor($d/86400).' hari lalu';return tglS($t);}
function safeA($a){return is_array($a)?$a:array();}
function jsonAttr($data){return htmlspecialchars(json_encode($data, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');}
function tgEsc($s){return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');}
function genKW($data,$jenis){
    $pfx=$jenis==='servis'?'KW-SRV':'KW-CUC';$t=date('Ymd');$c=1;
    $arr=$jenis==='servis'?$data['servis']:$data['doorsmeer'];
    foreach($arr as $s) if(!empty($s['no_kwitansi'])&&strpos($s['no_kwitansi'],$pfx.'-'.$t)!==false) $c++;
    return $pfx.'-'.$t.'-'.str_pad($c,3,'0',STR_PAD_LEFT);
}
function genORD($data){
    $t=date('Ymd');$c=1;
    foreach($data['pesanan'] as $p) if(!empty($p['no_order'])&&strpos($p['no_order'],'ORD-'.$t)!==false) $c++;
    return 'ORD-'.$t.'-'.str_pad($c,3,'0',STR_PAD_LEFT);
}
function hrgF($it,$j){
    if(isset($it['harga_final'])) return $it['harga_final'];
    return $j==='servis'?(isset($it['biaya'])?$it['biaya']:0):(isset($it['harga'])?$it['harga']:0);
}
function statusLabel($s){$m=array('antri'=>'Menunggu Dikerjakan','proses'=>'Sedang Dikerjakan','selesai'=>'Selesai Dikerjakan');return isset($m[$s])?$m[$s]:ucfirst($s);}

function waMsg($p) {
    $msg  = "Halo " . (isset($p['nama_customer']) ? $p['nama_customer'] : 'Pelanggan') . " 🙏\n\n";
    $msg .= "Update pesanan Anda di *" . APP_NAME . "*:\n\n";
    $msg .= "📄 No. Order: " . (isset($p['no_order']) ? $p['no_order'] : '-') . "\n";
    $msg .= "📅 Tanggal: " . (isset($p['tanggal']) ? tglS($p['tanggal']) : '-') . "\n";
    if (!empty($p['no_polisi'])) $msg .= "🚗 Mobil: " . (isset($p['tipe']) ? $p['tipe'] : '') . " (" . $p['no_polisi'] . ")\n";
    $msg .= "\n*Rincian Layanan:*\n";
    if (!empty($p['items']) && is_array($p['items'])) {
        foreach ($p['items'] as $it) $msg .= "• " . $it['nama'] . " x" . $it['qty'] . " = " . rp($it['subtotal']) . "\n";
    }
    if (!empty($p['tambahan']) && is_array($p['tambahan'])) {
        foreach ($p['tambahan'] as $it) $msg .= "• + " . $it['nama'] . " x" . $it['qty'] . " = " . rp($it['subtotal']) . "\n";
    }
    if (!empty($p['servis']['jenis'])) $msg .= "• Servis: " . $p['servis']['jenis'] . " = " . rp($p['servis']['biaya']) . "\n";
    if (!empty($p['doorsmeer']['paket'])) $msg .= "• Cuci: " . $p['doorsmeer']['paket'] . " = " . rp($p['doorsmeer']['harga']) . "\n";
    if (!empty($p['diskon']) && $p['diskon'] > 0) $msg .= "• Diskon: -" . rp($p['diskon']) . "\n";
    $msg .= "\n💰 *Total: " . rp(isset($p['total']) ? $p['total'] : 0) . "*\n";
    $statusPekerjaan = isset($p['status_pekerjaan']) ? $p['status_pekerjaan'] : 'antri';
    $statusBayar = isset($p['status_bayar']) ? $p['status_bayar'] : 'belum_bayar';
    $msg .= "🔧 Status: " . statusLabel($statusPekerjaan) . "\n";
    $msg .= "💵 Bayar: " . ($statusBayar === 'lunas' ? '✅ LUNAS' : '⏳ Belum Bayar') . "\n";
    if (!empty($p['estimasi_selesai']) && $statusPekerjaan !== 'selesai') {
        $msg .= "⏰ Estimasi selesai: " . tgl($p['estimasi_selesai']) . "\n";
    }
    $msg .= "\nTerima kasih telah mempercayakan mobil Anda kepada kami 🙏\n*" . APP_NAME . "*";
    return $msg;
}

function tgPad($str, $w, $align='left') {
    $str = (string)$str;
    $len = function_exists('mb_strlen') ? mb_strlen($str,'UTF-8') : strlen($str);
    if ($len > $w) {
        $str = function_exists('mb_substr') ? mb_substr($str,0,$w-1,'UTF-8').'…' : substr($str,0,$w-1).'.';
        $len = $w;
    }
    $pad = $w - $len;
    if ($pad <= 0) return $str;
    return $align==='right' ? str_repeat(' ',$pad).$str : $str.str_repeat(' ',$pad);
}
function tgTable($headers, $rows, $widths, $aligns) {
    $out = '';
    $hLine = '';
    foreach ($headers as $i=>$h) $hLine .= tgPad($h,$widths[$i],$aligns[$i]).' ';
    $out .= rtrim($hLine)."\n";
    $sep = '';
    foreach ($widths as $w) $sep .= str_repeat('─',$w+1);
    $out .= $sep."\n";
    foreach ($rows as $row) {
        $r = '';
        foreach ($row as $i=>$c) $r .= tgPad($c,$widths[$i],$aligns[$i]).' ';
        $out .= rtrim($r)."\n";
    }
    $out .= $sep."\n";
    return $out;
}
function tgPre($content) {
    return '<pre>'.htmlspecialchars($content, ENT_QUOTES, 'UTF-8').'</pre>';
}

function sendTGSingle($msg, $parseMode = 'HTML') {
    $data = array('chat_id'=>TELEGRAM_CHAT_ID, 'text'=>$msg, 'disable_web_page_preview'=>true);
    if ($parseMode) $data['parse_mode'] = $parseMode;
    $ch = curl_init("https://api.telegram.org/bot".TELEGRAM_BOT_TOKEN."/sendMessage");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $resp = curl_exec($ch);
    $errNo = curl_errno($ch); $err = curl_error($ch);
    curl_close($ch);
    if ($resp === false || $errNo) {
        @file_put_contents(__DIR__.'/telegram_error.log', date('Y-m-d H:i:s')." CURL({$errNo}): {$err}\n", FILE_APPEND);
        return false;
    }
    $res = json_decode($resp, true);
    if (!isset($res['ok']) || !$res['ok']) {
        $desc = isset($res['description']) ? $res['description'] : 'unknown error';
        @file_put_contents(__DIR__.'/telegram_error.log', date('Y-m-d H:i:s')." API: {$desc} | len=".strlen($msg)."\n", FILE_APPEND);
        return false;
    }
    return true;
}

function sendTGArray($msgs) {
    if (!TELEGRAM_BOT_TOKEN || !TELEGRAM_CHAT_ID) return false;
    $ok = true;
    $total = count($msgs);
    $idx = 0;
    foreach ($msgs as $msg) {
        if (trim($msg) === '') continue;
        $idx++;
        if ($total > 1) $msg = "({$idx}/{$total})\n" . $msg;
        if (!sendTGSingle($msg, 'HTML')) {
            if (!sendTGSingle(strip_tags($msg), null)) $ok = false;
        }
        usleep(300000);
    }
    return $ok;
}

function sendTG($msg) {
    return sendTGSingle($msg, 'HTML');
}

// ============================================================
// KIRIM DOKUMEN KE TELEGRAM
// ============================================================
function sendTGDocument($filePath, $caption = '') {
    if (!TELEGRAM_BOT_TOKEN || !TELEGRAM_CHAT_ID) return false;
    if (!file_exists($filePath)) return false;

    $ch = curl_init("https://api.telegram.org/bot".TELEGRAM_BOT_TOKEN."/sendDocument");
    $post = [
        'chat_id'    => TELEGRAM_CHAT_ID,
        'caption'    => $caption,
        'parse_mode' => 'HTML',
        'document'   => new CURLFile($filePath, 'application/pdf', basename($filePath)),
    ];
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $resp = curl_exec($ch);
    $errNo = curl_errno($ch); $err = curl_error($ch);
    curl_close($ch);

    if ($resp === false || $errNo) {
        @file_put_contents(__DIR__.'/telegram_error.log', date('Y-m-d H:i:s')." CURL-DOC({$errNo}): {$err}\n", FILE_APPEND);
        return false;
    }
    $res = json_decode($resp, true);
    if (!isset($res['ok']) || !$res['ok']) {
        $desc = isset($res['description']) ? $res['description'] : 'unknown';
        @file_put_contents(__DIR__.'/telegram_error.log', date('Y-m-d H:i:s')." API-DOC: {$desc}\n", FILE_APPEND);
        return false;
    }
    return true;
}

// ============================================================
// BUAT PDF LAPORAN HARIAN — KWITANSI STYLE
// ============================================================
function buildLaporanPDF($tanggal) {
    $data = readData();
    if (!$tanggal) $tanggal = date('Y-m-d');

    $hI = ['Sunday'=>'Minggu','Monday'=>'Senin','Tuesday'=>'Selasa','Wednesday'=>'Rabu','Thursday'=>'Kamis','Friday'=>'Jumat','Saturday'=>'Sabtu'];
    $bI = ['January'=>'Januari','February'=>'Februari','March'=>'Maret','April'=>'April','May'=>'Mei','June'=>'Juni','July'=>'Juli','August'=>'Agustus','September'=>'September','October'=>'Oktober','November'=>'November','December'=>'Desember'];
    $hf = $hI[date('l',strtotime($tanggal))];
    $bf = $bI[date('F',strtotime($tanggal))];
    $tf = date('d',strtotime($tanggal)).' '.$bf.' '.date('Y',strtotime($tanggal));
    $tglLabel = "{$hf}, {$tf}";

    $sm=[]; foreach($data['stock_masuk'] as $x) if(date('Y-m-d',strtotime($x['tanggal']))===$tanggal) $sm[]=$x;
    $sk=[]; foreach($data['stock_keluar'] as $x) if(date('Y-m-d',strtotime($x['tanggal']))===$tanggal) $sk[]=$x;
    $ss=[]; foreach($data['servis'] as $x) if(date('Y-m-d',strtotime($x['tanggal']))===$tanggal && $x['status']==='selesai') $ss[]=$x;
    $cs=[]; foreach($data['doorsmeer'] as $x) if(date('Y-m-d',strtotime($x['tanggal']))===$tanggal && $x['status']==='selesai') $cs[]=$x;
    $ps=[]; foreach($data['pesanan'] as $x) if(date('Y-m-d',strtotime($x['tanggal']))===$tanggal) $ps[]=$x;
    $pg=[]; foreach($data['pengeluaran'] as $x) if($x['tanggal']===$tanggal) $pg[]=$x;

    $oS=0; foreach($ss as $s) $oS+=hrgF($s,'servis');
    $oC=0; foreach($cs as $c) $oC+=hrgF($c,'cuci');
    $oP=0; foreach($ps as $p) $oP += (isset($p['subtotal_barang'])?$p['subtotal_barang']:0) + (isset($p['subtotal_tambahan'])?$p['subtotal_tambahan']:0);
    $tP=0; foreach($pg as $g) $tP+=$g['jumlah'];
    $tO=$oS+$oC+$oP; $laba=$tO-$tP;

    $uS=[]; foreach($data['servis'] as $x) if($x['status']==='selesai' && (!isset($x['status_bayar'])||$x['status_bayar']!=='lunas')) $uS[]=$x;
    $uC=[]; foreach($data['doorsmeer'] as $x) if($x['status']==='selesai' && (!isset($x['status_bayar'])||$x['status_bayar']!=='lunas')) $uC[]=$x;
    $uP=[]; foreach($data['pesanan'] as $x) if(!isset($x['status_bayar'])||$x['status_bayar']!=='lunas') $uP[]=$x;
    $tU=0; foreach($uS as $u) $tU+=hrgF($u,'servis');
    foreach($uC as $u) $tU+=hrgF($u,'cuci');
    foreach($uP as $u) $tU+=isset($u['total'])?$u['total']:0;
    $stokM=[]; foreach($data['barang'] as $b) if($b['stok']<=$b['stok_minimum']) $stokM[]=$b;

    $GOLD    = [184,134,11];
    $GOLD_L  = [255,215,0];
    $DARK    = [22,22,22];
    $DARKER  = [8,8,8];
    $GRAY    = [110,110,110];
    $RED     = [192,42,42];
    $GREEN   = [34,139,34];
    $CREAM   = [252,248,238];
    $WHITE   = [255,255,255];
    $BORDER  = [228,222,205];

    $pdf = new SimplePDF();
    $PW = $pdf->pageW;
    $PH = $pdf->pageH;
    $M  = 28;
    $CW = $PW - 2 * $M;
    $pageNum = 1;

    $deco  = sprintf("1.2 w\n%.3f %.3f %.3f RG\n", $GOLD[0]/255, $GOLD[1]/255, $GOLD[2]/255);
    $deco .= sprintf("%.2f %.2f %.2f %.2f re S\n", 8, 8, $PW-16, $PH-16);
    $deco .= sprintf("0.3 w\n%.3f %.3f %.3f RG\n", $GOLD_L[0]/255, $GOLD_L[1]/255, $GOLD_L[2]/255);
    $deco .= sprintf("%.2f %.2f %.2f %.2f re S\n", 11, 11, $PW-22, $PH-22);
    $pdf->setDecorator($deco);

    $HDR_H = 90;
    $pdf->rectFill(0, $PH - $HDR_H, $PW, $HDR_H, $DARKER);
    $pdf->rectFill(0, $PH - $HDR_H - 3, $PW, 3, $GOLD);
    $pdf->rectFill(0, $PH - $HDR_H - 4.5, $PW, 1.5, $GOLD_L);

    $logoData = prepareLogoJPEG();
    $logoSize = 50;
    $brandX = 30;
    if ($logoData) {
        $pdf->image(28, $PH - $HDR_H + 20, $logoSize, $logoSize, $logoData);
        $brandX = 90;
    }

    $pdf->text($brandX, $PH - 42, APP_NAME, 'F4', 26, $GOLD_L);
    $pdf->text($brandX, $PH - 58, 'BENGKEL MOBIL  -  CARWASH  -  AUTO DETAILING', 'F1', 8, [180,180,180]);
    $pdf->text($brandX, $PH - 70, 'Binjai, Sumatera Utara   |   0887-7792-28899   |   carsotocare@gmail.com', 'F1', 7, [130,130,130]);

    $pdf->textRight($PW - 30, $PH - 40, 'LAPORAN HARIAN', 'F4', 20, $GOLD_L);
    $pdf->textRight($PW - 30, $PH - 58, $tglLabel, 'F1', 10, [230,230,230]);
    $pdf->textRight($PW - 30, $PH - 72, 'Dicetak: ' . date('d/m/Y H:i') . ' WIB', 'F1', 7.5, [150,150,150]);

    $y = $PH - $HDR_H - 30;

    $drawRunningHeader = function() use ($pdf, $PW, $PH, $GOLD, $GOLD_L, $DARKER) {
        $h = 38;
        $pdf->rectFill(14, $PH - 14 - $h, $PW - 28, $h, $DARKER);
        $pdf->rectFill(14, $PH - 14 - $h - 2, $PW - 28, 2, $GOLD);
        $pdf->text(28, $PH - 14 - $h + 15, APP_NAME . '   ·   LAPORAN HARIAN', 'F4', 12, $GOLD_L);
        $pdf->textRight($PW - 28, $PH - 14 - $h + 15, 'LANJUTAN', 'F1', 8, [180,180,180]);
    };

    $checkBreak = function($needed) use (&$y, &$pageNum, $pdf, $PH, $M, $drawRunningHeader) {
        if ($y - $needed < $M + 30) {
            $pdf->addPage();
            $pageNum++;
            $drawRunningHeader();
            $y = $PH - 14 - 38 - 22;
            return true;
        }
        return false;
    };

    $sectionTitle = function($title, $count = null) use (&$y, $pdf, $M, $CW, $GOLD, $GOLD_L, $DARK, $GRAY_L, $checkBreak) {
        $checkBreak(45);
        $h = 24;
        $bot = $y - $h;

        $pdf->rectFill($M, $bot, $CW, $h, $DARK);
        $pdf->rectFill($M, $bot, 4, $h, $GOLD);
        $pdf->line($M, $y, $M + $CW, $y, 0.6, $GOLD_L);
        $pdf->line($M, $bot, $M + $CW, $bot, 0.3, $GOLD);

        $baseline = ($y + $bot) / 2 - 11.5 * 0.36;
        $pdf->text($M + 18, $baseline, $title, 'F4', 11.5, $GOLD_L);

        if ($count !== null) {
            $pdf->textRight($M + $CW - 18, $baseline, $count, 'F1', 9.5, $GRAY_L);
        }

        $y -= ($h + 12);
    };

    $kvRow = function($label, $value, $style = 'normal') use (&$y, $pdf, $M, $CW, $GOLD, $GOLD_L, $DARK, $DARKER, $CREAM, $WHITE) {
        $rowH = 20;

        if ($style === 'total') $rowH = 24;
        elseif ($style === 'laba') $rowH = 28;

        $bot = $y - $rowH;

        $labelColor = [55,55,55];
        $valueColor = [25,25,25];
        $labelFont  = 'F1';
        $valueFont  = 'F2';
        $labelSize  = 10;
        $valueSize  = 11;

        if ($style === 'cream') {
            $pdf->rectFill($M, $bot, $CW, $rowH, $CREAM);
        } elseif ($style === 'white') {
            $pdf->rectFill($M, $bot, $CW, $rowH, $WHITE);
        } elseif ($style === 'subtotal') {
            $pdf->rectFill($M, $bot, $CW, $rowH, [245,235,205]);
            $labelColor = [85,62,22];
            $valueColor = [85,62,22];
            $labelFont = 'F2';
            $labelSize = 10.5;
            $valueSize = 11.5;
        } elseif ($style === 'total') {
            $pdf->rectFill($M, $bot, $CW, $rowH, $DARK);
            $pdf->rectFill($M, $bot, 3, $rowH, $GOLD);
            $labelColor = $WHITE;
            $valueColor = $GOLD_L;
            $labelFont = 'F2';
            $labelSize = 11;
            $valueSize = 12;
        } elseif ($style === 'laba') {
            $pdf->rectFill($M, $bot, $CW, $rowH, $DARKER);
            $pdf->rectFill($M, $bot, 4, $rowH, $GOLD);
            $pdf->line($M, $y, $M + $CW, $y, 0.5, $GOLD_L);
            $labelColor = $GOLD_L;
            $valueColor = $GOLD_L;
            $labelFont = 'F2';
            $labelSize = 13;
            $valueSize = 15;
        }

        $baselineL = ($y + $bot) / 2 - $labelSize * 0.36;
        $baselineV = ($y + $bot) / 2 - $valueSize * 0.36;

        $pdf->text($M + 18, $baselineL, $label, $labelFont, $labelSize, $labelColor);
        $pdf->textRight($M + $CW - 18, $baselineV, $value, $valueFont, $valueSize, $valueColor);

        $y = $bot;
    };

    $calcWidths = function($weights) use ($CW) {
        $inner = $CW - 24;
        $sumW = array_sum($weights);
        $widths = [];
        $acc = 0;
        foreach ($weights as $i => $w) {
            $cw = ($i === count($weights) - 1) ? ($inner - $acc) : round($w / $sumW * $inner, 2);
            $widths[] = $cw;
            $acc += $cw;
        }
        return $widths;
    };

    $tableHead = function($cols, $weights, $aligns) use (&$y, $pdf, $M, $CW, $GOLD, $GOLD_L, $DARK, $checkBreak, $calcWidths) {
        $checkBreak(30);
        $h = 24;
        $widths = $calcWidths($weights);
        $bot = $y - $h;

        $pdf->rectFill($M, $bot, $CW, $h, $DARK);
        $pdf->rectFill($M, $bot, 3, $h, $GOLD);
        $pdf->line($M, $y, $M + $CW, $y, 0.6, $GOLD_L);

        $baseline = ($y + $bot) / 2 - 9.5 * 0.36;

        $cx = $M + 14;
        foreach ($cols as $i => $col) {
            $w = $widths[$i];
            $al = $aligns[$i] ?? 'left';

            if ($al === 'right') {
                $pdf->textRight($cx + $w - 10, $baseline, $col, 'F2', 9.5, $GOLD_L);
            } elseif ($al === 'center') {
                $pdf->textCenter($cx + $w / 2, $baseline, $col, 'F2', 9.5, $GOLD_L);
            } else {
                $pdf->text($cx + 10, $baseline, $col, 'F2', 9.5, $GOLD_L);
            }
            $cx += $w;
        }

        $y = $bot;
    };

    $tableRow = function($cols, $weights, $aligns, $zebra = true) use (&$y, $pdf, $M, $CW, $CREAM, $WHITE, $DARK, $BORDER, $calcWidths) {
        $h = 22;
        $widths = $calcWidths($weights);
        $bot = $y - $h;

        $pdf->rectFill($M, $bot, $CW, $h, $zebra ? $CREAM : $WHITE);
        $pdf->line($M, $bot, $M + $CW, $bot, 0.15, $BORDER);

        $baseline = ($y + $bot) / 2 - 9 * 0.36;

        $cx = $M + 14;
        foreach ($cols as $i => $col) {
            $w = $widths[$i];
            $al = $aligns[$i] ?? 'left';

            $color = $DARK;
            $font = 'F1';
            if (is_array($col)) {
                $color = $col['color'] ?? $DARK;
                $font  = ($col['bold'] ?? false) ? 'F2' : 'F1';
                $col   = $col['text'] ?? '';
            }

            $truncated = $pdf->truncate((string)$col, $w - 20, $font, 9);

            if ($al === 'right') {
                $pdf->textRight($cx + $w - 10, $baseline, $truncated, $font, 9, $color);
            } elseif ($al === 'center') {
                $pdf->textCenter($cx + $w / 2, $baseline, $truncated, $font, 9, $color);
            } else {
                $pdf->text($cx + 10, $baseline, $truncated, $font, 9, $color);
            }
            $cx += $w;
        }

        $y = $bot;
    };

    $tableTotal = function($label, $value, $valueColor = null) use (&$y, $pdf, $M, $CW, $DARK, $GOLD_L, $GOLD) {
        $h = 24;
        $bot = $y - $h;

        $pdf->rectFill($M, $bot, $CW, $h, $DARK);
        $pdf->rectFill($M, $bot, 3, $h, $GOLD);
        $pdf->line($M, $y, $M + $CW, $y, 0.6, $GOLD_L);

        $bl = ($y + $bot) / 2 - 10.5 * 0.36;
        $bv = ($y + $bot) / 2 - 11.5 * 0.36;

        $vc = $valueColor ?: $GOLD_L;
        $pdf->text($M + 18, $bl, $label, 'F2', 10.5, $GOLD_L);
        $pdf->textRight($M + $CW - 18, $bv, $value, 'F2', 11.5, $vc);

        $y = $bot;
    };

    // RINGKASAN
    $sectionTitle('RINGKASAN KEUANGAN');
    $kvRow('Pendapatan Servis',      'Rp ' . rpNum($oS), 'cream');
    $kvRow('Pendapatan Cuci Mobil',  'Rp ' . rpNum($oC), 'white');
    $kvRow('Penjualan Barang',       'Rp ' . rpNum($oP), 'cream');
    $kvRow('Total Pendapatan',       'Rp ' . rpNum($tO), 'subtotal');
    $kvRow('Pengeluaran',           '-Rp ' . rpNum($tP), 'white');
    $kvRow('LABA BERSIH',            'Rp ' . rpNum($laba), 'laba');
    $y -= 16;

    // PESANAN
    if (!empty($ps)) {
        $sectionTitle('PESANAN', count($ps) . ' Transaksi');
        $n = 1;
        foreach ($ps as $p) {
            $checkBreak(80);
            $isLunas = (isset($p['status_bayar']) && $p['status_bayar']==='lunas');

            $hdH = 26;
            $hdBot = $y - $hdH;

            $pdf->rectFill($M, $hdBot, $CW, $hdH, [244,238,222]);
            $pdf->rectFill($M, $hdBot, 3, $hdH, $GOLD);
            $pdf->line($M, $hdBot, $M + $CW, $hdBot, 0.2, $BORDER);

            $bl1 = ($y + $hdBot) / 2 + 3 - 11 * 0.36;
            $bl2 = ($y + $hdBot) / 2 - 4 - 8 * 0.36;

            $pdf->text($M + 14, $bl1, $n . '. ' . $p['nama_customer'], 'F2', 11, $DARK);
            $pdf->textRight($M + $CW - 14, $bl1, 'Rp ' . rpNum($p['total']), 'F2', 12, $GOLD);
            $pdf->text($M + 14, $bl2, (isset($p['no_order'])?$p['no_order']:'-') . '   ·   ' . ($isLunas ? 'LUNAS' : 'BELUM BAYAR'), 'F1', 8, $GRAY);

            $y = $hdBot;

            $lineH = 15;

            if (!empty($p['items'])) {
                foreach ($p['items'] as $it) {
                    $checkBreak($lineH + 5);
                    $bot = $y - $lineH;
                    $baseline = ($y + $bot) / 2 - 9 * 0.36;
                    $line = '-  ' . $it['nama'] . '  x' . $it['qty'];
                    $pdf->text($M + 24, $baseline, $pdf->truncate($line, $CW - 180, 'F1', 9), 'F1', 9, [60,60,60]);
                    $pdf->textRight($M + $CW - 24, $baseline, 'Rp ' . rpNum($it['subtotal']), 'F1', 9, $DARK);
                    $y = $bot;
                }
            }
            if (!empty($p['tambahan']) && is_array($p['tambahan'])) {
                foreach ($p['tambahan'] as $it) {
                    $checkBreak($lineH + 5);
                    $bot = $y - $lineH;
                    $baseline = ($y + $bot) / 2 - 9 * 0.36;
                    $line = '+  ' . $it['nama'] . '  x' . $it['qty'] . '  (tambahan)';
                    $pdf->text($M + 24, $baseline, $pdf->truncate($line, $CW - 180, 'F1', 9), 'F1', 9, $GREEN);
                    $pdf->textRight($M + $CW - 24, $baseline, 'Rp ' . rpNum($it['subtotal']), 'F1', 9, $GREEN);
                    $y = $bot;
                }
            }
            if (!empty($p['servis']['jenis'])) {
                $checkBreak($lineH + 5);
                $bot = $y - $lineH;
                $baseline = ($y + $bot) / 2 - 9 * 0.36;
                $line = '*  Jasa Servis: ' . $p['servis']['jenis'];
                $pdf->text($M + 24, $baseline, $pdf->truncate($line, $CW - 180, 'F1', 9), 'F1', 9, $GOLD);
                $pdf->textRight($M + $CW - 24, $baseline, 'Rp ' . rpNum($p['servis']['biaya']), 'F1', 9, $GOLD);
                $y = $bot;
            }
            if (!empty($p['servis']['mekanik_biaya'])) {
                $checkBreak($lineH + 5);
                $bot = $y - $lineH;
                $baseline = ($y + $bot) / 2 - 9 * 0.36;
                $line = '*  Jasa Mekanik' . (!empty($p['servis']['mekanik_nama']) ? ' (' . $p['servis']['mekanik_nama'] . ')' : '');
                $pdf->text($M + 24, $baseline, $pdf->truncate($line, $CW - 180, 'F1', 9), 'F1', 9, $GOLD);
                $pdf->textRight($M + $CW - 24, $baseline, 'Rp ' . rpNum($p['servis']['mekanik_biaya']), 'F1', 9, $GOLD);
                $y = $bot;
            }
            if (!empty($p['doorsmeer']['paket'])) {
                $checkBreak($lineH + 5);
                $bot = $y - $lineH;
                $baseline = ($y + $bot) / 2 - 9 * 0.36;
                $line = '*  Cuci Mobil: ' . $p['doorsmeer']['paket'];
                $pdf->text($M + 24, $baseline, $pdf->truncate($line, $CW - 180, 'F1', 9), 'F1', 9, [77,171,247]);
                $pdf->textRight($M + $CW - 24, $baseline, 'Rp ' . rpNum($p['doorsmeer']['harga']), 'F1', 9, [77,171,247]);
                $y = $bot;
            }
            if (!empty($p['diskon']) && $p['diskon'] > 0) {
                $checkBreak($lineH + 5);
                $bot = $y - $lineH;
                $baseline = ($y + $bot) / 2 - 9 * 0.36;
                $pdf->text($M + 24, $baseline, 'Diskon', 'F1', 9, $RED);
                $pdf->textRight($M + $CW - 24, $baseline, '-Rp ' . rpNum($p['diskon']), 'F1', 9, $RED);
                $y = $bot;
            }
            if (!empty($p['note'])) {
                $checkBreak($lineH + 5);
                $bot = $y - $lineH;
                $baseline = ($y + $bot) / 2 - 8.5 * 0.36;
                $pdf->text($M + 24, $baseline, 'Catatan: ' . $pdf->truncate($p['note'], $CW - 50, 'F1', 8.5), 'F1', 8.5, $GRAY);
                $y = $bot;
            }

            $pdf->line($M + 14, $y - 5, $M + $CW - 14, $y - 5, 0.15, [220,215,200]);
            $y -= 12;
            $n++;
        }
        $y -= 10;
    }

    // SERVIS SELESAI
    if (!empty($ss)) {
        $sectionTitle('SERVIS SELESAI', count($ss) . ' Transaksi');
        $weights = [0.9, 3.6, 3.0, 2.0, 1.5];
        $aligns  = ['center', 'left', 'left', 'right', 'center'];
        $tableHead(['No', 'Pelanggan', 'Jenis Servis', 'Biaya', 'Status'], $weights, $aligns);

        $z = true; $totS = 0;
        foreach ($ss as $i => $s) {
            $checkBreak(25);
            $isLunas = (isset($s['status_bayar']) && $s['status_bayar']==='lunas');
            $stColor = $isLunas ? $GREEN : $RED;
            $tableRow([
                ($i+1) . '.',
                $s['nama_customer'],
                $s['jenis_servis'],
                ['text' => 'Rp ' . rpNum(hrgF($s,'servis')), 'bold' => true],
                ['text' => $isLunas ? 'LUNAS' : 'UTANG', 'color' => $stColor, 'bold' => true],
            ], $weights, $aligns, $z);
            $totS += hrgF($s,'servis');
            $z = !$z;
        }
        $tableTotal('TOTAL SERVIS', 'Rp ' . rpNum($totS));
        $y -= 14;
    }

    // CUCI SELESAI
    if (!empty($cs)) {
        $sectionTitle('CUCI SELESAI', count($cs) . ' Transaksi');
        $weights = [0.9, 3.6, 3.0, 2.0, 1.5];
        $aligns  = ['center', 'left', 'left', 'right', 'center'];
        $tableHead(['No', 'Pelanggan', 'Paket Cuci', 'Harga', 'Status'], $weights, $aligns);

        $z = true; $totC = 0;
        foreach ($cs as $i => $c) {
            $checkBreak(25);
            $isLunas = (isset($c['status_bayar']) && $c['status_bayar']==='lunas');
            $stColor = $isLunas ? $GREEN : $RED;
            $tableRow([
                ($i+1) . '.',
                $c['nama_customer'],
                $c['paket'],
                ['text' => 'Rp ' . rpNum(hrgF($c,'cuci')), 'bold' => true],
                ['text' => $isLunas ? 'LUNAS' : 'UTANG', 'color' => $stColor, 'bold' => true],
            ], $weights, $aligns, $z);
            $totC += hrgF($c,'cuci');
            $z = !$z;
        }
        $tableTotal('TOTAL CUCI', 'Rp ' . rpNum($totC));
        $y -= 14;
    }

    // BARANG MASUK
    if (!empty($sm)) {
        $sectionTitle('BARANG MASUK', count($sm) . ' Item');
        $weights = [0.9, 1.8, 6.2, 1.5];
        $aligns  = ['center', 'left', 'left', 'right'];
        $tableHead(['No', 'Waktu', 'Nama Barang / Supplier', 'Qty'], $weights, $aligns);

        $z = true; $totM = 0;
        foreach ($sm as $i => $x) {
            $checkBreak(25);
            $tableRow([
                ($i+1) . '.',
                date('d/m H:i', strtotime($x['tanggal'])),
                $x['nama_barang'] . '   [' . $x['supplier'] . ']',
                ['text' => '+' . $x['jumlah'], 'color' => $GREEN, 'bold' => true],
            ], $weights, $aligns, $z);
            $totM += $x['jumlah'];
            $z = !$z;
        }
        $tableTotal('TOTAL MASUK', '+' . $totM . ' unit');
        $y -= 14;
    }

    // BARANG KELUAR
    if (!empty($sk)) {
        $sectionTitle('BARANG KELUAR', count($sk) . ' Item');
        $weights = [0.9, 1.8, 6.2, 1.5];
        $aligns  = ['center', 'left', 'left', 'right'];
        $tableHead(['No', 'Waktu', 'Nama Barang / Keterangan', 'Qty'], $weights, $aligns);

        $z = true; $totK = 0;
        foreach ($sk as $i => $x) {
            $checkBreak(25);
            $ket = (isset($x['keterangan']) && $x['keterangan']) ? $x['keterangan'] : '-';
            $tableRow([
                ($i+1) . '.',
                date('d/m H:i', strtotime($x['tanggal'])),
                $x['nama_barang'] . '   [' . $ket . ']',
                ['text' => '-' . $x['jumlah'], 'color' => $RED, 'bold' => true],
            ], $weights, $aligns, $z);
            $totK += $x['jumlah'];
            $z = !$z;
        }
        $tableTotal('TOTAL KELUAR', '-' . $totK . ' unit');
        $y -= 14;
    }

    // PENGELUARAN
    if (!empty($pg)) {
        $sectionTitle('PENGELUARAN', count($pg) . ' Item');
        $weights = [0.9, 2.0, 6.5, 1.8];
        $aligns  = ['center', 'left', 'left', 'right'];
        $tableHead(['No', 'Tanggal', 'Keterangan', 'Jumlah'], $weights, $aligns);

        $z = true;
        foreach ($pg as $i => $g) {
            $checkBreak(25);
            $tableRow([
                ($i+1) . '.',
                date('d/m/Y', strtotime($g['tanggal'])),
                $g['keterangan'],
                ['text' => '-Rp ' . rpNum($g['jumlah']), 'color' => $RED, 'bold' => true],
            ], $weights, $aligns, $z);
            $z = !$z;
        }
        $tableTotal('TOTAL PENGELUARAN', '-Rp ' . rpNum($tP), $RED);
        $y -= 14;
    }

    // UTANG
    if ($tU > 0) {
        $sectionTitle('UTANG PELANGGAN');
        $sumS=0; foreach($uS as $u) $sumS+=hrgF($u,'servis');
        $sumC=0; foreach($uC as $u) $sumC+=hrgF($u,'cuci');
        $sumP=0; foreach($uP as $u) $sumP+=isset($u['total'])?$u['total']:0;

        $weights = [3.0, 3.2, 3.8];
        $aligns  = ['left', 'center', 'right'];
        $tableHead(['Jenis', 'Jumlah Transaksi', 'Nilai Utang'], $weights, $aligns);

        $z = true;
        $tableRow(['Servis',      count($uS) . ' transaksi',  ['text' => 'Rp ' . rpNum($sumS), 'bold' => true]], $weights, $aligns, $z); $z = !$z;
        $tableRow(['Cuci Mobil',  count($uC) . ' transaksi',  ['text' => 'Rp ' . rpNum($sumC), 'bold' => true]], $weights, $aligns, $z); $z = !$z;
        $tableRow(['Pesanan',     count($uP) . ' transaksi',  ['text' => 'Rp ' . rpNum($sumP), 'bold' => true]], $weights, $aligns, $z);
        $tableTotal('TOTAL UTANG', 'Rp ' . rpNum($tU), $RED);
        $y -= 14;
    }

    // STOK MENIPIS
    if (!empty($stokM)) {
        $sectionTitle('PERINGATAN STOK MENIPIS', count($stokM) . ' Item');
        $weights = [0.9, 7.0, 1.5, 1.5];
        $aligns  = ['center', 'left', 'right', 'right'];
        $tableHead(['No', 'Nama Barang', 'Stok', 'Min'], $weights, $aligns);

        $z = true;
        foreach ($stokM as $i => $b) {
            $checkBreak(25);
            $tableRow([
                ($i+1) . '.',
                $b['merek'] . ' ' . $b['nama'],
                ['text' => (string)$b['stok'], 'color' => $RED, 'bold' => true],
                (string)$b['stok_minimum'],
            ], $weights, $aligns, $z);
            $z = !$z;
        }
        $y -= 14;
    }

    // FOOTER
    $y -= 26;
    $checkBreak(70);

    $pdf->line($M, $y, $M + $CW, $y, 0.8, $GOLD);
    $pdf->line($M, $y - 1.5, $M + $CW, $y - 1.5, 0.15, $GOLD_L);
    $y -= 22;

    $pdf->textCenter($PW / 2, $y, 'Terima kasih atas kerja keras seluruh tim hari ini', 'F4', 13, $GOLD);
    $y -= 15;
    $pdf->textCenter($PW / 2, $y, 'Laporan ini dihasilkan secara otomatis oleh sistem ' . APP_NAME, 'F1', 8, $GRAY);
    $y -= 11;
    $pdf->textCenter($PW / 2, $y, 'Dicetak: ' . date('d/m/Y H:i:s') . ' WIB', 'F1', 7.5, [160,160,160]);

    $pdf->textCenter($PW / 2, 22, 'Halaman ' . $pageNum, 'F1', 7.5, [170,170,170]);

    $filename = 'Laporan_Harian_' . $tanggal . '.pdf';
    $tmpPath = __DIR__ . '/' . $filename;
    @file_put_contents($tmpPath, $pdf->output());
    return $tmpPath;
}

function kirimLaporan($tanggal=null) {
    $data = readData();
    if (!$tanggal) $tanggal = date('Y-m-d');
    $hI=array('Sunday'=>'Minggu','Monday'=>'Senin','Tuesday'=>'Selasa','Wednesday'=>'Rabu','Thursday'=>'Kamis','Friday'=>'Jumat','Saturday'=>'Sabtu');
    $bI=array('January'=>'Januari','February'=>'Februari','March'=>'Maret','April'=>'April','May'=>'Mei','June'=>'Juni','July'=>'Juli','August'=>'Agustus','September'=>'September','October'=>'Oktober','November'=>'November','December'=>'Desember');
    $hf=$hI[date('l',strtotime($tanggal))];
    $bf=$bI[date('F',strtotime($tanggal))];
    $tf=date('d',strtotime($tanggal)).' '.$bf.' '.date('Y',strtotime($tanggal));

    $sm=array(); foreach($data['stock_masuk'] as $x) if(date('Y-m-d',strtotime($x['tanggal']))===$tanggal) $sm[]=$x;
    $sk=array(); foreach($data['stock_keluar'] as $x) if(date('Y-m-d',strtotime($x['tanggal']))===$tanggal) $sk[]=$x;
    $ss=array(); foreach($data['servis'] as $x) if(date('Y-m-d',strtotime($x['tanggal']))===$tanggal && $x['status']==='selesai') $ss[]=$x;
    $cs=array(); foreach($data['doorsmeer'] as $x) if(date('Y-m-d',strtotime($x['tanggal']))===$tanggal && $x['status']==='selesai') $cs[]=$x;
    $ps=array(); foreach($data['pesanan'] as $x) if(date('Y-m-d',strtotime($x['tanggal']))===$tanggal) $ps[]=$x;
    $pg=array(); foreach($data['pengeluaran'] as $x) if($x['tanggal']===$tanggal) $pg[]=$x;

    $oS=0; foreach($ss as $s) $oS+=hrgF($s,'servis');
    $oC=0; foreach($cs as $c) $oC+=hrgF($c,'cuci');
    $oP=0; foreach($ps as $p) $oP += (isset($p['subtotal_barang'])?$p['subtotal_barang']:0) + (isset($p['subtotal_tambahan'])?$p['subtotal_tambahan']:0);
    $tP=0; foreach($pg as $g) $tP+=$g['jumlah'];
    $tO=$oS+$oC+$oP; $laba=$tO-$tP;

    $uS=array(); foreach($data['servis'] as $x) if($x['status']==='selesai' && (!isset($x['status_bayar'])||$x['status_bayar']!=='lunas')) $uS[]=$x;
    $uC=array(); foreach($data['doorsmeer'] as $x) if($x['status']==='selesai' && (!isset($x['status_bayar'])||$x['status_bayar']!=='lunas')) $uC[]=$x;
    $uP=array(); foreach($data['pesanan'] as $x) if(!isset($x['status_bayar'])||$x['status_bayar']!=='lunas') $uP[]=$x;
    $tU=0; foreach($uS as $u) $tU+=hrgF($u,'servis');
    foreach($uC as $u) $tU+=hrgF($u,'cuci');
    foreach($uP as $u) $tU+=isset($u['total'])?$u['total']:0;
    $stokM=array(); foreach($data['barang'] as $b) if($b['stok']<=$b['stok_minimum']) $stokM[]=$b;

    $L = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
    $msgs = array();

    $m  = $L."\n";
    $m .= "📊 <b>LAPORAN HARIAN ".APP_NAME."</b>\n";
    $m .= $L."\n";
    $m .= "🗓 <b>{$hf}, {$tf}</b>\n";
    $m .= "🕐 <b>".date('H:i:s')." WIB</b>\n\n";
    $m .= $L."\n💰 <b>RINGKASAN KEUANGAN</b>\n".$L."\n";
    $rowsKeu = array(
        array('Servis', 'Rp '.rpNum($oS)),
        array('Cuci Mobil', 'Rp '.rpNum($oC)),
        array('Barang', 'Rp '.rpNum($oP)),
        array('— — —', '— — —'),
        array('TOTAL', 'Rp '.rpNum($tO)),
        array('Pengeluaran', '-Rp '.rpNum($tP)),
        array('— — —', '— — —'),
        array('LABA BERSIH', 'Rp '.rpNum($laba))
    );
    $m .= tgPre(tgTable(array('Kategori','Jumlah'), $rowsKeu, array(18, 18), array('left','right')));
    $msgs[] = $m;

    if (!empty($ps)) {
        $m  = $L."\n🛒 <b>PESANAN (".count($ps).")</b>\n".$L."\n";
        $rows = array(); $totP = 0;
        foreach ($ps as $i=>$p) {
            $st = (isset($p['status_bayar']) && $p['status_bayar']==='lunas') ? 'LUNAS' : 'UTANG';
            $nama = $p['nama_customer'];
            if (function_exists('mb_substr') && mb_strlen($nama,'UTF-8') > 14) $nama = mb_substr($nama,0,13,'UTF-8').'…';
            $rows[] = array(($i+1).'.', $nama, rpNum(isset($p['total'])?$p['total']:0), $st);
            $totP += isset($p['total'])?$p['total']:0;
        }
        $rows[] = array('', 'TOTAL', rpNum($totP), '');
        $m .= tgPre(tgTable(array('No','Pelanggan','Total','Status'), $rows, array(3, 14, 13, 6), array('left','left','right','left')));
        $msgs[] = $m;

        $no = 1;
        foreach ($ps as $p) {
            $st = (isset($p['status_bayar']) && $p['status_bayar']==='lunas') ? '✅ LUNAS' : '⏳ UTANG';
            $mr  = "<b>{$no}. ".tgEsc($p['nama_customer'])."</b> [{$st}]\n";
            $mr .= "📄 <code>".tgEsc(isset($p['no_order'])?$p['no_order']:'-')."</code>\n";
            if (!empty($p['items'])) foreach ($p['items'] as $it) $mr .= "   • ".tgEsc($it['nama'])." x".$it['qty']." = ".rp($it['subtotal'])."\n";
            if (!empty($p['tambahan'])) foreach ($p['tambahan'] as $it) $mr .= "   • ➕ ".tgEsc($it['nama'])." x".$it['qty']." = ".rp($it['subtotal'])."\n";
            if (!empty($p['servis']['jenis'])) $mr .= "   • Servis: ".tgEsc($p['servis']['jenis'])." = ".rp($p['servis']['biaya'])."\n";
            if (!empty($p['servis']['mekanik_biaya'])) $mr .= "   • Mekanik: ".tgEsc(isset($p['servis']['mekanik_nama'])?$p['servis']['mekanik_nama']:'')." = ".rp($p['servis']['mekanik_biaya'])."\n";
            if (!empty($p['doorsmeer']['paket'])) $mr .= "   • Cuci: ".tgEsc($p['doorsmeer']['paket'])." = ".rp($p['doorsmeer']['harga'])."\n";
            if (!empty($p['diskon']) && $p['diskon']>0) $mr .= "   • Diskon: -".rp($p['diskon'])."\n";
            $mr .= "   💵 <b>Total: ".rp(isset($p['total'])?$p['total']:0)."</b>";
            if (!empty($p['note'])) $mr .= "\n   📝 <i>Catatan: ".tgEsc($p['note'])."</i>";
            $msgs[] = $mr;
            $no++;
        }
    }

    if (!empty($ss)) {
        $m  = $L."\n🔧 <b>SERVIS SELESAI (".count($ss).")</b>\n".$L."\n";
        $rows = array(); $totS=0;
        foreach ($ss as $i=>$s) {
            $st = (isset($s['status_bayar']) && $s['status_bayar']==='lunas') ? 'LUNAS' : 'UTANG';
            $nama = $s['nama_customer'];
            if (function_exists('mb_substr') && mb_strlen($nama,'UTF-8') > 12) $nama = mb_substr($nama,0,11,'UTF-8').'…';
            $serv = $s['jenis_servis'];
            if (function_exists('mb_substr') && mb_strlen($serv,'UTF-8') > 10) $serv = mb_substr($serv,0,9,'UTF-8').'…';
            $rows[] = array(($i+1).'.', $nama, $serv, rpNum(hrgF($s,'servis')), $st);
            $totS += hrgF($s,'servis');
        }
        $rows[] = array('', 'TOTAL', '', rpNum($totS), '');
        $m .= tgPre(tgTable(array('No','Pelanggan','Servis','Biaya','Status'), $rows, array(3, 12, 11, 11, 6), array('left','left','left','right','left')));
        $msgs[] = $m;
    }

    if (!empty($cs)) {
        $m  = $L."\n🚿 <b>CUCI SELESAI (".count($cs).")</b>\n".$L."\n";
        $rows=array(); $totC=0;
        foreach ($cs as $i=>$c) {
            $st = (isset($c['status_bayar']) && $c['status_bayar']==='lunas') ? 'LUNAS' : 'UTANG';
            $nama = $c['nama_customer'];
            if (function_exists('mb_substr') && mb_strlen($nama,'UTF-8') > 12) $nama = mb_substr($nama,0,11,'UTF-8').'…';
            $pak = $c['paket'];
            if (function_exists('mb_substr') && mb_strlen($pak,'UTF-8') > 10) $pak = mb_substr($pak,0,9,'UTF-8').'…';
            $rows[] = array(($i+1).'.', $nama, $pak, rpNum(hrgF($c,'cuci')), $st);
            $totC += hrgF($c,'cuci');
        }
        $rows[] = array('', 'TOTAL', '', rpNum($totC), '');
        $m .= tgPre(tgTable(array('No','Pelanggan','Paket','Biaya','Status'), $rows, array(3, 12, 11, 11, 6), array('left','left','left','right','left')));
        $msgs[] = $m;
    }

    if (!empty($sm)) {
        $m  = $L."\n📥 <b>BARANG MASUK (".count($sm).")</b>\n".$L."\n";
        $rows=array(); $totM=0;
        foreach ($sm as $i=>$x) {
            $brg = $x['nama_barang'];
            if (function_exists('mb_substr') && mb_strlen($brg,'UTF-8') > 16) $brg = mb_substr($brg,0,15,'UTF-8').'…';
            $sup = $x['supplier'];
            if (function_exists('mb_substr') && mb_strlen($sup,'UTF-8') > 12) $sup = mb_substr($sup,0,11,'UTF-8').'…';
            $rows[] = array(($i+1).'.', date('d/m H:i', strtotime($x['tanggal'])), $brg, $sup, '+'.$x['jumlah']);
            $totM += $x['jumlah'];
        }
        $rows[] = array('', '', 'TOTAL', '', '+'.$totM);
        $m .= tgPre(tgTable(array('No','Waktu','Barang','Supplier','Qty'), $rows, array(3, 10, 15, 11, 5), array('left','left','left','left','right')));
        $msgs[] = $m;
    }

    if (!empty($sk)) {
        $m  = $L."\n📤 <b>BARANG KELUAR (".count($sk).")</b>\n".$L."\n";
        $rows=array(); $totK=0;
        foreach ($sk as $i=>$x) {
            $brg = $x['nama_barang'];
            if (function_exists('mb_substr') && mb_strlen($brg,'UTF-8') > 22) $brg = mb_substr($brg,0,21,'UTF-8').'…';
            $rows[] = array(($i+1).'.', date('d/m H:i', strtotime($x['tanggal'])), $brg, '-'.$x['jumlah']);
            $totK += $x['jumlah'];
        }
        $rows[] = array('', '', 'TOTAL', '-'.$totK);
        $m .= tgPre(tgTable(array('No','Waktu','Barang','Qty'), $rows, array(3, 10, 24, 6), array('left','left','left','right')));
        $msgs[] = $m;
    }

    if (!empty($pg)) {
        $m  = $L."\n💸 <b>PENGELUARAN (".count($pg).")</b>\n".$L."\n";
        $rows=array();
        foreach ($pg as $i=>$g) {
            $ket = $g['keterangan'];
            if (function_exists('mb_substr') && mb_strlen($ket,'UTF-8') > 20) $ket = mb_substr($ket,0,19,'UTF-8').'…';
            $rows[] = array(($i+1).'.', $ket, '-'.rpNum($g['jumlah']));
        }
        $rows[] = array('', 'TOTAL', '-'.rpNum($tP));
        $m .= tgPre(tgTable(array('No','Keterangan','Jumlah'), $rows, array(3, 20, 15), array('left','left','right')));
        $msgs[] = $m;
    }

    if ($tU > 0) {
        $m  = $L."\n💰 <b>UTANG PELANGGAN</b>\n".$L."\n";
        $sumS=0; foreach($uS as $u) $sumS+=hrgF($u,'servis');
        $sumC=0; foreach($uC as $u) $sumC+=hrgF($u,'cuci');
        $sumP=0; foreach($uP as $u) $sumP+=isset($u['total'])?$u['total']:0;
        $rows = array(
            array('Servis',  count($uS).' trx', 'Rp '.rpNum($sumS)),
            array('Cuci',    count($uC).' trx', 'Rp '.rpNum($sumC)),
            array('Pesanan', count($uP).' trx', 'Rp '.rpNum($sumP)),
            array('— —', '— —', '— —'),
            array('TOTAL',  (count($uS)+count($uC)+count($uP)).' trx', 'Rp '.rpNum($tU))
        );
        $m .= tgPre(tgTable(array('Jenis','Jml','Nilai'), $rows, array(10, 8, 20), array('left','left','right')));
        $msgs[] = $m;
    }

    if (!empty($stokM)) {
        $m  = $L."\n⚠️ <b>STOK MENIPIS (".count($stokM).")</b>\n".$L."\n";
        $rows=array();
        foreach ($stokM as $i=>$b) {
            $nm = $b['merek'].' '.$b['nama'];
            if (function_exists('mb_substr') && mb_strlen($nm,'UTF-8') > 24) $nm = mb_substr($nm,0,23,'UTF-8').'…';
            $rows[] = array(($i+1).'.', $nm, $b['stok'], $b['stok_minimum']);
        }
        $m .= tgPre(tgTable(array('No','Barang','Stok','Min'), $rows, array(3, 24, 5, 5), array('left','left','right','right')));
        $msgs[] = $m;
    }

    $m  = $L."\n";
    $m .= "✨ <b>LABA BERSIH: ".rp($laba)."</b>\n";
    $m .= $L."\n";
    $m .= "🤖 <i>".APP_NAME." Auto Report</i>\n";
    $m .= "🕐 <i>".date('d/m/Y H:i:s')." WIB</i>";
    $msgs[] = $m;

    $okText = sendTGArray($msgs);

    $okPdf = false;
    $pdfPath = buildLaporanPDF($tanggal);
    if ($pdfPath && file_exists($pdfPath)) {
        $caption  = "📄 <b>LAPORAN PDF — " . APP_NAME . "</b>\n";
        $caption .= "🗓 <b>" . $hf . ", " . $tf . "</b>\n";
        $caption .= "💰 <b>LABA BERSIH: " . rp($laba) . "</b>\n\n";
        $caption .= "<i>File lengkap dalam format PDF profesional.</i>";
        $okPdf = sendTGDocument($pdfPath, $caption);
        @unlink($pdfPath);
    }

    return ($okText || $okPdf);
}

function autoBackup() {
    if (!is_dir(BACKUP_FOLDER)) @mkdir(BACKUP_FOLDER, 0755, true);
    $lb = BACKUP_FOLDER . '/last_backup.txt';
    $need = true;
    if (file_exists($lb)) {
        $last = (int)@file_get_contents($lb);
        if ($last > 0 && (time() - $last) < 24*60*60) $need = false;
    }
    if ($need && file_exists(DATA_FILE)) {
        @copy(DATA_FILE, BACKUP_FOLDER . '/backup_' . date('Y-m-d_His') . '.json');
        @file_put_contents($lb, time());
        $files = glob(BACKUP_FOLDER . '/backup_*.json');
        if (is_array($files) && count($files) > 14) {
            sort($files);
            $del = array_slice($files, 0, count($files) - 14);
            foreach ($del as $f) @unlink($f);
        }
    }
}
function autoOmset() {
    if (!is_dir(OMSET_FOLDER)) @mkdir(OMSET_FOLDER, 0755, true);
    $d=readData(); $t=date('Y-m-d');
    $os=0;$oc=0;$op=0;$p=0;$tp=0;
    foreach($d['servis'] as $s) if(date('Y-m-d',strtotime($s['tanggal']))===$t && $s['status']==='selesai'){$os+=hrgF($s,'servis');$tp++;}
    foreach($d['doorsmeer'] as $x) if(date('Y-m-d',strtotime($x['tanggal']))===$t && $x['status']==='selesai'){$oc+=hrgF($x,'cuci');$tp++;}
    foreach($d['pesanan'] as $x) if(date('Y-m-d',strtotime($x['tanggal']))===$t){$op += (isset($x['subtotal_barang'])?$x['subtotal_barang']:0) + (isset($x['subtotal_tambahan'])?$x['subtotal_tambahan']:0);$tp++;}
    foreach($d['pengeluaran'] as $x) if($x['tanggal']===$t) $p += $x['jumlah'];
    @file_put_contents(OMSET_FOLDER.'/omset_'.$t.'.json', json_encode(array('tanggal'=>$t,'omset_servis'=>$os,'omset_cuci'=>$oc,'omset_pesanan'=>$op,'pengeluaran'=>$p,'total_bersih'=>$os+$oc+$op-$p,'total_pesanan'=>$tp,'disimpan'=>date('Y-m-d H:i:s')), JSON_PRETTY_PRINT));
}

autoBackup();
$data = readData();

$cDB=array(); $pDB=array(); $plDB=array();
function addCust(&$cDB,&$pDB,&$plDB,$nama,$hp,$jk,$merek,$tipe,$plat){
    $nk=strtolower(trim($nama)); if($nk==='') return;
    $pk=preg_replace('/[^0-9]/','',$hp); $plk=strtoupper(preg_replace('/\s+/','',$plat));
    if(!isset($cDB[$nk])) $cDB[$nk]=array('nama'=>$nama,'no_hp'=>$hp,'jenis_kendaraan'=>$jk,'kendaraan'=>array());
    if(!empty($plat) && !empty($tipe)){$vk=strtoupper($plat); if(!isset($cDB[$nk]['kendaraan'][$vk])) $cDB[$nk]['kendaraan'][$vk]=array('jenis'=>$jk,'tipe'=>$tipe,'no_polisi'=>$plat);}
    if(!empty($plat)) $plDB[$plk]=array('nama'=>$nama,'no_hp'=>$hp,'jenis_kendaraan'=>$jk,'tipe'=>$tipe,'no_polisi'=>$plat);
    if(!empty($pk)) $pDB[$pk]=array('nama'=>$nama,'no_hp'=>$hp);
}
foreach($data['servis'] as $s) addCust($cDB,$pDB,$plDB,$s['nama_customer'],isset($s['no_hp'])?$s['no_hp']:'',isset($s['jenis_kendaraan'])?$s['jenis_kendaraan']:'',isset($s['merek_kendaraan'])?$s['merek_kendaraan']:'',isset($s['tipe_mobil'])?$s['tipe_mobil']:'',isset($s['no_polisi'])?$s['no_polisi']:'');
foreach($data['doorsmeer'] as $d) addCust($cDB,$pDB,$plDB,$d['nama_customer'],isset($d['no_hp'])?$d['no_hp']:'',isset($d['jenis_kendaraan'])?$d['jenis_kendaraan']:'',isset($d['merek'])?$d['merek']:'',isset($d['tipe'])?$d['tipe']:'',isset($d['no_polisi'])?$d['no_polisi']:'');
foreach($data['pesanan'] as $p) addCust($cDB,$pDB,$plDB,$p['nama_customer'],isset($p['no_hp'])?$p['no_hp']:'',isset($p['jenis_kendaraan'])?$p['jenis_kendaraan']:'',isset($p['merek'])?$p['merek']:'',isset($p['tipe'])?$p['tipe']:'',isset($p['no_polisi'])?$p['no_polisi']:'');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $act=$_POST['action'];
    switch ($act) {
        case 'kirim_laporan_harian':
            $ok = kirimLaporan(isset($_POST['tanggal_laporan'])?$_POST['tanggal_laporan']:date('Y-m-d'));
            if ($ok) header('Location: dashboard.php?tab=home&msg=Laporan terkirim!');
            else header('Location: dashboard.php?tab=home&msg=Laporan GAGAL! Cek telegram_error.log');
            exit();

        case 'tambah_barang':
            $f='';
            if(!empty($_POST['foto_url'])) $f=$_POST['foto_url'];
            elseif(isset($_FILES['foto'])&&$_FILES['foto']['error']===UPLOAD_ERR_OK){
                if(!is_dir(UPLOAD_FOLDER)) @mkdir(UPLOAD_FOLDER,0755,true);
                $e=strtolower(pathinfo($_FILES['foto']['name'],PATHINFO_EXTENSION));
                $f='uploads/'.genID('IMG').'.'.$e;
                move_uploaded_file($_FILES['foto']['tmp_name'],__DIR__.'/'.$f);
            }
            $data['barang'][]=array('id'=>genID('BRG'),'price_code'=>$_POST['price_code'],'merek'=>$_POST['merek'],'nama'=>$_POST['nama'],'kategori'=>$_POST['kategori'],'stok'=>(int)$_POST['stok'],'stok_minimum'=>(int)$_POST['stok_minimum'],'modal'=>(float)$_POST['modal'],'harga_jual'=>(float)$_POST['harga_jual'],'asal'=>$_POST['asal'],'foto'=>$f,'created_at'=>date('Y-m-d H:i:s'));
            writeData($data); header('Location: dashboard.php?tab=barang&msg=Barang ditambahkan!'); exit();

        case 'edit_barang':
            $id=$_POST['id']; $f=isset($_POST['foto_lama'])?$_POST['foto_lama']:'';
            if(!empty($_POST['foto_url'])) $f=$_POST['foto_url'];
            elseif(isset($_FILES['foto'])&&$_FILES['foto']['error']===UPLOAD_ERR_OK){
                if(!is_dir(UPLOAD_FOLDER)) @mkdir(UPLOAD_FOLDER,0755,true);
                $e=strtolower(pathinfo($_FILES['foto']['name'],PATHINFO_EXTENSION));
                $f='uploads/'.genID('IMG').'.'.$e;
                move_uploaded_file($_FILES['foto']['tmp_name'],__DIR__.'/'.$f);
            }
            foreach($data['barang'] as $k=>$b) if($b['id']===$id){$data['barang'][$k]=array_merge($b,array('price_code'=>$_POST['price_code'],'merek'=>$_POST['merek'],'nama'=>$_POST['nama'],'kategori'=>$_POST['kategori'],'stok'=>(int)$_POST['stok'],'stok_minimum'=>(int)$_POST['stok_minimum'],'modal'=>(float)$_POST['modal'],'harga_jual'=>(float)$_POST['harga_jual'],'asal'=>$_POST['asal'],'foto'=>$f)); break;}
            writeData($data); header('Location: dashboard.php?tab=barang&msg=Barang diupdate!'); exit();

        case 'hapus_barang':
            $id=$_POST['id']; $bd=null;
            foreach($data['barang'] as $b) if($b['id']===$id){$bd=$b;break;}
            if($bd){$nl=$bd['merek'].' '.$bd['nama'];
                $data['barang']=array_values(array_filter($data['barang'],function($b) use($id){return $b['id']!==$id;}));
                $data['stock_masuk']=array_values(array_filter($data['stock_masuk'],function($s) use($nl){return $s['nama_barang']!==$nl;}));
                $data['stock_keluar']=array_values(array_filter($data['stock_keluar'],function($s) use($nl){return $s['nama_barang']!==$nl;}));
                writeData($data);
            }
            header('Location: dashboard.php?tab=barang&msg=Barang dihapus!'); exit();

        case 'tambah_stock_masuk':
            $id=$_POST['id_barang']; $j=(int)$_POST['jumlah']; $nb='';
            foreach($data['barang'] as $k=>$b) if($b['id']===$id){$data['barang'][$k]['stok']+=$j;$nb=$b['merek'].' '.$b['nama'];break;}
            $tglMasuk = !empty($_POST['tanggal']) ? str_replace('T',' ',$_POST['tanggal']).':00' : date('Y-m-d H:i:s');
            $data['stock_masuk'][]=array('id'=>genID('SM'),'id_barang'=>$id,'nama_barang'=>$nb,'jumlah'=>$j,'supplier'=>$_POST['supplier'],'tanggal'=>$tglMasuk);
            writeData($data); header('Location: dashboard.php?tab=masuk&msg=Barang masuk!'); exit();

        case 'tambah_stock_keluar':
            $id=$_POST['id_barang']; $j=(int)$_POST['jumlah']; $nb='';
            foreach($data['barang'] as $k=>$b) if($b['id']===$id){if($b['stok']<$j){header('Location: dashboard.php?tab=keluar&msg=Stok tidak cukup!');exit();}$data['barang'][$k]['stok']-=$j;$nb=$b['merek'].' '.$b['nama'];break;}
            $tglKeluar = !empty($_POST['tanggal']) ? str_replace('T',' ',$_POST['tanggal']).':00' : date('Y-m-d H:i:s');
            $data['stock_keluar'][]=array('id'=>genID('SK'),'id_barang'=>$id,'nama_barang'=>$nb,'jumlah'=>$j,'keterangan'=>$_POST['keterangan'],'tanggal'=>$tglKeluar);
            writeData($data); header('Location: dashboard.php?tab=keluar&msg=Barang keluar!'); exit();

        case 'tambah_pesanan':
            $items=array(); $subBarang=0;
            $ij=json_decode(isset($_POST['items_json'])?$_POST['items_json']:'[]',true);
            if(!is_array($ij)) $ij=array();
            foreach($ij as $it){
                $idb=isset($it['id_barang'])?$it['id_barang']:'';
                $q=isset($it['qty'])?(int)$it['qty']:0;
                if(!$idb||$q<=0) continue;
                $brg=null;
                foreach($data['barang'] as $b) if($b['id']===$idb){$brg=$b;break;}
                if(!$brg) continue;
                if($brg['stok']<$q){header('Location: dashboard.php?tab=pesanan&msg=Stok '.$brg['nama'].' tidak cukup!');exit();}
                $h=isset($it['harga'])?(float)$it['harga']:$brg['harga_jual'];
                $sb=$h*$q;
                $items[]=array('id_barang'=>$idb,'price_code'=>$brg['price_code'],'nama'=>$brg['merek'].' '.$brg['nama'],'qty'=>$q,'harga'=>$h,'subtotal'=>$sb);
                $subBarang+=$sb;
            }
            $servisData=array('jenis'=>'','biaya'=>0,'mekanik_nama'=>'','mekanik_keterangan'=>'','mekanik_biaya'=>0,'id_servis'=>'');
            if(!empty($_POST['servis_jenis']) && (float)(isset($_POST['servis_biaya'])?$_POST['servis_biaya']:0)>0){
                $servisData['jenis']=$_POST['servis_jenis'];
                $servisData['biaya']=(float)$_POST['servis_biaya'];
                $servisData['mekanik_nama']=isset($_POST['mekanik_nama'])?$_POST['mekanik_nama']:'';
                $servisData['mekanik_keterangan']=isset($_POST['mekanik_keterangan'])?$_POST['mekanik_keterangan']:'';
                $servisData['mekanik_biaya']=(float)(isset($_POST['mekanik_biaya'])?$_POST['mekanik_biaya']:0);
            }
            $dmData=array('paket'=>'','harga'=>0,'id_doorsmeer'=>'');
            if(!empty($_POST['dm_paket']) && (float)(isset($_POST['dm_harga'])?$_POST['dm_harga']:0)>0){
                $dmData['paket']=$_POST['dm_paket'];
                $dmData['harga']=(float)$_POST['dm_harga'];
            }
            if(empty($items) && empty($servisData['jenis']) && empty($dmData['paket'])){
                header('Location: dashboard.php?tab=pesanan&msg=Isi minimal 1 layanan!'); exit();
            }
            $diskon=(float)(isset($_POST['diskon'])?$_POST['diskon']:0);
            $note=isset($_POST['note'])?trim($_POST['note']):'';
            $estimasi = !empty($_POST['estimasi_selesai']) ? str_replace('T', ' ', $_POST['estimasi_selesai']) . ':00' : null;
            $total=$subBarang+$servisData['biaya']+$servisData['mekanik_biaya']+$dmData['harga']-$diskon;
            $idPesanan=genID('ORD');
            $noOrder=genORD($data);
            $jkIn = isset($_POST['jenis_kendaraan'])?$_POST['jenis_kendaraan']:'';
            $tipeIn = isset($_POST['tipe'])?$_POST['tipe']:'';
            $platIn = isset($_POST['no_polisi'])?$_POST['no_polisi']:'';
            if(!empty($servisData['jenis'])){
                $idSrv=genID('SRV');
                $servisData['id_servis']=$idSrv;
                $data['servis'][]=array(
                    'id'=>$idSrv,'id_pesanan'=>$idPesanan,
                    'nama_customer'=>$_POST['nama_customer'],'no_hp'=>$_POST['no_hp'],
                    'jenis_kendaraan'=>$jkIn,'merek_kendaraan'=>'','tipe_mobil'=>$tipeIn,
                    'no_polisi'=>$platIn,
                    'jenis_servis'=>$servisData['jenis'],
                    'biaya'=>$servisData['biaya'],'harga_asli'=>$servisData['biaya'],
                    'diskon'=>0,'harga_final'=>$servisData['biaya'],
                    'mekanik_nama'=>$servisData['mekanik_nama'],
                    'mekanik_keterangan'=>$servisData['mekanik_keterangan'],
                    'mekanik_biaya'=>$servisData['mekanik_biaya'],
                    'status_bayar'=>'belum_bayar','tanggal_bayar'=>null,
                    'status'=>'antri','tanggal'=>date('Y-m-d H:i:s')
                );
            }
            if(!empty($dmData['paket'])){
                $idDm=genID('DRS');
                $dmData['id_doorsmeer']=$idDm;
                $data['doorsmeer'][]=array(
                    'id'=>$idDm,'id_pesanan'=>$idPesanan,
                    'nama_customer'=>$_POST['nama_customer'],'no_hp'=>$_POST['no_hp'],
                    'jenis_kendaraan'=>$jkIn,'merek'=>'','tipe'=>$tipeIn,
                    'no_polisi'=>$platIn,
                    'paket'=>$dmData['paket'],'harga'=>$dmData['harga'],
                    'harga_asli'=>$dmData['harga'],'diskon'=>0,'harga_final'=>$dmData['harga'],
                    'status_bayar'=>'belum_bayar','tanggal_bayar'=>null,
                    'status'=>'antri','tanggal'=>date('Y-m-d H:i:s')
                );
            }
            foreach($items as $it){
                foreach($data['barang'] as $k=>$b) if($b['id']===$it['id_barang']){$data['barang'][$k]['stok']-=$it['qty'];break;}
                $data['stock_keluar'][]=array('id'=>genID('SK'),'id_barang'=>$it['id_barang'],'nama_barang'=>$it['nama'],'jumlah'=>$it['qty'],'keterangan'=>'Penjualan '.$noOrder,'tanggal'=>date('Y-m-d H:i:s'));
            }
            $data['pesanan'][]=array(
                'id'=>$idPesanan,'no_order'=>$noOrder,
                'nama_customer'=>$_POST['nama_customer'],'no_hp'=>$_POST['no_hp'],
                'jenis_kendaraan'=>$jkIn,'merek'=>'','tipe'=>$tipeIn,'no_polisi'=>$platIn,
                'items'=>$items,'subtotal_barang'=>$subBarang,
                'tambahan'=>array(),'subtotal_tambahan'=>0,
                'servis'=>$servisData,'doorsmeer'=>$dmData,
                'diskon'=>$diskon,'total'=>$total,
                'note'=>$note,
                'estimasi_selesai'=>$estimasi,
                'status_pekerjaan'=>'antri',
                'status_bayar'=>'belum_bayar',
                'tanggal'=>date('Y-m-d H:i:s'),
                'tanggal_bayar'=>null,
                'tanggal_selesai'=>null
            );
            writeData($data); autoOmset();
            header('Location: dashboard.php?tab=detail_pesanan&id='.$idPesanan.'&msg=Pesanan berhasil dibuat!&new=1'); exit();

        case 'update_status_pesanan':
            $id=$_POST['id']; $newStatus=$_POST['status_pekerjaan'];
            $allowed=array('antri','proses','selesai');
            if(!in_array($newStatus,$allowed)){header('Location: dashboard.php?tab=detail_pesanan&id='.$id.'&msg=Status tidak valid!');exit();}
            foreach($data['pesanan'] as $k=>$p) if($p['id']===$id){
                $data['pesanan'][$k]['status_pekerjaan']=$newStatus;
                if($newStatus==='selesai'){
                    $data['pesanan'][$k]['tanggal_selesai']=date('Y-m-d H:i:s');
                    if(!empty($p['servis']['id_servis'])) foreach($data['servis'] as $kk=>$s) if($s['id']===$p['servis']['id_servis']){$data['servis'][$kk]['status']='selesai';$data['servis'][$kk]['tanggal_selesai']=date('Y-m-d H:i:s');if(empty($s['no_kwitansi']))$data['servis'][$kk]['no_kwitansi']=genKW($data,'servis');}
                    if(!empty($p['doorsmeer']['id_doorsmeer'])) foreach($data['doorsmeer'] as $kk=>$d) if($d['id']===$p['doorsmeer']['id_doorsmeer']){$data['doorsmeer'][$kk]['status']='selesai';$data['doorsmeer'][$kk]['tanggal_selesai']=date('Y-m-d H:i:s');if(empty($d['no_kwitansi']))$data['doorsmeer'][$kk]['no_kwitansi']=genKW($data,'cuci');}
                }
                if($newStatus==='proses'){
                    if(!empty($p['servis']['id_servis'])) foreach($data['servis'] as $kk=>$s) if($s['id']===$p['servis']['id_servis']){$data['servis'][$kk]['status']='proses';}
                    if(!empty($p['doorsmeer']['id_doorsmeer'])) foreach($data['doorsmeer'] as $kk=>$d) if($d['id']===$p['doorsmeer']['id_doorsmeer']){$data['doorsmeer'][$kk]['status']='proses';}
                }
                break;
            }
            writeData($data); autoOmset();
            $m=array('antri'=>'Pesanan dikembalikan ke antrian.','proses'=>'Pesanan mulai dikerjakan!','selesai'=>'Pesanan selesai dikerjakan!');
            header('Location: dashboard.php?tab=detail_pesanan&id='.$id.'&msg='.urlencode($m[$newStatus])); exit();

        case 'bayar_pesanan':
            $id=$_POST['id'];
            foreach($data['pesanan'] as $k=>$p) if($p['id']===$id){
                $data['pesanan'][$k]['status_bayar']='lunas';
                $data['pesanan'][$k]['tanggal_bayar']=date('Y-m-d H:i:s');
                if(!empty($p['servis']['id_servis'])) foreach($data['servis'] as $kk=>$s) if($s['id']===$p['servis']['id_servis']){$data['servis'][$kk]['status_bayar']='lunas';$data['servis'][$kk]['tanggal_bayar']=date('Y-m-d H:i:s');}
                if(!empty($p['doorsmeer']['id_doorsmeer'])) foreach($data['doorsmeer'] as $kk=>$d) if($d['id']===$p['doorsmeer']['id_doorsmeer']){$data['doorsmeer'][$kk]['status_bayar']='lunas';$data['doorsmeer'][$kk]['tanggal_bayar']=date('Y-m-d H:i:s');}
                break;
            }
            writeData($data); autoOmset();
            $redir=isset($_POST['redirect'])?$_POST['redirect']:'detail_pesanan';
            if($redir==='utang') header('Location: dashboard.php?tab=utang&msg=Pembayaran dicatat!');
            else header('Location: dashboard.php?tab=detail_pesanan&id='.$id.'&msg=Pembayaran dicatat! Pesanan LUNAS!');
            exit();

        case 'batal_lunas':
            $id=$_POST['id'];
            foreach($data['pesanan'] as $k=>$p) if($p['id']===$id){
                $data['pesanan'][$k]['status_bayar']='belum_bayar';
                $data['pesanan'][$k]['tanggal_bayar']=null;
                if(!empty($p['servis']['id_servis'])) foreach($data['servis'] as $kk=>$s) if($s['id']===$p['servis']['id_servis']){$data['servis'][$kk]['status_bayar']='belum_bayar';}
                if(!empty($p['doorsmeer']['id_doorsmeer'])) foreach($data['doorsmeer'] as $kk=>$d) if($d['id']===$p['doorsmeer']['id_doorsmeer']){$data['doorsmeer'][$kk]['status_bayar']='belum_bayar';}
                break;
            }
            writeData($data); autoOmset();
            header('Location: dashboard.php?tab=detail_pesanan&id='.$id.'&msg=Status LUNAS dibatalkan.'); exit();

        case 'edit_note_pesanan':
            $id=$_POST['id'];
            foreach($data['pesanan'] as $k=>$p) if($p['id']===$id){$data['pesanan'][$k]['note']=isset($_POST['note'])?trim($_POST['note']):'';break;}
            writeData($data);
            header('Location: dashboard.php?tab=detail_pesanan&id='.$id.'&msg=Catatan diupdate!'); exit();

        case 'edit_estimasi_pesanan':
            $id=$_POST['id'];
            $est = !empty($_POST['estimasi_selesai']) ? str_replace('T', ' ', $_POST['estimasi_selesai']) . ':00' : null;
            foreach($data['pesanan'] as $k=>$p) if($p['id']===$id){$data['pesanan'][$k]['estimasi_selesai']=$est;break;}
            writeData($data);
            header('Location: dashboard.php?tab=detail_pesanan&id='.$id.'&msg=Estimasi diupdate!'); exit();

        case 'tambah_barang_pesanan':
            $idPesanan = $_POST['id_pesanan'];
            $idBarang = $_POST['id_barang'];
            $qty = (int)$_POST['qty'];
            $noteTambahan = isset($_POST['note_tambahan']) ? trim($_POST['note_tambahan']) : '';
            if ($qty <= 0) { header('Location: dashboard.php?tab=detail_pesanan&id='.$idPesanan.'&msg=Qty tidak valid!'); exit(); }

            $brg = null;
            foreach ($data['barang'] as $b) if ($b['id'] === $idBarang) { $brg = $b; break; }
            if (!$brg) { header('Location: dashboard.php?tab=detail_pesanan&id='.$idPesanan.'&msg=Barang tidak ditemukan!'); exit(); }
            if ($brg['stok'] < $qty) { header('Location: dashboard.php?tab=detail_pesanan&id='.$idPesanan.'&msg=Stok tidak cukup!'); exit(); }

            $found = false;
            $noOrderTmb = '';
            foreach ($data['pesanan'] as $k => $p) {
                if ($p['id'] === $idPesanan) {
                    if (isset($p['status_bayar']) && $p['status_bayar'] === 'lunas') {
                        header('Location: dashboard.php?tab=detail_pesanan&id='.$idPesanan.'&msg=Pesanan sudah LUNAS, tidak bisa ditambah!'); exit();
                    }
                    $harga = (float)$brg['harga_jual'];
                    $sub = $harga * $qty;
                    $tambahan = isset($p['tambahan']) && is_array($p['tambahan']) ? $p['tambahan'] : array();
                    $tambahan[] = array(
                        'id' => genID('TMB'),
                        'id_barang' => $idBarang,
                        'price_code' => $brg['price_code'],
                        'nama' => $brg['merek'].' '.$brg['nama'],
                        'qty' => $qty,
                        'harga' => $harga,
                        'subtotal' => $sub,
                        'note' => $noteTambahan,
                        'tanggal' => date('Y-m-d H:i:s')
                    );
                    $data['pesanan'][$k]['tambahan'] = $tambahan;
                    $subTotalTambah = 0;
                    foreach ($tambahan as $t) $subTotalTambah += $t['subtotal'];
                    $subBarang = isset($p['subtotal_barang']) ? $p['subtotal_barang'] : 0;
                    $servisBiaya = isset($p['servis']['biaya']) ? $p['servis']['biaya'] : 0;
                    $mekanikBiaya = isset($p['servis']['mekanik_biaya']) ? $p['servis']['mekanik_biaya'] : 0;
                    $cuciHarga = isset($p['doorsmeer']['harga']) ? $p['doorsmeer']['harga'] : 0;
                    $diskon = isset($p['diskon']) ? $p['diskon'] : 0;
                    $data['pesanan'][$k]['subtotal_tambahan'] = $subTotalTambah;
                    $data['pesanan'][$k]['total'] = $subBarang + $subTotalTambah + $servisBiaya + $mekanikBiaya + $cuciHarga - $diskon;
                    $noOrderTmb = isset($p['no_order']) ? $p['no_order'] : '';
                    $found = true;
                    break;
                }
            }
            if (!$found) { header('Location: dashboard.php?tab=daftar_pesanan&msg=Pesanan tidak ditemukan!'); exit(); }

            foreach ($data['barang'] as $k => $b) if ($b['id'] === $idBarang) { $data['barang'][$k]['stok'] -= $qty; break; }
            $data['stock_keluar'][] = array('id'=>genID('SK'), 'id_barang'=>$idBarang, 'nama_barang'=>$brg['merek'].' '.$brg['nama'], 'jumlah'=>$qty, 'keterangan'=>'Tambahan '.$noOrderTmb, 'tanggal'=>date('Y-m-d H:i:s'));

            writeData($data); autoOmset();
            header('Location: dashboard.php?tab=detail_pesanan&id='.$idPesanan.'&msg=Barang berhasil ditambahkan ke pesanan!'); exit();

        case 'hapus_tambahan_pesanan':
            $idPesanan = $_POST['id_pesanan'];
            $idTambahan = $_POST['id_tambahan'];
            foreach ($data['pesanan'] as $k => $p) {
                if ($p['id'] === $idPesanan) {
                    $tambahan = isset($p['tambahan']) && is_array($p['tambahan']) ? $p['tambahan'] : array();
                    $target = null;
                    foreach ($tambahan as $t) if ($t['id'] === $idTambahan) { $target = $t; break; }
                    if (!$target) { header('Location: dashboard.php?tab=detail_pesanan&id='.$idPesanan.'&msg=Tambahan tidak ditemukan!'); exit(); }
                    foreach ($data['barang'] as $kk => $b) if ($b['id'] === $target['id_barang']) { $data['barang'][$kk]['stok'] += $target['qty']; break; }
                    $tambahan = array_values(array_filter($tambahan, function($t) use ($idTambahan) { return $t['id'] !== $idTambahan; }));
                    $data['pesanan'][$k]['tambahan'] = $tambahan;
                    $subTotalTambah = 0;
                    foreach ($tambahan as $t) $subTotalTambah += $t['subtotal'];
                    $subBarang = isset($p['subtotal_barang']) ? $p['subtotal_barang'] : 0;
                    $servisBiaya = isset($p['servis']['biaya']) ? $p['servis']['biaya'] : 0;
                    $mekanikBiaya = isset($p['servis']['mekanik_biaya']) ? $p['servis']['mekanik_biaya'] : 0;
                    $cuciHarga = isset($p['doorsmeer']['harga']) ? $p['doorsmeer']['harga'] : 0;
                    $diskon = isset($p['diskon']) ? $p['diskon'] : 0;
                    $data['pesanan'][$k]['subtotal_tambahan'] = $subTotalTambah;
                    $data['pesanan'][$k]['total'] = $subBarang + $subTotalTambah + $servisBiaya + $mekanikBiaya + $cuciHarga - $diskon;
                    break;
                }
            }
            writeData($data); autoOmset();
            header('Location: dashboard.php?tab=detail_pesanan&id='.$idPesanan.'&msg=Tambahan dihapus & stok dikembalikan!'); exit();

        case 'hapus_pesanan':
            $id=$_POST['id']; $target=null;
            foreach($data['pesanan'] as $p) if($p['id']===$id){$target=$p;break;}
            if($target){
                foreach(safeA($target['items']) as $it) foreach($data['barang'] as $k=>$b) if($b['id']===$it['id_barang']){$data['barang'][$k]['stok']+=$it['qty'];break;}
                if (isset($target['tambahan']) && is_array($target['tambahan'])) {
                    foreach ($target['tambahan'] as $it) foreach($data['barang'] as $k=>$b) if($b['id']===$it['id_barang']){$data['barang'][$k]['stok']+=$it['qty'];break;}
                }
                if(!empty($target['servis']['id_servis'])) $data['servis']=array_values(array_filter($data['servis'],function($s) use($target){return $s['id']!==$target['servis']['id_servis'];}));
                if(!empty($target['doorsmeer']['id_doorsmeer'])) $data['doorsmeer']=array_values(array_filter($data['doorsmeer'],function($d) use($target){return $d['id']!==$target['doorsmeer']['id_doorsmeer'];}));
                $data['pesanan']=array_values(array_filter($data['pesanan'],function($p) use($id){return $p['id']!==$id;}));
                writeData($data); autoOmset();
            }
            header('Location: dashboard.php?tab=daftar_pesanan&msg=Pesanan dihapus!'); exit();

        case 'tambah_pengeluaran':
            $fb='';
            if(isset($_FILES['foto_bukti'])&&$_FILES['foto_bukti']['error']===UPLOAD_ERR_OK){
                if(!is_dir(UPLOAD_FOLDER)) @mkdir(UPLOAD_FOLDER,0755,true);
                $e=strtolower(pathinfo($_FILES['foto_bukti']['name'],PATHINFO_EXTENSION));
                $fb='uploads/'.genID('BKT').'.'.$e;
                move_uploaded_file($_FILES['foto_bukti']['tmp_name'],__DIR__.'/'.$fb);
            }
            $data['pengeluaran'][]=array('id'=>genID('OUT'),'keterangan'=>$_POST['keterangan'],'jumlah'=>(float)$_POST['jumlah'],'tanggal'=>$_POST['tanggal'],'foto_bukti'=>$fb,'created_at'=>date('Y-m-d H:i:s'));
            writeData($data); autoOmset(); header('Location: dashboard.php?tab=pengeluaran&msg=Dicatat!'); exit();
        case 'hapus_pengeluaran':
            $data['pengeluaran']=array_values(array_filter($data['pengeluaran'],function($p){return $p['id']!==$_POST['id'];}));
            writeData($data); autoOmset(); header('Location: dashboard.php?tab=pengeluaran&msg=Dihapus!'); exit();
    }
}

$today=date('Y-m-d'); $yd=date('Y-m-d',strtotime('-1 day')); $ms=date('Y-m-01'); $ws=date('Y-m-d',strtotime('monday this week'));
function stats($d,$tm,$ta=null){
    if(!$ta) $ta=date('Y-m-d');
    $oS=0;$oC=0;$oP=0;$pg=0;$tp=0;$ss=0;$cs=0;$dsk=0;
    foreach($d['servis'] as $s){$t=date('Y-m-d',strtotime($s['tanggal'])); if($t>=$tm&&$t<=$ta&&$s['status']==='selesai'){$oS+=hrgF($s,'servis');$ss++;$tp++;}}
    foreach($d['doorsmeer'] as $x){$t=date('Y-m-d',strtotime($x['tanggal'])); if($t>=$tm&&$t<=$ta&&$x['status']==='selesai'){$oC+=hrgF($x,'cuci');$cs++;$tp++;}}
    foreach($d['pesanan'] as $p){$t=date('Y-m-d',strtotime($p['tanggal'])); if($t>=$tm&&$t<=$ta){$oP += (isset($p['subtotal_barang'])?$p['subtotal_barang']:0) + (isset($p['subtotal_tambahan'])?$p['subtotal_tambahan']:0);$tp++;$dsk += isset($p['diskon'])?$p['diskon']:0;}}
    foreach($d['pengeluaran'] as $g) if($g['tanggal']>=$tm&&$g['tanggal']<=$ta) $pg+=$g['jumlah'];
    return array('os'=>$oS,'oc'=>$oC,'op'=>$oP,'pg'=>$pg,'tb'=>$oS+$oC+$oP-$pg,'tp'=>$tp,'ss'=>$ss,'cs'=>$cs,'diskon'=>$dsk);
}
$period=isset($_GET['period'])?$_GET['period']:'hari_ini';
if($period==='kemarin') $st=stats($data,$yd,$yd);
elseif($period==='minggu_ini') $st=stats($data,$ws,$today);
elseif($period==='bulan_ini') $st=stats($data,$ms,$today);
else $st=stats($data,$today,$today);

$stokM=array(); $stokH=array();
foreach($data['barang'] as $b){if($b['stok']<=$b['stok_minimum'])$stokM[]=$b;if($b['stok']<=0)$stokH[]=$b;}

$pAntri=array(); $pProses=array(); $pSelesai=array();
foreach($data['pesanan'] as $p){
    $sp=isset($p['status_pekerjaan'])?$p['status_pekerjaan']:'antri';
    if($sp==='antri') $pAntri[]=$p;
    elseif($sp==='proses') $pProses[]=$p;
    elseif($sp==='selesai') $pSelesai[]=$p;
}
$pUtang=array();
foreach($data['pesanan'] as $p) if(!isset($p['status_bayar'])||$p['status_bayar']!=='lunas') $pUtang[]=$p;

$totU=0; foreach($pUtang as $u) $totU+=isset($u['total'])?$u['total']:0;
$totUC=count($pUtang);
$totA=count($pAntri); $totP=count($pProses); $totS=count($pSelesai);

$last7 = array();
for ($i = 6; $i >= 0; $i--) {
    $dt = date('Y-m-d', strtotime("-$i days"));
    $row = stats($data, $dt, $dt);
    $last7[] = array(
        'tanggal' => $dt,
        'label'   => date('d/m', strtotime($dt)),
        'total'   => $row['os'] + $row['oc'] + $row['op'],
    );
}

$recent=array();
foreach(array_slice($data['pesanan'],-15) as $p) $recent[]=array('i'=>'bi-cart-check','c'=>'#51cf66','t'=>'Pesanan: '.$p['nama_customer'],'d'=>count(safeA(isset($p['items'])?$p['items']:array())).' item - '.rp(isset($p['total'])?$p['total']:0),'tm'=>$p['tanggal']);
foreach(array_slice($data['servis'],-5) as $s) $recent[]=array('i'=>'bi-wrench','c'=>'#ffd43b','t'=>'Servis: '.$s['nama_customer'],'d'=>$s['jenis_servis'],'tm'=>$s['tanggal']);
foreach(array_slice($data['doorsmeer'],-5) as $d) $recent[]=array('i'=>'bi-droplet-half','c'=>'#4dabf7','t'=>'Cuci: '.$d['nama_customer'],'d'=>$d['paket'],'tm'=>$d['tanggal']);
foreach(array_slice($data['stock_masuk'],-5) as $x) $recent[]=array('i'=>'bi-arrow-down-circle','c'=>'#51cf66','t'=>'Barang Masuk','d'=>$x['nama_barang'].' (+'.$x['jumlah'].')','tm'=>$x['tanggal']);
foreach(array_slice($data['pengeluaran'],-5) as $x) $recent[]=array('i'=>'bi-cash-coin','c'=>'#ff6b6b','t'=>'Pengeluaran','d'=>$x['keterangan'],'tm'=>$x['tanggal'].' 00:00:00');
usort($recent,function($a,$b){return strtotime($b['tm'])-strtotime($a['tm']);});
$recent=array_slice($recent,0,10);

$lp=isset($_GET['laporan'])?$_GET['laporan']:'hari_ini';
$ltm=$today; $lta=$today;
if($lp==='kemarin'){$ltm=$yd;$lta=$yd;}
if($lp==='minggu_ini')$ltm=$ws;
if($lp==='bulan_ini')$ltm=$ms;
if($lp==='custom'){$ltm=isset($_GET['tanggal_mulai'])?$_GET['tanggal_mulai']:$today;$lta=isset($_GET['tanggal_akhir'])?$_GET['tanggal_akhir']:$today;}
function inRange($a,$f,$from,$to){$o=array();foreach(safeA($a) as $x){$t=date('Y-m-d',strtotime(isset($x[$f])?$x[$f]:''));if($t>=$from&&$t<=$to)$o[]=$x;}return $o;}
$lM=inRange($data['stock_masuk'],'tanggal',$ltm,$lta);
$lK=inRange($data['stock_keluar'],'tanggal',$ltm,$lta);
$tM=0; foreach($lM as $x) $tM+=$x['jumlah'];
$tK=0; foreach($lK as $x) $tK+=$x['jumlah'];
$lP=inRange($data['pesanan'],'tanggal',$ltm,$lta);
$lG=array(); foreach($data['pengeluaran'] as $g) if($g['tanggal']>=$ltm&&$g['tanggal']<=$lta) $lG[]=$g;
$lOS=0; $lOC=0; $lOP=0;
foreach($lP as $p) $lOP += (isset($p['subtotal_barang'])?$p['subtotal_barang']:0) + (isset($p['subtotal_tambahan'])?$p['subtotal_tambahan']:0);
foreach($lP as $p){ if(isset($p['servis']['biaya'])) $lOS += $p['servis']['biaya']+(isset($p['servis']['mekanik_biaya'])?$p['servis']['mekanik_biaya']:0); if(isset($p['doorsmeer']['harga'])) $lOC += $p['doorsmeer']['harga']; }
$lTG=0; foreach($lG as $g) $lTG+=$g['jumlah'];
$lKotor=$lOS+$lOC+$lOP; $lBersih=$lKotor-$lTG;

$sp=isset($_GET['stok_period'])?$_GET['stok_period']:'hari_ini';
$sm=$today; $sa=$today;
if($sp==='kemarin'){$sm=$yd;$sa=$yd;}
if($sp==='minggu_ini'){$sm=$ws;$sa=$today;}
if($sp==='bulan_ini'){$sm=$ms;$sa=$today;}
$sML=inRange($data['stock_masuk'],'tanggal',$sm,$sa);
$sKL=inRange($data['stock_keluar'],'tanggal',$sm,$sa);
$tSM=0; foreach($sML as $x) $tSM+=$x['jumlah'];
$tSK=0; foreach($sKL as $x) $tSK+=$x['jumlah'];

$cust=array();
foreach($data['pesanan'] as $p){$k=strtolower($p['nama_customer'].'|'.$p['no_hp']);if(!isset($cust[$k]))$cust[$k]=array('nama'=>$p['nama_customer'],'no_hp'=>$p['no_hp'],'servis'=>0,'cuci'=>0,'pesanan'=>0,'last'=>$p['tanggal']);$cust[$k]['pesanan']++;if(strtotime($p['tanggal'])>strtotime($cust[$k]['last']))$cust[$k]['last']=$p['tanggal'];}
foreach($data['servis'] as $s){$k=strtolower($s['nama_customer'].'|'.$s['no_hp']);if(!isset($cust[$k]))$cust[$k]=array('nama'=>$s['nama_customer'],'no_hp'=>$s['no_hp'],'servis'=>0,'cuci'=>0,'pesanan'=>0,'last'=>$s['tanggal']);$cust[$k]['servis']++;}
foreach($data['doorsmeer'] as $d){$k=strtolower($d['nama_customer'].'|'.$d['no_hp']);if(!isset($cust[$k]))$cust[$k]=array('nama'=>$d['nama_customer'],'no_hp'=>$d['no_hp'],'servis'=>0,'cuci'=>0,'pesanan'=>0,'last'=>$d['tanggal']);$cust[$k]['cuci']++;}
$totCust=count($cust);

$sb=isset($_GET['search'])?$_GET['search']:'';
$bT=$data['barang'];
if($sb){$k=strtolower($sb);$bT=array();foreach($data['barang'] as $b) if(strpos(strtolower($b['nama']),$k)!==false||strpos(strtolower($b['merek']),$k)!==false||strpos(strtolower($b['price_code']),$k)!==false) $bT[]=$b;}

$fp=isset($_GET['filter_pesanan'])?$_GET['filter_pesanan']:'semua';
$pF=$data['pesanan'];
if($fp==='lunas'){$pF=array();foreach($data['pesanan'] as $p) if(isset($p['status_bayar'])&&$p['status_bayar']==='lunas') $pF[]=$p;}
elseif($fp==='utang'){$pF=array();foreach($data['pesanan'] as $p) if(!isset($p['status_bayar'])||$p['status_bayar']!=='lunas') $pF[]=$p;}
elseif($fp==='antri') $pF=$pAntri;
elseif($fp==='proses') $pF=$pProses;
elseif($fp==='selesai') $pF=$pSelesai;

$tab=isset($_GET['tab'])?$_GET['tab']:'home';
$msg=isset($_GET['msg'])?$_GET['msg']:'';
$view=isset($_GET['view'])?$_GET['view']:'grid';
$isNew=isset($_GET['new'])?true:false;
function act($t,$c){return $t===$c?'active':'';}
$hr=(int)date('H');
if($hr<11)$greet='Selamat Pagi';elseif($hr<15)$greet='Selamat Siang';elseif($hr<18)$greet='Selamat Sore';else$greet='Selamat Malam';

$detailP=null;
if($tab==='detail_pesanan' && isset($_GET['id'])) foreach($data['pesanan'] as $p) if($p['id']===$_GET['id']){$detailP=$p;break;}

$defaultTglLocal = date('Y-m-d\TH:i');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
<title>Dashboard - <?= APP_NAME ?></title>
<link rel="icon" type="image/webp" href="<?= LOGO_URL ?>">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700;800&family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
:root{
    --gold:#FFD700;--gold-2:#E5B80B;--gold-light:#f9e29c;--gold-dark:#8B6508;
    --black-1:#000000;--black-2:#050505;--black-3:#0a0a0a;--black-4:#111111;
    --glass:rgba(10,10,10,.62);
    --glass-2:rgba(15,15,15,.55);
    --glass-light:rgba(20,20,20,.45);
    --glass-border:rgba(212,175,55,.18);
    --glass-border-2:rgba(212,175,55,.28);
    --text:#ffffff;--text-muted:#a0a0a8;--text-dim:#6a6a72;
    --danger:#ff6b6b;--success:#51cf66;--info:#4dabf7;--warning:#ffd43b;
    --sp-1:6px;--sp-2:10px;--sp-3:14px;--sp-4:18px;--sp-5:24px;--sp-6:32px;
    --radius-sm:10px;--radius-md:14px;--radius-lg:18px;
    --gap-grid:12px;
    --gold-grad:linear-gradient(120deg,#d4af37 0%,#ffe9a8 50%,#d4af37 100%);
    --gold-grad-2:linear-gradient(135deg,#E5B80B 0%,#FFD700 50%,#E5B80B 100%);
}
*{margin:0;padding:0;box-sizing:border-box;}
html,body{height:100%;width:100%;}
body{font-family:'Inter',sans-serif;background:#000;min-height:100vh;color:var(--text);font-size:15px;overflow-x:hidden;-webkit-font-smoothing:antialiased;text-rendering:optimizeLegibility;}

.bg-layer{position:fixed;inset:0;overflow:hidden;pointer-events:none;z-index:0;background:radial-gradient(circle at 50% 40%,#0a0a0a 0%,#000 70%);}
.bg-layer .grid{position:absolute;inset:0;background-image:linear-gradient(rgba(212,175,55,.025) 1px, transparent 1px),linear-gradient(90deg, rgba(212,175,55,.025) 1px, transparent 1px);background-size:64px 64px;-webkit-mask-image:radial-gradient(ellipse 70% 70% at 50% 40%, #000 15%, transparent 80%);mask-image:radial-gradient(ellipse 70% 70% at 50% 40%, #000 15%, transparent 80%);}
.bg-layer .glow{position:absolute;width:900px;height:900px;top:50%;left:50%;transform:translate(-50%,-50%);border-radius:50%;background:radial-gradient(circle,rgba(212,175,55,.09) 0%,transparent 62%);filter:blur(50px);animation:glowBreath 10s ease-in-out infinite;}
@keyframes glowBreath{0%,100%{opacity:.5;transform:translate(-50%,-50%) scale(1);}50%{opacity:.9;transform:translate(-50%,-50%) scale(1.06);}}
.bg-rings{position:absolute;top:50%;left:50%;width:0;height:0;pointer-events:none;}
.bg-logo{position:absolute;top:50%;left:50%;width:650px;height:650px;margin:-325px 0 0 -325px;object-fit:contain;opacity:.09;filter:drop-shadow(0 0 30px rgba(212,175,55,.5));animation:bgLogoBreath 8s ease-in-out infinite;z-index:1;}
@keyframes bgLogoBreath{0%,100%{opacity:.07;transform:scale(1);}50%{opacity:.13;transform:scale(1.02);}}
.bg-ring{position:absolute;top:0;left:0;border-radius:50%;border:1.5px solid transparent;will-change:transform;z-index:2;}
.bg-r1{width:420px;height:420px;margin:-210px 0 0 -210px;border-top-color:rgba(212,175,55,.55);border-right-color:rgba(212,175,55,.06);animation:spinCW 18s linear infinite;}
.bg-r2{width:580px;height:580px;margin:-290px 0 0 -290px;border-top-color:rgba(212,175,55,.38);border-left-color:rgba(212,175,55,.05);animation:spinCCW 26s linear infinite;}
.bg-r3{width:760px;height:760px;margin:-380px 0 0 -380px;border-top-color:rgba(212,175,55,.22);border-right-color:rgba(212,175,55,.03);animation:spinCW 38s linear infinite;}
.bg-r4{width:960px;height:960px;margin:-480px 0 0 -480px;border-top-color:rgba(212,175,55,.12);border-right-color:rgba(212,175,55,.02);animation:spinCCW 52s linear infinite;}
@keyframes spinCW{from{transform:rotate(0);}to{transform:rotate(360deg);}}
@keyframes spinCCW{from{transform:rotate(0);}to{transform:rotate(-360deg);}}
.bg-rd{position:absolute;top:50%;left:50%;border-radius:50%;background:var(--gold);box-shadow:0 0 10px var(--gold),0 0 20px rgba(212,175,55,.6);z-index:3;}
.bg-r1 .bg-rd{width:6px;height:6px;margin:-3px 0 0 -3px;transform:translateY(-210px);}
.bg-r2 .bg-rd{width:5px;height:5px;margin:-2.5px 0 0 -2.5px;transform:translateY(-290px);opacity:.85;}
.bg-r3 .bg-rd{width:4px;height:4px;margin:-2px 0 0 -2px;transform:translateY(-380px);opacity:.65;}
.bg-r4 .bg-rd{width:3px;height:3px;margin:-1.5px 0 0 -1.5px;transform:translateY(-480px);opacity:.45;}
@media(max-width:991px){.bg-rings{transform:scale(.7);}}
@media(max-width:600px){.bg-rings{transform:scale(.5);}.bg-logo{width:400px;height:400px;margin:-200px 0 0 -200px;}}

.navbar-top{position:fixed;top:0;left:0;right:0;z-index:1000;height:64px;padding:0 16px;background:rgba(5,5,5,.72);backdrop-filter:blur(20px) saturate(140%);-webkit-backdrop-filter:blur(20px) saturate(140%);border-bottom:1px solid rgba(212,175,55,.22);display:flex;align-items:center;justify-content:space-between;gap:var(--sp-2);box-shadow:0 8px 32px rgba(0,0,0,.5);}
.navbar-brand{display:flex;align-items:center;gap:var(--sp-2);text-decoration:none;flex-shrink:0;}
.brand-logo{width:40px;height:40px;border-radius:50%;border:1.5px solid var(--gold);object-fit:cover;background:#000;padding:2px;box-shadow:0 0 18px rgba(212,175,55,.35);}
.brand-text h5{font-family:'Cinzel',serif;font-weight:700;font-size:15px;line-height:1.15;letter-spacing:1.5px;background:var(--gold-grad);background-size:200% auto;-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;animation:shineText 5s linear infinite;}
@keyframes shineText{to{background-position:200% center;}}
.brand-text small{color:var(--text-dim);font-size:10px;letter-spacing:1px;text-transform:uppercase;}
.btn-logout-top{background:rgba(255,107,107,.08);color:#ffa3a3;border:1px solid rgba(255,107,107,.28);padding:8px 14px;border-radius:var(--radius-sm);font-weight:600;font-size:13px;cursor:pointer;text-decoration:none;white-space:nowrap;display:inline-flex;align-items:center;gap:6px;transition:all .2s;backdrop-filter:blur(8px);}
.btn-logout-top:hover{background:rgba(255,107,107,.18);color:#fff;border-color:rgba(255,107,107,.5);}

.sidebar-desktop{display:none;position:fixed;left:0;top:64px;bottom:0;width:240px;background:rgba(5,5,5,.7);backdrop-filter:blur(20px) saturate(140%);-webkit-backdrop-filter:blur(20px) saturate(140%);border-right:1px solid rgba(212,175,55,.18);padding:var(--sp-2) 0;overflow-y:auto;z-index:900;box-shadow:8px 0 32px rgba(0,0,0,.4);}
.sidebar-desktop::-webkit-scrollbar{width:6px;}
.sidebar-desktop::-webkit-scrollbar-thumb{background:rgba(212,175,55,.3);border-radius:3px;}
.sidebar-group{margin-bottom:var(--sp-2);}
.sidebar-group-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:rgba(212,175,55,.6);padding:12px 20px 6px 20px;}
.sidebar-desktop .sidebar-item{color:#9a9aa0;text-decoration:none;display:flex;align-items:center;gap:12px;padding:11px 20px;font-size:13.5px;font-weight:600;border-left:3px solid transparent;transition:all .22s;position:relative;}
.sidebar-desktop .sidebar-item:hover{background:rgba(212,175,55,.06);color:var(--gold);border-left-color:rgba(212,175,55,.5);padding-left:24px;}
.sidebar-desktop .sidebar-item.active{background:linear-gradient(90deg,rgba(212,175,55,.14),rgba(212,175,55,.02));color:var(--gold);border-left-color:var(--gold);box-shadow:inset 0 0 24px rgba(212,175,55,.05);}
.sidebar-desktop .sidebar-item i{font-size:18px;width:22px;text-align:center;flex-shrink:0;}
.indicator-dot{position:absolute;top:12px;right:14px;width:8px;height:8px;border-radius:50%;animation:blink 1.2s infinite;box-shadow:0 0 8px currentColor;}
.indicator-red{background:#ff6b6b;color:#ff6b6b;}
.indicator-green{background:#51cf66;color:#51cf66;}
.indicator-yellow{background:#ffd43b;color:#ffd43b;}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.35}}

.main-content{padding:80px 16px 96px;position:relative;z-index:1;}

.welcome-card{background:linear-gradient(135deg,rgba(20,15,0,.6),rgba(10,10,10,.55));backdrop-filter:blur(14px) saturate(140%);-webkit-backdrop-filter:blur(14px) saturate(140%);border:1px solid var(--glass-border-2);border-radius:var(--radius-lg);padding:var(--sp-4);margin-bottom:var(--sp-4);box-shadow:0 12px 40px rgba(0,0,0,.55),inset 0 1px 0 rgba(212,175,55,.1);position:relative;overflow:hidden;}
.welcome-card::before{content:'';position:absolute;top:0;left:15%;right:15%;height:1px;background:linear-gradient(90deg,transparent,rgba(212,175,55,.6),transparent);}
.welcome-greet{font-size:12px;color:rgba(212,175,55,.75);text-transform:uppercase;letter-spacing:2px;font-weight:600;}
.welcome-name{font-family:'Cinzel',serif;font-size:20px;font-weight:700;margin:6px 0;letter-spacing:1px;background:var(--gold-grad);background-size:200% auto;-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;animation:shineText 5s linear infinite;}
.welcome-date{font-size:13px;display:flex;align-items:center;gap:6px;flex-wrap:wrap;color:#b0b0b8;}
.welcome-date i{color:var(--gold);}

.kpi-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:var(--gap-grid);margin-bottom:var(--sp-4);}
.kpi-card{background:var(--glass);backdrop-filter:blur(14px) saturate(140%);-webkit-backdrop-filter:blur(14px) saturate(140%);border:1px solid var(--glass-border);border-radius:var(--radius-md);padding:var(--sp-3);transition:all .25s;position:relative;overflow:hidden;}
.kpi-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:var(--gold-grad);opacity:.35;}
.kpi-card:hover{border-color:var(--glass-border-2);transform:translateY(-2px);box-shadow:0 12px 32px rgba(0,0,0,.5);}
.kpi-icon{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;margin-bottom:var(--sp-2);backdrop-filter:blur(4px);}
.kpi-label{font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.8px;margin-bottom:3px;font-weight:600;}
.kpi-value{font-size:15px;font-weight:800;word-break:break-word;line-height:1.25;}
.kpi-sub{font-size:11px;color:var(--text-dim);margin-top:4px;}

.section-card,.form-card{background:var(--glass);backdrop-filter:blur(14px) saturate(140%);-webkit-backdrop-filter:blur(14px) saturate(140%);border:1px solid var(--glass-border);border-radius:var(--radius-md);padding:var(--sp-4);margin-bottom:var(--sp-4);box-shadow:0 8px 28px rgba(0,0,0,.45);position:relative;}
.section-title,.form-title{color:var(--gold);font-family:'Cinzel',serif;font-weight:700;font-size:14px;letter-spacing:1px;display:flex;align-items:center;gap:var(--sp-2);border-bottom:1px solid rgba(212,175,55,.18);padding-bottom:var(--sp-2);margin-bottom:var(--sp-3);}
.section-title i,.form-title i{color:var(--gold);font-size:16px;}
.section-title .count-pill{background:rgba(212,175,55,.15);color:var(--gold);font-size:11px;padding:2px 10px;border-radius:10px;font-weight:700;margin-left:auto;border:1px solid rgba(212,175,55,.25);font-family:'Inter',sans-serif;letter-spacing:0;}

.quick-actions{display:grid;grid-template-columns:repeat(2,1fr);gap:var(--gap-grid);}
.qa-btn{display:flex;align-items:center;gap:var(--sp-2);padding:var(--sp-3);background:rgba(20,20,20,.5);border:1px solid rgba(212,175,55,.12);border-radius:var(--radius-sm);text-decoration:none;color:var(--text);font-weight:600;font-size:13px;transition:all .22s;backdrop-filter:blur(6px);}
.qa-btn:hover{background:rgba(212,175,55,.08);border-color:var(--glass-border-2);color:var(--gold);transform:translateY(-2px);}
.qa-icon{width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0;}

.order-overview{display:grid;grid-template-columns:repeat(3,1fr);gap:var(--gap-grid);margin-bottom:var(--sp-3);}
.order-stat{background:rgba(15,15,15,.6);border:1px solid rgba(212,175,55,.14);border-radius:var(--radius-sm);padding:var(--sp-3);text-align:center;text-decoration:none;color:var(--text);transition:all .22s;backdrop-filter:blur(8px);}
.order-stat:hover{border-color:var(--glass-border-2);transform:translateY(-2px);}
.order-stat .num{font-size:24px;font-weight:900;line-height:1;font-family:'Cinzel',serif;}
.order-stat .lbl{font-size:10.5px;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-top:6px;font-weight:600;}
.order-stat.antri .num{color:#ff6b6b;}
.order-stat.proses .num{color:#ffd43b;}
.order-stat.selesai .num{color:#51cf66;}

.activity-list{max-height:420px;overflow-y:auto;padding-right:4px;}
.activity-list::-webkit-scrollbar{width:4px;}
.activity-list::-webkit-scrollbar-thumb{background:rgba(212,175,55,.3);border-radius:2px;}
.activity-item{display:flex;gap:var(--sp-3);padding:12px 0;border-bottom:1px solid rgba(212,175,55,.08);}
.activity-item:last-child{border-bottom:none;}
.activity-icon{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;background:rgba(212,175,55,.06);border:1px solid rgba(212,175,55,.12);}
.activity-body{flex:1;min-width:0;}
.activity-title{font-weight:600;font-size:13px;color:#e5e5e5;}
.activity-desc{font-size:11.5px;color:var(--text-muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.activity-time{font-size:10.5px;color:var(--text-dim);margin-top:3px;}

.mini-stats{display:grid;grid-template-columns:repeat(2,1fr);gap:var(--gap-grid);}
.mini-stat{background:rgba(15,15,15,.55);border:1px solid rgba(212,175,55,.12);border-radius:var(--radius-sm);padding:var(--sp-3);display:flex;align-items:center;gap:var(--sp-2);backdrop-filter:blur(8px);}
.mini-stat-icon{width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0;}
.mini-stat-val{font-weight:800;font-size:14px;}
.mini-stat-lbl{font-size:10.5px;color:var(--text-muted);}

.warn-banner{display:flex;align-items:center;gap:var(--sp-3);padding:var(--sp-3);border-radius:var(--radius-sm);margin-bottom:var(--sp-3);text-decoration:none;color:#fff;font-weight:700;font-size:13px;border:1px solid transparent;transition:all .22s;backdrop-filter:blur(10px);}
.warn-banner .w-icon{font-size:22px;}
.warn-banner.danger{background:linear-gradient(135deg,rgba(201,42,42,.85),rgba(139,28,28,.9));border-color:rgba(255,107,107,.4);}
.warn-banner.warning{background:linear-gradient(135deg,rgba(184,134,11,.85),rgba(139,101,8,.9));border-color:rgba(255,212,59,.4);}
.warn-banner.info{background:linear-gradient(135deg,rgba(24,100,171,.85),rgba(14,67,112,.9));border-color:rgba(77,171,247,.4);}
.warn-banner:hover{color:#fff;transform:translateY(-2px);box-shadow:0 12px 32px rgba(0,0,0,.5);}

.form-label{font-weight:600;color:#c0c0c8;font-size:12.5px;margin-bottom:6px;display:block;letter-spacing:.2px;}
.form-control,.form-select{background:rgba(10,10,10,.55)!important;border:1.5px solid rgba(212,175,55,.25)!important;border-radius:var(--radius-sm)!important;padding:11px 14px!important;font-size:14px!important;color:#fff!important;font-weight:500;width:100%;transition:border-color .2s,background .2s,box-shadow .2s;backdrop-filter:blur(4px);}
.form-control::placeholder{color:rgba(255,255,255,.35);}
.form-control:focus,.form-select:focus{outline:none;border-color:var(--gold)!important;background:rgba(20,15,0,.55)!important;box-shadow:0 0 0 3px rgba(212,175,55,.12);}
.form-select option{background:#0a0a0a;color:#fff;}
textarea.form-control{min-height:60px;}
.mb-3{margin-bottom:var(--sp-3)!important;}
.mb-2{margin-bottom:var(--sp-2)!important;}
.row.g-3{--bs-gutter-x:var(--sp-3);--bs-gutter-y:var(--sp-3);}
.row.g-2{--bs-gutter-x:var(--sp-2);--bs-gutter-y:var(--sp-2);}
.form-control[type="file"]{padding:8px 12px!important;color:#c0c0c8!important;font-size:13px!important;}
.form-control[type="file"]::file-selector-button{background:var(--gold-grad-2);color:#000;border:none;padding:6px 12px;border-radius:6px;font-weight:700;font-size:12px;margin-right:10px;cursor:pointer;}
input[type="date"]::-webkit-calendar-picker-indicator,input[type="datetime-local"]::-webkit-calendar-picker-indicator{filter:invert(.9) sepia(1) saturate(5) hue-rotate(5deg);cursor:pointer;}

.btn-gold{background:var(--gold-grad-2);color:#000;border:none;padding:12px 20px;border-radius:var(--radius-sm);font-weight:800;font-size:14px;letter-spacing:.4px;cursor:pointer;width:100%;display:inline-flex;align-items:center;justify-content:center;gap:8px;transition:transform .18s,box-shadow .18s;box-shadow:0 6px 20px rgba(212,175,55,.2);position:relative;overflow:hidden;text-transform:uppercase;}
.btn-gold::before{content:'';position:absolute;top:0;left:-100%;width:100%;height:100%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.35),transparent);transition:left .6s ease;}
.btn-gold:hover::before{left:100%;}
.btn-gold:hover{transform:translateY(-1px);box-shadow:0 10px 28px rgba(212,175,55,.32);}
.btn-gold:active{transform:translateY(0);}
.btn-soft-gold{background:rgba(212,175,55,.1);border:1px solid rgba(212,175,55,.4);padding:11px 16px;border-radius:var(--radius-sm);font-weight:700;font-size:13px;color:var(--gold);cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:6px;width:100%;transition:all .2s;backdrop-filter:blur(6px);}
.btn-soft-gold:hover{background:rgba(212,175,55,.18);border-color:var(--gold);color:#fff;}

.table-card{background:var(--glass);backdrop-filter:blur(14px) saturate(140%);-webkit-backdrop-filter:blur(14px) saturate(140%);border:1px solid var(--glass-border-2);border-radius:var(--radius-md);padding:var(--sp-4);margin-bottom:var(--sp-4);box-shadow:0 8px 28px rgba(0,0,0,.5);position:relative;}
.table-card::before{content:'';position:absolute;top:0;left:15%;right:15%;height:1px;background:linear-gradient(90deg,transparent,rgba(212,175,55,.4),transparent);}
.table-title{color:var(--gold);font-family:'Cinzel',serif;font-weight:700;font-size:14px;letter-spacing:1px;margin-bottom:var(--sp-3);display:flex;align-items:center;gap:8px;border-bottom:1px solid rgba(212,175,55,.15);padding-bottom:var(--sp-2);}
.table{color:#fff;font-size:13px;margin-bottom:0;--bs-table-bg:transparent;}
.table thead th{color:var(--gold);font-weight:700;background:rgba(212,175,55,.06);font-size:11px;text-transform:uppercase;letter-spacing:.8px;border-bottom:1px solid rgba(212,175,55,.25)!important;padding:12px 10px;}
.table tbody td{color:#dcdce0;background:transparent;border-bottom:1px solid rgba(212,175,55,.08);padding:12px 10px;vertical-align:middle;}
.table tbody tr:hover{background:rgba(212,175,55,.04);}
.table tbody tr:last-child td{border-bottom:none;}
.table strong{color:#fff;}
.table small{color:var(--text-muted);}
.table .badge{font-weight:700;font-size:10.5px;padding:5px 9px;letter-spacing:.3px;}
.bukti-thumb{width:40px;height:40px;border-radius:8px;object-fit:cover;cursor:pointer;border:1.5px solid rgba(212,175,55,.5);transition:border-color .2s;}
.bukti-thumb:hover{border-color:var(--gold);}

.grid-view{display:grid;grid-template-columns:repeat(2,1fr);gap:var(--gap-grid);}
.grid-item{background:rgba(15,15,15,.65);backdrop-filter:blur(10px);border:1px solid rgba(212,175,55,.15);border-radius:var(--radius-md);padding:var(--sp-3);text-align:center;transition:all .25s;}
.grid-item:hover{border-color:var(--glass-border-2);transform:translateY(-2px);box-shadow:0 12px 28px rgba(0,0,0,.5);}
.grid-item img,.grid-item .no-photo{width:70px;height:70px;border-radius:50%;object-fit:cover;display:flex;align-items:center;justify-content:center;background:rgba(212,175,55,.08);color:rgba(212,175,55,.5);font-size:28px;margin:0 auto 8px;border:1.5px solid rgba(212,175,55,.2);}
.grid-item .grid-name{font-weight:800;color:#fff;font-size:13px;margin-bottom:4px;}
.grid-item .grid-detail{font-size:11px;color:#a0a0a8;margin-bottom:2px;}
.grid-item .grid-detail strong{color:var(--gold);}
.grid-item .badge{font-size:10px;font-weight:700;padding:4px 8px;}

.select2-container .select2-selection--single{height:44px!important;display:flex!important;align-items:center!important;border:1.5px solid rgba(212,175,55,.25)!important;border-radius:var(--radius-sm)!important;background:rgba(10,10,10,.55)!important;backdrop-filter:blur(4px);}
.select2-container--focus .select2-selection--single{border-color:var(--gold)!important;}
.select2-container .select2-selection--single .select2-selection__rendered{color:#fff!important;font-size:13.5px!important;font-weight:500!important;padding-left:12px!important;line-height:42px!important;}
.select2-container .select2-selection--single .select2-selection__placeholder{color:rgba(255,255,255,.35)!important;}
.select2-container .select2-selection--single .select2-selection__arrow{height:40px!important;}
.select2-container .select2-selection--single .select2-selection__arrow b{border-color:var(--gold) transparent transparent transparent!important;}
.select2-container--default .select2-selection--single .select2-selection__clear{font-size:20px;color:#c92a2a;margin-right:6px;}
.select2-dropdown{background:#0a0a0a!important;border:1.5px solid rgba(212,175,55,.4)!important;border-radius:var(--radius-sm)!important;z-index:99999!important;box-shadow:0 16px 40px rgba(0,0,0,.7)!important;}
.select2-search__field{border:1px solid rgba(212,175,55,.3)!important;border-radius:6px!important;padding:8px 10px!important;font-size:13px!important;color:#fff!important;background:rgba(0,0,0,.5)!important;}
.select2-results__option{color:#d0d0d8!important;padding:9px 14px!important;font-size:12.5px!important;}
.select2-results__option--highlighted{background:rgba(212,175,55,.2)!important;color:var(--gold)!important;}
.select2-results__option[aria-selected="true"]{background:rgba(212,175,55,.15)!important;color:var(--gold)!important;}

.period-selector{display:flex;gap:8px;margin-bottom:var(--sp-4);flex-wrap:wrap;}
.period-selector a{padding:8px 14px;border-radius:20px;font-size:12.5px;font-weight:600;text-decoration:none;color:var(--text-muted);background:rgba(15,15,15,.6);border:1px solid rgba(212,175,55,.15);display:inline-flex;align-items:center;gap:5px;white-space:nowrap;transition:all .2s;backdrop-filter:blur(6px);}
.period-selector a:hover{border-color:rgba(212,175,55,.4);color:var(--gold);}
.period-selector a.active{color:#000;background:var(--gold-grad-2);border-color:var(--gold);box-shadow:0 6px 18px rgba(212,175,55,.3);}

.utang-total{background:linear-gradient(135deg,rgba(201,42,42,.9),rgba(139,28,28,.95));color:#fff;border-radius:var(--radius-md);padding:20px;text-align:center;margin-bottom:var(--sp-4);border:1px solid rgba(255,107,107,.4);backdrop-filter:blur(10px);box-shadow:0 12px 32px rgba(201,42,42,.25);}
.utang-total h2{font-size:26px;font-weight:900;margin:5px 0;word-break:break-word;font-family:'Cinzel',serif;}

.btn-telegram{background:linear-gradient(135deg,#0088cc,#006699);color:#fff;border:none;padding:12px 20px;border-radius:var(--radius-sm);font-weight:800;font-size:14px;cursor:pointer;width:100%;display:inline-flex;align-items:center;justify-content:center;gap:8px;box-shadow:0 6px 20px rgba(0,136,204,.25);transition:transform .18s,box-shadow .18s;}
.btn-telegram:hover{transform:translateY(-1px);box-shadow:0 10px 28px rgba(0,136,204,.4);}

.btn-print{background:rgba(23,162,184,.15);color:#4dd0e1!important;border:1px solid rgba(23,162,184,.4);padding:10px 18px;border-radius:8px;font-weight:700;font-size:12.5px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:all .2s;}
.btn-print:hover{background:rgba(23,162,184,.25);color:#fff!important;}

.search-box-barang{position:relative;min-width:180px;}
.search-box-barang i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:rgba(212,175,55,.7);z-index:2;}
.search-box-barang input{padding-left:35px;background:rgba(10,10,10,.55);border:1.5px solid rgba(212,175,55,.25);border-radius:var(--radius-sm);color:#fff;font-size:13px;backdrop-filter:blur(4px);}
.search-box-barang input::placeholder{color:rgba(255,255,255,.35);}

.customer-hint{font-size:11px;color:#51cf66;margin-top:3px;display:none;}
.customer-hint.show{display:block;}
.vehicle-list{margin-top:8px;display:none;}
.vehicle-list.show{display:block;}
.vehicle-option{background:rgba(212,175,55,.08);border:1px solid rgba(212,175,55,.3);padding:8px 12px;border-radius:8px;margin-bottom:5px;font-size:12px;cursor:pointer;color:#fff;transition:background .2s;}
.vehicle-option:hover{background:rgba(212,175,55,.18);}
.vehicle-option i{color:var(--gold);margin-right:6px;}

.laporan-profesional{background:#fff;border-radius:12px;padding:25px;margin-bottom:18px;color:#000;}
.laporan-profesional *{color:#000!important;}
.laporan-header-pro{text-align:center;border-bottom:3px double #000;padding-bottom:18px;margin-bottom:20px;}
.laporan-header-pro .logo-img{width:60px;height:60px;border:3px solid #B8860B;border-radius:50%;object-fit:cover;display:block;margin:0 auto 10px;background:#fffbf0;padding:4px;}
.laporan-header-pro h1{font-size:22px;font-weight:900;letter-spacing:3px;margin-bottom:4px;}
.laporan-header-pro .tagline{font-size:12px;}
.laporan-header-pro .periode-box{display:inline-block;background:#B8860B;color:#fff!important;padding:7px 18px;border-radius:6px;font-size:12px;font-weight:800;margin-top:12px;}
.summary-section{margin-bottom:20px;}
.summary-title{font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:1.5px;padding:8px 12px;background:#000;color:#fff!important;border-radius:5px 5px 0 0;display:flex;align-items:center;gap:8px;}
.summary-title i{color:#fff!important;}
.summary-table{width:100%;border-collapse:collapse;}
.summary-table td{padding:10px 12px;border:1px solid #ddd;font-size:12.5px;}
.summary-table .label{font-weight:600;width:60%;}
.summary-table .value{text-align:right;font-weight:800;font-family:'Courier New',monospace;}
.summary-table .highlight-row{background:#000!important;}
.summary-table .highlight-row .label,.summary-table .highlight-row .value{color:#FFD700!important;}
.table-section{margin-bottom:22px;}
.table-section-title{font-size:11.5px;font-weight:800;text-transform:uppercase;letter-spacing:1px;padding:9px 12px;background:#B8860B;color:#fff!important;border-radius:5px 5px 0 0;display:flex;align-items:center;gap:6px;}
.table-section-title i{color:#fff!important;}
.table-pro{width:100%;border-collapse:collapse;font-size:10.5px;border:1px solid #ddd;}
.table-pro thead{background:#1a1a1a;}
.table-pro thead th{padding:8px 6px;text-align:left;font-weight:700;color:#FFD700!important;font-size:9.5px;text-transform:uppercase;}
.table-pro tbody td{padding:7px 6px;border-bottom:1px solid #eee;font-size:10.5px;}
.table-pro tfoot{background:#f0f0f0;border-top:2px solid #000;}
.table-pro .text-right{text-align:right;}
.table-pro .text-center{text-align:center;}
.table-pro .kwitansi-no{font-family:'Courier New',monospace;font-weight:700;font-size:9.5px;color:#B8860B!important;}
.table-pro .badge-lunas{background:#d4edda;color:#155724!important;padding:2px 7px;border-radius:10px;font-size:9px;font-weight:800;}
.table-pro .badge-utang{background:#f8d7da;color:#721c24!important;padding:2px 7px;border-radius:10px;font-size:9px;font-weight:800;}
.table-pro .bukti-thumb-pro{width:32px;height:32px;border-radius:6px;object-fit:cover;border:1px solid #B8860B;cursor:pointer;}
.ttd-section{margin-top:30px;padding-top:20px;border-top:2px dashed #000;display:flex;justify-content:space-around;gap:20px;flex-wrap:wrap;}
.ttd-box{text-align:center;flex:1;min-width:140px;}
.ttd-box .ttd-label{font-size:11px;margin-bottom:50px;}
.ttd-box .ttd-name{font-weight:800;font-size:12px;border-top:1.5px solid #000;padding-top:5px;display:inline-block;min-width:120px;}
.ttd-box .ttd-title{font-size:10px;color:#666!important;margin-top:2px;}
.laporan-footer-note{text-align:center;font-size:10px;color:#666!important;margin-top:18px;padding-top:12px;border-top:1px solid #ddd;font-style:italic;}

.info-legend{background:rgba(212,175,55,.05);border:1px solid rgba(212,175,55,.25);border-radius:var(--radius-md);padding:var(--sp-3);margin-bottom:var(--sp-4);backdrop-filter:blur(6px);}
.info-legend h6{color:var(--gold);font-weight:700;font-size:13px;margin-bottom:var(--sp-2);display:flex;align-items:center;gap:6px;}
.info-legend ul{list-style:none;padding:0;margin:0;}
.info-legend li{font-size:12px;color:var(--text-muted);padding:5px 0;display:flex;align-items:flex-start;gap:8px;line-height:1.55;}
.info-legend li i{color:var(--gold);margin-top:3px;flex-shrink:0;}
.info-legend li strong{color:var(--gold-light);}

.step-card{padding:var(--sp-4);}
.step-header{display:flex;align-items:center;gap:var(--sp-3);padding-bottom:var(--sp-3);border-bottom:1px dashed rgba(212,175,55,.2);margin-bottom:var(--sp-4);}
.step-badge{width:44px;height:44px;border-radius:50%;background:var(--gold-grad-2);color:#000;font-family:'Cinzel',serif;font-weight:800;font-size:19px;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 6px 20px rgba(212,175,55,.35);}
.step-info{flex:1;min-width:0;}
.step-title{font-family:'Cinzel',serif;font-size:15px;font-weight:700;color:var(--gold);line-height:1.2;letter-spacing:.6px;}
.step-desc{font-size:12.5px;color:var(--text-muted);margin-top:4px;}

.layanan-toggle{display:grid;grid-template-columns:repeat(3,1fr);gap:var(--gap-grid);margin-bottom:var(--sp-4);}
.layanan-btn{position:relative;background:rgba(15,15,15,.55);border:1.5px solid rgba(212,175,55,.15);border-radius:var(--radius-md);padding:var(--sp-4) var(--sp-2);text-align:center;cursor:pointer;transition:all .25s;color:var(--text-muted);backdrop-filter:blur(8px);}
.layanan-btn:hover{border-color:rgba(212,175,55,.4);transform:translateY(-2px);}
.layanan-btn.active{background:linear-gradient(135deg,rgba(212,175,55,.15),rgba(212,175,55,.04));border-color:var(--gold);color:var(--gold);box-shadow:0 8px 24px rgba(212,175,55,.15),inset 0 0 40px rgba(212,175,55,.05);}
.layanan-btn .check-badge{position:absolute;top:-8px;right:-8px;width:26px;height:26px;border-radius:50%;background:#51cf66;color:#fff;display:none;align-items:center;justify-content:center;font-size:14px;font-weight:900;box-shadow:0 2px 10px rgba(81,207,102,.6);}
.layanan-btn.active .check-badge{display:flex;}
.layanan-btn i.bi{font-size:32px;display:block;margin-bottom:8px;}
.layanan-btn span{display:block;font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:.6px;margin-bottom:4px;}
.layanan-btn small{display:block;font-size:10.5px;color:rgba(255,255,255,.4);line-height:1.35;}
.layanan-btn.active small{color:rgba(212,175,55,.85);}
.layanan-section{display:none;}
.layanan-section.show{display:block;animation:fadeIn .3s;}
@keyframes fadeIn{from{opacity:0;transform:translateY(-5px)}to{opacity:1;transform:translateY(0)}}
.section-subtitle{font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:1.2px;margin-bottom:var(--sp-3);display:flex;align-items:center;gap:8px;padding-bottom:var(--sp-2);border-bottom:1px solid rgba(212,175,55,.18);font-family:'Cinzel',serif;}
.section-subtitle .badge-hint{background:rgba(212,175,55,.15);color:var(--gold);font-size:10.5px;padding:3px 10px;border-radius:10px;font-weight:700;margin-left:auto;letter-spacing:0;text-transform:none;font-family:'Inter',sans-serif;border:1px solid rgba(212,175,55,.25);}
.optional-section{background:rgba(77,171,247,.05);border:1px dashed rgba(77,171,247,.4);border-radius:var(--radius-md);padding:var(--sp-3);margin-bottom:var(--sp-3);backdrop-filter:blur(4px);}
.mekanik-section{background:rgba(255,107,107,.05);border:1px dashed rgba(255,107,107,.4);border-radius:var(--radius-md);padding:var(--sp-3);margin-bottom:var(--sp-3);backdrop-filter:blur(4px);}
.mekanik-section .opt-title{color:#ff9090;font-size:12.5px;font-weight:700;text-transform:uppercase;letter-spacing:1.2px;margin-bottom:10px;display:flex;align-items:center;gap:6px;}

.pesanan-item-row{background:rgba(15,15,15,.55);backdrop-filter:blur(10px);border:1px solid rgba(212,175,55,.18);border-radius:var(--radius-md);padding:var(--sp-4) var(--sp-3) var(--sp-3);margin-bottom:var(--sp-3);position:relative;}
.pesanan-item-row .item-num{position:absolute;top:-10px;left:14px;background:var(--gold-grad-2);color:#000;font-size:10.5px;font-weight:900;padding:3px 11px;border-radius:10px;letter-spacing:.4px;box-shadow:0 4px 14px rgba(212,175,55,.35);}
.pesanan-item-row .btn-remove{position:absolute;top:10px;right:10px;background:rgba(255,107,107,.12);border:1px solid rgba(255,107,107,.4);color:#ff9090;width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:14px;transition:all .2s;}
.pesanan-item-row .btn-remove:hover{background:rgba(255,107,107,.25);color:#fff;}
.pesanan-item-row .item-stok-info{font-size:11px;margin-top:6px;color:var(--text-muted);}

.pesanan-summary{background:linear-gradient(135deg,rgba(25,20,0,.7),rgba(15,12,0,.65));backdrop-filter:blur(14px);border:1px solid rgba(212,175,55,.35);border-radius:var(--radius-md);padding:var(--sp-4);margin-top:var(--sp-3);box-shadow:inset 0 1px 0 rgba(212,175,55,.15),0 8px 24px rgba(0,0,0,.4);}
.pesanan-summary .row-line{display:flex;justify-content:space-between;padding:9px 0;font-size:13.5px;border-bottom:1px dashed rgba(212,175,55,.15);color:#d0d0d8;}
.pesanan-summary .row-line:last-child{border-bottom:none;padding-top:14px;margin-top:10px;border-top:2px solid var(--gold);font-size:18px;font-weight:900;color:var(--gold);font-family:'Cinzel',serif;letter-spacing:.5px;}

.order-card{background:var(--glass);backdrop-filter:blur(14px) saturate(140%);-webkit-backdrop-filter:blur(14px) saturate(140%);border:1px solid var(--glass-border-2);border-radius:var(--radius-md);padding:var(--sp-4);margin-bottom:var(--sp-3);color:#fff;box-shadow:0 8px 28px rgba(0,0,0,.5);transition:border-color .2s,transform .2s,box-shadow .2s;position:relative;overflow:hidden;}
.order-card::before{content:'';position:absolute;top:0;left:15%;right:15%;height:1px;background:linear-gradient(90deg,transparent,rgba(212,175,55,.4),transparent);}
.order-card:hover{border-color:rgba(212,175,55,.4);transform:translateY(-2px);box-shadow:0 14px 40px rgba(0,0,0,.6);}
.order-card .oc-header{display:flex;justify-content:space-between;align-items:flex-start;gap:var(--sp-2);flex-wrap:wrap;padding-bottom:var(--sp-2);border-bottom:1px dashed rgba(212,175,55,.18);margin-bottom:var(--sp-2);}
.order-card .oc-no{font-family:'Courier New',monospace;font-weight:800;color:var(--gold);font-size:13px;letter-spacing:.5px;}
.order-card .oc-date{font-size:11px;color:#8a8a92;}
.order-card .oc-customer{font-size:15px;font-weight:800;color:#fff;margin:6px 0;}
.order-card .oc-customer i{color:var(--gold);}
.order-card .oc-meta{font-size:11.5px;color:#a0a0a8;}
.order-card .oc-badge{display:inline-block;padding:4px 11px;border-radius:10px;font-size:10px;font-weight:800;margin-right:4px;letter-spacing:.5px;text-transform:uppercase;}
.order-card .oc-badge.lunas{background:rgba(81,207,102,.18);color:#51cf66;border:1px solid rgba(81,207,102,.35);}
.order-card .oc-badge.utang{background:rgba(255,107,107,.15);color:#ff9090;border:1px solid rgba(255,107,107,.35);}
.order-card .oc-badge.antri{background:rgba(255,107,107,.12);color:#ff9090;border:1px solid rgba(255,107,107,.3);}
.order-card .oc-badge.proses{background:rgba(255,212,59,.12);color:#ffd43b;border:1px solid rgba(255,212,59,.3);}
.order-card .oc-badge.selesai{background:rgba(81,207,102,.15);color:#51cf66;border:1px solid rgba(81,207,102,.3);}
.order-card .oc-items{background:rgba(0,0,0,.35);border-radius:var(--radius-sm);padding:var(--sp-3);margin:var(--sp-2) 0;font-size:12px;color:#d0d0d8;border:1px solid rgba(212,175,55,.08);}
.order-card .oc-items .item-line{display:flex;justify-content:space-between;padding:4px 0;}
.order-card .oc-items .item-line strong{color:#fff;}
.order-card .oc-total{display:flex;justify-content:space-between;font-weight:900;color:#fff;font-size:17px;padding-top:10px;border-top:1px solid rgba(212,175,55,.25);margin-top:8px;font-family:'Cinzel',serif;letter-spacing:.5px;}
.order-card .oc-total span:last-child{color:var(--gold);}
.order-card .oc-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:var(--sp-3);}
.order-card .oc-actions .btn{font-size:11.5px;font-weight:700;padding:7px 12px;border-radius:8px;border:none;}
.order-card .oc-actions .btn-warning{background:var(--gold-grad-2);color:#000;}
.order-card .oc-actions .btn-primary{background:rgba(77,171,247,.18);color:#4dabf7;border:1px solid rgba(77,171,247,.4);}
.order-card .oc-actions .btn-success{background:rgba(81,207,102,.15);color:#51cf66;border:1px solid rgba(81,207,102,.35);}
.order-card .note-mini{background:rgba(255,212,59,.08);color:#ffd43b;border-radius:6px;padding:6px 10px;margin:8px 0;font-size:11.5px;border-left:3px solid rgba(255,212,59,.6);}

.progress-flow{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:var(--sp-4);}
.step-flow{background:rgba(15,15,15,.55);backdrop-filter:blur(8px);border:1.5px solid rgba(212,175,55,.15);border-radius:var(--radius-md);padding:var(--sp-3) 8px;text-align:center;transition:all .3s;}
.step-flow .step-icon{width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 8px;font-size:18px;background:rgba(30,30,30,.6);color:#666;}
.step-flow .step-label{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#666;}
.step-flow .step-sub{font-size:9px;color:#555;margin-top:3px;}
.step-flow.done{border-color:rgba(81,207,102,.5);background:rgba(81,207,102,.06);}
.step-flow.done .step-icon{background:#51cf66;color:#fff;box-shadow:0 0 16px rgba(81,207,102,.4);}
.step-flow.done .step-label{color:#51cf66;}
.step-flow.active{border-color:var(--gold);background:rgba(212,175,55,.1);box-shadow:0 0 20px rgba(212,175,55,.25);}
.step-flow.active .step-icon{background:var(--gold-grad-2);color:#000;animation:pulse 1.5s infinite;}
.step-flow.active .step-label{color:var(--gold);}
@keyframes pulse{0%,100%{transform:scale(1);}50%{transform:scale(1.08);}}

.action-box{background:linear-gradient(135deg,rgba(25,20,0,.7),rgba(15,12,0,.65));backdrop-filter:blur(14px);border:2px solid rgba(212,175,55,.4);border-radius:var(--radius-md);padding:var(--sp-4);text-align:center;margin-bottom:var(--sp-4);box-shadow:0 12px 32px rgba(0,0,0,.5);}
.action-box h4{color:var(--gold);font-family:'Cinzel',serif;font-size:15px;font-weight:700;margin-bottom:8px;letter-spacing:.8px;}
.action-box p{color:var(--text-muted);font-size:12.5px;margin-bottom:var(--sp-3);}
.btn-action-big{background:var(--gold-grad-2);color:#000;border:none;padding:16px 24px;border-radius:12px;font-weight:800;font-size:15px;letter-spacing:.5px;cursor:pointer;width:100%;display:inline-flex;align-items:center;justify-content:center;gap:10px;transition:transform .2s,box-shadow .2s;box-shadow:0 8px 24px rgba(212,175,55,.3);text-transform:uppercase;}
.btn-action-big:hover{transform:translateY(-2px);box-shadow:0 14px 36px rgba(212,175,55,.45);}
.btn-action-big.green{background:linear-gradient(135deg,#2b8a3e,#51cf66);color:#fff;box-shadow:0 8px 24px rgba(81,207,102,.3);}
.btn-action-big.blue{background:linear-gradient(135deg,#1864ab,#4dabf7);color:#fff;box-shadow:0 8px 24px rgba(77,171,247,.3);}

.note-box{background:rgba(255,212,59,.06);border:1px solid rgba(255,212,59,.25);border-left:4px solid rgba(255,212,59,.6);border-radius:var(--radius-md);padding:var(--sp-3);margin-bottom:var(--sp-2);backdrop-filter:blur(6px);}
.note-box .note-title{font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:1.2px;margin-bottom:6px;color:#ffd43b;}
.note-box .note-content{font-size:13px;color:#ffe9a0;white-space:pre-wrap;word-break:break-word;}
.tambahan-box{background:rgba(81,207,102,.06);border:1px solid rgba(81,207,102,.25);border-left:4px solid rgba(81,207,102,.5);border-radius:var(--radius-md);padding:var(--sp-3);margin-bottom:var(--sp-2);backdrop-filter:blur(6px);}
.tambahan-box .tambahan-title{font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:1.2px;color:#51cf66;margin-bottom:10px;display:flex;align-items:center;gap:6px;}
.tambahan-row{display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px dashed rgba(81,207,102,.2);gap:8px;}
.tambahan-row:last-child{border-bottom:none;}
.tambahan-row strong{color:#fff;}
.tambahan-row small{color:#8a8a92;}

.est-box{background:rgba(77,171,247,.08);border:1px solid rgba(77,171,247,.3);border-radius:var(--radius-sm);padding:12px;margin-top:10px;color:#8ec5ff;font-size:13px;backdrop-filter:blur(6px);}
.qr-box{text-align:center;margin-top:14px;padding-top:14px;border-top:1px dashed rgba(212,175,55,.2);}
.qr-box img{border-radius:8px;border:3px solid var(--gold);padding:4px;background:#fff;box-shadow:0 8px 24px rgba(212,175,55,.25);}

.bottom-nav{display:flex;position:fixed;bottom:0;left:0;right:0;background:rgba(5,5,5,.75);backdrop-filter:blur(20px) saturate(140%);-webkit-backdrop-filter:blur(20px) saturate(140%);border-top:1px solid rgba(212,175,55,.22);z-index:1000;padding:4px 0;overflow-x:auto;scrollbar-width:none;box-shadow:0 -8px 32px rgba(0,0,0,.5);}
.bottom-nav::-webkit-scrollbar{display:none;}
.bottom-nav a{color:var(--text-muted);text-decoration:none;display:flex;flex-direction:column;align-items:center;gap:3px;padding:8px 6px;font-size:9px;font-weight:600;min-width:60px;white-space:nowrap;position:relative;flex:1;transition:color .2s;text-transform:uppercase;letter-spacing:.4px;}
.bottom-nav a i{font-size:18px;}
.bottom-nav a.active{color:var(--gold);}
.bottom-nav a.active i{filter:drop-shadow(0 0 8px rgba(212,175,55,.6));}
.bottom-nav .indicator-dot{top:5px;right:12px;width:6px;height:6px;}

.modal-content{background:#0d0d0d!important;border:1px solid rgba(212,175,55,.3)!important;color:#fff!important;backdrop-filter:blur(20px);}
.modal-header,.modal-footer{border-color:rgba(212,175,55,.15)!important;}
.modal-title{color:var(--gold)!important;font-family:'Cinzel',serif;letter-spacing:.8px;}
.modal .btn-secondary{background:rgba(60,60,60,.4);border:1px solid rgba(255,255,255,.15);color:#e0e0e0;}
.modal .btn-warning{background:var(--gold-grad-2);color:#000;border:none;font-weight:700;}

@media(min-width:768px){
    .main-content{margin-left:240px;padding:84px 22px 30px;}
    .sidebar-desktop{display:block;}
    .kpi-grid{grid-template-columns:repeat(4,1fr);}
    .grid-view{grid-template-columns:repeat(3,1fr);}
    .quick-actions{grid-template-columns:repeat(3,1fr);}
    .mini-stats{grid-template-columns:repeat(4,1fr);}
    .bottom-nav{display:none!important;}
    .order-overview{grid-template-columns:repeat(4,1fr);}
    :root{--gap-grid:14px;}
}
@media(min-width:1024px){
    .main-content{padding:84px 32px 30px;}
    .grid-view{grid-template-columns:repeat(4,1fr);}
    .quick-actions{grid-template-columns:repeat(5,1fr);}
    :root{--gap-grid:16px;}
}
@media(max-width:767px){
    .brand-text small{display:none;}
    .btn-logout-top span{display:none;}
    .laporan-profesional{padding:14px;}
    .table-pro{font-size:10px;}
    .progress-flow{grid-template-columns:repeat(2,1fr);}
    .layanan-toggle{grid-template-columns:1fr;}
    .step-flow .step-label{font-size:9.5px;}
    .layanan-btn{padding:var(--sp-3);}
    .layanan-btn i.bi{font-size:26px;}
    .navbar-top{padding:0 12px;height:60px;}
    .sidebar-desktop{top:60px;}
    .main-content{padding:76px 14px 96px;}
}

@media print{
    @page{size:A4 portrait;margin:12mm 8mm;}
    body{background:#fff!important;font-size:10px;}
    .bg-layer,.navbar-top,.sidebar-desktop,.bottom-nav,.btn-print,.period-selector,.form-card,.info-legend,.progress-flow,.action-box,.no-print{display:none!important;}
    .main-content{margin:0!important;padding:0!important;}
    .laporan-profesional{padding:0!important;box-shadow:none!important;background:#fff!important;backdrop-filter:none!important;border:none!important;}
    .laporan-profesional *{color:#000!important;-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important;}
    .summary-title{background:#000!important;color:#fff!important;}
    .summary-table .highlight-row{background:#000!important;}
    .summary-table .highlight-row .label,.summary-table .highlight-row .value{color:#FFD700!important;}
    .table-section-title{background:#B8860B!important;color:#fff!important;}
    .table-pro thead{background:#1a1a1a!important;}
    .table-pro thead th{color:#FFD700!important;}
    .table-section,.ttd-section{page-break-inside:avoid;}
    .section-card,.form-card,.table-card,.order-card,.kpi-card,.welcome-card,.grid-item,.step-flow,.action-box,.pesanan-item-row,.pesanan-summary{background:#fff!important;backdrop-filter:none!important;border-color:#ccc!important;box-shadow:none!important;color:#000!important;}
    .section-title,.form-title,.table-title{color:#000!important;border-color:#ccc!important;}
}
</style>
</head>
<body>

<div class="bg-layer" aria-hidden="true">
    <div class="grid"></div>
    <div class="glow"></div>
    <div class="bg-rings">
        <img src="<?= LOGO_URL ?>" alt="" class="bg-logo">
        <div class="bg-ring bg-r1"><span class="bg-rd"></span></div>
        <div class="bg-ring bg-r2"><span class="bg-rd"></span></div>
        <div class="bg-ring bg-r3"><span class="bg-rd"></span></div>
        <div class="bg-ring bg-r4"><span class="bg-rd"></span></div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<?php if ($tab === 'home'): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<?php endif; ?>

<script>
var CUSTOMER_DB = <?= json_encode($cDB, JSON_UNESCAPED_UNICODE) ?>;
var PHONE_DB = <?= json_encode($pDB, JSON_UNESCAPED_UNICODE) ?>;
var PLATE_DB = <?= json_encode($plDB, JSON_UNESCAPED_UNICODE) ?>;
var BARANG_LIST = <?php
$bl=array();
foreach($data['barang'] as $b) $bl[]=array('id'=>$b['id'],'label'=>$b['merek'].' '.$b['nama'].' ('.$b['price_code'].')','harga'=>(float)$b['harga_jual'],'stok'=>(int)$b['stok']);
echo json_encode($bl, JSON_UNESCAPED_UNICODE);
?>;
var LOGO_B64 = <?= json_encode($LOGO_B64) ?>;
var APP_NAME = '<?= APP_NAME ?>';
var LOGO_URL_JS = '<?= LOGO_URL ?>';
</script>

<div class="navbar-top">
    <a class="navbar-brand" href="?tab=home">
        <img src="<?= LOGO_URL ?>" alt="<?= APP_NAME ?>" class="brand-logo">
        <div class="brand-text"><h5><?= APP_NAME ?></h5><small>WIB &bull; Sumatera Utara</small></div>
    </a>
    <a href="logout.php" class="btn-logout-top" onclick="return confirmLogout()"><i class="bi bi-box-arrow-right"></i> <span>Keluar</span></a>
</div>

<div class="sidebar-desktop">
    <div class="sidebar-group">
        <div class="sidebar-group-label">Dashboard</div>
        <a class="sidebar-item <?= act('home',$tab) ?>" href="?tab=home"><i class="bi bi-speedometer2"></i> Dashboard<?php if(count($stokM)>0): ?><span class="indicator-dot indicator-red"></span><?php endif; ?></a>
    </div>
    <div class="sidebar-group">
        <div class="sidebar-group-label">Pesanan</div>
        <a class="sidebar-item <?= act('pesanan',$tab) ?>" href="?tab=pesanan"><i class="bi bi-cart-plus"></i> Tambah Pesanan</a>
        <a class="sidebar-item <?= act('daftar_pesanan',$tab) ?>" href="?tab=daftar_pesanan"><i class="bi bi-receipt"></i> Semua Pesanan</a>
        <a class="sidebar-item <?= act('antrian',$tab) ?>" href="?tab=antrian"><i class="bi bi-list-ol"></i> Antrian<?php if($totA>0): ?><span class="indicator-dot indicator-red"></span><?php endif; ?></a>
        <a class="sidebar-item <?= act('proses',$tab) ?>" href="?tab=proses"><i class="bi bi-gear"></i> Dikerjakan<?php if($totP>0): ?><span class="indicator-dot indicator-green"></span><?php endif; ?></a>
        <a class="sidebar-item <?= act('selesai',$tab) ?>" href="?tab=selesai"><i class="bi bi-check-circle"></i> Selesai<?php if($totS>0): ?><span class="indicator-dot indicator-green"></span><?php endif; ?></a>
        <a class="sidebar-item <?= act('utang',$tab) ?>" href="?tab=utang"><i class="bi bi-cash-stack"></i> Belum Bayar<?php if($totUC>0): ?><span class="indicator-dot indicator-yellow"></span><?php endif; ?></a>
    </div>
    <div class="sidebar-group">
        <div class="sidebar-group-label">Barang</div>
        <a class="sidebar-item <?= act('tambah',$tab) ?>" href="?tab=tambah"><i class="bi bi-plus-circle"></i> Tambah Barang</a>
        <a class="sidebar-item <?= act('barang',$tab) ?>" href="?tab=barang"><i class="bi bi-box"></i> Data Barang</a>
        <a class="sidebar-item <?= act('masuk',$tab) ?>" href="?tab=masuk"><i class="bi bi-arrow-down-circle"></i> Barang Masuk</a>
        <a class="sidebar-item <?= act('keluar',$tab) ?>" href="?tab=keluar"><i class="bi bi-arrow-up-circle"></i> Barang Keluar</a>
        <a class="sidebar-item <?= act('stokmenipis',$tab) ?>" href="?tab=stokmenipis"><i class="bi bi-exclamation-triangle"></i> Stok Menipis<?php if(count($stokM)>0): ?><span class="indicator-dot indicator-red"></span><?php endif; ?></a>
    </div>
    <div class="sidebar-group">
        <div class="sidebar-group-label">Laporan</div>
        <a class="sidebar-item <?= act('laporan',$tab) ?>" href="?tab=laporan"><i class="bi bi-file-earmark-bar-graph"></i> Laporan Lengkap</a>
        <a class="sidebar-item <?= act('laporan_stok',$tab) ?>" href="?tab=laporan_stok"><i class="bi bi-arrow-left-right"></i> Keluar Masuk Barang</a>
        <a class="sidebar-item <?= act('pengeluaran',$tab) ?>" href="?tab=pengeluaran"><i class="bi bi-cash-coin"></i> Pengeluaran</a>
    </div>
    <div class="sidebar-group">
        <div class="sidebar-group-label">Pelanggan</div>
        <a class="sidebar-item <?= act('pelanggan',$tab) ?>" href="?tab=pelanggan"><i class="bi bi-person-lines-fill"></i> Data Pelanggan</a>
    </div>
</div>

<div class="main-content">
<?php if ($msg): ?><script>Swal.fire({icon:'success',title:'<?= $isNew ? "Pesanan Dibuat!" : "Berhasil!" ?>',text:'<?= htmlspecialchars($msg) ?>',timer:3000,showConfirmButton:false,background:'#0a0a0a',color:'#fff'});</script><?php endif; ?>

<?php if ($tab === 'home'): ?>
<div class="welcome-card">
    <div class="welcome-greet"><?= $greet ?></div>
    <div class="welcome-name"><?= APP_NAME ?> &mdash; Owner Panel</div>
    <div class="welcome-date"><i class="bi bi-calendar-event"></i> <?= tglL($today) ?> <span style="color:#555;">&bull;</span> <i class="bi bi-clock"></i> <span id="liveClock"><?= date('H:i:s') ?></span> WIB</div>
</div>
<?php if (count($stokH)>0): ?>
<a href="?tab=stokmenipis" class="warn-banner danger"><i class="bi bi-x-octagon-fill w-icon"></i><div><div><?= count($stokH) ?> BARANG STOK HABIS!</div><small style="opacity:.9;font-weight:400;">Segera restock</small></div><i class="bi bi-chevron-right ms-auto"></i></a>
<?php elseif (count($stokM)>0): ?>
<a href="?tab=stokmenipis" class="warn-banner warning"><i class="bi bi-exclamation-triangle-fill w-icon"></i><div><div><?= count($stokM) ?> BARANG STOK MENIPIS</div><small style="opacity:.9;font-weight:400;">Perlu segera restock</small></div><i class="bi bi-chevron-right ms-auto"></i></a>
<?php endif; ?>
<?php if ($totUC > 0): ?>
<a href="?tab=utang" class="warn-banner danger"><i class="bi bi-cash-stack w-icon"></i><div><div>BELUM BAYAR: <?= rp($totU) ?></div><small style="opacity:.9;font-weight:400;"><?= $totUC ?> pesanan belum lunas</small></div><i class="bi bi-chevron-right ms-auto"></i></a>
<?php endif; ?>
<?php if ($totA > 0): ?>
<a href="?tab=antrian" class="warn-banner info"><i class="bi bi-hourglass-split w-icon"></i><div><div><?= $totA ?> PESANAN MENUNGGU</div><small style="opacity:.9;font-weight:400;">Klik untuk lihat antrian</small></div><i class="bi bi-chevron-right ms-auto"></i></a>
<?php endif; ?>

<div class="period-selector">
    <a class="<?= $period==='hari_ini'?'active':'' ?>" href="?tab=home&period=hari_ini"><i class="bi bi-calendar-day"></i> Hari Ini</a>
    <a class="<?= $period==='kemarin'?'active':'' ?>" href="?tab=home&period=kemarin"><i class="bi bi-calendar-minus"></i> Kemarin</a>
    <a class="<?= $period==='minggu_ini'?'active':'' ?>" href="?tab=home&period=minggu_ini"><i class="bi bi-calendar-week"></i> Minggu</a>
    <a class="<?= $period==='bulan_ini'?'active':'' ?>" href="?tab=home&period=bulan_ini"><i class="bi bi-calendar-month"></i> Bulan</a>
</div>

<div class="kpi-grid">
    <div class="kpi-card"><div class="kpi-icon" style="background:rgba(255,212,59,.12);color:#ffd43b;"><i class="bi bi-wrench"></i></div><div class="kpi-label">Omset Servis</div><div class="kpi-value" style="color:#ffd43b;"><?= rp($st['os']) ?></div><div class="kpi-sub"><?= $st['ss'] ?> selesai</div></div>
    <div class="kpi-card"><div class="kpi-icon" style="background:rgba(77,171,247,.12);color:#4dabf7;"><i class="bi bi-droplet-half"></i></div><div class="kpi-label">Omset Cuci</div><div class="kpi-value" style="color:#4dabf7;"><?= rp($st['oc']) ?></div><div class="kpi-sub"><?= $st['cs'] ?> selesai</div></div>
    <div class="kpi-card"><div class="kpi-icon" style="background:rgba(81,207,102,.12);color:#51cf66;"><i class="bi bi-cart-check"></i></div><div class="kpi-label">Penjualan Barang</div><div class="kpi-value" style="color:#51cf66;"><?= rp($st['op']) ?></div><div class="kpi-sub">Via pesanan</div></div>
    <div class="kpi-card"><div class="kpi-icon" style="background:rgba(255,107,107,.12);color:#ff6b6b;"><i class="bi bi-trophy"></i></div><div class="kpi-label">Laba Bersih</div><div class="kpi-value" style="color:#ff6b6b;"><?= rp($st['tb']) ?></div><div class="kpi-sub"><?= $st['tp'] ?> trx</div></div>
</div>

<div class="section-card">
    <div class="section-title"><i class="bi bi-graph-up-arrow"></i> Tren Omset 7 Hari Terakhir</div>
    <div style="position:relative;height:220px;"><canvas id="chart7"></canvas></div>
</div>

<div class="section-card">
    <div class="section-title"><i class="bi bi-lightning-charge-fill"></i> Aksi Cepat</div>
    <div class="quick-actions">
        <a href="?tab=pesanan" class="qa-btn"><div class="qa-icon" style="background:rgba(81,207,102,.15);color:#51cf66;"><i class="bi bi-cart-plus"></i></div> Pesanan Baru</a>
        <a href="?tab=antrian" class="qa-btn"><div class="qa-icon" style="background:rgba(255,212,59,.15);color:#ffd43b;"><i class="bi bi-list-ol"></i></div> Antrian</a>
        <a href="?tab=masuk" class="qa-btn"><div class="qa-icon" style="background:rgba(81,207,102,.15);color:#51cf66;"><i class="bi bi-arrow-down-circle"></i></div> Barang Masuk</a>
        <a href="?tab=laporan" class="qa-btn"><div class="qa-icon" style="background:rgba(77,171,247,.15);color:#4dabf7;"><i class="bi bi-file-earmark-text"></i></div> Laporan</a>
        <a href="?tab=pengeluaran" class="qa-btn"><div class="qa-icon" style="background:rgba(255,107,107,.15);color:#ff6b6b;"><i class="bi bi-cash-coin"></i></div> Pengeluaran</a>
    </div>
</div>

<div class="section-card">
    <div class="section-title"><i class="bi bi-clipboard-data-fill"></i> Flow Pesanan <span class="count-pill"><?= $totA+$totP+$totS ?></span></div>
    <div class="order-overview">
        <a href="?tab=antrian" class="order-stat antri"><div class="num"><?= $totA ?></div><div class="lbl">Antrian</div></a>
        <a href="?tab=proses" class="order-stat proses"><div class="num"><?= $totP ?></div><div class="lbl">Dikerjakan</div></a>
        <a href="?tab=selesai" class="order-stat selesai"><div class="num"><?= $totS ?></div><div class="lbl">Selesai</div></a>
    </div>
    <div class="mini-stats">
        <div class="mini-stat"><div class="mini-stat-icon" style="background:rgba(81,207,102,.12);color:#51cf66;"><i class="bi bi-cart"></i></div><div><div class="mini-stat-val"><?= count($data['pesanan']) ?></div><div class="mini-stat-lbl">Total Pesanan</div></div></div>
        <div class="mini-stat"><div class="mini-stat-icon" style="background:rgba(255,107,107,.12);color:#ff6b6b;"><i class="bi bi-clock-history"></i></div><div><div class="mini-stat-val"><?= $totUC ?></div><div class="mini-stat-lbl">Belum Bayar</div></div></div>
        <div class="mini-stat"><div class="mini-stat-icon" style="background:rgba(77,171,247,.12);color:#4dabf7;"><i class="bi bi-people"></i></div><div><div class="mini-stat-val"><?= $totCust ?></div><div class="mini-stat-lbl">Pelanggan</div></div></div>
        <div class="mini-stat"><div class="mini-stat-icon" style="background:rgba(255,212,59,.12);color:#ffd43b;"><i class="bi bi-box-seam"></i></div><div><div class="mini-stat-val"><?= count($data['barang']) ?></div><div class="mini-stat-lbl">Barang</div></div></div>
    </div>
</div>

<div class="form-card">
    <h5 class="form-title"><i class="bi bi-telegram"></i> Kirim Laporan ke Telegram</h5>
    <form method="POST" onsubmit="return confirmKirimLaporan(event)">
        <input type="hidden" name="action" value="kirim_laporan_harian">
        <div class="row g-3">
            <div class="col-md-8"><label class="form-label">Tanggal Laporan</label><input type="date" name="tanggal_laporan" class="form-control" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required></div>
            <div class="col-md-4 d-flex align-items-end"><button type="submit" class="btn-telegram"><i class="bi bi-send"></i> KIRIM</button></div>
        </div>
        <small style="color:var(--text-muted);font-size:11px;display:block;margin-top:10px;"><i class="bi bi-info-circle"></i> Laporan dikirim dalam <strong style="color:var(--gold);">2 format</strong>: Tabel teks + <strong style="color:var(--gold);">File PDF profesional</strong>.</small>
    </form>
</div>

<div class="section-card">
    <div class="section-title"><i class="bi bi-activity"></i> Aktivitas Terbaru</div>
    <div class="activity-list">
    <?php if (empty($recent)): ?><div style="text-align:center;padding:25px;color:var(--text-muted);font-size:12px;"><i class="bi bi-inbox" style="font-size:28px;display:block;margin-bottom:6px;"></i>Belum ada aktivitas</div>
    <?php else: foreach ($recent as $r): ?>
        <div class="activity-item">
            <div class="activity-icon" style="color:<?= $r['c'] ?>;"><i class="bi <?= $r['i'] ?>"></i></div>
            <div class="activity-body">
                <div class="activity-title"><?= htmlspecialchars($r['t']) ?></div>
                <div class="activity-desc"><?= htmlspecialchars($r['d']) ?></div>
                <div class="activity-time"><i class="bi bi-clock"></i> <?= ago($r['tm']) ?></div>
            </div>
        </div>
    <?php endforeach; endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($tab === 'pesanan'): ?>
<div class="info-legend">
    <h6><i class="bi bi-info-circle-fill"></i> Panduan Membuat Pesanan</h6>
    <ul>
        <li><i class="bi bi-dot"></i><span><strong>Langkah 1:</strong> isi nama pelanggan, nomor HP, dan info mobil.</span></li>
        <li><i class="bi bi-dot"></i><span><strong>Langkah 2:</strong> pilih layanan — bisa lebih dari satu (Barang + Servis + Cuci).</span></li>
        <li><i class="bi bi-dot"></i><span><strong>Langkah 3:</strong> isi catatan &amp; estimasi selesai (opsional).</span></li>
        <li><i class="bi bi-dot"></i><span><strong>Langkah 4:</strong> cek ringkasan total, lalu klik <strong>SIMPAN</strong>.</span></li>
        <li><i class="bi bi-lightbulb-fill" style="color:#ffd43b!important;"></i><span>Di kolom barang, <strong>ketik nama barang</strong> untuk cari cepat.</span></li>
    </ul>
</div>

<form method="POST" onsubmit="return confirmSavePesanan(event)" id="formPesanan">
<input type="hidden" name="action" value="tambah_pesanan">
<input type="hidden" name="items_json" id="itemsJson" value="[]">

<div class="form-card step-card">
    <div class="step-header">
        <div class="step-badge">1</div>
        <div class="step-info">
            <div class="step-title">Data Pelanggan</div>
            <div class="step-desc">Nama, nomor HP, dan info mobil pelanggan</div>
        </div>
    </div>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Nama Pelanggan <span style="color:#ffd43b;">*</span></label>
            <input type="text" name="nama_customer" id="pesananNama" class="form-control" list="customerList" required autocomplete="off" placeholder="Ketik nama pelanggan...">
            <datalist id="customerList"><?php foreach ($cDB as $c): ?><option value="<?= htmlspecialchars($c['nama']) ?>"><?php endforeach; ?></datalist>
            <div class="customer-hint" id="hintPesananNama"><i class="bi bi-check-circle-fill"></i> Data pelanggan ditemukan!</div>
        </div>
        <div class="col-md-6">
            <label class="form-label">No HP / WhatsApp <span style="color:#ffd43b;">*</span></label>
            <input type="text" name="no_hp" id="pesananHp" class="form-control" required autocomplete="off" placeholder="08xxxxxxxxxx">
            <div class="customer-hint" id="hintPesananHp"><i class="bi bi-check-circle-fill"></i> Ditemukan!</div>
        </div>
        <div class="col-md-4">
            <label class="form-label"><i class="bi bi-car-front-fill"></i> Jenis Mobil</label>
            <select name="jenis_kendaraan" id="pesananJenis" class="form-select">
                <option value="">-- Pilih jenis mobil --</option>
                <option value="Mobil Kecil">🚗 Mobil Kecil (City Car, Hatchback)</option>
                <option value="Mobil Sedang">🚙 Mobil Sedang (Sedan, MPV)</option>
                <option value="Mobil Besar">🚐 Mobil Besar (SUV, Van)</option>
                <option value="Mobil Sangat Besar">🚚 Mobil Sangat Besar (Truck, Bus)</option>
                <option value="Pickup">🛻 Pickup / Bak Terbuka</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label"><i class="bi bi-tag-fill"></i> Tipe Mobil</label>
            <input type="text" name="tipe" id="pesananTipe" class="form-control" placeholder="Avanza, Innova, Brio...">
        </div>
        <div class="col-md-4">
            <label class="form-label"><i class="bi bi-card-text"></i> No Polisi</label>
            <input type="text" name="no_polisi" id="pesananPlat" class="form-control" placeholder="BK 1234 ABC" style="text-transform:uppercase;">
            <div class="vehicle-list" id="vehicleListPesanan"></div>
        </div>
    </div>
</div>

<div class="form-card step-card">
    <div class="step-header">
        <div class="step-badge">2</div>
        <div class="step-info">
            <div class="step-title">Pilih Layanan</div>
            <div class="step-desc">Klik kotak untuk memilih — bisa pilih lebih dari satu</div>
        </div>
    </div>

    <div class="layanan-toggle">
        <div class="layanan-btn active" data-target="secBarang" onclick="toggleLayanan(this)">
            <div class="check-badge"><i class="bi bi-check-lg"></i></div>
            <i class="bi bi-box-seam"></i>
            <span>Barang</span>
            <small>Jual sparepart / oli</small>
        </div>
        <div class="layanan-btn" data-target="secServis" onclick="toggleLayanan(this)">
            <div class="check-badge"><i class="bi bi-check-lg"></i></div>
            <i class="bi bi-wrench-adjustable"></i>
            <span>Servis</span>
            <small>Jasa perbaikan mobil</small>
        </div>
        <div class="layanan-btn" data-target="secCuci" onclick="toggleLayanan(this)">
            <div class="check-badge"><i class="bi bi-check-lg"></i></div>
            <i class="bi bi-droplet-half"></i>
            <span>Cuci Mobil</span>
            <small>Cuci & poles body</small>
        </div>
    </div>

    <div class="layanan-section show" id="secBarang">
        <div class="section-subtitle" style="color:#51cf66;">
            <i class="bi bi-box-seam-fill"></i> Barang yang Dibeli
            <span class="badge-hint" id="itemCountLabel">0 item</span>
        </div>
        <div id="itemsContainer"></div>
        <button type="button" class="btn-soft-gold" onclick="addPesananItem()"><i class="bi bi-plus-circle-fill"></i> TAMBAH BARANG</button>
    </div>

    <div class="layanan-section" id="secServis">
        <div class="section-subtitle" style="color:#ffd43b;"><i class="bi bi-wrench-adjustable"></i> Jasa Servis Mobil</div>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Jenis Servis</label>
                <input type="text" name="servis_jenis" id="servisJenis" class="form-control" list="servisList" placeholder="Pilih atau ketik...">
                <datalist id="servisList">
                    <option value="Ganti Oli"><option value="Tune Up Mesin"><option value="Servis Rem">
                    <option value="Ganti Ban"><option value="Ganti Aki"><option value="Servis CVT / Transmisi">
                    <option value="Servis Rutin"><option value="Turun Mesin"><option value="Kelistrikan">
                    <option value="Servis AC"><option value="Ganti Busi"><option value="Ganti Filter Udara">
                </datalist>
            </div>
            <div class="col-md-6">
                <label class="form-label">Biaya Servis (Rp)</label>
                <input type="number" name="servis_biaya" id="servisBiaya" class="form-control" min="0" value="0" placeholder="0">
            </div>
        </div>
        <div class="mekanik-section" style="margin-top:var(--sp-3);">
            <div class="opt-title"><i class="bi bi-tools"></i> Biaya Jasa Mekanik <span style="color:#888;font-weight:400;text-transform:none;letter-spacing:0;">(opsional)</span></div>
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">Nama Mekanik</label><input type="text" name="mekanik_nama" class="form-control" placeholder="Nama tukang"></div>
                <div class="col-md-4"><label class="form-label">Keterangan Jasa</label><input type="text" name="mekanik_keterangan" class="form-control" placeholder="Pasang, bongkar, dll"></div>
                <div class="col-md-4"><label class="form-label">Biaya (Rp)</label><input type="number" name="mekanik_biaya" id="mekanikBiaya" class="form-control" min="0" value="0"></div>
            </div>
        </div>
    </div>

    <div class="layanan-section" id="secCuci">
        <div class="section-subtitle" style="color:#4dabf7;"><i class="bi bi-droplet-half"></i> Jasa Cuci Mobil</div>
        <div class="optional-section">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Paket Cuci</label>
                    <input type="text" name="dm_paket" id="dmPaket" class="form-control" list="paketList" placeholder="Pilih atau ketik...">
                    <datalist id="paketList">
                        <option value="Cuci Biasa"><option value="Cuci Premium"><option value="Cuci Detailing">
                        <option value="Cuci Mesin"><option value="Poles Body"><option value="Coating">
                        <option value="Salon Interior">
                    </datalist>
                </div>
                <div class="col-md-6"><label class="form-label">Harga (Rp)</label><input type="number" name="dm_harga" id="dmHarga" class="form-control" min="0" value="0" placeholder="0"></div>
            </div>
        </div>
    </div>
</div>

<div class="form-card step-card">
    <div class="step-header">
        <div class="step-badge">3</div>
        <div class="step-info">
            <div class="step-title">Catatan &amp; Estimasi</div>
            <div class="step-desc">Catatan tambahan &amp; perkiraan waktu selesai</div>
        </div>
    </div>
    <label class="form-label"><i class="bi bi-sticky"></i> Catatan <span style="color:#888;font-weight:400;">(opsional)</span></label>
    <textarea name="note" class="form-control" rows="2" maxlength="500" placeholder="Contoh: HP dikirim besok, tolong dipacking rapi, mobil tunggu di rumah, dll..."></textarea>
    <small style="color:var(--text-muted);font-size:11px;display:block;margin-top:8px;margin-bottom:var(--sp-3);"><i class="bi bi-info-circle"></i> Catatan akan tampil di <strong style="color:var(--gold);">kwitansi PDF</strong> dan <strong style="color:var(--gold);">laporan Telegram</strong>.</small>

    <label class="form-label"><i class="bi bi-hourglass-split"></i> Estimasi Waktu Selesai <span style="color:#888;font-weight:400;">(opsional)</span></label>
    <input type="datetime-local" name="estimasi_selesai" class="form-control" value="">
    <small style="color:var(--text-muted);font-size:11px;display:block;margin-top:8px;"><i class="bi bi-info-circle"></i> Kalau diisi, pelanggan bisa lihat sisa waktu via QR / WhatsApp.</small>
</div>

<div class="form-card step-card">
    <div class="step-header">
        <div class="step-badge">4</div>
        <div class="step-info">
            <div class="step-title">Ringkasan &amp; Simpan</div>
            <div class="step-desc">Cek total, tambah diskon kalau ada, lalu simpan</div>
        </div>
    </div>
    <div class="row g-3">
        <div class="col-md-12"><label class="form-label">Diskon (Rp) — Opsional</label><input type="number" name="diskon" id="pesananDiskon" class="form-control" min="0" value="0"></div>
    </div>
    <div class="pesanan-summary">
        <div class="row-line"><span><i class="bi bi-box-seam"></i> Subtotal Barang</span><span id="sumBarang">Rp 0</span></div>
        <div class="row-line"><span><i class="bi bi-wrench"></i> Biaya Servis</span><span id="sumServis">Rp 0</span></div>
        <div class="row-line"><span><i class="bi bi-tools"></i> Biaya Mekanik</span><span id="sumMekanik">Rp 0</span></div>
        <div class="row-line"><span><i class="bi bi-droplet-half"></i> Biaya Cuci</span><span id="sumCuci">Rp 0</span></div>
        <div class="row-line"><span><i class="bi bi-tag"></i> Diskon</span><span id="sumDiskon">-Rp 0</span></div>
        <div class="row-line"><span>TOTAL</span><span id="sumTotal">Rp 0</span></div>
    </div>
    <button type="submit" class="btn-gold mt-3" style="padding:16px;font-size:15px;"><i class="bi bi-check-circle-fill"></i> SIMPAN PESANAN</button>
</div>
</form>
<?php endif; ?>

<?php if ($tab === 'detail_pesanan' && $detailP):
    $sp = isset($detailP['status_pekerjaan'])?$detailP['status_pekerjaan']:'antri';
    $sbyr = isset($detailP['status_bayar'])?$detailP['status_bayar']:'belum_bayar';
    $stepDibuat = 1;
    $stepDikerjakan = ($sp==='proses'||$sp==='selesai') ? 1 : 0;
    $stepSelesai = ($sp==='selesai') ? 1 : 0;
    $stepLunas = ($sbyr==='lunas') ? 1 : 0;
    $waLink = 'https://wa.me/' . preg_replace('/^0/','62',isset($detailP['no_hp'])?$detailP['no_hp']:'') . '?text=' . urlencode(waMsg($detailP));
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
    $qrUrl = $baseUrl . '/cek.php?id=' . $detailP['id'];
?>
<div style="text-align:right;margin-bottom:var(--sp-3);">
    <a href="?tab=daftar_pesanan" class="btn-print me-2 no-print" style="text-decoration:none;"><i class="bi bi-list-ul"></i> Semua Pesanan</a>
</div>

<div class="section-card">
    <div class="section-title"><i class="bi bi-receipt"></i> <?= htmlspecialchars(isset($detailP['no_order'])?$detailP['no_order']:'-') ?></div>
    <div style="background:rgba(0,0,0,.35);border:1px solid rgba(212,175,55,.15);border-radius:var(--radius-sm);padding:var(--sp-3);">
        <div style="font-size:17px;font-weight:800;color:#fff;"><i class="bi bi-person-fill" style="color:var(--gold);"></i> <?= htmlspecialchars($detailP['nama_customer']) ?></div>
        <div style="font-size:12.5px;color:#a0a0a8;margin-top:6px;">
            <i class="bi bi-telephone"></i> <?= htmlspecialchars(isset($detailP['no_hp'])?$detailP['no_hp']:'-') ?>
            <?php if(!empty($detailP['jenis_kendaraan'])): ?> &bull; <i class="bi bi-car-front"></i> <?= htmlspecialchars($detailP['jenis_kendaraan']) ?><?php endif; ?>
            <?php if(!empty($detailP['tipe'])): ?> <?= htmlspecialchars($detailP['tipe']) ?><?php endif; ?>
            <?php if(!empty($detailP['no_polisi'])): ?> &bull; <i class="bi bi-card-text"></i> <?= htmlspecialchars($detailP['no_polisi']) ?><?php endif; ?>
        </div>
        <div style="font-size:11.5px;color:#8a8a92;margin-top:6px;"><i class="bi bi-clock"></i> <?= tgl($detailP['tanggal']) ?></div>

        <?php if (!empty($detailP['estimasi_selesai']) && $sp !== 'selesai'): 
            $est = strtotime($detailP['estimasi_selesai']);
            $diff = $est - time();
        ?>
        <div class="est-box">
            <i class="bi bi-hourglass-split"></i> <strong>Estimasi Selesai:</strong> <?= tgl($detailP['estimasi_selesai']) ?>
            <?php if ($diff > 0): ?>
                <br><small style="color:#8ec5ff;">(sisa <?= floor($diff/3600) ?> jam <?= floor(($diff%3600)/60) ?> menit)</small>
            <?php else: ?>
                <br><small style="color:#ff9e9e;">(terlambat <?= floor(abs($diff)/3600) ?> jam <?= floor((abs($diff)%3600)/60) ?> menit)</small>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="qr-box">
            <div style="font-size:11px;color:#a0a0a8;margin-bottom:8px;"><i class="bi bi-qr-code"></i> Scan untuk cek status pesanan</div>
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=140x140&data=<?= urlencode($qrUrl) ?>" alt="QR Code">
        </div>
    </div>
</div>

<div class="progress-flow no-print">
    <div class="step-flow done">
        <div class="step-icon"><i class="bi bi-check-lg"></i></div>
        <div class="step-label">Dibuat</div>
        <div class="step-sub">Pesanan diterima</div>
    </div>
    <div class="step-flow <?= $stepDikerjakan?'done':($sp==='antri'?'active':'') ?>">
        <div class="step-icon"><i class="bi <?= $stepDikerjakan?'bi-check-lg':'bi-play-fill' ?>"></i></div>
        <div class="step-label">Dikerjakan</div>
        <div class="step-sub"><?= $sp==='antri'?'Belum mulai':($stepDikerjakan?'Sedang proses':'') ?></div>
    </div>
    <div class="step-flow <?= $stepSelesai?'done':($sp==='proses'?'active':'') ?>">
        <div class="step-icon"><i class="bi <?= $stepSelesai?'bi-check-lg':'bi-hourglass-split' ?>"></i></div>
        <div class="step-label">Selesai</div>
        <div class="step-sub"><?= $stepSelesai?'Pekerjaan selesai':($sp==='proses'?'Tinggal finish':'') ?></div>
    </div>
    <div class="step-flow <?= $stepLunas?'done':($stepSelesai&&!$stepLunas?'active':'') ?>">
        <div class="step-icon"><i class="bi <?= $stepLunas?'bi-check-lg':'bi-cash-coin' ?>"></i></div>
        <div class="step-label">Lunas</div>
        <div class="step-sub"><?= $stepLunas?'Sudah dibayar':($stepSelesai?'Tinggal bayar':'') ?></div>
    </div>
</div>

<?php if ($sp==='antri'): ?>
<div class="action-box no-print">
    <h4><i class="bi bi-play-circle-fill"></i> Pesanan Siap Dikerjakan</h4>
    <p>Klik tombol di bawah kalau mekanik/kru sudah mulai mengerjakan pesanan ini.</p>
    <form method="POST" onsubmit="return confirmAction(event,'Mulai kerjakan pesanan ini?','Status akan berubah ke SEDANG DIKERJAKAN')">
        <input type="hidden" name="action" value="update_status_pesanan">
        <input type="hidden" name="id" value="<?= $detailP['id'] ?>">
        <input type="hidden" name="status_pekerjaan" value="proses">
        <button type="submit" class="btn-action-big blue"><i class="bi bi-play-fill"></i> MULAI KERJAKAN SEKARANG</button>
    </form>
</div>
<?php elseif ($sp==='proses'): ?>
<div class="action-box no-print">
    <h4><i class="bi bi-gear-fill"></i> Sedang Dikerjakan</h4>
    <p>Kalau pekerjaan sudah selesai, klik tombol hijau di bawah. Nanti akan ditanya: sudah dibayar atau belum.</p>
    <form method="POST" onsubmit="return konfirmasiSelesaiPesanan(event,'<?= addslashes($detailP['nama_customer']) ?>',<?= isset($detailP['total'])?$detailP['total']:0 ?>,'<?= $detailP['id'] ?>')">
        <input type="hidden" name="action" value="update_status_pesanan">
        <input type="hidden" name="id" value="<?= $detailP['id'] ?>">
        <input type="hidden" name="status_pekerjaan" value="selesai">
        <button type="submit" class="btn-action-big green"><i class="bi bi-check-circle-fill"></i> TANDAI SELESAI DIKERJAKAN</button>
    </form>
    <form method="POST" class="mt-2" onsubmit="return confirmAction(event,'Kembalikan ke Antrian?','Status akan kembali ke MENUNGGU DIKERJAKAN')">
        <input type="hidden" name="action" value="update_status_pesanan">
        <input type="hidden" name="id" value="<?= $detailP['id'] ?>">
        <input type="hidden" name="status_pekerjaan" value="antri">
        <button type="submit" class="btn-soft-gold w-100"><i class="bi bi-arrow-counterclockwise"></i> Kembalikan ke Antrian</button>
    </form>
</div>
<?php elseif ($sp==='selesai' && $sbyr!=='lunas'): ?>
<div class="action-box no-print" style="border-color:rgba(255,107,107,.5);background:linear-gradient(135deg,rgba(40,5,5,.7),rgba(20,0,0,.6));">
    <h4 style="color:#ff9090;"><i class="bi bi-cash-coin"></i> Pekerjaan Selesai — Menunggu Pembayaran</h4>
    <p>Klik tombol di bawah kalau customer sudah bayar. Pesanan akan ditandai LUNAS.</p>
    <form method="POST" onsubmit="return bayarConfirm(event,'<?= addslashes($detailP['nama_customer']) ?>',<?= isset($detailP['total'])?$detailP['total']:0 ?>)">
        <input type="hidden" name="action" value="bayar_pesanan">
        <input type="hidden" name="id" value="<?= $detailP['id'] ?>">
        <input type="hidden" name="redirect" value="detail_pesanan">
        <button type="submit" class="btn-action-big green"><i class="bi bi-cash-coin"></i> TANDAI LUNAS / SUDAH DIBAYAR</button>
    </form>
</div>
<?php elseif ($sbyr==='lunas'): ?>
<div class="action-box no-print" style="border-color:rgba(81,207,102,.5);background:linear-gradient(135deg,rgba(5,40,5,.7),rgba(0,20,0,.6));">
    <h4 style="color:#51cf66;"><i class="bi bi-check-circle-fill"></i> Pesanan Selesai &amp; LUNAS</h4>
    <p>Transaksi sudah selesai sepenuhnya. Cetak kwitansi atau kirim ke pelanggan via WhatsApp.</p>
    <button onclick='generateKwitansiPesanan(<?= jsonAttr($detailP) ?>)' class="btn-action-big"><i class="bi bi-receipt"></i> CETAK KWITANSI PDF</button>
    <form method="POST" class="mt-2" onsubmit="return confirmAction(event,'Batalkan status LUNAS?','Status bayar akan kembali ke BELUM BAYAR')">
        <input type="hidden" name="action" value="batal_lunas">
        <input type="hidden" name="id" value="<?= $detailP['id'] ?>">
        <button type="submit" class="btn-soft-gold w-100"><i class="bi bi-x-circle"></i> Batalkan LUNAS (Salah Tandai)</button>
    </form>
</div>
<?php endif; ?>

<div class="section-card">
    <div class="section-title"><i class="bi bi-list-check"></i> Rincian Pesanan</div>
    <?php if (!empty($detailP['items'])): ?>
    <div style="background:rgba(0,0,0,.35);border:1px solid rgba(81,207,102,.15);border-radius:var(--radius-sm);padding:var(--sp-3);margin-bottom:var(--sp-2);">
        <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:1.2px;color:#51cf66;margin-bottom:10px;"><i class="bi bi-box-seam"></i> Barang</div>
        <?php foreach ($detailP['items'] as $it): ?>
            <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px dashed rgba(255,255,255,.08);font-size:13px;gap:8px;">
                <span style="color:#d0d0d8;"><strong style="color:#fff;"><?= htmlspecialchars($it['nama']) ?></strong> x<?= $it['qty'] ?> @ <?= rp($it['harga']) ?></span>
                <strong style="color:var(--gold);"><?= rp($it['subtotal']) ?></strong>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($detailP['tambahan']) && is_array($detailP['tambahan'])): ?>
    <div class="tambahan-box">
        <div class="tambahan-title">
            <i class="bi bi-plus-circle-fill"></i> TAMBAHAN (<?= count($detailP['tambahan']) ?> item)
            <span style="margin-left:auto;font-weight:600;font-size:10px;color:#8a8a92;">Total: <?= rp(isset($detailP['subtotal_tambahan'])?$detailP['subtotal_tambahan']:0) ?></span>
        </div>
        <?php foreach ($detailP['tambahan'] as $t): ?>
            <div class="tambahan-row">
                <div style="flex:1;">
                    <strong><?= htmlspecialchars($t['nama']) ?></strong> x<?= $t['qty'] ?> @ <?= rp($t['harga']) ?>
                    <?php if (!empty($t['note'])): ?><br><small style="color:#8a8a92;"><i class="bi bi-chat-left-text"></i> <?= htmlspecialchars($t['note']) ?></small><?php endif; ?>
                    <br><small style="color:#666;font-size:10.5px;"><i class="bi bi-clock"></i> <?= tgl($t['tanggal']) ?></small>
                </div>
                <div style="text-align:right;white-space:nowrap;">
                    <strong style="color:#51cf66;"><?= rp($t['subtotal']) ?></strong>
                    <?php if ($sbyr !== 'lunas'): ?>
                        <form method="POST" style="display:inline;margin-left:6px;" onsubmit="return confirmAction(event,'Hapus tambahan ini?','Stok akan dikembalikan ke gudang')">
                            <input type="hidden" name="action" value="hapus_tambahan_pesanan">
                            <input type="hidden" name="id_pesanan" value="<?= $detailP['id'] ?>">
                            <input type="hidden" name="id_tambahan" value="<?= $t['id'] ?>">
                            <button type="submit" class="btn btn-sm" style="padding:3px 9px;font-size:11px;background:rgba(255,107,107,.15);color:#ff9090;border:1px solid rgba(255,107,107,.4);border-radius:6px;"><i class="bi bi-trash"></i></button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($detailP['servis']['jenis'])): ?>
    <div style="background:rgba(0,0,0,.35);border:1px solid rgba(212,175,55,.15);border-radius:var(--radius-sm);padding:var(--sp-3);margin-bottom:var(--sp-2);">
        <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:1.2px;color:var(--gold);margin-bottom:10px;"><i class="bi bi-wrench"></i> Jasa Servis</div>
        <div style="display:flex;justify-content:space-between;padding:7px 0;font-size:13px;gap:8px;color:#d0d0d8;"><span><?= htmlspecialchars($detailP['servis']['jenis']) ?></span><strong style="color:var(--gold);"><?= rp($detailP['servis']['biaya']) ?></strong></div>
        <?php if (!empty($detailP['servis']['mekanik_biaya'])): ?>
        <div style="display:flex;justify-content:space-between;padding:7px 0;border-top:1px dashed rgba(255,255,255,.08);font-size:12px;color:#ff9090;gap:8px;"><span><i class="bi bi-tools"></i> Mekanik: <?= htmlspecialchars(isset($detailP['servis']['mekanik_nama'])?$detailP['servis']['mekanik_nama']:'') ?> <?= !empty($detailP['servis']['mekanik_keterangan'])?'('.htmlspecialchars($detailP['servis']['mekanik_keterangan']).')':'' ?></span><strong><?= rp($detailP['servis']['mekanik_biaya']) ?></strong></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($detailP['doorsmeer']['paket'])): ?>
    <div style="background:rgba(0,0,0,.35);border:1px solid rgba(77,171,247,.15);border-radius:var(--radius-sm);padding:var(--sp-3);margin-bottom:var(--sp-2);">
        <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:1.2px;color:#4dabf7;margin-bottom:10px;"><i class="bi bi-droplet-half"></i> Cuci Mobil</div>
        <div style="display:flex;justify-content:space-between;padding:7px 0;font-size:13px;gap:8px;color:#d0d0d8;"><span><?= htmlspecialchars($detailP['doorsmeer']['paket']) ?></span><strong style="color:#4dabf7;"><?= rp($detailP['doorsmeer']['harga']) ?></strong></div>
    </div>
    <?php endif; ?>

    <?php if (!empty($detailP['diskon']) && $detailP['diskon']>0): ?>
    <div style="background:rgba(255,212,59,.06);border:1px solid rgba(255,212,59,.2);border-radius:var(--radius-sm);padding:var(--sp-3);margin-bottom:var(--sp-2);font-size:13px;display:flex;justify-content:space-between;gap:8px;color:#ffd43b;">
        <span><i class="bi bi-tag"></i> Diskon</span><strong>-<?= rp($detailP['diskon']) ?></strong>
    </div>
    <?php endif; ?>

    <?php if (!empty($detailP['note'])): ?>
    <div class="note-box">
        <div class="note-title"><i class="bi bi-sticky-fill"></i> Catatan</div>
        <div class="note-content"><?= nl2br(htmlspecialchars($detailP['note'])) ?></div>
    </div>
    <?php endif; ?>

    <div style="background:linear-gradient(135deg,rgba(30,25,0,.6),rgba(15,12,0,.55));border:2px solid rgba(212,175,55,.4);border-radius:var(--radius-md);padding:var(--sp-4);text-align:center;">
        <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:2px;">Total Pesanan</div>
        <div style="font-family:'Cinzel',serif;font-size:30px;font-weight:900;color:var(--gold);margin-top:6px;letter-spacing:.5px;"><?= rp(isset($detailP['total'])?$detailP['total']:0) ?></div>
    </div>
</div>

<?php if ($sbyr !== 'lunas'): ?>
<div class="section-card no-print">
    <div class="section-title"><i class="bi bi-plus-circle"></i> Tambah Barang ke Pesanan Ini</div>
    <form method="POST" onsubmit="return confirmAction(event,'Tambah barang ini ke pesanan?','Stok akan berkurang & total akan di-update')">
        <input type="hidden" name="action" value="tambah_barang_pesanan">
        <input type="hidden" name="id_pesanan" value="<?= $detailP['id'] ?>">
        <div class="row g-3">
            <div class="col-md-5">
                <label class="form-label">Cari Barang</label>
                <select name="id_barang" class="form-select searchable-select" required>
                    <option value="">-- Ketik cari --</option>
                    <?php foreach ($data['barang'] as $b): ?>
                        <option value="<?= $b['id'] ?>"><?= $b['merek'] ?> <?= $b['nama'] ?> (<?= $b['price_code'] ?>) - Stok: <?= $b['stok'] ?> - <?= rp($b['harga_jual']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><label class="form-label">Qty</label><input type="number" name="qty" class="form-control" min="1" value="1" required></div>
            <div class="col-md-5"><label class="form-label">Catatan (opsional)</label><input type="text" name="note_tambahan" class="form-control" placeholder="Cth: Minta tambah 1 botol oli"></div>
        </div>
        <button type="submit" class="btn-gold mt-3"><i class="bi bi-plus-circle"></i> TAMBAH KE PESANAN</button>
    </form>
</div>
<?php endif; ?>

<div class="section-card no-print">
    <div class="section-title"><i class="bi bi-hourglass-split"></i> Edit Estimasi Selesai</div>
    <form method="POST">
        <input type="hidden" name="action" value="edit_estimasi_pesanan">
        <input type="hidden" name="id" value="<?= $detailP['id'] ?>">
        <input type="datetime-local" name="estimasi_selesai" class="form-control" value="<?= !empty($detailP['estimasi_selesai']) ? date('Y-m-d\TH:i', strtotime($detailP['estimasi_selesai'])) : '' ?>">
        <button type="submit" class="btn-soft-gold mt-2"><i class="bi bi-save"></i> Update Estimasi</button>
    </form>
</div>

<div class="section-card no-print">
    <div class="section-title"><i class="bi bi-pencil-square"></i> Edit Catatan</div>
    <form method="POST">
        <input type="hidden" name="action" value="edit_note_pesanan">
        <input type="hidden" name="id" value="<?= $detailP['id'] ?>">
        <textarea name="note" class="form-control" rows="2" maxlength="500" placeholder="Kosongkan jika tidak ada catatan..."><?= htmlspecialchars(isset($detailP['note'])?$detailP['note']:'') ?></textarea>
        <button type="submit" class="btn-soft-gold mt-2"><i class="bi bi-save"></i> Update Catatan</button>
    </form>
</div>

<div class="section-card no-print">
    <div class="section-title"><i class="bi bi-three-dots"></i> Aksi Lain</div>
    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:8px;">
        <button onclick='generateKwitansiPesanan(<?= jsonAttr($detailP) ?>)' class="btn-soft-gold"><i class="bi bi-receipt"></i> Kwitansi PDF</button>
        <a href="<?= $waLink ?>" target="_blank" class="btn-soft-gold" style="text-decoration:none;background:rgba(37,211,102,.12);border-color:rgba(37,211,102,.5);color:#25D366;"><i class="bi bi-whatsapp"></i> WhatsApp</a>
        <a href="?tab=pesanan" class="btn-soft-gold" style="text-decoration:none;"><i class="bi bi-plus-circle"></i> Pesanan Baru</a>
        <form method="POST" onsubmit="return confirmDelete(event)">
            <input type="hidden" name="action" value="hapus_pesanan">
            <input type="hidden" name="id" value="<?= $detailP['id'] ?>">
            <button type="submit" class="btn-soft-gold" style="background:rgba(255,107,107,.12);border-color:rgba(255,107,107,.5);color:#ff9090;width:100%;"><i class="bi bi-trash"></i> Hapus Pesanan</button>
        </form>
    </div>
</div>
<?php elseif ($tab === 'detail_pesanan' && !$detailP): ?>
<div class="section-card">
    <div style="text-align:center;padding:40px 20px;color:var(--text-muted);">
        <i class="bi bi-exclamation-triangle" style="font-size:40px;display:block;margin-bottom:10px;color:var(--gold);"></i>
        Pesanan tidak ditemukan.
        <br><br>
        <a href="?tab=daftar_pesanan" class="btn-soft-gold" style="text-decoration:none;"><i class="bi bi-arrow-left"></i> Kembali ke Daftar</a>
    </div>
</div>
<?php endif; ?>

<?php if ($tab === 'daftar_pesanan'): ?>
<div class="section-card">
    <div class="section-title"><i class="bi bi-receipt"></i> Semua Pesanan <span class="count-pill"><?= count($pF) ?></span></div>
    <div class="period-selector">
        <a class="<?= $fp==='semua'?'active':'' ?>" href="?tab=daftar_pesanan&filter_pesanan=semua"><i class="bi bi-list-ul"></i> Semua</a>
        <a class="<?= $fp==='antri'?'active':'' ?>" href="?tab=daftar_pesanan&filter_pesanan=antri"><i class="bi bi-hourglass"></i> Antri</a>
        <a class="<?= $fp==='proses'?'active':'' ?>" href="?tab=daftar_pesanan&filter_pesanan=proses"><i class="bi bi-gear"></i> Dikerjakan</a>
        <a class="<?= $fp==='selesai'?'active':'' ?>" href="?tab=daftar_pesanan&filter_pesanan=selesai"><i class="bi bi-check-circle"></i> Selesai</a>
        <a class="<?= $fp==='lunas'?'active':'' ?>" href="?tab=daftar_pesanan&filter_pesanan=lunas"><i class="bi bi-cash-coin"></i> Lunas</a>
        <a class="<?= $fp==='utang'?'active':'' ?>" href="?tab=daftar_pesanan&filter_pesanan=utang"><i class="bi bi-clock-history"></i> Belum Bayar</a>
    </div>
    <?php if (empty($pF)): ?><p style="color:var(--text-muted);text-align:center;padding:30px 10px;">Belum ada pesanan</p>
    <?php else: foreach (array_reverse($pF) as $p):
        $sp=isset($p['status_pekerjaan'])?$p['status_pekerjaan']:'antri';
        $sbyr=isset($p['status_bayar'])?$p['status_bayar']:'belum_bayar';
        $waL='https://wa.me/'.preg_replace('/^0/', '62', isset($p['no_hp'])?$p['no_hp']:'').'?text='.urlencode(waMsg($p));
    ?>
        <div class="order-card" style="cursor:pointer;" onclick="location.href='?tab=detail_pesanan&id=<?= $p['id'] ?>'">
            <div class="oc-header">
                <div>
                    <div class="oc-no"><i class="bi bi-receipt"></i> <?= htmlspecialchars(isset($p['no_order'])?$p['no_order']:'-') ?></div>
                    <div class="oc-date"><i class="bi bi-clock"></i> <?= tgl(isset($p['tanggal'])?$p['tanggal']:date('Y-m-d H:i:s')) ?></div>
                </div>
                <div>
                    <?php if($sp==='antri'): ?><span class="oc-badge antri"><i class="bi bi-hourglass"></i> ANTRI</span>
                    <?php elseif($sp==='proses'): ?><span class="oc-badge proses"><i class="bi bi-gear"></i> DIKERJAKAN</span>
                    <?php else: ?><span class="oc-badge selesai"><i class="bi bi-check-circle"></i> SELESAI</span><?php endif; ?>
                    <?php if($sbyr==='lunas'): ?><span class="oc-badge lunas"><i class="bi bi-cash"></i> LUNAS</span>
                    <?php else: ?><span class="oc-badge utang"><i class="bi bi-exclamation"></i> BELUM BAYAR</span><?php endif; ?>
                </div>
            </div>
            <div class="oc-customer"><i class="bi bi-person-fill"></i> <?= htmlspecialchars(isset($p['nama_customer'])?$p['nama_customer']:'-') ?></div>
            <div class="oc-meta"><i class="bi bi-telephone"></i> <?= htmlspecialchars(isset($p['no_hp'])?$p['no_hp']:'-') ?><?php if (!empty($p['no_polisi'])): ?> &bull; <i class="bi bi-car-front"></i> <?= htmlspecialchars($p['no_polisi']) ?><?php endif; ?></div>
            <?php if (!empty($p['note'])): ?>
                <div class="note-mini"><i class="bi bi-sticky-fill"></i> <?= htmlspecialchars($p['note']) ?></div>
            <?php endif; ?>
            <div class="oc-items">
                <?php if (!empty($p['items'])): foreach ($p['items'] as $it): ?>
                    <div class="item-line"><span><i class="bi bi-box-seam" style="color:#51cf66;"></i> <?= htmlspecialchars($it['nama']) ?> x<?= $it['qty'] ?></span><strong><?= rp($it['subtotal']) ?></strong></div>
                <?php endforeach; endif; ?>
                <?php if (!empty($p['tambahan']) && is_array($p['tambahan'])): foreach ($p['tambahan'] as $it): ?>
                    <div class="item-line" style="color:#51cf66;"><span><i class="bi bi-plus-circle"></i> <?= htmlspecialchars($it['nama']) ?> x<?= $it['qty'] ?> (tambahan)</span><strong><?= rp($it['subtotal']) ?></strong></div>
                <?php endforeach; endif; ?>
                <?php if (!empty($p['servis']['jenis'])): ?>
                    <div class="item-line" style="color:#ffd43b;"><span><i class="bi bi-wrench"></i> <?= htmlspecialchars($p['servis']['jenis']) ?></span><strong><?= rp($p['servis']['biaya']) ?></strong></div>
                <?php endif; ?>
                <?php if (!empty($p['doorsmeer']['paket'])): ?>
                    <div class="item-line" style="color:#4dabf7;"><span><i class="bi bi-droplet-half"></i> <?= htmlspecialchars($p['doorsmeer']['paket']) ?></span><strong><?= rp($p['doorsmeer']['harga']) ?></strong></div>
                <?php endif; ?>
            </div>
            <div class="oc-total"><span>TOTAL</span><span><?= rp(isset($p['total'])?$p['total']:0) ?></span></div>
            <div class="oc-actions" onclick="event.stopPropagation();">
                <a href="?tab=detail_pesanan&id=<?= $p['id'] ?>" class="btn btn-warning"><i class="bi bi-eye"></i> Detail / Kelola</a>
                <button class="btn btn-primary" onclick='generateKwitansiPesanan(<?= jsonAttr($p) ?>)'><i class="bi bi-receipt"></i> Kwt</button>
                <a href="<?= $waL ?>" target="_blank" class="btn" style="background:rgba(37,211,102,.15);color:#25D366;border:1px solid rgba(37,211,102,.4);"><i class="bi bi-whatsapp"></i></a>
            </div>
        </div>
    <?php endforeach; endif; ?>
</div>
<?php endif; ?>

<?php if ($tab === 'antrian'): ?>
<div class="info-legend">
    <h6><i class="bi bi-info-circle-fill"></i> Antrian Pesanan</h6>
    <ul><li><i class="bi bi-dot"></i><span>Pesanan yang <strong>BELUM MULAI dikerjakan</strong>. Klik <strong>Detail / Kelola</strong> untuk mulai.</span></li></ul>
</div>
<div class="section-card">
    <div class="section-title"><i class="bi bi-hourglass-split"></i> Menunggu Dikerjakan <span class="count-pill"><?= count($pAntri) ?></span></div>
    <?php if (empty($pAntri)): ?><p style="color:var(--text-muted);text-align:center;padding:30px 10px;"><i class="bi bi-check-circle" style="font-size:32px;display:block;margin-bottom:8px;color:#51cf66;"></i>Semua pesanan sudah dikerjakan!</p>
    <?php else: foreach (array_reverse($pAntri) as $p): ?>
        <div class="order-card">
            <div class="oc-header">
                <div>
                    <div class="oc-no"><i class="bi bi-receipt"></i> <?= htmlspecialchars(isset($p['no_order'])?$p['no_order']:'-') ?></div>
                    <div class="oc-date"><i class="bi bi-clock"></i> <?= tgl(isset($p['tanggal'])?$p['tanggal']:date('Y-m-d H:i:s')) ?></div>
                </div>
                <span class="oc-badge antri"><i class="bi bi-hourglass"></i> ANTRI</span>
            </div>
            <div class="oc-customer"><i class="bi bi-person-fill"></i> <?= htmlspecialchars(isset($p['nama_customer'])?$p['nama_customer']:'-') ?></div>
            <div class="oc-meta"><i class="bi bi-telephone"></i> <?= htmlspecialchars(isset($p['no_hp'])?$p['no_hp']:'-') ?><?php if (!empty($p['no_polisi'])): ?> &bull; <i class="bi bi-car-front"></i> <?= htmlspecialchars($p['no_polisi']) ?><?php endif; ?></div>
            <?php if (!empty($p['note'])): ?><div class="note-mini"><i class="bi bi-sticky-fill"></i> <?= htmlspecialchars($p['note']) ?></div><?php endif; ?>
            <div class="oc-items">
                <?php foreach (safeA(isset($p['items'])?$p['items']:array()) as $it): ?>
                    <div class="item-line"><span><i class="bi bi-box-seam" style="color:#51cf66;"></i> <?= htmlspecialchars($it['nama']) ?> x<?= $it['qty'] ?></span><strong><?= rp($it['subtotal']) ?></strong></div>
                <?php endforeach; ?>
                <?php if (!empty($p['tambahan']) && is_array($p['tambahan'])): foreach ($p['tambahan'] as $it): ?>
                    <div class="item-line" style="color:#51cf66;"><span><i class="bi bi-plus-circle"></i> <?= htmlspecialchars($it['nama']) ?> x<?= $it['qty'] ?> (tambahan)</span><strong><?= rp($it['subtotal']) ?></strong></div>
                <?php endforeach; endif; ?>
                <?php if (!empty($p['servis']['jenis'])): ?><div class="item-line" style="color:#ffd43b;"><span><i class="bi bi-wrench"></i> <?= htmlspecialchars($p['servis']['jenis']) ?></span><strong><?= rp($p['servis']['biaya']) ?></strong></div><?php endif; ?>
                <?php if (!empty($p['doorsmeer']['paket'])): ?><div class="item-line" style="color:#4dabf7;"><span><i class="bi bi-droplet-half"></i> <?= htmlspecialchars($p['doorsmeer']['paket']) ?></span><strong><?= rp($p['doorsmeer']['harga']) ?></strong></div><?php endif; ?>
            </div>
            <div class="oc-total"><span>TOTAL</span><span><?= rp(isset($p['total'])?$p['total']:0) ?></span></div>
            <div class="oc-actions">
                <form method="POST" style="display:inline;" onsubmit="return confirmAction(event,'Mulai kerjakan pesanan ini?','Status akan berubah ke SEDANG DIKERJAKAN')">
                    <input type="hidden" name="action" value="update_status_pesanan">
                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                    <input type="hidden" name="status_pekerjaan" value="proses">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-play-fill"></i> Mulai Kerjakan</button>
                </form>
                <a href="?tab=detail_pesanan&id=<?= $p['id'] ?>" class="btn btn-warning"><i class="bi bi-eye"></i> Detail</a>
            </div>
        </div>
    <?php endforeach; endif; ?>
</div>
<?php endif; ?>

<?php if ($tab === 'proses'): ?>
<div class="section-card">
    <div class="section-title"><i class="bi bi-gear"></i> Sedang Dikerjakan <span class="count-pill"><?= count($pProses) ?></span></div>
    <?php if (empty($pProses)): ?><p style="color:var(--text-muted);text-align:center;padding:30px 10px;">Belum ada yang sedang dikerjakan</p>
    <?php else: foreach (array_reverse($pProses) as $p): ?>
        <div class="order-card">
            <div class="oc-header">
                <div>
                    <div class="oc-no"><i class="bi bi-receipt"></i> <?= htmlspecialchars(isset($p['no_order'])?$p['no_order']:'-') ?></div>
                    <div class="oc-date"><i class="bi bi-clock"></i> <?= tgl(isset($p['tanggal'])?$p['tanggal']:date('Y-m-d H:i:s')) ?></div>
                </div>
                <span class="oc-badge proses"><i class="bi bi-gear"></i> DIKERJAKAN</span>
            </div>
            <div class="oc-customer"><i class="bi bi-person-fill"></i> <?= htmlspecialchars(isset($p['nama_customer'])?$p['nama_customer']:'-') ?></div>
            <div class="oc-meta"><i class="bi bi-telephone"></i> <?= htmlspecialchars(isset($p['no_hp'])?$p['no_hp']:'-') ?></div>
            <?php if (!empty($p['note'])): ?><div class="note-mini"><i class="bi bi-sticky-fill"></i> <?= htmlspecialchars($p['note']) ?></div><?php endif; ?>
            <div class="oc-total"><span>TOTAL</span><span><?= rp(isset($p['total'])?$p['total']:0) ?></span></div>
            <div class="oc-actions">
                <form method="POST" style="display:inline;" onsubmit="return konfirmasiSelesaiPesanan(event,'<?= addslashes($p['nama_customer']) ?>',<?= isset($p['total'])?$p['total']:0 ?>,'<?= $p['id'] ?>')">
                    <input type="hidden" name="action" value="update_status_pesanan">
                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                    <input type="hidden" name="status_pekerjaan" value="selesai">
                    <button type="submit" class="btn btn-success"><i class="bi bi-check-lg"></i> Tandai Selesai</button>
                </form>
                <a href="?tab=detail_pesanan&id=<?= $p['id'] ?>" class="btn btn-warning"><i class="bi bi-eye"></i> Detail</a>
            </div>
        </div>
    <?php endforeach; endif; ?>
</div>
<?php endif; ?>

<?php if ($tab === 'selesai'): ?>
<div class="section-card">
    <div class="section-title"><i class="bi bi-check-circle"></i> Selesai Dikerjakan <span class="count-pill"><?= count($pSelesai) ?></span></div>
    <?php if (empty($pSelesai)): ?><p style="color:var(--text-muted);text-align:center;padding:30px 10px;">Belum ada yang selesai</p>
    <?php else: foreach (array_reverse($pSelesai) as $p):
        $sbyr=isset($p['status_bayar'])?$p['status_bayar']:'belum_bayar';
    ?>
        <div class="order-card">
            <div class="oc-header">
                <div>
                    <div class="oc-no"><i class="bi bi-receipt"></i> <?= htmlspecialchars(isset($p['no_order'])?$p['no_order']:'-') ?></div>
                    <div class="oc-date"><i class="bi bi-clock"></i> <?= tgl(isset($p['tanggal_selesai'])?$p['tanggal_selesai']:$p['tanggal']) ?></div>
                </div>
                <div>
                    <span class="oc-badge selesai"><i class="bi bi-check-circle"></i> SELESAI</span>
                    <?php if($sbyr==='lunas'): ?><span class="oc-badge lunas"><i class="bi bi-cash"></i> LUNAS</span>
                    <?php else: ?><span class="oc-badge utang"><i class="bi bi-exclamation"></i> BELUM BAYAR</span><?php endif; ?>
                </div>
            </div>
            <div class="oc-customer"><i class="bi bi-person-fill"></i> <?= htmlspecialchars(isset($p['nama_customer'])?$p['nama_customer']:'-') ?></div>
            <div class="oc-meta"><i class="bi bi-telephone"></i> <?= htmlspecialchars(isset($p['no_hp'])?$p['no_hp']:'-') ?></div>
            <?php if (!empty($p['note'])): ?><div class="note-mini"><i class="bi bi-sticky-fill"></i> <?= htmlspecialchars($p['note']) ?></div><?php endif; ?>
            <div class="oc-total"><span>TOTAL</span><span><?= rp(isset($p['total'])?$p['total']:0) ?></span></div>
            <div class="oc-actions">
                <?php if($sbyr!=='lunas'): ?>
                <form method="POST" style="display:inline;" onsubmit="return bayarConfirm(event,'<?= addslashes($p['nama_customer']) ?>',<?= isset($p['total'])?$p['total']:0 ?>)">
                    <input type="hidden" name="action" value="bayar_pesanan">
                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                    <input type="hidden" name="redirect" value="selesai">
                    <button type="submit" class="btn btn-success"><i class="bi bi-cash-coin"></i> Tandai Lunas</button>
                </form>
                <?php endif; ?>
                <a href="?tab=detail_pesanan&id=<?= $p['id'] ?>" class="btn btn-warning"><i class="bi bi-eye"></i> Detail</a>
                <button class="btn btn-primary" onclick='generateKwitansiPesanan(<?= jsonAttr($p) ?>)'><i class="bi bi-receipt"></i> Kwt</button>
            </div>
        </div>
    <?php endforeach; endif; ?>
</div>
<?php endif; ?>

<?php if ($tab === 'utang'): ?>
<div class="utang-total">
    <i class="bi bi-cash-stack" style="font-size:28px;"></i>
    <p style="margin:5px 0 0 0;font-size:12px;opacity:.9;letter-spacing:1.5px;">TOTAL BELUM DIBAYAR</p>
    <h2><?= rp($totU) ?></h2>
    <p style="margin:0;font-size:12px;opacity:.9;"><?= $totUC ?> pesanan belum lunas</p>
</div>
<div class="section-card">
    <div class="section-title"><i class="bi bi-clock-history"></i> Daftar Belum Bayar <span class="count-pill"><?= count($pUtang) ?></span></div>
    <?php if (empty($pUtang)): ?><p style="color:#51cf66;text-align:center;padding:30px 10px;"><i class="bi bi-check-circle" style="font-size:32px;display:block;margin-bottom:8px;"></i>Semua pesanan sudah lunas!</p>
    <?php else: foreach (array_reverse($pUtang) as $p):
        $sp=isset($p['status_pekerjaan'])?$p['status_pekerjaan']:'antri';
        $waL='https://wa.me/'.preg_replace('/^0/', '62', isset($p['no_hp'])?$p['no_hp']:'').'?text='.urlencode(waMsg($p));
    ?>
        <div class="order-card">
            <div class="oc-header">
                <div>
                    <div class="oc-no"><i class="bi bi-receipt"></i> <?= htmlspecialchars(isset($p['no_order'])?$p['no_order']:'-') ?></div>
                    <div class="oc-date"><i class="bi bi-clock"></i> <?= tgl($p['tanggal']) ?></div>
                </div>
                <div>
                    <?php if($sp==='antri'): ?><span class="oc-badge antri">ANTRI</span>
                    <?php elseif($sp==='proses'): ?><span class="oc-badge proses">DIKERJAKAN</span>
                    <?php else: ?><span class="oc-badge selesai">SELESAI</span><?php endif; ?>
                    <span class="oc-badge utang"><i class="bi bi-exclamation"></i> BELUM BAYAR</span>
                </div>
            </div>
            <div class="oc-customer"><i class="bi bi-person-fill"></i> <?= htmlspecialchars($p['nama_customer']) ?></div>
            <div class="oc-meta"><i class="bi bi-telephone"></i> <?= htmlspecialchars(isset($p['no_hp'])?$p['no_hp']:'-') ?></div>
            <?php if (!empty($p['note'])): ?><div class="note-mini"><i class="bi bi-sticky-fill"></i> <?= htmlspecialchars($p['note']) ?></div><?php endif; ?>
            <div class="oc-total" style="color:#ff9090;"><span>BELUM DIBAYAR</span><span><?= rp(isset($p['total'])?$p['total']:0) ?></span></div>
            <div class="oc-actions">
                <form method="POST" style="display:inline;" onsubmit="return bayarConfirm(event,'<?= addslashes($p['nama_customer']) ?>',<?= isset($p['total'])?$p['total']:0 ?>)">
                    <input type="hidden" name="action" value="bayar_pesanan">
                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                    <input type="hidden" name="redirect" value="utang">
                    <button type="submit" class="btn btn-success"><i class="bi bi-cash-coin"></i> Tandai Lunas</button>
                </form>
                <a href="?tab=detail_pesanan&id=<?= $p['id'] ?>" class="btn btn-warning"><i class="bi bi-eye"></i> Detail</a>
                <a href="<?= $waL ?>" target="_blank" class="btn" style="background:rgba(37,211,102,.15);color:#25D366;border:1px solid rgba(37,211,102,.4);"><i class="bi bi-whatsapp"></i></a>
            </div>
        </div>
    <?php endforeach; endif; ?>
</div>
<?php endif; ?>

<?php if ($tab === 'pelanggan'): ?>
<div class="table-card">
    <h5 class="table-title"><i class="bi bi-people"></i> Data Pelanggan (<?= count($cust) ?>)</h5>
    <div class="table-responsive"><table class="table">
    <thead><tr><th>Nama</th><th>No HP</th><th>Servis</th><th>Cuci</th><th>Pesanan</th><th>Terakhir</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($cust as $c):
        $waPel = 'https://wa.me/'.preg_replace('/^0/', '62', $c['no_hp']).'?text='.urlencode("Halo ".$c['nama']." 🙏\n\nTerima kasih telah menjadi pelanggan setia ".APP_NAME.". Ada yang bisa kami bantu?");
    ?>
        <tr>
            <td><strong><?= $c['nama'] ?></strong></td><td><?= $c['no_hp'] ?></td>
            <td><span class="badge" style="background:rgba(255,212,59,.15);color:#ffd43b;border:1px solid rgba(255,212,59,.3);"><?= $c['servis'] ?>x</span></td>
            <td><span class="badge" style="background:rgba(77,171,247,.15);color:#4dabf7;border:1px solid rgba(77,171,247,.3);"><?= $c['cuci'] ?>x</span></td>
            <td><span class="badge" style="background:rgba(81,207,102,.15);color:#51cf66;border:1px solid rgba(81,207,102,.3);"><?= $c['pesanan'] ?>x</span></td>
            <td><small><?= ago($c['last']) ?></small></td>
            <td><a href="<?= $waPel ?>" target="_blank" class="btn btn-sm" style="background:rgba(37,211,102,.15);color:#25D366;border:1px solid rgba(37,211,102,.4);border-radius:6px;padding:5px 10px;"><i class="bi bi-whatsapp"></i></a></td>
        </tr>
    <?php endforeach; ?>
    <?php if (empty($cust)): ?><tr><td colspan="7" class="text-center py-3">Belum ada</td></tr><?php endif; ?>
    </tbody></table></div>
</div>
<?php endif; ?>

<?php if ($tab === 'tambah'): ?>
<div class="form-card">
    <h5 class="form-title"><i class="bi bi-plus-circle"></i> Tambah Barang Baru</h5>
    <form method="POST" enctype="multipart/form-data" onsubmit="return confirmSave(event)">
        <input type="hidden" name="action" value="tambah_barang">
        <div class="mb-3"><label class="form-label">Upload Foto</label><input type="file" name="foto" class="form-control" accept="image/*"></div>
        <div class="mb-3"><label class="form-label">Atau URL Foto</label><input type="url" name="foto_url" class="form-control" placeholder="https://..."></div>
        <div class="mb-3"><label class="form-label">Kode Harga</label><input type="text" name="price_code" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Merek</label><input type="text" name="merek" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Nama Barang</label><input type="text" name="nama" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Kategori</label><input type="text" name="kategori" class="form-control" required></div>
        <div class="row g-3"><div class="col-6"><label class="form-label">Stok</label><input type="number" name="stok" class="form-control" value="0" required></div><div class="col-6"><label class="form-label">Stok Min</label><input type="number" name="stok_minimum" class="form-control" value="5" required></div></div>
        <div class="row g-3" style="margin-top:var(--sp-3);"><div class="col-6"><label class="form-label">Modal</label><input type="number" name="modal" class="form-control" required></div><div class="col-6"><label class="form-label">Jual</label><input type="number" name="harga_jual" class="form-control" required></div></div>
        <div class="mb-3" style="margin-top:var(--sp-3);"><label class="form-label">Asal</label><input type="text" name="asal" class="form-control" required></div>
        <button type="submit" class="btn-gold"><i class="bi bi-check-circle"></i> SIMPAN</button>
    </form>
</div>
<?php endif; ?>

<?php if ($tab === 'barang'): ?>
<div class="table-card">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h5 class="table-title mb-0"><i class="bi bi-box-seam"></i> Daftar Barang (<?= count($bT) ?>)</h5>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <form method="GET" class="d-flex gap-2"><input type="hidden" name="tab" value="barang"><input type="hidden" name="view" value="<?= $view ?>"><div class="search-box-barang"><i class="bi bi-search"></i><input type="text" name="search" class="form-control" placeholder="Cari..." value="<?= htmlspecialchars($sb) ?>"></div><button type="submit" class="btn btn-sm" style="background:var(--gold-grad-2);color:#000;border:none;border-radius:8px;padding:9px 14px;"><i class="bi bi-search"></i></button></form>
            <a href="?tab=barang&view=grid" class="btn btn-sm" style="background:rgba(212,175,55,.15);color:var(--gold);border:1px solid rgba(212,175,55,.4);border-radius:6px;"><i class="bi bi-grid"></i></a>
            <a href="?tab=barang&view=list" class="btn btn-sm" style="background:rgba(212,175,55,.15);color:var(--gold);border:1px solid rgba(212,175,55,.4);border-radius:6px;"><i class="bi bi-list"></i></a>
        </div>
    </div>
    <?php if (empty($bT)): ?><p class="text-center" style="color:var(--text-muted);padding:20px 0;">Barang tidak ditemukan</p>
    <?php elseif ($view === 'grid'): ?>
        <div class="grid-view">
        <?php foreach ($bT as $b): ?>
            <div class="grid-item">
                <?php if (!empty($b['foto'])): ?><img src="<?= htmlspecialchars($b['foto']) ?>" alt="Foto" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"><div class="no-photo" style="display:none;"><i class="bi bi-box-seam"></i></div><?php else: ?><div class="no-photo"><i class="bi bi-box-seam"></i></div><?php endif; ?>
                <div class="grid-name"><?= $b['merek'] ?> <?= $b['nama'] ?></div>
                <div class="grid-detail"><i class="bi bi-tag me-1"></i><?= $b['price_code'] ?></div>
                <div class="grid-detail"><i class="bi bi-box-seam me-1"></i>Stok: <strong><?= $b['stok'] ?></strong></div>
                <div class="grid-detail"><i class="bi bi-cash me-1"></i>Jual: <strong><?= rp($b['harga_jual']) ?></strong></div>
                <div class="mt-2"><?= $b['stok'] <= $b['stok_minimum'] ? '<span class="badge" style="background:rgba(255,107,107,.15);color:#ff9090;border:1px solid rgba(255,107,107,.4);">Menipis</span>' : '<span class="badge" style="background:rgba(81,207,102,.15);color:#51cf66;border:1px solid rgba(81,207,102,.35);">Tersedia</span>' ?></div>
                <div class="mt-2 d-flex gap-1 justify-content-center">
                    <button class="btn btn-sm" style="background:rgba(77,171,247,.15);color:#4dabf7;border:1px solid rgba(77,171,247,.4);border-radius:6px;padding:5px 10px;" onclick='openEditBarangModal(<?= jsonAttr($b) ?>)'><i class="bi bi-pencil"></i></button>
                    <form method="POST" onsubmit="return confirmDelete(event)" style="display:inline;"><input type="hidden" name="action" value="hapus_barang"><input type="hidden" name="id" value="<?= $b['id'] ?>"><button class="btn btn-sm" style="background:rgba(255,107,107,.15);color:#ff9090;border:1px solid rgba(255,107,107,.4);border-radius:6px;padding:5px 10px;"><i class="bi bi-trash"></i></button></form>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="table-responsive"><table class="table">
        <thead><tr><th>Foto</th><th>Kode</th><th>Nama</th><th>Kategori</th><th>Stok</th><th>Modal</th><th>Jual</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php foreach ($bT as $b): ?>
            <tr>
                <td><?= !empty($b['foto']) ? '<img src="'.htmlspecialchars($b['foto']).'" width="40" height="40" style="border-radius:50%;object-fit:cover;border:1.5px solid rgba(212,175,55,.4);">' : '<i class="bi bi-box-seam" style="font-size:28px;color:rgba(212,175,55,.4);"></i>' ?></td>
                <td><strong><?= $b['price_code'] ?></strong></td>
                <td><strong><?= $b['merek'] ?> <?= $b['nama'] ?></strong></td>
                <td><span class="badge" style="background:rgba(212,175,55,.12);color:var(--gold);border:1px solid rgba(212,175,55,.3);"><?= $b['kategori'] ?></span></td>
                <td><strong><?= $b['stok'] ?></strong></td>
                <td><?= rp($b['modal']) ?></td>
                <td><strong style="color:var(--gold);"><?= rp($b['harga_jual']) ?></strong></td>
                <td><?= $b['stok'] <= $b['stok_minimum'] ? '<span class="badge" style="background:rgba(255,107,107,.15);color:#ff9090;border:1px solid rgba(255,107,107,.4);">Menipis</span>' : '<span class="badge" style="background:rgba(81,207,102,.15);color:#51cf66;border:1px solid rgba(81,207,102,.35);">Tersedia</span>' ?></td>
                <td><button class="btn btn-sm" style="background:rgba(77,171,247,.15);color:#4dabf7;border:1px solid rgba(77,171,247,.4);border-radius:6px;padding:5px 10px;" onclick='openEditBarangModal(<?= jsonAttr($b) ?>)'><i class="bi bi-pencil"></i></button><form method="POST" onsubmit="return confirmDelete(event)" style="display:inline;"><input type="hidden" name="action" value="hapus_barang"><input type="hidden" name="id" value="<?= $b['id'] ?>"><button class="btn btn-sm" style="background:rgba(255,107,107,.15);color:#ff9090;border:1px solid rgba(255,107,107,.4);border-radius:6px;padding:5px 10px;margin-left:4px;"><i class="bi bi-trash"></i></button></form></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($tab === 'masuk'): ?>
<div class="form-card">
    <h5 class="form-title"><i class="bi bi-arrow-down-circle"></i> Barang Masuk</h5>
    <form method="POST" onsubmit="return confirmSave(event)">
        <input type="hidden" name="action" value="tambah_stock_masuk">
        <div class="mb-3"><label class="form-label">Cari Barang</label><select name="id_barang" class="form-select searchable-select" required><option value="">-- Ketik cari --</option><?php foreach ($data['barang'] as $b): ?><option value="<?= $b['id'] ?>"><?= $b['merek'] ?> <?= $b['nama'] ?> (<?= $b['price_code'] ?>) - Stok: <?= $b['stok'] ?></option><?php endforeach; ?></select></div>
        <div class="mb-3"><label class="form-label">Jumlah</label><input type="number" name="jumlah" class="form-control" min="1" required></div>
        <div class="mb-3"><label class="form-label">Supplier</label><input type="text" name="supplier" class="form-control" required></div>
        <div class="mb-3"><label class="form-label"><i class="bi bi-calendar-event"></i> Tanggal &amp; Jam Masuk</label><input type="datetime-local" name="tanggal" class="form-control" value="<?= $defaultTglLocal ?>" required></div>
        <button type="submit" class="btn-gold"><i class="bi bi-plus-circle"></i> TAMBAH</button>
    </form>
</div>
<div class="table-card">
    <h5 class="table-title"><i class="bi bi-clock-history"></i> Riwayat Barang Masuk</h5>
    <div class="table-responsive"><table class="table">
    <thead><tr><th>Tanggal &amp; Jam</th><th>Nama Barang</th><th>Supplier</th><th>Jumlah</th></tr></thead>
    <tbody>
    <?php foreach (array_slice(array_reverse($data['stock_masuk']), 0, 30) as $x): ?>
        <tr><td><strong><?= tgl($x['tanggal']) ?></strong></td><td><strong><?= $x['nama_barang'] ?></strong></td><td><?= $x['supplier'] ?></td><td><strong style="color:#51cf66;">+<?= $x['jumlah'] ?></strong></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>
<?php endif; ?>

<?php if ($tab === 'keluar'): ?>
<div class="form-card">
    <h5 class="form-title"><i class="bi bi-arrow-up-circle"></i> Barang Keluar</h5>
    <form method="POST" onsubmit="return confirmSave(event)">
        <input type="hidden" name="action" value="tambah_stock_keluar">
        <div class="mb-3"><label class="form-label">Cari Barang</label><select name="id_barang" class="form-select searchable-select" required><option value="">-- Ketik cari --</option><?php foreach ($data['barang'] as $b): ?><option value="<?= $b['id'] ?>"><?= $b['merek'] ?> <?= $b['nama'] ?> (<?= $b['price_code'] ?>) - Stok: <?= $b['stok'] ?></option><?php endforeach; ?></select></div>
        <div class="mb-3"><label class="form-label">Jumlah</label><input type="number" name="jumlah" class="form-control" min="1" required></div>
        <div class="mb-3"><label class="form-label">Keterangan</label><input type="text" name="keterangan" class="form-control" placeholder="Dipakai servis, dijual, dll"></div>
        <div class="mb-3"><label class="form-label"><i class="bi bi-calendar-event"></i> Tanggal &amp; Jam Keluar</label><input type="datetime-local" name="tanggal" class="form-control" value="<?= $defaultTglLocal ?>" required></div>
        <button type="submit" class="btn-gold"><i class="bi bi-dash-circle"></i> KURANGI</button>
    </form>
</div>
<div class="table-card">
    <h5 class="table-title"><i class="bi bi-clock-history"></i> Riwayat Barang Keluar</h5>
    <div class="table-responsive"><table class="table">
    <thead><tr><th>Tanggal &amp; Jam</th><th>Nama Barang</th><th>Keterangan</th><th>Jumlah</th></tr></thead>
    <tbody>
    <?php foreach (array_slice(array_reverse($data['stock_keluar']), 0, 30) as $x): ?>
        <tr><td><strong><?= tgl($x['tanggal']) ?></strong></td><td><strong><?= $x['nama_barang'] ?></strong></td><td><?= $x['keterangan'] ? $x['keterangan'] : '-' ?></td><td><strong style="color:#ff9090;">-<?= $x['jumlah'] ?></strong></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>
<?php endif; ?>

<?php if ($tab === 'pengeluaran'): ?>
<div class="form-card">
    <h5 class="form-title"><i class="bi bi-cash-coin"></i> Catat Pengeluaran</h5>
    <form method="POST" enctype="multipart/form-data" onsubmit="return confirmSave(event)">
        <input type="hidden" name="action" value="tambah_pengeluaran">
        <div class="mb-3"><label class="form-label">Untuk Apa?</label><input type="text" name="keterangan" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Jumlah</label><input type="number" name="jumlah" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Tanggal</label><input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
        <div class="mb-3"><label class="form-label">Foto Bukti</label><input type="file" name="foto_bukti" class="form-control" accept="image/*"></div>
        <button type="submit" class="btn-gold"><i class="bi bi-save"></i> CATAT</button>
    </form>
</div>
<div class="table-card">
    <h5 class="table-title"><i class="bi bi-clock-history"></i> Riwayat Pengeluaran</h5>
    <div class="table-responsive"><table class="table">
    <thead><tr><th>Tanggal</th><th>Keterangan</th><th>Jumlah</th><th>Bukti</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php foreach (array_reverse($data['pengeluaran']) as $g): ?>
        <tr>
            <td><?= tglS($g['tanggal']) ?></td><td><?= $g['keterangan'] ?></td>
            <td><strong style="color:#ff9090;">-<?= rp($g['jumlah']) ?></strong></td>
            <td><?php if (!empty($g['foto_bukti']) && file_exists($g['foto_bukti'])): ?><img src="<?= htmlspecialchars($g['foto_bukti']) ?>" class="bukti-thumb" onclick="showFoto('<?= htmlspecialchars($g['foto_bukti']) ?>')"><?php else: ?><span style="color:#666;">-</span><?php endif; ?></td>
            <td><form method="POST" onsubmit="return confirmDelete(event)"><input type="hidden" name="action" value="hapus_pengeluaran"><input type="hidden" name="id" value="<?= $g['id'] ?>"><button class="btn btn-sm" style="background:rgba(255,107,107,.15);color:#ff9090;border:1px solid rgba(255,107,107,.4);border-radius:6px;padding:5px 10px;"><i class="bi bi-trash"></i></button></form></td>
        </tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>
<?php endif; ?>

<?php if ($tab === 'stokmenipis'): ?>
<div class="warn-banner danger"><i class="bi bi-exclamation-triangle-fill w-icon"></i><div><div>PERINGATAN STOK MENIPIS</div><small style="opacity:.9;font-weight:400;"><?= count($stokM) ?> barang perlu direstock</small></div></div>
<div class="table-card">
    <h5 class="table-title"><i class="bi bi-exclamation-triangle"></i> Daftar Stok Menipis</h5>
    <div class="table-responsive"><table class="table">
    <thead><tr><th>Barang</th><th>Kategori</th><th>Stok</th><th>Min</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($stokM as $b): ?>
        <tr><td><strong><?= $b['merek'] ?> <?= $b['nama'] ?></strong></td><td><?= $b['kategori'] ?></td><td><strong style="color:#ff9090;"><?= $b['stok'] ?></strong></td><td><?= $b['stok_minimum'] ?></td><td><span class="badge" style="background:rgba(255,107,107,.15);color:#ff9090;border:1px solid rgba(255,107,107,.4);">SEGERA RESTOCK!</span></td></tr>
    <?php endforeach; ?>
    <?php if (empty($stokM)): ?><tr><td colspan="5" class="text-center py-3" style="color:#51cf66;"><i class="bi bi-check-circle"></i> Semua stok aman</td></tr><?php endif; ?>
    </tbody></table></div>
</div>
<?php endif; ?>

<?php if ($tab === 'laporan'): ?>
<div class="period-selector">
    <a class="<?= $lp==='hari_ini'?'active':'' ?>" href="?tab=laporan&laporan=hari_ini"><i class="bi bi-calendar-day"></i> Hari Ini</a>
    <a class="<?= $lp==='kemarin'?'active':'' ?>" href="?tab=laporan&laporan=kemarin"><i class="bi bi-calendar-minus"></i> Kemarin</a>
    <a class="<?= $lp==='minggu_ini'?'active':'' ?>" href="?tab=laporan&laporan=minggu_ini"><i class="bi bi-calendar-week"></i> Minggu</a>
    <a class="<?= $lp==='bulan_ini'?'active':'' ?>" href="?tab=laporan&laporan=bulan_ini"><i class="bi bi-calendar-month"></i> Bulan</a>
    <a class="<?= $lp==='custom'?'active':'' ?>" href="#" onclick="showCustomDate(event)"><i class="bi bi-calendar-range"></i> Custom</a>
</div>
<div class="form-card" id="customDateForm" style="display:<?= $lp==='custom'?'block':'none' ?>;">
    <h5 class="form-title"><i class="bi bi-calendar-range"></i> Pilih Rentang</h5>
    <form method="GET">
        <input type="hidden" name="tab" value="laporan"><input type="hidden" name="laporan" value="custom">
        <div class="row g-3">
            <div class="col-md-5"><label class="form-label">Dari</label><input type="date" name="tanggal_mulai" class="form-control" value="<?= $ltm ?>" required></div>
            <div class="col-md-5"><label class="form-label">Sampai</label><input type="date" name="tanggal_akhir" class="form-control" value="<?= $lta ?>" required></div>
            <div class="col-md-2 d-flex align-items-end"><button type="submit" class="btn-gold w-100">TAMPILKAN</button></div>
        </div>
    </form>
</div>
<div style="text-align:right;margin-bottom:var(--sp-3);">
    <button class="btn-print" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
</div>
<div class="laporan-profesional">
    <div class="laporan-header-pro">
        <img src="<?= LOGO_URL ?>" class="logo-img" alt="Logo">
        <h1><?= APP_NAME ?></h1>
        <div class="tagline">Bengkel &amp; Doorsmeer</div>
        <div class="periode-box"><i class="bi bi-calendar-check"></i>
            <?php if ($lp==='hari_ini'): ?><?= tglL($today) ?>
            <?php elseif ($lp==='kemarin'): ?><?= tglL($yd) ?>
            <?php elseif ($lp==='minggu_ini'): ?>Minggu <?= tglS($ws) ?> s/d <?= tglS($today) ?>
            <?php elseif ($lp==='bulan_ini'): ?><?= date('F Y', strtotime($ms)) ?>
            <?php else: ?><?= tglS($ltm) ?> s/d <?= tglS($lta) ?><?php endif; ?>
        </div>
    </div>
    <div class="summary-section">
        <div class="summary-title"><i class="bi bi-bar-chart-fill"></i> RINGKASAN KEUANGAN</div>
        <table class="summary-table">
            <tr><td class="label"><i class="bi bi-wrench"></i>Pendapatan Servis</td><td class="value"><?= rp($lOS) ?></td></tr>
            <tr><td class="label"><i class="bi bi-droplet-half"></i>Pendapatan Cuci</td><td class="value"><?= rp($lOC) ?></td></tr>
            <tr><td class="label"><i class="bi bi-cart-check"></i>Penjualan Barang</td><td class="value"><?= rp($lOP) ?></td></tr>
            <tr><td class="label"><i class="bi bi-wallet2"></i>Total Laba Kotor</td><td class="value"><?= rp($lKotor) ?></td></tr>
            <tr><td class="label"><i class="bi bi-cash-coin"></i>Total Pengeluaran</td><td class="value" style="color:#c92a2a!important;">-<?= rp($lTG) ?></td></tr>
            <tr class="highlight-row"><td class="label"><i class="bi bi-trophy-fill"></i>LABA BERSIH</td><td class="value"><?= rp($lBersih) ?></td></tr>
        </table>
    </div>
    <div class="table-section">
        <div class="table-section-title"><i class="bi bi-cart-check"></i> DETAIL PESANAN (<?= count($lP) ?>)</div>
        <div class="table-responsive"><table class="table-pro">
        <thead><tr><th>No</th><th>Order</th><th>Tanggal</th><th>Pelanggan</th><th>Rincian</th><th>Catatan</th><th class="text-right">Total</th><th class="text-center">Status</th></tr></thead>
        <tbody>
        <?php if (empty($lP)): ?><tr><td colspan="8" style="text-align:center;padding:20px;color:#999;">Tidak ada pesanan</td></tr>
        <?php else: $n=1; foreach (array_reverse($lP) as $p):
            $sp=isset($p['status_pekerjaan'])?$p['status_pekerjaan']:'antri';
            $sbyr=isset($p['status_bayar'])?$p['status_bayar']:'belum_bayar';
        ?>
            <tr>
                <td><?= $n++ ?></td>
                <td><span class="kwitansi-no"><?= isset($p['no_order'])?$p['no_order']:'-' ?></span></td>
                <td><?= tglS($p['tanggal']) ?></td>
                <td><strong><?= $p['nama_customer'] ?></strong><br><small><?= isset($p['no_hp'])?$p['no_hp']:'' ?></small></td>
                <td>
                <?php foreach (safeA(isset($p['items'])?$p['items']:array()) as $it): ?>
                    <div>- <?= $it['nama'] ?> x<?= $it['qty'] ?> = <?= rp($it['subtotal']) ?></div>
                <?php endforeach; ?>
                <?php if (!empty($p['tambahan']) && is_array($p['tambahan'])): foreach ($p['tambahan'] as $it): ?>
                    <div style="color:#2e7d32;">- ➕ <?= $it['nama'] ?> x<?= $it['qty'] ?> = <?= rp($it['subtotal']) ?></div>
                <?php endforeach; endif; ?>
                <?php if (!empty($p['servis']['jenis'])): ?><div>- Servis: <?= $p['servis']['jenis'] ?> = <?= rp($p['servis']['biaya']) ?></div><?php endif; ?>
                <?php if (!empty($p['doorsmeer']['paket'])): ?><div>- Cuci: <?= $p['doorsmeer']['paket'] ?> = <?= rp($p['doorsmeer']['harga']) ?></div><?php endif; ?>
                </td>
                <td><small><?= !empty($p['note']) ? htmlspecialchars($p['note']) : '-' ?></small></td>
                <td class="text-right"><strong><?= rp(isset($p['total'])?$p['total']:0) ?></strong></td>
                <td class="text-center"><?= statusLabel($sp) ?><br><?= ($sbyr==='lunas') ? '<span class="badge-lunas">LUNAS</span>' : '<span class="badge-utang">UTANG</span>' ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
        <tfoot><tr><td colspan="6" class="text-right">TOTAL BARANG</td><td class="text-right"><?= rp($lOP) ?></td><td></td></tr></tfoot>
        </table></div>
    </div>
    <div class="table-section">
        <div class="table-section-title"><i class="bi bi-cash-coin"></i> PENGELUARAN (<?= count($lG) ?>)</div>
        <div class="table-responsive"><table class="table-pro">
        <thead><tr><th>No</th><th>Tanggal</th><th>Keterangan</th><th class="text-center">Bukti</th><th class="text-right">Jumlah</th></tr></thead>
        <tbody>
        <?php if (empty($lG)): ?><tr><td colspan="5" style="text-align:center;padding:20px;color:#999;">Tidak ada</td></tr>
        <?php else: $n=1; foreach (array_reverse($lG) as $g): ?>
            <tr>
                <td><?= $n++ ?></td><td><?= tglS($g['tanggal']) ?></td><td><strong><?= $g['keterangan'] ?></strong></td>
                <td class="text-center"><?php if (!empty($g['foto_bukti']) && file_exists($g['foto_bukti'])): ?><img src="<?= htmlspecialchars($g['foto_bukti']) ?>" class="bukti-thumb-pro" onclick="showFoto('<?= htmlspecialchars($g['foto_bukti']) ?>')"><?php else: ?>-<?php endif; ?></td>
                <td class="text-right"><strong style="color:#c92a2a!important;">-<?= rp($g['jumlah']) ?></strong></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
        <tfoot><tr><td colspan="4" class="text-right">TOTAL</td><td class="text-right">-<?= rp($lTG) ?></td></tr></tfoot>
        </table></div>
    </div>
    <div class="ttd-section">
        <div class="ttd-box"><div class="ttd-label">Mengetahui,</div><div class="ttd-name">Pemilik</div><div class="ttd-title"><?= APP_NAME ?></div></div>
        <div class="ttd-box"><div class="ttd-label">Dibuat oleh,</div><div class="ttd-name">Admin</div><div class="ttd-title">Operator</div></div>
    </div>
    <div class="laporan-footer-note"><i class="bi bi-shield-check"></i> Dicetak <?= date('d/m/Y H:i:s') ?> WIB</div>
</div>
<?php endif; ?>

<?php if ($tab === 'laporan_stok'): ?>
<div class="period-selector">
    <a class="<?= $sp==='hari_ini'?'active':'' ?>" href="?tab=laporan_stok&stok_period=hari_ini"><i class="bi bi-calendar-day"></i> Hari Ini</a>
    <a class="<?= $sp==='kemarin'?'active':'' ?>" href="?tab=laporan_stok&stok_period=kemarin"><i class="bi bi-calendar-minus"></i> Kemarin</a>
    <a class="<?= $sp==='minggu_ini'?'active':'' ?>" href="?tab=laporan_stok&stok_period=minggu_ini"><i class="bi bi-calendar-week"></i> Minggu</a>
    <a class="<?= $sp==='bulan_ini'?'active':'' ?>" href="?tab=laporan_stok&stok_period=bulan_ini"><i class="bi bi-calendar-month"></i> Bulan</a>
</div>
<div style="text-align:right;margin-bottom:var(--sp-3);">
    <button class="btn-print" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
</div>
<div class="laporan-profesional">
    <div class="laporan-header-pro">
        <img src="<?= LOGO_URL ?>" class="logo-img" alt="Logo">
        <h1><?= APP_NAME ?></h1>
        <div class="tagline">Laporan Keluar Masuk Barang</div>
        <div class="periode-box"><i class="bi bi-calendar-check"></i> <?= tglS($sm) ?> s/d <?= tglS($sa) ?></div>
    </div>
    <div class="summary-section">
        <div class="summary-title"><i class="bi bi-bar-chart-fill"></i> RINGKASAN</div>
        <table class="summary-table">
            <tr><td class="label"><i class="bi bi-arrow-down-circle"></i>Total Masuk</td><td class="value" style="color:#155724!important;">+<?= $tSM ?> unit</td></tr>
            <tr><td class="label"><i class="bi bi-arrow-up-circle"></i>Total Keluar</td><td class="value" style="color:#721c24!important;">-<?= $tSK ?> unit</td></tr>
            <tr class="highlight-row"><td class="label"><i class="bi bi-arrow-left-right"></i>PERUBAHAN BERSIH</td><td class="value"><?= ($tSM-$tSK)>=0?'+':'' ?><?= $tSM-$tSK ?> unit</td></tr>
        </table>
    </div>
    <div class="table-section">
        <div class="table-section-title"><i class="bi bi-arrow-down-circle"></i> BARANG MASUK</div>
        <div class="table-responsive"><table class="table-pro">
        <thead><tr><th>No</th><th>Tanggal &amp; Jam</th><th>Barang</th><th>Supplier</th><th class="text-right">Jumlah</th></tr></thead>
        <tbody>
        <?php $n=1; foreach (array_reverse($sML) as $x): ?>
            <tr><td><?= $n++ ?></td><td><?= tgl($x['tanggal']) ?></td><td><strong><?= $x['nama_barang'] ?></strong></td><td><?= $x['supplier'] ?></td><td class="text-right"><strong style="color:#155724!important;">+<?= $x['jumlah'] ?></strong></td></tr>
        <?php endforeach; ?>
        <?php if (empty($sML)): ?><tr><td colspan="5" style="text-align:center;padding:20px;color:#999;">Tidak ada data</td></tr><?php endif; ?>
        </tbody></table></div>
    </div>
    <div class="table-section">
        <div class="table-section-title"><i class="bi bi-arrow-up-circle"></i> BARANG KELUAR</div>
        <div class="table-responsive"><table class="table-pro">
        <thead><tr><th>No</th><th>Tanggal &amp; Jam</th><th>Barang</th><th>Keterangan</th><th class="text-right">Jumlah</th></tr></thead>
        <tbody>
        <?php $n=1; foreach (array_reverse($sKL) as $x): ?>
            <tr><td><?= $n++ ?></td><td><?= tgl($x['tanggal']) ?></td><td><strong><?= $x['nama_barang'] ?></strong></td><td><?= $x['keterangan'] ? $x['keterangan'] : '-' ?></td><td class="text-right"><strong style="color:#721c24!important;">-<?= $x['jumlah'] ?></strong></td></tr>
        <?php endforeach; ?>
        <?php if (empty($sKL)): ?><tr><td colspan="5" style="text-align:center;padding:20px;color:#999;">Tidak ada data</td></tr><?php endif; ?>
        </tbody></table></div>
    </div>
</div>
<?php endif; ?>

</div>

<div class="modal fade" id="editBarangModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-pencil-square"></i> Edit Barang</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_barang">
                    <input type="hidden" name="id" id="ebId">
                    <input type="hidden" name="foto_lama" id="ebFotoLama">
                    <div class="mb-3"><label class="form-label">Ganti Foto</label><input type="file" name="foto" class="form-control" accept="image/*"></div>
                    <div class="mb-3"><label class="form-label">URL Foto Baru</label><input type="url" name="foto_url" class="form-control"></div>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Kode</label><input type="text" name="price_code" id="ebKode" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label">Merek</label><input type="text" name="merek" id="ebMerek" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label">Nama</label><input type="text" name="nama" id="ebNama" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label">Kategori</label><input type="text" name="kategori" id="ebKategori" class="form-control" required></div>
                        <div class="col-md-3"><label class="form-label">Stok</label><input type="number" name="stok" id="ebStok" class="form-control" required></div>
                        <div class="col-md-3"><label class="form-label">Stok Min</label><input type="number" name="stok_minimum" id="ebStokMin" class="form-control" required></div>
                        <div class="col-md-3"><label class="form-label">Modal</label><input type="number" name="modal" id="ebModal" class="form-control" required></div>
                        <div class="col-md-3"><label class="form-label">Jual</label><input type="number" name="harga_jual" id="ebJual" class="form-control" required></div>
                        <div class="col-md-12"><label class="form-label">Asal</label><input type="text" name="asal" id="ebAsal" class="form-control" required></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-warning"><i class="bi bi-save"></i> Simpan</button></div>
            </form>
        </div>
    </div>
</div>

<div class="bottom-nav">
    <a class="<?= act('home',$tab) ?>" href="?tab=home"><i class="bi bi-house"></i>Home</a>
    <a class="<?= act('pesanan',$tab) ?>" href="?tab=pesanan"><i class="bi bi-cart-plus"></i>Pesanan</a>
    <a class="<?= act('antrian',$tab) ?>" href="?tab=antrian"><i class="bi bi-list-ol"></i>Antrian<?php if($totA>0): ?><span class="indicator-dot indicator-red"></span><?php endif; ?></a>
    <a class="<?= act('laporan',$tab) ?>" href="?tab=laporan"><i class="bi bi-file-earmark-text"></i>Laporan</a>
    <a href="#" onclick="toggleMobileMenu(event)"><i class="bi bi-list"></i>Menu</a>
</div>

<script>
(function(){var el=document.getElementById('liveClock');if(!el)return;setInterval(function(){var d=new Date();el.textContent=String(d.getHours()).padStart(2,'0')+':'+String(d.getMinutes()).padStart(2,'0')+':'+String(d.getSeconds()).padStart(2,'0');},1000);})();

function nPhone(p){return String(p||'').replace(/[^0-9]/g,'');}
function nPlate(p){return String(p||'').toUpperCase().replace(/\s+/g,'');}
function nName(n){return String(n||'').trim().toLowerCase();}
function rpStr(n){return 'Rp ' + (parseFloat(n)||0).toLocaleString('id-ID');}

function setupAutofill(prefix){
    var eN=document.getElementById(prefix+'Nama'),eH=document.getElementById(prefix+'Hp'),
        eJ=document.getElementById(prefix+'Jenis'),eT=document.getElementById(prefix+'Tipe'),
        eP=document.getElementById(prefix+'Plat');
    var cap=prefix.charAt(0).toUpperCase()+prefix.slice(1);
    var hN=document.getElementById('hint'+cap+'Nama'),hH=document.getElementById('hint'+cap+'Hp'),
        vL=document.getElementById('vehicleList'+cap);
    if(!eN)return;
    function setSelectValue(sel,val){
        if(!sel||!val)return;
        var found=false;
        for(var i=0;i<sel.options.length;i++){ if(sel.options[i].value===val){sel.selectedIndex=i;found=true;break;} }
        if(!found){var o=document.createElement('option');o.value=val;o.textContent=val;o.selected=true;sel.appendChild(o);}
    }
    function fill(c,v){
        v=v||null;
        if(c){
            if(eH&&!eH.value)eH.value=c.no_hp||'';
            if(eJ&&!eJ.value && c.jenis_kendaraan) setSelectValue(eJ,c.jenis_kendaraan);
        }
        if(v){
            if(eJ && v.jenis) setSelectValue(eJ,v.jenis);
            if(eT)eT.value=v.tipe||'';
            if(eP)eP.value=v.no_polisi||'';
        }
    }
    function showH(el){if(!el)return;el.classList.add('show');setTimeout(function(){el.classList.remove('show');},2500);}
    function showV(c){
        if(!vL)return;
        var vs=c.kendaraan||{},ks=Object.keys(vs);
        if(ks.length===0){vL.classList.remove('show');vL.innerHTML='';return;}
        vL.innerHTML='<div style="font-size:11px;color:#FFD700;margin-bottom:6px;"><i class="bi bi-car-front-fill"></i> Pilih mobil tersimpan:</div>';
        ks.forEach(function(k){
            var v=vs[k];
            var d=document.createElement('div');d.className='vehicle-option';
            var label=(v.tipe||'Mobil')+(v.no_polisi?' ('+v.no_polisi+')':'');
            d.innerHTML='<i class="bi bi-car-front"></i> <strong>'+label+'</strong>';
            d.onclick=function(){fill(c,v);vL.classList.remove('show');};
            vL.appendChild(d);
        });
        vL.classList.add('show');
    }
    eN.addEventListener('input',function(){var k=nName(this.value);if(CUSTOMER_DB[k]){var c=CUSTOMER_DB[k];fill(c);showH(hN);showV(c);}else if(vL)vL.classList.remove('show');});
    if(eH)eH.addEventListener('input',function(){var k=nPhone(this.value);if(k.length>=8&&PHONE_DB[k]){var p=PHONE_DB[k];if(eN&&!eN.value)eN.value=p.nama;var nk=nName(p.nama);if(CUSTOMER_DB[nk]){fill(CUSTOMER_DB[nk]);showV(CUSTOMER_DB[nk]);}showH(hH);}});
    if(eP)eP.addEventListener('input',function(){var k=nPlate(this.value);if(k.length>=4&&PLATE_DB[k]){var p=PLATE_DB[k];if(eN)eN.value=p.nama;if(eH)eH.value=p.no_hp;if(eJ&&p.jenis_kendaraan)setSelectValue(eJ,p.jenis_kendaraan);if(eT)eT.value=p.tipe||'';if(vL)vL.classList.remove('show');showH(hN);}});
}
document.addEventListener('DOMContentLoaded',function(){if(document.getElementById('pesananNama'))setupAutofill('pesanan');});

function toggleLayanan(el){
    var t=el.dataset.target,s=document.getElementById(t);if(!s)return;
    if(el.classList.contains('active')){el.classList.remove('active');s.classList.remove('show');}
    else{el.classList.add('active');s.classList.add('show');}
    recalcTotal();
}

var itemCounter=0;
function addPesananItem(){
    itemCounter++;
    var c=document.getElementById('itemsContainer');if(!c)return;
    var row=document.createElement('div');
    row.className='pesanan-item-row';
    var opts='<option value="">-- Ketik / pilih barang --</option>';
    BARANG_LIST.forEach(function(b){
        opts+='<option value="'+b.id+'" data-harga="'+b.harga+'" data-stok="'+b.stok+'">'+b.label+' — Stok: '+b.stok+' — Rp '+b.harga.toLocaleString('id-ID')+'</option>';
    });
    row.innerHTML=
        '<span class="item-num">#'+itemCounter+'</span>'+
        '<button type="button" class="btn-remove" onclick="removePesananItem(this)"><i class="bi bi-x-lg"></i></button>'+
        '<div class="row g-2">'+
            '<div class="col-12"><label class="form-label">Cari &amp; Pilih Barang</label><select class="form-select item-barang-select">'+opts+'</select><div class="item-stok-info" style="display:none;"><i class="bi bi-box-seam"></i> <span class="stok-text"></span></div></div>'+
            '<div class="col-4"><label class="form-label">Qty</label><input type="number" class="form-control item-qty" min="1" value="1" oninput="recalcTotal()"></div>'+
            '<div class="col-4"><label class="form-label">Harga</label><input type="number" class="form-control item-harga" min="0" value="0" oninput="recalcTotal()"></div>'+
            '<div class="col-4"><label class="form-label">Subtotal</label><input type="text" class="form-control item-subtotal" value="Rp 0" disabled style="background:rgba(0,0,0,.5)!important;color:#888!important;"></div>'+
        '</div>';
    c.appendChild(row);
    if(window.jQuery && jQuery.fn.select2){
        var $sel=jQuery(row).find('.item-barang-select');
        $sel.select2({placeholder:'-- Ketik / pilih barang --',allowClear:true,width:'100%',
            matcher:function(params,data){
                if(jQuery.trim(params.term)==='')return data;
                var term=params.term.toLowerCase();
                var text=(data.text||'').toLowerCase();
                if(text.indexOf(term)>-1)return data;
                return null;
            }});
        $sel.on('select2:select',function(){onBarangChange(this);});
        $sel.on('select2:clear',function(){onBarangChange(this);});
    }
    recalcTotal();updateItemCount();
    setTimeout(function(){var $f=jQuery(row).find('.select2-search__field');if($f.length)$f.focus();},80);
}
function removePesananItem(b){
    var row=b.closest('.pesanan-item-row');
    if(row){if(window.jQuery){jQuery(row).find('.item-barang-select').select2('destroy');}row.remove();}
    recalcTotal();updateItemCount();
}
function onBarangChange(s){
    var o=s.options[s.selectedIndex];if(!o)return;
    var h=parseFloat(o.dataset.harga)||0;
    var stok=parseInt(o.dataset.stok)||0;
    var r=s.closest('.pesanan-item-row');
    r.querySelector('.item-harga').value=h;
    var info=r.querySelector('.item-stok-info'),stokText=r.querySelector('.stok-text');
    if(o.value){info.style.display='block';stokText.textContent='Stok tersedia: '+stok+' unit';stokText.style.color=stok>0?'#51cf66':'#ff6b6b';}
    else{info.style.display='none';}
    recalcTotal();
}
function updateItemCount(){var c=document.querySelectorAll('.pesanan-item-row').length;var l=document.getElementById('itemCountLabel');if(l)l.textContent=c+' item';}
function recalcTotal(){
    var subB=0;
    document.querySelectorAll('.pesanan-item-row').forEach(function(r){
        var q=parseInt(r.querySelector('.item-qty').value)||0;
        var h=parseFloat(r.querySelector('.item-harga').value)||0;
        var s=q*h;
        r.querySelector('.item-subtotal').value=rpStr(s);
        subB+=s;
    });
    var sJ=document.getElementById('servisBiaya'),sB=sJ?(parseFloat(sJ.value)||0):0;
    var mB=document.getElementById('mekanikBiaya'),mD=mB?(parseFloat(mB.value)||0):0;
    var cJ=document.getElementById('dmHarga'),cH=cJ?(parseFloat(cJ.value)||0):0;
    var dJ=document.getElementById('pesananDiskon'),dK=dJ?(parseFloat(dJ.value)||0):0;
    var tot=subB+sB+mD+cH-dK;
    function setT(id,v){var e=document.getElementById(id);if(e)e.textContent=rpStr(v);}
    setT('sumBarang',subB);setT('sumServis',sB);setT('sumMekanik',mD);setT('sumCuci',cH);
    var eD=document.getElementById('sumDiskon');if(eD)eD.textContent='-'+rpStr(dK);
    setT('sumTotal',tot);
    var j=[];
    document.querySelectorAll('.pesanan-item-row').forEach(function(r){
        var s=r.querySelector('.item-barang-select').value;if(!s)return;
        j.push({id_barang:s,qty:parseInt(r.querySelector('.item-qty').value)||0,harga:parseFloat(r.querySelector('.item-harga').value)||0});
    });
    var ji=document.getElementById('itemsJson');if(ji)ji.value=JSON.stringify(j);
}
document.addEventListener('DOMContentLoaded',function(){
    ['servisBiaya','mekanikBiaya','dmHarga','pesananDiskon'].forEach(function(id){var e=document.getElementById(id);if(e)e.addEventListener('input',recalcTotal);});
    if(document.getElementById('itemsContainer'))addPesananItem();
});

function validateStockItems(){
    var valid=true;
    document.querySelectorAll('.pesanan-item-row').forEach(function(r){
        var s=r.querySelector('.item-barang-select');if(!s||!s.value)return;
        var opt=s.options[s.selectedIndex];
        var stok=parseInt(opt.dataset.stok)||0;
        var qty=parseInt(r.querySelector('.item-qty').value)||0;
        if(qty>stok)valid=false;
    });
    return valid;
}

function confirmSavePesanan(e){
    e.preventDefault();var f=e.target;recalcTotal();
    var items=JSON.parse(document.getElementById('itemsJson').value||'[]');
    var sJ=document.getElementById('servisJenis')?document.getElementById('servisJenis').value:'';
    var dJ=document.getElementById('dmPaket')?document.getElementById('dmPaket').value:'';
    if(items.length===0 && !sJ && !dJ){Swal.fire({icon:'warning',title:'Belum ada layanan!',text:'Isi minimal 1 layanan (Barang / Servis / Cuci).',background:'#0a0a0a',color:'#fff'});return false;}
    if(!validateStockItems()){Swal.fire({icon:'error',title:'Stok tidak cukup',text:'Ada barang yang jumlahnya melebihi stok tersedia.',background:'#0a0a0a',color:'#fff'});return false;}
    var tot=document.getElementById('sumTotal').textContent;
    Swal.fire({title:'Simpan Pesanan?',html:'<p style="font-size:14px;">Total: <strong style="color:#FFD700;">'+tot+'</strong></p><div style="background:rgba(212,175,55,.1);border:1px solid rgba(212,175,55,.3);border-radius:10px;padding:10px;text-align:left;font-size:12px;margin-top:10px;color:#ddd;"><i class="bi bi-info-circle-fill" style="color:#FFD700;"></i> Pesanan akan masuk <strong style="color:#fff;">antrian</strong> dan <strong style="color:#fff;">belum lunas</strong>.</div>',icon:'question',showCancelButton:true,confirmButtonText:'<i class="bi bi-check-circle"></i> Ya, Simpan!',cancelButtonText:'Batal',background:'#0a0a0a',color:'#fff',confirmButtonColor:'#B8860B'}).then(function(r){if(r.isConfirmed)f.submit();});
    return false;
}
function confirmAction(e,title,text){
    e.preventDefault();var f=e.target;
    Swal.fire({title:title,text:text,icon:'question',showCancelButton:true,confirmButtonText:'Ya, Lanjutkan',cancelButtonText:'Batal',background:'#0a0a0a',color:'#fff',confirmButtonColor:'#B8860B'}).then(function(r){if(r.isConfirmed)f.submit();});
    return false;
}
function konfirmasiSelesaiPesanan(e,nama,total,id){
    e.preventDefault();var f=e.target;
    Swal.fire({title:'Pekerjaan Selesai?',html:'<p style="color:#fff;"><strong>'+nama+'</strong></p><p style="color:#fff;">Total: <strong style="color:#FFD700;">Rp '+total.toLocaleString('id-ID')+'</strong></p><hr style="border-color:#333;"><p style="font-size:13px;color:#ddd;">Apakah customer <strong style="color:#fff;">sudah membayar</strong>?</p>',icon:'question',showCancelButton:true,showDenyButton:true,confirmButtonText:'<i class="bi bi-cash"></i> Sudah Bayar',denyButtonText:'<i class="bi bi-hourglass"></i> Belum Bayar',cancelButtonText:'Batal',background:'#0a0a0a',color:'#fff',confirmButtonColor:'#51cf66',denyButtonColor:'#ff6b6b'}).then(function(r){
        if(r.isConfirmed){f.submit();setTimeout(function(){var pf=document.createElement('form');pf.method='POST';pf.innerHTML='<input type="hidden" name="action" value="bayar_pesanan"><input type="hidden" name="id" value="'+id+'"><input type="hidden" name="redirect" value="detail_pesanan">';document.body.appendChild(pf);pf.submit();},600);}
        else if(r.isDenied){f.submit();}
    });
    return false;
}
function bayarConfirm(e,nama,jumlah){
    e.preventDefault();var f=e.target;
    Swal.fire({title:'Konfirmasi Pembayaran',html:'<p style="color:#fff;"><strong>'+nama+'</strong></p><p style="font-size:18px;color:#51cf66;">Rp '+jumlah.toLocaleString('id-ID')+'</p><p style="font-size:12px;color:#999;">Klik konfirmasi kalau customer sudah bayar.</p>',icon:'success',showCancelButton:true,confirmButtonText:'<i class="bi bi-check-circle"></i> Ya, Lunas',cancelButtonText:'Batal',background:'#0a0a0a',color:'#fff',confirmButtonColor:'#51cf66'}).then(function(r){if(r.isConfirmed)f.submit();});
    return false;
}
function showCustomDate(e){e.preventDefault();var f=document.getElementById('customDateForm');f.style.display='block';f.scrollIntoView({behavior:'smooth'});}
function showFoto(url){Swal.fire({imageUrl:url,imageWidth:'90%',background:'#0a0a0a',color:'#fff',confirmButtonColor:'#B8860B',showCloseButton:true});}
function confirmKirimLaporan(e){e.preventDefault();var f=e.target;var t=f.querySelector('input[name="tanggal_laporan"]').value;Swal.fire({title:'Kirim Laporan?',html:'Tanggal: <strong style="color:#FFD700;">'+t+'</strong><br><small style="color:#999;">Format: Tabel teks + File PDF</small>',icon:'question',showCancelButton:true,confirmButtonText:'Kirim',cancelButtonText:'Batal',background:'#0a0a0a',color:'#fff',confirmButtonColor:'#0088cc'}).then(function(r){if(r.isConfirmed)f.submit();});return false;}
function openEditBarangModal(d){
    document.getElementById('ebId').value=d.id;
    document.getElementById('ebFotoLama').value=d.foto||'';
    document.getElementById('ebKode').value=d.price_code;
    document.getElementById('ebMerek').value=d.merek;
    document.getElementById('ebNama').value=d.nama;
    document.getElementById('ebKategori').value=d.kategori;
    document.getElementById('ebStok').value=d.stok;
    document.getElementById('ebStokMin').value=d.stok_minimum;
    document.getElementById('ebModal').value=d.modal;
    document.getElementById('ebJual').value=d.harga_jual;
    document.getElementById('ebAsal').value=d.asal;
    new bootstrap.Modal(document.getElementById('editBarangModal')).show();
}

function fmtTglKw(s){
    if(!s)return new Date().toLocaleDateString('id-ID');
    var d=new Date(String(s).replace(' ','T'));
    if(isNaN(d.getTime()))return s;
    var bulan=['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    var dd=String(d.getDate()).padStart(2,'0');
    var mm=bulan[d.getMonth()];
    var yy=d.getFullYear();
    var hh=String(d.getHours()).padStart(2,'0');
    var mi=String(d.getMinutes()).padStart(2,'0');
    return dd+' '+mm+' '+yy+', '+hh+':'+mi+' WIB';
}

/* ============ KWITANSI PREMIUM ============ */
function generateKwitansiPro(d,jenis){
    try{
        var jsPDFLib=(window.jspdf&&window.jspdf.jsPDF)?window.jspdf.jsPDF:window.jsPDF;
        if(!jsPDFLib){Swal.fire({icon:'error',title:'PDF Library Gagal Dimuat',html:'Coba refresh halaman (Ctrl+F5).',background:'#0a0a0a',color:'#fff'});return;}
        
        var doc=new jsPDFLib('p','mm','a4');
        var W=210,H=297,P=14,CW=W-P*2;
        var y=0;
        
        var GOLD=[184,134,11];
        var GOLD_LIGHT=[255,215,0];
        var DARK=[18,18,18];
        var DARKER=[8,8,8];
        var GRAY=[120,120,120];
        var GRAY_LIGHT=[200,200,200];
        var CREAM=[252,248,238];
        
        doc.setDrawColor(GOLD[0],GOLD[1],GOLD[2]);
        doc.setLineWidth(1.5);
        doc.rect(6,6,W-12,H-12);
        doc.setLineWidth(0.3);
        doc.rect(8,8,W-16,H-16);
        
        function corner(x,y){
            doc.setFillColor(GOLD[0],GOLD[1],GOLD[2]);
            doc.circle(x,y,1.2,'F');
            doc.setFillColor(GOLD_LIGHT[0],GOLD_LIGHT[1],GOLD_LIGHT[2]);
            doc.circle(x,y,0.5,'F');
        }
        corner(6,6);corner(W-6,6);corner(6,H-6);corner(W-6,H-6);
        
        var headerH=50;
        doc.setFillColor(DARKER[0],DARKER[1],DARKER[2]);
        doc.rect(8,8,W-16,headerH,'F');
        
        doc.setFillColor(GOLD[0],GOLD[1],GOLD[2]);
        doc.rect(8,8+headerH,W-16,0.8,'F');
        doc.setFillColor(GOLD_LIGHT[0],GOLD_LIGHT[1],GOLD_LIGHT[2]);
        doc.rect(8,8+headerH+0.8,W-16,0.3,'F');
        
        var logoX=20,logoY=15,logoS=34;
        if(LOGO_B64 && LOGO_B64.length>100){
            try{doc.addImage(LOGO_B64,'PNG',logoX,logoY,logoS,logoS);}
            catch(e){try{doc.addImage(LOGO_B64,'WEBP',logoX,logoY,logoS,logoS);}catch(e2){}}
        }
        
        var brandX=logoX+logoS+6;
        doc.setFont('times','bold');
        doc.setFontSize(28);
        doc.setTextColor(GOLD_LIGHT[0],GOLD_LIGHT[1],GOLD_LIGHT[2]);
        doc.text('CARSOTOCARE',brandX,30);
        
        doc.setFont('helvetica','normal');
        doc.setFontSize(8.5);
        doc.setTextColor(220,220,220);
        doc.text('BENGKEL MOBIL  ·  CARWASH  ·  AUTO DETAILING',brandX,38);
        
        doc.setFontSize(8);
        doc.setTextColor(180,180,180);
        doc.text('Binjai, Sumatera Utara  ·  0887-7792-28899',brandX,44);
        doc.text('carsotocare@gmail.com',brandX,49.5);
        
        doc.setFont('times','bold');
        doc.setFontSize(20);
        doc.setTextColor(GOLD_LIGHT[0],GOLD_LIGHT[1],GOLD_LIGHT[2]);
        doc.text('INVOICE',W-P-4,28,{align:'right'});
        
        doc.setDrawColor(GOLD[0],GOLD[1],GOLD[2]);
        doc.setLineWidth(0.5);
        doc.line(W-P-46,31,W-P-4,31);
        
        var noKw=(jenis==='pesanan')?(d.no_order||'-'):(d.no_kwitansi||('KW-'+Date.now()));
        var tglKw=fmtTglKw(d.tanggal_selesai||d.tanggal||d.tanggal_bayar);
        
        doc.setFont('helvetica','normal');
        doc.setFontSize(8);
        doc.setTextColor(180,180,180);
        doc.text('Nomor Invoice',W-P-4,40,{align:'right'});
        doc.text('Tanggal',W-P-4,48,{align:'right'});
        
        doc.setFont('helvetica','bold');
        doc.setFontSize(10);
        doc.setTextColor(GOLD_LIGHT[0],GOLD_LIGHT[1],GOLD_LIGHT[2]);
        doc.text(noKw,W-P-4,44,{align:'right'});
        doc.setTextColor(240,240,240);
        doc.text(tglKw,W-P-4,52,{align:'right'});
        
        y=8+headerH+12;
        
        doc.setDrawColor(GOLD[0],GOLD[1],GOLD[2]);
        doc.setLineWidth(0.3);
        doc.line(P,y,W-P,y);
        
        doc.setFillColor(GOLD[0],GOLD[1],GOLD[2]);
        doc.circle(W/2,y,1.5,'F');
        doc.setFillColor(GOLD_LIGHT[0],GOLD_LIGHT[1],GOLD_LIGHT[2]);
        doc.circle(W/2,y,0.7,'F');
        
        y+=9;
        
        doc.setFont('times','bold');
        doc.setFontSize(11);
        doc.setTextColor(GOLD[0],GOLD[1],GOLD[2]);
        doc.text('INFORMASI PELANGGAN',P,y);
        
        y+=3;
        doc.setDrawColor(GOLD[0],GOLD[1],GOLD[2]);
        doc.setLineWidth(0.6);
        doc.line(P,y,P+58,y);
        y+=8;
        
        var halfW=CW/2-2;
        var infoY=y;
        
        function infoBlock(x,yy,label,value,valueFontSize){
            doc.setFont('helvetica','normal');
            doc.setFontSize(7);
            doc.setTextColor(GRAY[0],GRAY[1],GRAY[2]);
            doc.text(label,x,yy);
            
            doc.setFont('times','bold');
            doc.setFontSize(valueFontSize||12);
            doc.setTextColor(DARK[0],DARK[1],DARK[2]);
            doc.text(String(value||'-'),x,yy+6);
        }
        
        infoBlock(P,infoY,'NAMA PELANGGAN',(d.nama_customer||'-').toString().toUpperCase(),13);
        infoBlock(P+halfW+6,infoY,'NO. HANDPHONE',(d.no_hp||'-'));
        
        infoY+=16;
        
        var kendaraan=((d.jenis_kendaraan||'')+' '+(d.tipe||d.tipe_mobil||'')).trim()||'-';
        infoBlock(P,infoY,'KENDARAAN',kendaraan,11);
        infoBlock(P+halfW+6,infoY,'NOMOR POLISI',(d.no_polisi||'-'),11);
        
        y=infoY+15;
        
        doc.setFont('times','bold');
        doc.setFontSize(11);
        doc.setTextColor(GOLD[0],GOLD[1],GOLD[2]);
        doc.text('RINCIAN LAYANAN',P,y);
        
        y+=3;
        doc.setDrawColor(GOLD[0],GOLD[1],GOLD[2]);
        doc.setLineWidth(0.6);
        doc.line(P,y,P+52,y);
        y+=5;
        
        var tHeadH=9;
        doc.setFillColor(DARK[0],DARK[1],DARK[2]);
        doc.rect(P,y,CW,tHeadH,'F');
        
        doc.setFillColor(GOLD[0],GOLD[1],GOLD[2]);
        doc.rect(P,y,CW,0.5,'F');
        
        var cNo=P+4,cDesc=P+15,cQty=115,cHrg=155,cSub=W-P-4;
        
        doc.setFont('helvetica','bold');
        doc.setFontSize(8.5);
        doc.setTextColor(GOLD_LIGHT[0],GOLD_LIGHT[1],GOLD_LIGHT[2]);
        doc.text('NO',cNo,y+6);
        doc.text('DESKRIPSI',cDesc,y+6);
        doc.text('QTY',cQty,y+6,{align:'center'});
        doc.text('HARGA',cHrg,y+6,{align:'right'});
        doc.text('SUBTOTAL',cSub,y+6,{align:'right'});
        
        y+=tHeadH;
        
        var rowH=8.5,subtotalSemua=0,no=0;
        
        function truncateText(t,maxW){
            if(doc.getTextWidth(t)<=maxW)return t;
            while(t.length>0 && doc.getTextWidth(t+'...')>maxW)t=t.substring(0,t.length-1);
            return t+'...';
        }
        
        function drawRow(desc,qty,harga,subtotal){
            no++;
            if(no%2===0){
                doc.setFillColor(CREAM[0],CREAM[1],CREAM[2]);
                doc.rect(P,y,CW,rowH,'F');
            }
            
            doc.setDrawColor(230,230,230);
            doc.setLineWidth(0.15);
            doc.line(P,y+rowH,W-P,y+rowH);
            
            doc.setFont('helvetica','normal');
            doc.setFontSize(9);
            doc.setTextColor(DARK[0],DARK[1],DARK[2]);
            
            doc.setFont('times','bold');
            doc.text(String(no),cNo,y+5.5);
            
            doc.setFont('helvetica','normal');
            var maxDescW=cQty-cDesc-10;
            doc.text(truncateText(String(desc),maxDescW),cDesc,y+5.5);
            
            doc.text(String(qty),cQty,y+5.5,{align:'center'});
            doc.text('Rp '+harga.toLocaleString('id-ID'),cHrg,y+5.5,{align:'right'});
            
            doc.setFont('helvetica','bold');
            doc.text('Rp '+subtotal.toLocaleString('id-ID'),cSub,y+5.5,{align:'right'});
            
            y+=rowH;
        }
        
        if(jenis==='pesanan'){
            if(d.items&&d.items.length>0)d.items.forEach(function(it){drawRow(it.nama,it.qty,it.harga,it.subtotal);subtotalSemua+=it.subtotal;});
            if(d.tambahan&&d.tambahan.length>0){d.tambahan.forEach(function(it){drawRow('[TAMBAHAN] '+it.nama,it.qty,it.harga,it.subtotal);subtotalSemua+=it.subtotal;});}
            if(d.servis&&d.servis.jenis){drawRow('Jasa Servis — '+d.servis.jenis,1,d.servis.biaya,d.servis.biaya);subtotalSemua+=d.servis.biaya;}
            if(d.servis&&d.servis.mekanik_biaya){var mek='Jasa Mekanik';if(d.servis.mekanik_nama)mek+=' — '+d.servis.mekanik_nama;drawRow(mek,1,d.servis.mekanik_biaya,d.servis.mekanik_biaya);subtotalSemua+=d.servis.mekanik_biaya;}
            if(d.doorsmeer&&d.doorsmeer.paket){drawRow('Cuci Mobil — '+d.doorsmeer.paket,1,d.doorsmeer.harga,d.doorsmeer.harga);subtotalSemua+=d.doorsmeer.harga;}
        }else if(jenis==='servis'){
            var label=d.jenis_servis||'Jasa Servis';
            var harga=d.harga_asli||d.biaya||0;
            drawRow(label,1,harga,harga);subtotalSemua+=harga;
        }else if(jenis==='cuci'){
            var label=d.paket||'Cuci Mobil';
            var harga=d.harga_asli||d.harga||0;
            drawRow(label,1,harga,harga);subtotalSemua+=harga;
        }
        
        y+=4;
        
        var diskon=d.diskon||0;
        var totalAkhir=d.total||(subtotalSemua-diskon);
        if(jenis==='servis'||jenis==='cuci')totalAkhir=d.harga_final||(subtotalSemua-diskon);
        
        var boxW=80;
        var boxX=W-P-boxW;
        var boxY=y;
        
        doc.setFont('helvetica','normal');
        doc.setFontSize(9.5);
        doc.setTextColor(DARK[0],DARK[1],DARK[2]);
        doc.text('Subtotal',boxX,boxY+5);
        doc.text('Rp '+subtotalSemua.toLocaleString('id-ID'),W-P-2,boxY+5,{align:'right'});
        boxY+=7;
        
        if(diskon>0){
            doc.setTextColor(200,50,50);
            doc.text('Diskon',boxX,boxY+5);
            doc.text('- Rp '+diskon.toLocaleString('id-ID'),W-P-2,boxY+5,{align:'right'});
            doc.setTextColor(DARK[0],DARK[1],DARK[2]);
            boxY+=7;
        }
        
        doc.setDrawColor(GOLD[0],GOLD[1],GOLD[2]);
        doc.setLineWidth(0.5);
        doc.line(boxX,boxY+2,W-P-2,boxY+2);
        boxY+=6;
        
        doc.setFillColor(DARKER[0],DARKER[1],DARKER[2]);
        doc.rect(boxX-3,boxY,boxW+3,13,'F');
        
        doc.setFillColor(GOLD[0],GOLD[1],GOLD[2]);
        doc.rect(boxX-3,boxY,1.5,13,'F');
        
        doc.setFont('times','bold');
        doc.setFontSize(11);
        doc.setTextColor(GOLD_LIGHT[0],GOLD_LIGHT[1],GOLD_LIGHT[2]);
        doc.text('TOTAL',boxX+2,boxY+9);
        
        doc.setFont('helvetica','bold');
        doc.setFontSize(14);
        doc.text('Rp '+totalAkhir.toLocaleString('id-ID'),W-P-2,boxY+9,{align:'right'});
        
        boxY+=17;
        
        /* STATUS PEMBAYARAN — PROFESSIONAL SEAL */
        var isLunas = (d.status_bayar === 'lunas');

        var statW = boxW + 3;
        var statH = 17;
        var statX = boxX - 3;
        var statY = boxY;

        doc.setFillColor(8, 8, 8);
        doc.rect(statX, statY, statW, statH, 'F');

        if (isLunas) {
            doc.setFillColor(22, 122, 63);
        } else {
            doc.setFillColor(160, 35, 35);
        }
        doc.rect(statX, statY, 2.2, statH, 'F');

        doc.setDrawColor(GOLD[0], GOLD[1], GOLD[2]);
        doc.setLineWidth(0.9);
        doc.rect(statX, statY, statW, statH);

        doc.setDrawColor(GOLD_LIGHT[0], GOLD_LIGHT[1], GOLD_LIGHT[2]);
        doc.setLineWidth(0.15);
        doc.rect(statX + 1.3, statY + 1.3, statW - 2.6, statH - 2.6);

        var emX = statX + 12;
        var emY = statY + statH / 2;

        doc.setDrawColor(GOLD[0], GOLD[1], GOLD[2]);
        doc.setLineWidth(0.5);
        doc.circle(emX, emY, 4.6, 'S');

        doc.setDrawColor(GOLD_LIGHT[0], GOLD_LIGHT[1], GOLD_LIGHT[2]);
        doc.setLineWidth(0.15);
        doc.circle(emX, emY, 3.9, 'S');

        if (isLunas) {
            doc.setFillColor(22, 122, 63);
        } else {
            doc.setFillColor(160, 35, 35);
        }
        doc.circle(emX, emY, 3.5, 'F');

        if (isLunas) {
            doc.setDrawColor(255, 255, 255);
            doc.setLineWidth(0.9);
            doc.line(emX - 1.8, emY + 0.2, emX - 0.4, emY + 1.6);
            doc.line(emX - 0.4, emY + 1.6, emX + 1.8, emY - 1.5);
        } else {
            doc.setFillColor(255, 255, 255);
            doc.rect(emX - 0.35, emY - 2.1, 0.7, 3.0, 'F');
            doc.circle(emX, emY + 1.9, 0.5, 'F');
        }

        doc.setFont('times', 'bold');
        doc.setFontSize(14);
        if (isLunas) {
            doc.setTextColor(GOLD_LIGHT[0], GOLD_LIGHT[1], GOLD_LIGHT[2]);
        } else {
            doc.setTextColor(255, 170, 170);
        }
        doc.text(
            isLunas ? 'LUNAS' : 'BELUM DIBAYAR',
            statX + statW - 4,
            statY + 8.8,
            { align: 'right' }
        );

        doc.setFont('helvetica', 'normal');
        doc.setFontSize(5.8);
        doc.setTextColor(200, 200, 200);
        doc.text(
            isLunas ? 'PAID IN FULL   ·   SETTLED' : 'OUTSTANDING PAYMENT   ·   UNPAID',
            statX + statW - 4,
            statY + 13.8,
            { align: 'right' }
        );

        boxY += statH + 5;

        doc.setFont('helvetica','normal');
        doc.setFontSize(8);
        doc.setTextColor(GRAY[0],GRAY[1],GRAY[2]);
        
        if(d.tanggal_selesai){
            doc.text('Tgl. Selesai: '+fmtTglKw(d.tanggal_selesai),W-P-2,boxY,{align:'right'});
            boxY+=4.5;
        }
        if(isLunas&&d.tanggal_bayar){
            doc.text('Tgl. Bayar: '+fmtTglKw(d.tanggal_bayar),W-P-2,boxY,{align:'right'});
            boxY+=4.5;
        }
        
        y=Math.max(y,boxY)+6;
        
        if(d.note&&d.note.trim()){
            var noteText=d.note.trim();
            var noteLines=doc.splitTextToSize(noteText,CW-14);
            var noteH=14+(noteLines.length*4.5);
            
            doc.setDrawColor(GOLD[0],GOLD[1],GOLD[2]);
            doc.setFillColor(CREAM[0],CREAM[1],CREAM[2]);
            doc.setLineWidth(0.3);
            doc.roundedRect(P,y,CW,noteH,2,2,'FD');
            
            doc.setFillColor(GOLD[0],GOLD[1],GOLD[2]);
            doc.rect(P,y,2,noteH,'F');
            
            doc.setFont('helvetica','bold');
            doc.setFontSize(8);
            doc.setTextColor(GOLD[0],GOLD[1],GOLD[2]);
            doc.text('CATATAN PENTING',P+6,y+5.5);
            
            doc.setFont('helvetica','normal');
            doc.setFontSize(8.5);
            doc.setTextColor(70,60,40);
            
            var ny=y+10;
            noteLines.forEach(function(l){doc.text(l,P+6,ny);ny+=4.5;});
            
            y+=noteH+6;
        }
        
        var footerY=H-38;
        
        doc.setDrawColor(GOLD[0],GOLD[1],GOLD[2]);
        doc.setLineWidth(0.5);
        doc.line(P,footerY-2,W-P,footerY-2);
        doc.setLineWidth(0.15);
        doc.line(P,footerY-1,W-P,footerY-1);
        
        doc.setFont('times','italic');
        doc.setFontSize(12);
        doc.setTextColor(GOLD[0],GOLD[1],GOLD[2]);
        doc.text('Terima kasih atas kepercayaan Anda',W/2,footerY+6,{align:'center'});
        
        doc.setFillColor(GOLD[0],GOLD[1],GOLD[2]);
        doc.circle(W/2-32,footerY+10.5,0.8,'F');
        doc.circle(W/2+32,footerY+10.5,0.8,'F');
        doc.setDrawColor(GOLD[0],GOLD[1],GOLD[2]);
        doc.setLineWidth(0.2);
        doc.line(W/2-28,footerY+10.5,W/2-6,footerY+10.5);
        doc.line(W/2+6,footerY+10.5,W/2+28,footerY+10.5);
        
        doc.setFont('helvetica','normal');
        doc.setFontSize(7.5);
        doc.setTextColor(GRAY[0],GRAY[1],GRAY[2]);
        doc.text('Semoga puas dengan layanan kami. Kami tunggu kedatangan Anda berikutnya.',W/2,footerY+16,{align:'center'});
        
        doc.setFontSize(6.5);
        doc.setTextColor(GRAY_LIGHT[0],GRAY_LIGHT[1],GRAY_LIGHT[2]);
        var appName=(typeof APP_NAME!=='undefined')?APP_NAME:'CARSOTOCARE';
        doc.text('Dicetak otomatis oleh sistem '+appName+'  ·  '+new Date().toLocaleString('id-ID')+' WIB',W/2,H-11,{align:'center'});
        
        doc.save('Kwitansi_'+noKw+'.pdf');
    }catch(err){
        console.error('Kwitansi error:',err);
        Swal.fire({
            icon:'error',
            title:'Gagal Membuat Kwitansi',
            html:'<div style="text-align:left;font-size:13px;color:#ddd;"><strong style="color:#ff9090;">Error:</strong><br>'+String(err.message||err)+'</div>',
            background:'#0a0a0a',color:'#fff',confirmButtonColor:'#B8860B'
        });
    }
}
function generateKwitansiPesanan(d){generateKwitansiPro(d,'pesanan');}
function generateKwitansiServis(d){generateKwitansiPro(d,'servis');}
function generateKwitansiCuci(d){generateKwitansiPro(d,'cuci');}

jQuery(document).ready(function(){
    if(jQuery('.searchable-select').length){
        jQuery('.searchable-select').select2({placeholder:'-- Ketik cari --',allowClear:true,width:'100%',
            matcher:function(params,data){
                if(jQuery.trim(params.term)==='')return data;
                var term=params.term.toLowerCase();
                var text=(data.text||'').toLowerCase();
                if(text.indexOf(term)>-1)return data;
                return null;
            }});
    }
});
function confirmSave(e){e.preventDefault();var f=e.target;Swal.fire({title:'Simpan?',icon:'question',showCancelButton:true,confirmButtonText:'Ya',cancelButtonText:'Batal',background:'#0a0a0a',color:'#fff',confirmButtonColor:'#B8860B'}).then(function(r){if(r.isConfirmed)f.submit();});return false;}
function confirmDelete(e){e.preventDefault();var f=e.target;Swal.fire({title:'Hapus?',text:'Tidak bisa dikembalikan!',icon:'warning',showCancelButton:true,confirmButtonText:'Ya, Hapus!',cancelButtonText:'Batal',background:'#0a0a0a',color:'#fff',confirmButtonColor:'#ff6b6b'}).then(function(r){if(r.isConfirmed)f.submit();});return false;}
function confirmLogout(){Swal.fire({title:'Keluar?',icon:'question',showCancelButton:true,confirmButtonText:'Ya',cancelButtonText:'Batal',background:'#0a0a0a',color:'#fff',confirmButtonColor:'#ff6b6b'}).then(function(r){if(r.isConfirmed)window.location.href='logout.php';});return false;}
function toggleMobileMenu(e){
    e.preventDefault();var sb=document.querySelector('.sidebar-desktop');if(!sb)return;
    if(sb.style.display==='block'){sb.style.display='none';}
    else{sb.style.display='block';sb.style.position='fixed';sb.style.top='60px';sb.style.left='0';sb.style.width='80%';sb.style.maxWidth='300px';sb.style.zIndex='999';sb.style.boxShadow='4px 0 20px rgba(0,0,0,.7)';}
}

<?php if ($tab === 'home' && !empty($last7)): ?>
(function(){
    var canvas = document.getElementById('chart7');
    if (!canvas || typeof Chart === 'undefined') return;
    var data7 = <?= json_encode($last7, JSON_UNESCAPED_UNICODE) ?>;
    new Chart(canvas, {
        type: 'line',
        data: {
            labels: data7.map(function(x){ return x.label; }),
            datasets: [{
                label: 'Total Omset',
                data: data7.map(function(x){ return x.total; }),
                borderColor: '#FFD700',
                backgroundColor: 'rgba(255,215,0,.15)',
                tension: 0.35,
                fill: true,
                pointBackgroundColor: '#FFD700',
                pointBorderColor: '#000',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0a0a0a',
                    borderColor: '#FFD700',
                    borderWidth: 1,
                    titleColor: '#FFD700',
                    bodyColor: '#fff',
                    callbacks: {
                        label: function(c){ return 'Total: Rp ' + c.parsed.y.toLocaleString('id-ID'); }
                    }
                }
            },
            scales: {
                x: {
                    ticks: { color: '#a0a0a8', font: { size: 11 } },
                    grid: { color: 'rgba(212,175,55,.06)' }
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: '#a0a0a8',
                        font: { size: 11 },
                        callback: function(v){ return 'Rp ' + (v >= 1000 ? (v/1000).toFixed(0) + 'k' : v); }
                    },
                    grid: { color: 'rgba(212,175,55,.06)' }
                }
            }
        }
    });
})();
<?php endif; ?>
</script>
</body>
</html>
