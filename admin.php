<?php
/**
 * admin.php — Yönetim paneli
 * Giriş yaptıktan sonra sitedeki tüm içerikleri düzenleyebilirsiniz.
 * Veriler index.php içindeki veri katmanı aracılığıyla veriler.txt'ye yazılır.
 * NOT: Bu dosya UTF-8 (BOM'suz) kaydedilmelidir.
 *
 * !!! ŞİFREYİ MUTLAKA DEĞİŞTİRİN (aşağıdaki ADMIN_SIFRE satırı) !!!
 */

define('ADMIN_MODU', true);
require __DIR__ . '/index.php';

const ADMIN_SIFRE = 'Kaya-Diyet-7391';
const VARSAYILAN_SIFRE = 'Kaya-Diyet-7391';

/* ------------------------------------------------------------------
 * OTURUM VE GÜVENLİK BAŞLIKLARI
 * ------------------------------------------------------------------ */
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: DENY');

ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params(0, '/', '', $https, true);
session_start();

function sifre_dogru($girilen)
{
    $girilen = (string)$girilen;
    if (substr(ADMIN_SIFRE, 0, 4) === '$2y$') {
        return password_verify($girilen, ADMIN_SIFRE);   // şifre hash olarak saklanmışsa
    }
    return hash_equals(ADMIN_SIFRE, $girilen);
}

function csrf_uret()
{
    if (empty($_SESSION['csrf'])) {
        try {
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
        } catch (Exception $ex) {
            $_SESSION['csrf'] = md5(uniqid((string)mt_rand(), true));
        }
    }
    return $_SESSION['csrf'];
}

function csrf_dogru()
{
    return isset($_POST['csrf'], $_SESSION['csrf'])
        && is_string($_POST['csrf'])
        && hash_equals((string)$_SESSION['csrf'], $_POST['csrf']);
}

function flash($tip, $metin)
{
    $_SESSION['flash'] = array($tip, $metin);
}

function yonlendir($hedef)
{
    header('Location: ' . $hedef);
    exit;
}

/* ------------------------------------------------------------------
 * ŞEMA: hangi bölümde hangi alanlar var (tek kaynak)
 * alan => array('Etiket', 'tip')   tipler: text, textarea, url, tel, eposta, sayi
 * ------------------------------------------------------------------ */
function sema()
{
    return array(
        'site' => array(
            'baslik' => 'Genel Bilgiler', 'tip' => 'alanlar',
            'alanlar' => array(
                'ad' => array('Ad Soyad (başlıkta görünür)', 'text'),
                'unvan' => array('Unvan', 'text'),
                'aciklama' => array('Sayfa açıklaması (Google için)', 'textarea'),
                'whatsapp' => array('WhatsApp numarası (905xxxxxxxxx)', 'tel'),
                'telefon' => array('Telefon', 'text'),
                'eposta' => array('E-posta', 'eposta'),
                'adres' => array('Adres', 'textarea'),
                'harita' => array('Harita bağlantısı', 'url'),
                'saatler' => array('Çalışma saatleri (her satıra bir gün)', 'textarea'),
                'instagram' => array('Instagram bağlantısı', 'url'),
                'linkedin' => array('LinkedIn bağlantısı', 'url'),
            ),
        ),
        'hero' => array(
            'baslik' => 'Hero Slaytları', 'tip' => 'liste', 'ana' => 'baslik',
            'alanlar' => array(
                'baslik' => array('Başlık', 'text'),
                'alt' => array('Alt metin', 'textarea'),
                'gorsel' => array('Görsel bağlantısı', 'url'),
                'buton' => array('Buton yazısı', 'text'),
                'link' => array('Buton bağlantısı', 'url'),
            ),
        ),
        'hizmetler' => array(
            'baslik' => 'Hizmetler', 'tip' => 'liste', 'ana' => 'baslik',
            'alanlar' => array(
                'baslik' => array('Başlık', 'text'),
                'metin' => array('Açıklama', 'textarea'),
            ),
        ),
        'sayilar' => array(
            'baslik' => 'Sayılar', 'tip' => 'liste', 'ana' => 'etiket',
            'alanlar' => array(
                'deger' => array('Değer', 'sayi'),
                'etiket' => array('Etiket', 'text'),
            ),
        ),
        'galeri' => array(
            'baslik' => 'Galeri', 'tip' => 'liste', 'ana' => 'kaynak',
            'alanlar' => array(
                'kaynak' => array('Görsel bağlantısı veya YouTube bağlantısı', 'url'),
                'baslik' => array('Açıklama', 'text'),
            ),
        ),
        'hakkinda' => array(
            'baslik' => 'Hakkında', 'tip' => 'alanlar',
            'alanlar' => array(
                'baslik' => array('Başlık', 'text'),
                'metin' => array('Metin (paragraflar arasında boş satır bırakın)', 'textarea'),
            ),
        ),
        'blog' => array(
            'baslik' => 'Blog', 'tip' => 'liste', 'ana' => 'baslik', 'id' => true,
            'alanlar' => array(
                'baslik' => array('Başlık', 'text'),
                'ozet' => array('Kısa özet', 'textarea'),
                'icerik' => array('Yazı (paragraflar arasında boş satır bırakın)', 'textarea'),
            ),
        ),
        'yorumlar' => array(
            'baslik' => 'Yorumlar', 'tip' => 'liste', 'ana' => 'metin',
            'alanlar' => array(
                'isim' => array('İsim', 'text'),
                'metin' => array('Yorum', 'textarea'),
            ),
        ),
        'sss' => array(
            'baslik' => 'S.S.S.', 'tip' => 'liste', 'ana' => 'soru',
            'alanlar' => array(
                'soru' => array('Soru', 'text'),
                'cevap' => array('Cevap', 'textarea'),
            ),
        ),
    );
}

