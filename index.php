<?php
session_start();

define('DATA_FILE', __DIR__ . '/data.json');
define('SESSION_TIMEOUT', 3600);

function readData() {
    if (file_exists(DATA_FILE)) {
        $json = file_get_contents(DATA_FILE);
        $data = json_decode($json, true);
        if (is_array($data)) return $data;
    }
    return ['users'=>[], 'barang'=>[], 'stock_masuk'=>[], 'stock_keluar'=>[], 'servis'=>[], 'doorsmeer'=>[], 'omset'=>[]];
}

function writeData($data) {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $fp = @fopen(DATA_FILE, 'c+');
    if (!$fp) { @file_put_contents(DATA_FILE, $json); return; }
    if (flock($fp, LOCK_EX)) {
        ftruncate($fp, 0); rewind($fp); fwrite($fp, $json); fflush($fp);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
}

function createDefaultUser() {
    $data = readData();
    if (empty($data['users'])) {
        $data['users'][] = [
            'id' => 1,
            'username' => 'admin',
            'password_hash' => password_hash('admin123', PASSWORD_BCRYPT),
            'nama' => 'Administrator',
            'role' => 'admin',
            'created_at' => date('Y-m-d H:i:s')
        ];
        writeData($data);
    }
}

function verifyLogin($username, $password) {
    $data = readData();
    foreach ($data['users'] as $user) {
        if ($user['username'] === $username && password_verify($password, $user['password_hash'])) {
            return $user;
        }
    }
    return false;
}

function isLoggedIn() {
    if (!isset($_SESSION['user_id'])) return false;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_unset(); session_destroy(); return false;
    }
    $_SESSION['last_activity'] = time();
    return true;
}

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

createDefaultUser();