/* ------------------------------------------------------------------
 * GİRDİ TEMİZLEME VE KAYDETME
 * ------------------------------------------------------------------ */
function tek_satir($s)
{
    $t = preg_replace('/\s+/u', ' ', $s);
    return trim($t === null ? $s : $t);
}

function temizle($ham, $tip, &$uyarilar, $etiket)
{
    $ham = str_replace("\0", '', (string)$ham);

    switch ($tip) {
        case 'textarea':
            return kisalt(trim(str_replace(array("\r\n", "\r"), "\n", $ham)), 10000);

        case 'url':
            $d = trim($ham);
            $t = url_ok($d);
            if ($d !== '' && $t === '') {
                $uyarilar[] = '"' . $etiket . '" geçerli bir bağlantı değil, boş bırakıldı.';
            }
            return kisalt($t, 500);

        case 'tel':
            $d = preg_replace('/\D+/', '', $ham);
            if (strlen($d) === 11 && $d[0] === '0') {
                $d = '9' . $d;        // 0545... -> 90545...
            } elseif (strlen($d) === 10 && $d[0] === '5') {
                $d = '90' . $d;       // 545...  -> 90545...
            }
            return substr($d, 0, 15);

        case 'eposta':
            $d = trim($ham);
            if ($d !== '' && !filter_var($d, FILTER_VALIDATE_EMAIL)) {
                $uyarilar[] = 'E-posta adresi geçerli değil, boş bırakıldı.';
                return '';
            }
            return kisalt($d, 150);

        case 'sayi':
            $n = (int)preg_replace('/\D+/', '', $ham);
            return min($n, 1000000);

        default:
            return kisalt(tek_satir($ham), 300);
    }
}

function bolum_isle($veri, $b, $s, $post, $ekle, &$uyarilar)
{
    // Tek parça alanlar (Genel Bilgiler, Hakkında)
    if ($s['tip'] === 'alanlar') {
        $f = (isset($post['f']) && is_array($post['f'])) ? $post['f'] : array();
        if (!isset($veri[$b]) || !is_array($veri[$b])) {
            $veri[$b] = array();
        }
        foreach ($s['alanlar'] as $ad => $t) {
            $ham = (isset($f[$ad]) && is_string($f[$ad])) ? $f[$ad] : '';
            $veri[$b][$ad] = temizle($ham, $t[1], $uyarilar, $t[0]);
        }
        return $veri;
    }

    // Liste bölümleri
    $gelen = (isset($post['s']) && is_array($post['s'])) ? $post['s'] : array();
    $id_var = !empty($s['id']);

    // En büyük kimliği bul (kayıtlı + gelen)
    $en_buyuk = 0;
    if ($id_var) {
        $kaynaklar = array();
        if (isset($veri[$b]) && is_array($veri[$b])) {
            $kaynaklar = array_merge($kaynaklar, $veri[$b]);
        }
        $kaynaklar = array_merge($kaynaklar, $gelen);
        foreach ($kaynaklar as $k) {
            if (is_array($k) && isset($k['id']) && is_scalar($k['id'])) {
                $en_buyuk = max($en_buyuk, (int)$k['id']);
            }
        }
    }

    $yeni = array();
    $eksik_ana = '';
    foreach ($gelen as $satir) {
        if (!is_array($satir) || !empty($satir['sil'])) {
            continue;
        }
        $kayit = array();
        foreach ($s['alanlar'] as $ad => $t) {
            $ham = (isset($satir[$ad]) && is_string($satir[$ad])) ? $satir[$ad] : '';
            $kayit[$ad] = temizle($ham, $t[1], $uyarilar, $t[0]);
        }
        // Tamamen boş bölüm: sessizce atla (yeni eklenmiş ama doldurulmamış)
        $tamamen_bos = true;
        foreach ($s['alanlar'] as $ad => $t) {
            if ($t[1] === 'sayi' ? ((int)$kayit[$ad] !== 0) : (trim((string)$kayit[$ad]) !== '')) {
                $tamamen_bos = false;
            }
        }
        if ($tamamen_bos) {
            continue;
        }
        // Ana alanı boş ama başka alanları dolu: SİLME, içeriği koru ve uyar
        if (trim((string)$kayit[$s['ana']]) === '') {
            $eksik_ana = $s['alanlar'][$s['ana']][0];
        }
        if ($id_var) {
            $id = (isset($satir['id']) && is_scalar($satir['id'])) ? (int)$satir['id'] : 0;
            if ($id <= 0) {
                $en_buyuk++;
                $id = $en_buyuk;
            }
            $kayit = array_merge(array('id' => $id), $kayit);
        }
        $yeni[] = $kayit;
        if (count($yeni) >= 100) {
            break;
        }
    }

    if ($eksik_ana !== '') {
        $uyarilar[] = '"' . $eksik_ana . '" alanı boş olan bölümler var. İçerikleri silinmedi ama sitede görünmeleri için bu alanı doldurun.';
    }

    // "Yeni satır ekle": en sona boş bir satır koy
    if ($ekle && count($yeni) < 100) {
        $bos = array();
        foreach ($s['alanlar'] as $ad => $t) {
            $bos[$ad] = ($t[1] === 'sayi') ? 0 : '';
        }
        if ($id_var) {
            $en_buyuk++;
            $bos = array_merge(array('id' => $en_buyuk), $bos);
        }
        $yeni[] = $bos;
    }

    $veri[$b] = $yeni;
    return $veri;
}

/* ------------------------------------------------------------------
 * İSTEK İŞLEME
 * ------------------------------------------------------------------ */
$giris = !empty($_SESSION['admin']);
$sema = sema();