if (isLoggedIn()) { header('Location: dashboard.php'); exit(); }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Username dan password wajib diisi.';
    } else {
        $user = verifyLogin($username, $password);
        if ($user) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['nama'] = $user['nama'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['last_activity'] = time();
            header('Location: dashboard.php');
            exit();
        } else {
            $error = 'Kombinasi username dan password tidak cocok.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, user-scalable=no, viewport-fit=cover">
<meta name="theme-color" content="#000000">
<meta name="format-detection" content="telephone=no">
<title>Masuk &mdash; CarsoToCare</title>
<link rel="icon" type="image/webp" href="https://carsotocare.com/icon.webp">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
/* ============ PALET: HANYA EMAS + HITAM ============ */
:root{
    --black-1:#000000;
    --black-2:#050505;
    --black-3:#0a0a0a;
    --black-4:#111111;
    --gold:#d4af37;
    --gold-2:#E5B80B;
    --gold-3:#FFD700;
    --gold-4:#f9e29c;
    --gold-5:#8B6508;
    --gold-grad:linear-gradient(120deg,#d4af37 0%,#ffe9a8 50%,#d4af37 100%);
    --gold-grad-2:linear-gradient(135deg,#E5B80B 0%,#FFD700 50%,#E5B80B 100%);
    --text:#ffffff;
    --text-soft:#c8c8c8;
    --text-mute:#7a7a7a;
    --text-fade:#4a4a4a;
    --danger:#ff6b6b;
}
*{
    margin:0;padding:0;box-sizing:border-box;
    -webkit-tap-highlight-color:transparent;
    -webkit-touch-callout:none;
}
html,body{
    height:100%;width:100%;
    overflow:hidden;position:fixed;inset:0;
    overscroll-behavior:none;
    touch-action:none;
    -webkit-user-select:none;
    -moz-user-select:none;
    user-select:none;
}
body{
    font-family:'Inter',system-ui,-apple-system,sans-serif;
    background:#000;
    color:var(--text);
    -webkit-font-smoothing:antialiased;
    text-rendering:optimizeLegibility;
    font-size:15px;
}
input,textarea,button{
    font-family:inherit;
    touch-action:manipulation;
    -webkit-user-select:text;
    user-select:text;
}
input::placeholder{color:var(--text-fade);}
::selection{background:var(--gold);color:#000;}
::-webkit-scrollbar{display:none;width:0;height:0;}

/* ============ PRELOADER ============ */
.preloader{
    position:fixed;inset:0;z-index:100000;
    background:radial-gradient(circle at 50% 50%,#080808 0%,#000 75%);
    display:flex;flex-direction:column;
    align-items:center;justify-content:center;
    overflow:hidden;
    transition:opacity .8s cubic-bezier(.4,0,.2,1),visibility .8s;
}
.preloader.exit{opacity:0;visibility:hidden;pointer-events:none;}

.preloader::before{
    content:'';position:absolute;inset:0;
    background-image:
        radial-gradient(2px 2px at 20% 30%, rgba(212,175,55,.5), transparent),
        radial-gradient(2px 2px at 60% 70%, rgba(212,175,55,.35), transparent),
        radial-gradient(1px 1px at 80% 20%, rgba(212,175,55,.6), transparent),
        radial-gradient(1px 1px at 30% 80%, rgba(212,175,55,.45), transparent),
        radial-gradient(2px 2px at 50% 50%, rgba(212,175,55,.35), transparent),
        radial-gradient(1px 1px at 90% 60%, rgba(212,175,55,.4), transparent),
        radial-gradient(1px 1px at 10% 55%, rgba(212,175,55,.35), transparent),
        radial-gradient(2px 2px at 75% 85%, rgba(212,175,55,.3), transparent);
    animation:starFloat 9s ease-in-out infinite;
    opacity:.75;
}
@keyframes starFloat{
    0%,100%{transform:translateY(0) scale(1);opacity:.75;}
    50%{transform:translateY(-12px) scale(1.04);opacity:1;}
}

.pre-stage{
    position:relative;
    width:270px;height:270px;
    display:flex;align-items:center;justify-content:center;
    flex-shrink:0;
    transition:transform .7s cubic-bezier(.4,0,.2,1);
}
.pre-glow{
    position:absolute;
    width:170px;height:170px;
    border-radius:50%;
    background:radial-gradient(circle,rgba(212,175,55,.5) 0%,transparent 70%);
    filter:blur(26px);
    animation:pulseGlow 2.6s ease-in-out infinite;
    z-index:1;
    transition:transform .7s cubic-bezier(.4,0,.2,1),opacity .7s ease;
}
@keyframes pulseGlow{
    0%,100%{transform:scale(.92);opacity:.6;}
    50%{transform:scale(1.14);opacity:1;}
}
.pre-ring{
    position:absolute;border-radius:50%;
    border:1.5px solid transparent;
    animation:spinCW 3s linear infinite;
    pointer-events:none;
    transition:transform .7s cubic-bezier(.4,0,.2,1),opacity .7s ease;
}
.pre-r1{inset:0;border-top-color:var(--gold);border-right-color:rgba(212,175,55,.22);animation-duration:4s;}
.pre-r2{inset:24px;border-top-color:var(--gold-4);border-left-color:rgba(212,175,55,.18);animation-duration:3s;animation-direction:reverse;}
.pre-r3{inset:48px;border-top-color:var(--gold-3);border-right-color:var(--gold);animation-duration:5s;}
.pre-r4{inset:72px;border-top-color:rgba(212,175,55,.5);border-right-color:rgba(212,175,55,.12);animation-duration:6s;animation-direction:reverse;}
@keyframes spinCW{from{transform:rotate(0);}to{transform:rotate(360deg);}}

.pre-logo{
    position:relative;z-index:5;
    width:100px;height:100px;
    object-fit:contain;
    filter:
        drop-shadow(0 0 18px rgba(212,175,55,.9))
        drop-shadow(0 0 44px rgba(212,175,55,.55))
        drop-shadow(0 0 70px rgba(212,175,55,.28));
    animation:logoFloat 3s ease-in-out infinite;
    transition:transform .6s cubic-bezier(.4,0,.2,1),opacity .6s ease;
}
@keyframes logoFloat{
    0%,100%{transform:translateY(0);}
    50%{transform:translateY(-5px);}
}

.pre-brand{
    margin-top:32px;
    display:flex;flex-direction:column;
    align-items:center;gap:9px;
    opacity:0;transform:translateY(10px);
    animation:fadeUp .9s ease .5s forwards;
    text-align:center;
    padding:0 20px;
    transition:opacity .5s ease,transform .5s ease;
}
@keyframes fadeUp{to{opacity:1;transform:translateY(0);}}
.pre-name{
    font-family:'Cinzel',serif;
    font-size:clamp(1.35rem,4.2vw,1.9rem);
    font-weight:700;
    letter-spacing:8px;
    padding-left:8px;
    background:var(--gold-grad);background-size:200% auto;
    -webkit-background-clip:text;background-clip:text;
    -webkit-text-fill-color:transparent;
    animation:shineText 3s linear infinite;
    line-height:1.15;
}
.pre-sub{
    font-family:'Inter',sans-serif;
    font-size:clamp(.62rem,1.7vw,.74rem);
    font-weight:600;
    letter-spacing:6px;
    padding-left:6px;
    color:var(--gold);
    text-transform:uppercase;
    opacity:.72;
}
@keyframes shineText{to{background-position:200% center;}}

.pre-bar{
    margin-top:38px;width:200px;height:1.5px;
    background:rgba(212,175,55,.12);
    position:relative;overflow:hidden;border-radius:2px;
    transition:opacity .4s,transform .4s;
}
.pre-bar-fill{
    position:absolute;left:0;top:0;height:100%;width:0%;
    background:var(--gold-grad);
    box-shadow:0 0 16px rgba(212,175,55,.9);
    transition:width .3s ease;
}
.pre-pct{
    margin-top:12px;font-size:.68rem;
    letter-spacing:4px;color:var(--gold);opacity:.7;
    font-weight:500;
    transition:opacity .4s,transform .4s;
}

.preloader.exit .pre-brand{opacity:0;transform:translateY(-18px);}
.preloader.exit .pre-logo{transform:scale(1.15);opacity:0;}
.preloader.exit .pre-ring{transform:scale(1.5);opacity:0;}
.preloader.exit .pre-glow{transform:scale(2.2);opacity:0;}
.preloader.exit .pre-bar,
.preloader.exit .pre-pct{opacity:0;transform:translateY(8px);}

@media(max-width:600px){
    .pre-stage{width:230px;height:230px;}
    .pre-logo{width:84px;height:84px;}
    .pre-name{letter-spacing:5px;padding-left:5px;}
    .pre-sub{letter-spacing:4px;padding-left:4px;}
    .pre-bar{width:170px;}
}

/* ============ SCREEN ============ */
.screen{
    position:fixed;inset:0;
    display:flex;align-items:center;justify-content:center;
    padding:20px;
    overflow:hidden;
    background:#000;
}

.ambient{
    position:absolute;inset:0;
    overflow:hidden;pointer-events:none;
    z-index:0;
}
.grid{
    position:absolute;inset:0;
    background-image:
        linear-gradient(rgba(212,175,55,.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(212,175,55,.03) 1px, transparent 1px);
    background-size:56px 56px;
    -webkit-mask-image:radial-gradient(ellipse 62% 62% at 50% 50%, #000 22%, transparent 78%);
    mask-image:radial-gradient(ellipse 62% 62% at 50% 50%, #000 22%, transparent 78%);
}
.glow{
    position:absolute;
    width:760px;height:760px;
    border-radius:50%;
    background:radial-gradient(circle,rgba(212,175,55,.1) 0%,transparent 62%);
    filter:blur(40px);
    top:50%;left:50%;
    transform:translate(-50%,-50%);
    animation:glowBreath 9s ease-in-out infinite;
}
@keyframes glowBreath{
    0%,100%{opacity:.55;transform:translate(-50%,-50%) scale(1);}
    50%{opacity:.9;transform:translate(-50%,-50%) scale(1.06);}
}

.rings{
    position:absolute;
    top:50%;left:50%;
    width:0;height:0;
    pointer-events:none;
    z-index:1;
}
.ring{
    position:absolute;top:0;left:0;
    border-radius:50%;
    border:1.5px solid transparent;
    will-change:transform;
    pointer-events:none;
}
.r1{
    width:360px;height:360px;
    margin:-180px 0 0 -180px;
    border-top-color:rgba(212,175,55,.55);
    border-right-color:rgba(212,175,55,.08);
    animation:spinCW 16s linear infinite;
}
.r2{
    width:480px;height:480px;
    margin:-240px 0 0 -240px;
    border-top-color:rgba(212,175,55,.35);
    border-left-color:rgba(212,175,55,.06);
    animation:spinCCW 22s linear infinite;
}
.r3{
    width:620px;height:620px;
    margin:-310px 0 0 -310px;
    border-top-color:rgba(212,175,55,.2);
    border-right-color:rgba(212,175,55,.04);
    animation:spinCW 32s linear infinite;
}
.r4{
    width:780px;height:780px;
    margin:-390px 0 0 -390px;
    border-top-color:rgba(212,175,55,.12);
    border-right-color:rgba(212,175,55,.02);
    animation:spinCCW 44s linear infinite;
}
@keyframes spinCCW{from{transform:rotate(0);}to{transform:rotate(-360deg);}}

/* ============ LOGO BESAR DI TENGAH (antara ring 2 & 3) ============ */
.ring-logo{
    position:absolute;
    top:50%;left:50%;
    width:550px;height:550px;
    margin:-275px 0 0 -275px;
    display:flex;
    align-items:center;
    justify-content:center;
    pointer-events:none;
    z-index:0;
}
.ring-logo img{
    width:100%;height:100%;
    object-fit:contain;
    opacity:.16;
    filter:
        drop-shadow(0 0 30px rgba(212,175,55,.55))
        drop-shadow(0 0 70px rgba(212,175,55,.3));
    animation:bgLogoBreath 7s ease-in-out infinite;
    will-change:opacity,transform;
}
@keyframes bgLogoBreath{
    0%,100%{opacity:.13;transform:scale(1);}
    50%{opacity:.22;transform:scale(1.02);}
}

/* Dot di tepi tiap ring */
.rd{
    position:absolute;top:50%;left:50%;
    border-radius:50%;
    background:var(--gold-3);
    box-shadow:0 0 10px var(--gold-3),0 0 20px rgba(212,175,55,.5);
}
.r1 .rd{width:6px;height:6px;margin:-3px 0 0 -3px;transform:translateY(-180px);}
.r2 .rd{width:5px;height:5px;margin:-2.5px 0 0 -2.5px;transform:translateY(-240px);opacity:.85;}
.r3 .rd{width:4px;height:4px;margin:-2px 0 0 -2px;transform:translateY(-310px);opacity:.65;}
.r4 .rd{width:3px;height:3px;margin:-1.5px 0 0 -1.5px;transform:translateY(-390px);opacity:.45;}

@media(max-width:991px){
    .rings{transform:scale(.85);}
}
@media(max-width:600px){
    .rings{transform:scale(.68);}
}

/* ============ LOGIN CARD — LEBIH TRANSPARAN ============ */
.card{
    position:relative;
    z-index:5;
    width:100%;
    max-width:400px;
    background:rgba(5,5,5,.20);
    backdrop-filter:blur(5px) saturate(140%);
    -webkit-backdrop-filter:blur(5px) saturate(140%);
    border:1px solid rgba(212,175,55,.24);
    border-radius:20px;
    padding:34px 30px 26px;
    box-shadow:
        0 30px 80px rgba(0,0,0,.65),
        0 0 60px rgba(212,175,55,.07),
        0 1px 0 rgba(255,215,0,.12) inset,
        0 0 0 1px rgba(255,215,0,.03) inset;
    animation:cardIn .9s cubic-bezier(.22,1,.36,1);
}
@keyframes cardIn{
    from{opacity:0;transform:translateY(26px) scale(.98);}
    to{opacity:1;transform:translateY(0) scale(1);}
}
.card::before{
    content:'';
    position:absolute;
    top:0;left:22%;right:22%;
    height:1px;
    background:linear-gradient(90deg,transparent,rgba(255,215,0,.6),transparent);
    border-radius:20px 20px 0 0;
}
.card::after{
    content:'';
    position:absolute;
    top:0;left:0;right:0;
    height:45%;
    background:linear-gradient(180deg,rgba(212,175,55,.06) 0%,transparent 100%);
    border-radius:20px 20px 0 0;
    pointer-events:none;
}

.brand{
    display:flex;
    align-items:center;
    gap:13px;
    margin-bottom:26px;
    padding-bottom:22px;
    border-bottom:1px solid rgba(212,175,55,.12);
}
.brand img{
    width:46px;height:46px;
    border-radius:12px;
    background:#000;
    padding:4px;
    border:1px solid rgba(212,175,55,.3);
    object-fit:cover;
    box-shadow:0 4px 14px rgba(212,175,55,.15);
    flex-shrink:0;
}
.brand-text .bn{
    font-family:'Cinzel',serif;
    font-weight:700;
    font-size:15px;
    letter-spacing:1px;
    color:#fff;
    line-height:1.2;
    background:var(--gold-grad);background-size:200% auto;
    -webkit-background-clip:text;background-clip:text;
    -webkit-text-fill-color:transparent;
    animation:shineText 4s linear infinite;
}
.brand-text .bs{
    font-size:9.5px;
    color:var(--gold);
    letter-spacing:2px;
    text-transform:uppercase;
    font-weight:600;
    margin-top:3px;
    opacity:.8;
}

.form-head{margin-bottom:22px;}
.form-head h1{
    font-size:21px;
    font-weight:700;
    color:#fff;
    letter-spacing:-.3px;
    margin-bottom:6px;
    line-height:1.25;
    text-shadow:0 2px 8px rgba(0,0,0,.6);
}
.form-head p{
    font-size:13px;
    color:var(--text-mute);
    line-height:1.5;
    text-shadow:0 1px 4px rgba(0,0,0,.5);
}

.alert{
    display:flex;align-items:flex-start;gap:10px;
    padding:11px 13px;
    border-radius:10px;
    font-size:12.5px;line-height:1.5;
    margin-bottom:18px;
    background:rgba(255,107,107,.08);
    border:1px solid rgba(255,107,107,.25);
    color:#f0a0a0;
    animation:shake .4s ease-out;
    backdrop-filter:blur(4px);
    -webkit-backdrop-filter:blur(4px);
}
.alert i{color:var(--danger);font-size:15px;margin-top:1px;flex-shrink:0;}
@keyframes shake{
    0%,100%{transform:translateX(0);}
    25%{transform:translateX(-5px);}
    75%{transform:translateX(5px);}
}

.field{margin-bottom:15px;}
.field label{
    display:block;
    font-size:11.5px;
    font-weight:600;
    color:#d0d0d0;
    margin-bottom:7px;
    letter-spacing:.15px;
    text-shadow:0 1px 4px rgba(0,0,0,.6);
}
.wrap{
    position:relative;
    display:flex;
    align-items:center;
    background:rgba(0,0,0,.45);
    border:1px solid rgba(212,175,55,.14);
    border-radius:10px;
    transition:border-color .2s,background .2s,box-shadow .2s;
    overflow:hidden;
}
.wrap:focus-within{
    border-color:rgba(212,175,55,.6);
    background:rgba(20,15,0,.55);
    box-shadow:0 0 0 3px rgba(212,175,55,.12);
}
.wrap .ic{
    padding:0 13px;
    font-size:15px;
    color:#666;
    display:flex;
    align-items:center;
    justify-content:center;
    transition:color .2s;
    flex-shrink:0;
}
.wrap:focus-within .ic{color:var(--gold-3);}
.wrap input{
    flex:1;
    background:transparent;
    border:none;
    outline:none;
    color:#fff;
    font-size:14.5px;
    padding:13px 14px 13px 0;
    min-width:0;
    letter-spacing:.1px;
}
.wrap input:-webkit-autofill,
.wrap input:-webkit-autofill:hover,
.wrap input:-webkit-autofill:focus{
    -webkit-text-fill-color:#fff;
    -webkit-box-shadow:0 0 0 1000px rgba(0,0,0,.55) inset;
    transition:background-color 5000s ease-in-out 0s;
    caret-color:#fff;
}
.toggle{
    background:transparent;border:none;outline:none;
    color:#666;
    padding:0 13px;
    cursor:pointer;
    font-size:15px;
    line-height:1;
    transition:color .2s;
    flex-shrink:0;
    touch-action:manipulation;
}
.toggle:hover,.toggle:active{color:var(--gold-3);}

.btn{
    width:100%;
    background:var(--gold-grad-2);
    color:#000;
    font-weight:700;
    font-size:13.5px;
    letter-spacing:.6px;
    text-transform:uppercase;
    border:none;
    border-radius:10px;
    padding:14px;
    cursor:pointer;
    display:flex;align-items:center;justify-content:center;gap:9px;
    transition:transform .18s,box-shadow .18s;
    box-shadow:0 6px 22px rgba(212,175,55,.25);
    margin-top:6px;
    touch-action:manipulation;
    position:relative;
    overflow:hidden;
}
.btn::before{
    content:'';
    position:absolute;
    top:0;left:-100%;
    width:100%;height:100%;
    background:linear-gradient(90deg,transparent,rgba(255,255,255,.4),transparent);
    transition:left .65s ease;
}
.btn:hover::before{left:100%;}
.btn:active{transform:translateY(1px);}
.btn:disabled{opacity:.72;cursor:not-allowed;}
.btn .spin{
    width:15px;height:15px;
    border:2px solid rgba(0,0,0,.2);
    border-top-color:#000;
    border-radius:50%;
    animation:sp .7s linear infinite;
    display:none;
}
@keyframes sp{to{transform:rotate(360deg);}}
.btn.loading .spin{display:block;}
.btn.loading .ic-btn{display:none;}

.note{
    display:flex;align-items:center;justify-content:center;gap:6px;
    margin-top:16px;
    font-size:11px;
    color:var(--text-fade);
    line-height:1.5;
    text-align:center;
    text-shadow:0 1px 4px rgba(0,0,0,.5);
}
.note i{color:#4a4a4a;font-size:12px;}

.copy{
    text-align:center;
    margin-top:22px;
    padding-top:16px;
    border-top:1px solid rgba(212,175,55,.1);
    font-size:10.5px;
    color:#4a4a4a;
    line-height:1.7;
    letter-spacing:.2px;
}
.copy .cn{
    display:block;
    margin-top:2px;
    color:#666;
    font-weight:500;
    font-size:10.5px;
}
.copy .cr{
    display:block;
    margin-top:1px;
    font-size:9.5px;
    color:#3a3a3a;
    letter-spacing:.6px;
    text-transform:uppercase;
}

@media(max-width:480px){
    .screen{padding:14px;}
    .card{padding:28px 22px 22px;border-radius:18px;max-width:100%;}
    .brand img{width:42px;height:42px;}
    .brand-text .bn{font-size:13.5px;letter-spacing:.8px;}
    .brand-text .bs{font-size:9px;letter-spacing:1.8px;}
    .form-head h1{font-size:19px;}
    .form-head p{font-size:12.5px;}
    .wrap input{font-size:14px;padding:12px 12px 12px 0;}
    .wrap .ic{padding:0 11px;font-size:14px;}
    .toggle{padding:0 11px;font-size:14px;}
    .btn{padding:13px;font-size:13px;}
}
@media(max-height:640px){
    .card{padding:22px 22px 18px;}
    .brand{margin-bottom:18px;padding-bottom:16px;}
    .form-head{margin-bottom:16px;}
    .field{margin-bottom:12px;}
    .form-head h1{font-size:18px;}
}
@media(prefers-reduced-motion:reduce){
    *,*::before,*::after{
        animation-duration:.01ms!important;
        animation-iteration-count:1!important;
        transition-duration:.01ms!important;
    }
}
</style>
</head>
<body>

<!-- ============ PRELOADER ============ -->
<div class="preloader" id="preloader">
    <div class="pre-stage">
        <div class="pre-glow"></div>
        <div class="pre-ring pre-r1"></div>
        <div class="pre-ring pre-r2"></div>
        <div class="pre-ring pre-r3"></div>
        <div class="pre-ring pre-r4"></div>
        <img src="https://carsotocare.com/icon.webp" alt="CarsoToCare" class="pre-logo">
    </div>
    <div class="pre-brand">
        <div class="pre-name">CARSOTOCARE</div>
        <div class="pre-sub">Management System</div>
    </div>
    <div class="pre-bar">
        <div class="pre-bar-fill" id="preBar"></div>
    </div>
    <div class="pre-pct" id="prePct">0%</div>
</div>

<!-- ============ SCREEN ============ -->
<div class="screen">

    <div class="ambient" aria-hidden="true">
        <div class="grid"></div>
        <div class="glow"></div>
        <div class="rings">
            <!-- Logo besar di antara ring 2 & 3 -->
            <div class="ring-logo">
                <img src="https://carsotocare.com/icon.webp" alt="">
            </div>

            <div class="ring r1"><span class="rd"></span></div>
            <div class="ring r2"><span class="rd"></span></div>
            <div class="ring r3"><span class="rd"></span></div>
            <div class="ring r4"><span class="rd"></span></div>
        </div>
    </div>

    <div class="card">

        <div class="brand">
            <img src="https://carsotocare.com/icon.webp" alt="CarsoToCare">
            <div class="brand-text">
                <div class="bn">CARSOTOCARE</div>
                <div class="bs">Management System</div>
            </div>
        </div>

        <div class="form-head">
            <h1>Masuk ke Akun</h1>
            <p>Gunakan username dan password Anda untuk melanjutkan.</p>
        </div>

        <?php if ($error): ?>
        <div class="alert">
            <i class="bi bi-exclamation-circle"></i>
            <div><?= htmlspecialchars($error) ?></div>
        </div>
        <?php endif; ?>

        <form method="POST" action="" autocomplete="off" onsubmit="return handleSubmit(this)" novalidate>

            <div class="field">
                <label for="usernameInput">Username</label>
                <div class="wrap">
                    <span class="ic"><i class="bi bi-person"></i></span>
                    <input type="text" id="usernameInput" name="username"
                           placeholder="Masukkan username"
                           required autofocus autocomplete="username"
                           spellcheck="false" autocapitalize="none">
                </div>
            </div>

            <div class="field">
                <label for="passwordInput">Password</label>
                <div class="wrap">
                    <span class="ic"><i class="bi bi-lock"></i></span>
                    <input type="password" id="passwordInput" name="password"
                           placeholder="Masukkan password"
                           required autocomplete="current-password">
                    <button type="button" class="toggle" onclick="togglePassword()" aria-label="Tampilkan password" tabindex="-1">
                        <i class="bi bi-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn" id="submitBtn">
                <span class="spin"></span>
                <i class="bi bi-box-arrow-in-right ic-btn"></i>
                <span class="btn-text">Masuk</span>
            </button>

        </form>

        <div class="note">
            <i class="bi bi-shield-lock"></i>
            <span>Koneksi terenkripsi &bull; Sesi berakhir otomatis setelah 1 jam</span>
        </div>

        <div class="copy">
            <span>&copy; <?= date('Y') ?> CarsoToCare</span>
            <span class="cn">Jervent Zergio Hersantio</span>
            <span class="cr">All Rights Reserved</span>
        </div>

    </div>

</div>

<script>
(function(){
    'use strict';

    /* ========== ANTI-ZOOM & ANTI-GERAK ========== */
    document.addEventListener('gesturestart', function(e){ e.preventDefault(); }, {passive:false});
    document.addEventListener('gesturechange', function(e){ e.preventDefault(); }, {passive:false});
    document.addEventListener('gestureend', function(e){ e.preventDefault(); }, {passive:false});

    var lastTouch = 0;
    document.addEventListener('touchend', function(e){
        var now = Date.now();
        if (now - lastTouch <= 300) e.preventDefault();
        lastTouch = now;
    }, {passive:false});

    document.addEventListener('touchstart', function(e){
        if (e.touches.length > 1) e.preventDefault();
    }, {passive:false});

    document.addEventListener('wheel', function(e){
        if (e.ctrlKey) e.preventDefault();
    }, {passive:false});

    document.addEventListener('keydown', function(e){
        if ((e.ctrlKey || e.metaKey) && (e.key === '+' || e.key === '-' || e.key === '=' || e.key === '0')) {
            e.preventDefault();
        }
    });

    window.addEventListener('scroll', function(){ window.scrollTo(0,0); }, {passive:true});

    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', function(){ window.scrollTo(0,0); });
    }

    /* ========== PRELOADER ========== */
    (function(){
        var pre = document.getElementById('preloader');
        var bar = document.getElementById('preBar');
        var pct = document.getElementById('prePct');
        if (!pre) return;

        var progress = 0;
        var startTime = Date.now();
        var MIN = 1300;

        var iv = setInterval(function(){
            progress += Math.random() * 14 + 4;
            if (progress >= 100) {
                progress = 100;
                clearInterval(iv);
                scheduleExit();
            }
            if (bar) bar.style.width = progress + '%';
            if (pct) pct.textContent = Math.floor(progress) + '%';
        }, 110);

        function scheduleExit(){
            var elapsed = Date.now() - startTime;
            var wait = Math.max(0, MIN - elapsed);
            setTimeout(exit, wait + 300);
        }

        function exit(){
            pre.classList.add('exit');
            setTimeout(function(){
                pre.style.display = 'none';
            }, 850);
        }

        setTimeout(function(){
            if (!pre.classList.contains('exit')) {
                if (bar) bar.style.width = '100%';
                if (pct) pct.textContent = '100%';
                clearInterval(iv);
                exit();
            }
        }, 5000);
    })();

    /* ========== PASSWORD TOGGLE ========== */
    window.togglePassword = function(){
        var input = document.getElementById('passwordInput');
        var icon = document.getElementById('eyeIcon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'bi bi-eye-slash';
        } else {
            input.type = 'password';
            icon.className = 'bi bi-eye';
        }
        input.focus();
    };

    /* ========== SUBMIT ========== */
    window.handleSubmit = function(form){
        var btn = document.getElementById('submitBtn');
        var u = form.querySelector('input[name="username"]').value.trim();
        var p = form.querySelector('input[name="password"]').value;
        if (!u || !p) return false;
        if (btn.classList.contains('loading')) return false;

        btn.classList.add('loading');
        btn.disabled = true;
        btn.querySelector('.btn-text').textContent = 'Memproses';

        setTimeout(function(){
            if (btn.classList.contains('loading')) {
                btn.classList.remove('loading');
                btn.disabled = false;
                btn.querySelector('.btn-text').textContent = 'Masuk';
            }
        }, 8000);
        return true;
    };

    /* ========== ENTER: USERNAME → PASSWORD ========== */
    var u = document.getElementById('usernameInput');
    if (u) {
        u.addEventListener('keydown', function(e){
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('passwordInput').focus();
            }
        });
    }
})();
</script>
</body>
</html>