$bolum = (isset($_GET['bolum']) && is_string($_GET['bolum'])) ? $_GET['bolum'] : 'site';
if (!isset($sema[$bolum]) && $bolum !== 'mesajlar' && $bolum !== 'sifre') {
    $bolum = 'site';
}

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $islem = post_str('islem');

    if (!csrf_dogru()) {
        flash('hata', 'Oturum doğrulanamadı. Sayfayı yenileyip tekrar deneyin.');
        yonlendir('?');
    }

    // --- Giriş ---
    if ($islem === 'giris') {
        if (time() < (int)(isset($_SESSION['kilit_bitis']) ? $_SESSION['kilit_bitis'] : 0)) {
            flash('hata', 'Çok fazla hatalı deneme yapıldı. Bir dakika bekleyip tekrar deneyin.');
            yonlendir('?');
        }
        if (sifre_dogru(post_str('sifre'))) {
            session_regenerate_id(true);
            $_SESSION['admin'] = true;
            $_SESSION['hatali'] = 0;
            yonlendir('?');
        }
        usleep(800000);
        $_SESSION['hatali'] = (isset($_SESSION['hatali']) ? (int)$_SESSION['hatali'] : 0) + 1;
        if ($_SESSION['hatali'] >= 5) {
            $_SESSION['kilit_bitis'] = time() + 60;
            $_SESSION['hatali'] = 0;
        }
        flash('hata', 'Şifre hatalı.');
        yonlendir('?');
    }

    // Bundan sonrası için giriş şart
    if (!$giris) {
        yonlendir('?');
    }

    // --- Çıkış ---
    if ($islem === 'cikis') {
        $_SESSION = array();
        session_destroy();
        yonlendir('?');
    }

    // --- Bölüm kaydet / satır ekle ---
    if ($islem === 'bolum') {
        $b = post_str('bolum');
        if (!isset($sema[$b])) {
            yonlendir('?');
        }
        $veri_admin = veri_oku();
        $uyarilar = array();
        $veri_admin = bolum_isle($veri_admin, $b, $sema[$b], $_POST, post_str('eylem') === 'ekle', $uyarilar);
        if (veri_yaz($veri_admin)) {
            flash($uyarilar ? 'uyari' : 'ok', 'Kaydedildi.' . ($uyarilar ? ' ' . implode(' ', $uyarilar) : ''));
        } else {
            flash('hata', veri_hata_metni());
        }
        yonlendir('?bolum=' . $b);
    }

    // --- Mesaj sil ---
    if ($islem === 'mesaj_sil') {
        $veri_admin = veri_oku();
        $idx = (int)post_str('idx');
        if (isset($veri_admin['mesajlar'][$idx])) {
            unset($veri_admin['mesajlar'][$idx]);
            $veri_admin['mesajlar'] = array_values($veri_admin['mesajlar']);
            if (veri_yaz($veri_admin)) {
                flash('ok', 'Mesaj silindi.');
            } else {
                flash('hata', veri_hata_metni());
            }
        }
        yonlendir('?bolum=mesajlar');
    }

    // --- Şifre hash üretici ---
    if ($islem === 'hash') {
        $yeni = post_str('yeni_sifre');
        if (uzunluk($yeni) < 8) {
            flash('hata', 'Şifre en az 8 karakter olmalı.');
        } else {
            $_SESSION['hash_sonuc'] = password_hash($yeni, PASSWORD_DEFAULT);
        }
        yonlendir('?bolum=sifre');
    }

    yonlendir('?');
}

/* ------------------------------------------------------------------
 * GÖRÜNÜM YARDIMCILARI
 * ------------------------------------------------------------------ */
function sayfa_ust($baslik)
{
    ?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($baslik) ?> — Yönetim</title>
<style>
body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;margin:0;background:#f4f5f7;color:#222;line-height:1.5}
.kap{max-width:900px;margin:0 auto;padding:16px}
nav.menu{display:flex;flex-wrap:wrap;gap:6px;margin:12px 0 20px}
nav.menu a{padding:6px 12px;background:#fff;border:1px solid #d5d8de;border-radius:6px;text-decoration:none;color:#222;font-size:14px}
nav.menu a.aktif{background:#222;color:#fff;border-color:#222}
.kart{background:#fff;border:1px solid #e1e4e8;border-radius:8px;padding:16px;margin-bottom:16px}
label{display:block;font-size:14px;font-weight:600;margin-top:10px}
input[type=text],input[type=email],input[type=number],input[type=password],textarea{width:100%;box-sizing:border-box;padding:8px;font:inherit;border:1px solid #c5c9d1;border-radius:6px;margin-top:4px}
button{padding:9px 16px;border:0;border-radius:6px;background:#222;color:#fff;font:inherit;cursor:pointer}
button.ikinci{background:#fff;color:#222;border:1px solid #c5c9d1}
button.tehlike{background:#b3261e}
.bildirim{padding:10px 14px;border-radius:6px;margin-bottom:14px}
.bildirim.ok{background:#e8f5e9;border:1px solid #c8e6c9}
.bildirim.hata{background:#fdecea;border:1px solid #f5c6cb}
.bildirim.uyari{background:#fff8e1;border:1px solid #ffe082}
.satir{border:1px dashed #c5c9d1;border-radius:8px;padding:12px;margin:12px 0}
.ustbar{display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap}
.kucuk{font-size:13px;color:#666}
</style>
</head>
<body>
<div class="kap">
<?php
}

function sayfa_alt()
{
    ?>
</div>
</body>
</html>
<?php
}

function alan_giris($onek, $isim, $tanim, $deger)
{
    $etiket = $tanim[0];
    $tip = $tanim[1];
    $ad = e($onek . '[' . $isim . ']');
    echo '<label>' . e($etiket);
    if ($tip === 'textarea') {
        $satir = (strpos($isim, 'icerik') !== false || $isim === 'metin') ? 8 : 3;
        echo '<textarea name="' . $ad . '" rows="' . $satir . '">' . e($deger) . '</textarea>';
    } elseif ($tip === 'sayi') {
        echo '<input type="number" min="0" max="1000000" name="' . $ad . '" value="' . (int)$deger . '">';
    } elseif ($tip === 'eposta') {
        echo '<input type="email" name="' . $ad . '" value="' . e($deger) . '">';
    } else {
        $mod = ($tip === 'tel') ? ' inputmode="numeric"' : (($tip === 'url') ? ' inputmode="url"' : '');
        echo '<input type="text"' . $mod . ' name="' . $ad . '" value="' . e($deger) . '">';
    }
    echo '</label>';
}

/* ------------------------------------------------------------------
 * SAYFA ÇİZİMİ
 * ------------------------------------------------------------------ */
$flash = null;
if (isset($_SESSION['flash']) && is_array($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
}
$csrf = csrf_uret();

/* ---- Giriş ekranı ---- */
if (!$giris) {
    sayfa_ust('Giriş');
    ?>
    <div class="kart" style="max-width:420px;margin:40px auto">
        <h1 style="margin-top:0">Yönetim Girişi</h1>
        <?php if ($flash): ?><div class="bildirim <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>
        <form method="post" action="?">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="islem" value="giris">
            <label>Şifre
                <input type="password" name="sifre" autocomplete="current-password" autofocus required>
            </label>
            <p><button type="submit">Giriş Yap</button></p>
        </form>
    </div>
    <?php
    sayfa_alt();
    exit;
}

/* ---- Panel ---- */
$veri_goster = veri_oku();
$mesaj_sayisi = isset($veri_goster['mesajlar']) && is_array($veri_goster['mesajlar']) ? count($veri_goster['mesajlar']) : 0;

sayfa_ust('Panel');
?>
<div class="ustbar">
    <h1 style="margin:0">Yönetim Paneli</h1>
    <div>
        <a href="./" target="_blank" rel="noopener">Siteyi gör</a>
        <form method="post" action="?" style="display:inline">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="islem" value="cikis">
            <button type="submit" class="ikinci">Çıkış</button>
        </form>
    </div>
</div>

<nav class="menu">
<?php foreach ($sema as $anahtar => $s): ?>
    <a href="?bolum=<?= e($anahtar) ?>" class="<?= $bolum === $anahtar ? 'aktif' : '' ?>"><?= e($s['baslik']) ?></a>
<?php endforeach; ?>
    <a href="?bolum=mesajlar" class="<?= $bolum === 'mesajlar' ? 'aktif' : '' ?>">Mesajlar (<?= (int)$mesaj_sayisi ?>)</a>
    <a href="?bolum=sifre" class="<?= $bolum === 'sifre' ? 'aktif' : '' ?>">Şifre</a>
</nav>

<?php if (ADMIN_SIFRE === VARSAYILAN_SIFRE): ?>
    <div class="bildirim uyari">Varsayılan şifre kullanılıyor. admin.php dosyasındaki <strong>ADMIN_SIFRE</strong> satırını değiştirin.</div>
<?php endif; ?>
<?php if (veri_bozuk_mu()): ?>
    <div class="bildirim hata"><strong>Dikkat:</strong> veriler.txt dosyası bozuk görünüyor, bu yüzden kaydetme kapatıldı (mevcut veri ezilmesin diye). Aşağıdaki formlarda varsayılan içerik görünüyor olabilir. Dosyayı düzeltin ya da silin.</div>
<?php endif; ?>
<?php if ($flash): ?><div class="bildirim <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>

<?php if ($bolum === 'mesajlar'): ?>

    <div class="kart">
        <h2 style="margin-top:0">Gelen Mesajlar</h2>
        <?php
        $mesajlar = isset($veri_goster['mesajlar']) && is_array($veri_goster['mesajlar']) ? $veri_goster['mesajlar'] : array();
        if (!$mesajlar) {
            echo '<p class="kucuk">Henüz mesaj yok.</p>';
        }
        foreach (array_reverse($mesajlar, true) as $idx => $m):
        ?>
            <div class="satir">
                <div class="kucuk"><?= e(al($m, 'tarih')) ?></div>
                <strong><?= e(al($m, 'ad')) ?></strong>
                <?php if (al($m, 'eposta') !== ''): ?> &middot; <?= e(al($m, 'eposta')) ?><?php endif; ?>
                <?php if (al($m, 'telefon') !== ''): ?> &middot; <?= e(al($m, 'telefon')) ?><?php endif; ?>
                <p><?= nl2br(e(al($m, 'mesaj')), false) ?></p>
                <form method="post" action="?bolum=mesajlar" onsubmit="return confirm('Bu mesaj silinsin mi?');">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="islem" value="mesaj_sil">
                    <input type="hidden" name="idx" value="<?= (int)$idx ?>">
                    <button type="submit" class="tehlike">Sil</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>

<?php elseif ($bolum === 'sifre'): ?>

    <div class="kart">
        <h2 style="margin-top:0">Şifre Değiştirme</h2>
        <p>Şifre, <strong>admin.php</strong> dosyasının üst kısmındaki <code>ADMIN_SIFRE</code> satırında tutulur. Dosyayı açıp tırnak içindeki yazıyı yeni şifrenizle değiştirin.</p>
        <p class="kucuk">İsterseniz şifrenizi düz yazı yerine hash olarak saklayabilirsiniz. Aşağıya yeni şifreyi yazın, çıkan değeri ADMIN_SIFRE satırına yapıştırın.</p>
        <form method="post" action="?bolum=sifre">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="islem" value="hash">
            <label>Yeni şifre (en az 8 karakter)
                <input type="text" name="yeni_sifre" autocomplete="off">
            </label>
            <p><button type="submit">Hash üret</button></p>
        </form>
        <?php if (!empty($_SESSION['hash_sonuc'])): ?>
            <label>Üretilen değer
                <textarea readonly rows="2" onclick="this.select()"><?= e($_SESSION['hash_sonuc']) ?></textarea>
            </label>
            <?php unset($_SESSION['hash_sonuc']); ?>
        <?php endif; ?>
    </div>

<?php else: ?>

    <?php $s = $sema[$bolum]; ?>
    <div class="kart">
        <h2 style="margin-top:0"><?= e($s['baslik']) ?></h2>
        <form method="post" action="?bolum=<?= e($bolum) ?>">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="islem" value="bolum">
            <input type="hidden" name="bolum" value="<?= e($bolum) ?>">

            <?php if ($s['tip'] === 'alanlar'): ?>

                <?php
                $mevcut = isset($veri_goster[$bolum]) && is_array($veri_goster[$bolum]) ? $veri_goster[$bolum] : array();
                foreach ($s['alanlar'] as $ad => $t) {
                    alan_giris('f', $ad, $t, al($mevcut, $ad));
                }
                ?>

            <?php else: ?>

                <?php
                $liste = isset($veri_goster[$bolum]) && is_array($veri_goster[$bolum]) ? $veri_goster[$bolum] : array();
                $sira = 0;
                foreach ($liste as $satir):
                    if (!is_array($satir)) {
                        continue;
                    }
                ?>
                    <div class="satir">
                        <?php if (!empty($s['id'])): ?>
                            <input type="hidden" name="s[<?= (int)$sira ?>][id]" value="<?= (int)al($satir, 'id') ?>">
                        <?php endif; ?>
                        <?php foreach ($s['alanlar'] as $ad => $t) { alan_giris('s[' . $sira . ']', $ad, $t, al($satir, $ad)); } ?>
                        <label style="font-weight:400"><input type="checkbox" name="s[<?= (int)$sira ?>][sil]" value="1"> Bu satırı sil</label>
                    </div>
                <?php
                    $sira++;
                endforeach;
                if ($sira === 0) {
                    echo '<p class="kucuk">Henüz kayıt yok. "Yeni satır ekle" ile başlayın.</p>';
                }
                ?>

            <?php endif; ?>

            <p>
                <button type="submit" name="eylem" value="kaydet">Kaydet</button>
                <?php if ($s['tip'] === 'liste'): ?>
                    <button type="submit" name="eylem" value="ekle" class="ikinci">+ Yeni satır ekle</button>
                <?php endif; ?>
            </p>
            <?php if ($s['tip'] === 'liste'): ?>
                <p class="kucuk">Ana alanı boş bırakılan satırlar kaydederken otomatik silinir.</p>
            <?php endif; ?>
        </form>
    </div>

<?php endif; ?>

<?php sayfa_alt(); ?>
