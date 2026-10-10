<?php
/**
 * index.php
 * Ana sayfa + ortak veri katmanı (admin.php de bu dosyayı kullanır).
 * Gereksinim: PHP 7.2 veya üzeri.
 * Veriler: veriler.txt (JSON). Okuma/yazma fopen / fwrite / fclose ile yapılır.
 * NOT: Bu dosya UTF-8 (BOM'suz) kaydedilmelidir.
 */

$yerel_ortam = in_array(isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '', array('localhost', '127.0.0.1', '::1'), true);
error_reporting(E_ALL);
ini_set('display_errors', $yerel_ortam ? '1' : '0');
date_default_timezone_set('Europe/Istanbul');

const VERI_DOSYA = __DIR__ . '/veriler.txt';

/* ------------------------------------------------------------------
 * YARDIMCI FONKSİYONLAR
 * ------------------------------------------------------------------ */

/** HTML'e güvenli çıktı */
function e($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Dizideki alanı güvenle metin olarak al */
function al($dizi, $anahtar)
{
    if (is_array($dizi) && isset($dizi[$anahtar]) && is_scalar($dizi[$anahtar])) {
        return (string)$dizi[$anahtar];
    }
    return '';
}

/** UTF-8 uyumlu karakter sayısı */
function uzunluk($s)
{
    return function_exists('mb_strlen') ? mb_strlen((string)$s, 'UTF-8') : strlen((string)$s);
}

/** UTF-8 karakterini bölmeden kısalt */
function kisalt($s, $n)
{
    $s = (string)$s;
    $n = max(0, (int)$n);
    if (function_exists('mb_substr')) {
        return mb_substr($s, 0, $n, 'UTF-8');
    }
    if (preg_match('/^.{0,' . $n . '}/su', $s, $m)) {
        return $m[0];
    }
    return substr($s, 0, $n);
}

/** Gönderilen (POST) değeri güvenle metin olarak al */
function post_str($anahtar)
{
    return (isset($_POST[$anahtar]) && is_string($_POST[$anahtar])) ? $_POST[$anahtar] : '';
}

/** Bağlantı güvenli mi? (javascript:, data: vb. engellenir) */
function url_ok($u)
{
    $u = trim((string)$u);
    if ($u === '') {
        return '';
    }
    if (preg_match('/[\s<>"\'`]/', $u)) {
        return '';
    }
    if (preg_match('~^https?://~i', $u)) {
        return $u;
    }
    if (preg_match('~^[a-z][a-z0-9+.\-]*:~i', $u)) {
        return '';
    }
    return $u;
}

/** YouTube bağlantısından video kimliğini çıkar */
function youtube_id($u)
{
    if (preg_match('~(?:youtube\.com/(?:watch\?(?:[^#]*&)?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})~', (string)$u, $m)) {
        return $m[1];
    }
    return '';
}

/** Boş satırlarla ayrılmış metni <p> etiketlerine çevir */
function paragraflar($metin)
{
    $cikti = '';
    $parcalar = preg_split('/\n{2,}/', str_replace("\r", '', trim((string)$metin)));
    foreach ($parcalar as $p) {
        $p = trim($p);
        if ($p !== '') {
            $cikti .= '<p>' . nl2br(e($p), false) . '</p>';
        }
    }
    return $cikti;
}

/** Bölümdeki, ana alanı dolu olan satırları getir */
function satirlar($veri, $bolum, $ana)
{
    $sonuc = array();
    if (isset($veri[$bolum]) && is_array($veri[$bolum])) {
        foreach ($veri[$bolum] as $satir) {
            if (is_array($satir) && al($satir, $ana) !== '') {
                $sonuc[] = $satir;
            }
        }
    }
    return $sonuc;
}

/* ------------------------------------------------------------------
 * VARSAYILAN İÇERİK (veriler.txt yoksa bununla oluşturulur)
 * ------------------------------------------------------------------ */
function varsayilan_veri()
{
    return array(
        'site' => array(
            'ad' => 'Dyt. Ayşe Kaya',
            'unvan' => 'Sağlıklı Yaşam Uzmanı',
            'aciklama' => 'İzmit\'te diyetisyen Ayşe Kaya ile kişiye özel beslenme programları, online danışmanlık ve sağlıklı kilo yönetimi.',
            'whatsapp' => '905454257827',
            'telefon' => '+90 545 425 78 27',
            'eposta' => 'info@aysekaya.com.tr',
            'adres' => 'Yahyakaptan, Akasyalar Cd. No:24, 41000 İzmit/Kocaeli',
            'harita' => 'https://maps.app.goo.gl/6bPh1ZyNCRNWULm79',
            'saatler' => "Pazartesi - Cuma: 09:00 - 18:00\nCumartesi: 09:00 - 14:00\nPazar: Kapalı",
            'instagram' => '',
            'linkedin' => '',
        ),
        'hero' => array(
            array('baslik' => 'Sağlıklı Kilo Verme', 'alt' => 'Dyt. Ayşe Kaya ile zindeliğe doğru', 'gorsel' => '', 'buton' => 'Bizi Tanıyın', 'link' => '#team'),
            array('baslik' => 'WhatsApp Desteği', 'alt' => 'Motivasyonel olarak sizi hep yüksek tutacak iletişim', 'gorsel' => '', 'buton' => '', 'link' => ''),
            array('baslik' => 'İzmit Merkezdeyiz', 'alt' => 'İzmit\'te çarşıda bulunan şubemiz ile merkezdeyiz. Dilerseniz online olarak da hizmet alabilirsiniz.', 'gorsel' => '', 'buton' => 'Haritada Göster', 'link' => 'https://maps.app.goo.gl/6bPh1ZyNCRNWULm79'),
        ),
        'hizmetler' => array(
            array('baslik' => 'Kilo Yönetimi', 'metin' => 'Bireysel beslenme planları ile sağlıklı kilo verme ve kilo alma programları sunuyoruz.'),
            array('baslik' => 'Hastalık Diyetleri', 'metin' => 'Diyabet, kalp hastalıkları ve metabolik bozukluklar için özel beslenme programları.'),
            array('baslik' => 'Spor Beslenmesi', 'metin' => 'Sporcu performansını artırmak için özel beslenme stratejileri ve supplement önerileri.'),
            array('baslik' => 'Çocuk Beslenmesi', 'metin' => 'Büyüme çağındaki çocuklar için sağlıklı gelişim odaklı beslenme planları.'),
            array('baslik' => 'Hamile Beslenmesi', 'metin' => 'Hamilelik döneminde anne ve bebek sağlığı için özel beslenme danışmanlığı.'),
            array('baslik' => 'Online Danışmanlık', 'metin' => 'Uzaktan beslenme danışmanlığı ve takip hizmetleri ile her yerden destek alın.'),
        ),
        'sayilar' => array(
            array('deger' => 1200, 'etiket' => 'Mutlu Danışan'),
            array('deger' => 8, 'etiket' => 'Yıllık Deneyim'),
            array('deger' => 350, 'etiket' => 'Başarılı Program'),
            array('deger' => 98, 'etiket' => '% Memnuniyet'),
        ),
        'galeri' => array(
            array('kaynak' => 'https://www.youtube.com/watch?v=mSh32aSM1do', 'baslik' => 'Video 1'),
            array('kaynak' => 'https://www.youtube.com/watch?v=daEo_UJN8O0', 'baslik' => 'Video 2'),
            array('kaynak' => 'https://www.youtube.com/watch?v=3hCUUFklmj8', 'baslik' => 'Video 3'),
            array('kaynak' => 'https://www.youtube.com/watch?v=bliG6rMU8kk', 'baslik' => 'Video 4'),
            array('kaynak' => 'https://www.youtube.com/watch?v=RDkvqH0Xc_Y', 'baslik' => 'Video 5'),
            array('kaynak' => 'https://www.youtube.com/watch?v=SRYS8AieucM', 'baslik' => 'Video 6'),
        ),
        'hakkinda' => array(
            'baslik' => 'Dyt. Ayşe Kaya',
            'metin' => "Dyt. Ayşe Kaya 2017 yılında Ankara Üniversitesi Beslenme ve Diyetetik lisans programını 'İştah-Glisemik Yük ve Kan şekerleri arasında ilişki' teziyle tamamlamıştır. Lisans eğitiminin ardından yabancı danışanlara da ulaşabilmek adına EC Bristol adlı kurumda İngiltere'de dil eğitimi almıştır.\n\nAnkara ve İstanbul'da pek çok eğitim ve sertifika programına katılmaya devam etmiştir. Mart 2018 tarihinde Sofit Beslenme ve Diyet danışmanlığını kurmuş ve bu tarihten itibaren pek çok hastalıkta beslenme tedavisi, zayıflama ve kilo alma gibi konularda danışmanlık yapmaya devam etmektedir.",
        ),
        'blog' => array(
            array(
                'id' => 1,
                'baslik' => 'Sağlıklı Kilo Vermenin 7 Altın Kuralı',
                'ozet' => 'Sürdürülebilir kilo kaybı için bilmeniz gereken temel prensipleri ve pratik önerilerimi keşfedin.',
                'icerik' => "Kalıcı kilo kaybı hızlı sonuçlardan çok, sürdürülebilir alışkanlıklarla gelir. İşte her danışanıma anlattığım yedi temel kural.\n\n1. Gerçekçi hedef koyun. Sağlıklı kilo kaybı haftada ortalama 0,5-1 kg arasındadır.\n\n2. Öğün atlamayın. Uzun süre aç kalmak akşam kontrolsüz yemeye yol açar.\n\n3. Yeterli su için. Susuzluk çoğu zaman açlık gibi hissedilir.\n\n4. Her öğünde protein ve lif bulundurun. Tokluk hissini uzatır.\n\n5. Porsiyonlarınıza dikkat edin. Yasak yok, ölçü var.\n\n6. Uykunuzu ihmal etmeyin. Yetersiz uyku iştah hormonlarını bozar.\n\n7. Sabırlı olun ve hareket edin. Küçük ama düzenli adımlar büyük fark yaratır.",
            ),
            array(
                'id' => 2,
                'baslik' => 'Metabolizmayı Hızlandıran Besinler',
                'ozet' => 'Metabolik hızınızı doğal yollarla artıracak süper besinleri ve tüketim şekillerini öğrenelim.',
                'icerik' => "Metabolizmayı tek başına hızlandıran mucize bir besin yoktur; ancak bazı besinler ve alışkanlıklar enerji harcamanıza destek olur.\n\nProtein açısından zengin besinler (yumurta, baklagiller, yoğurt, tavuk, balık) sindirilirken daha fazla enerji harcatır. Yeterli su tüketimi ve düzenli öğünler de metabolizmanın dengeli çalışmasına yardımcı olur.\n\nDüzenli direnç egzersizi ile kas kütlenizi korumak ise uzun vadede en etkili yöntemlerden biridir. Kendinize uygun planı bir diyetisyenle birlikte oluşturmanız en doğrusudur.",
            ),
            array(
                'id' => 3,
                'baslik' => 'Spor Öncesi ve Sonrası Beslenme',
                'ozet' => 'Antrenman performansınızı maksimize etmek için doğru zamanlama ile beslenme stratejileri.',
                'icerik' => "Antrenman öncesi, egzersizden yaklaşık 1-3 saat önce karbonhidrat ağırlıklı, yağı az ve kolay sindirilen bir öğün tercih edin. Örneğin yulaf, muz ya da tam tahıllı ekmek uygun seçeneklerdir.\n\nAntrenman sonrasında 30-60 dakika içinde protein ve karbonhidratı birlikte almak kasların toparlanmasına yardımcı olur. Egzersiz boyunca ve sonrasında sıvı kaybınızı yerine koymayı unutmayın.\n\nİhtiyaçlar kişiye, spora ve hedefe göre değişir; bu yüzden size özel plan için uzmana danışın.",
            ),
        ),
        'yorumlar' => array(
            array('isim' => 'Elif Özkan', 'metin' => 'Ayşe Hanım\'ı evime yakın olduğu için tercih etmiştim, iyi ki tercih etmişim. Daha önce birçok kilo verme sürecine girdim ama en başarılısı Ayşe Hanım ile oldu. Metabolizmamın yavaş olması ve insülin direncim olmasına rağmen 3 ayda spor yapmadan 10 kilo verdim. Sağlıklı kilo vermek isteyen herkese Ayşe Hanım\'ı tavsiye ederim.'),
            array('isim' => 'Ahmet Yılmaz', 'metin' => 'Tavsiye üzerine gittiğimiz Diyetisyen Ayşe Hanım sayesinde kızım 3 haftada 2 kg\'a yakın zayıfladı. Bu süreçte Ayşe Hanım\'ın duyduğu şüphe üzerine kızıma yaptırdığımız tahliller sonucu demir ve D vitamini eksikliğini öğrendik. Sayesinde kızım hem zayıfladı hem de takviyelere başladı. Tatlı dilli, ilgili, bilgili Ayşe Hanım\'ı ihtiyacı olan herkese tavsiye ediyoruz.'),
            array('isim' => 'Can Demir', 'metin' => 'Spor performansım çok arttı. Ayşe Hanım\'ın önerdiği beslenme programı sayesinde hedeflediğim kas kütlesine ulaştım.'),
            array('isim' => 'Aylin Kaya', 'metin' => 'Hamilelik sürecimde çok destek oldular. Hem kendim hem de bebeğim için en sağlıklı beslenmeyi sağladım.'),
            array('isim' => 'Murat Şahin', 'metin' => 'Online danışmanlık hizmeti harika! Evden çıkmadan profesyonel destek almak çok pratik.'),
        ),
        'sss' => array(
            array('soru' => 'Kilo verme programı ne kadar sürer?', 'cevap' => 'Kilo verme sürecinin süresi kişinin başlangıç kilosu, hedef kilo ve metabolik özelliklerine bağlı olarak değişir. Ortalama olarak sağlıklı kilo kaybı haftada 0,5-1 kg arası olmalıdır.'),
            array('soru' => 'Online danışmanlık nasıl çalışır?', 'cevap' => 'Online danışmanlık video konferans üzerinden gerçekleşir. İlk görüşmede detaylı anamnez alınır, beslenme programınız hazırlanır ve düzenli takipleriniz yapılır.'),
            array('soru' => 'Beslenme programı ne sıklıkla güncellenir?', 'cevap' => 'Programlar genellikle 15 gün arayla kontrol edilir ve gerekli güncellemeler yapılır. Kilo kaybı durumu, yaşam koşullarındaki değişiklikler dikkate alınarak revize edilir.'),
            array('soru' => 'Hangi yaş grubuna hizmet veriyorsunuz?', 'cevap' => '2 yaşından itibaren tüm yaş gruplarına hizmet veriyoruz. Çocuk, ergen, yetişkin ve yaşlı beslenme konularında uzman ekibimiz bulunmaktadır.'),
            array('soru' => 'Özel sağlık durumları için program yapılır mı?', 'cevap' => 'Evet, diyabet, hipertansiyon, kolesterol yüksekliği, PCOS gibi özel durumlar için medikal beslenme tedavisi programları hazırlanmaktadır.'),
        ),
        'mesajlar' => array(),
    );
}

/* ------------------------------------------------------------------
 * VERİ OKUMA / YAZMA  (fopen - fwrite - fclose)
 * ------------------------------------------------------------------ */

/** veriler.txt'yi oku. Dosya yoksa oluşturur, bozuksa varsayılana döner. */
function veri_oku()
{
    $v = varsayilan_veri();

    if (!is_file(VERI_DOSYA)) {
        veri_yaz($v);
        return $v;
    }

    $h = @fopen(VERI_DOSYA, 'rb');
    if ($h === false) {
        return $v;
    }
    @flock($h, LOCK_SH);
    $icerik = (string)stream_get_contents($h);
    @flock($h, LOCK_UN);
    fclose($h);

    $icerik = preg_replace('/^\xEF\xBB\xBF/', '', $icerik);   // Not Defteri'nin eklediği BOM'u temizle
    $d = json_decode((string)$icerik, true);
    if (!is_array($d)) {
        return $v;
    }

    foreach ($v as $anahtar => $varsayilan) {
        if (!array_key_exists($anahtar, $d) || !is_array($d[$anahtar])) {
            continue;
        }
        if ($anahtar === 'site' || $anahtar === 'hakkinda') {
            $v[$anahtar] = array_merge($varsayilan, $d[$anahtar]);
        } else {
            $v[$anahtar] = $d[$anahtar];
        }
    }
    return $v;
}

/** Son veri hatasının kodu: '', 'json', 'izin', 'bozuk', 'yazma' */
function veri_hata($yeni = null)
{
    static $hata = '';
    if ($yeni !== null) {
        $hata = $yeni;
    }
    return $hata;
}

/** Metin geçerli bir veri dosyası içeriği mi? (boş dosya da geçerli sayılır) */
function veri_gecerli_mi($metin)
{
    $metin = (string)preg_replace('/^\xEF\xBB\xBF/', '', (string)$metin);
    if (trim($metin) === '') {
        return true;
    }
    return is_array(json_decode($metin, true));
}

/** veriler.txt var ama içeriği bozuk mu? */
function veri_bozuk_mu()
{
    if (!is_file(VERI_DOSYA)) {
        return false;
    }
    $m = @file_get_contents(VERI_DOSYA);
    if ($m === false) {
        return false;
    }
    return !veri_gecerli_mi($m);
}

/** Son hataya uygun, anlaşılır Türkçe açıklama */
function veri_hata_metni()
{
    switch (veri_hata()) {
        case 'bozuk':
            return 'veriler.txt dosyasının içeriği bozulmuş görünüyor. Mevcut veri kaybolmasın diye hiçbir şey kaydedilmedi. Dosyayı düzeltin ya da silin (silerseniz site varsayılan içerikle yeniden oluşturulur).';
        case 'yazma':
            return 'Dosyaya yazılırken bir hata oluştu (disk dolu olabilir). Eski içerik korundu.';
        case 'json':
            return 'İçerik kaydedilemedi (geçersiz karakter içeriyor olabilir).';
        default:
            return 'veriler.txt dosyasına yazılamadı. Dosyanın yazma iznini (chmod 666 veya 664) ve klasör iznini kontrol edin.';
    }
}

/**
 * veriler.txt'ye yaz. Başarılıysa true döner.
 * Koruma 1: dosya bozuksa üzerine yazmaz (veri kaybını önler).
 * Koruma 2: yazma yarıda kesilirse eski içeriği geri koyar.
 */
function veri_yaz($veri)
{
    veri_hata('');

    $secenek = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $secenek |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    $json = json_encode($veri, $secenek);
    if ($json === false) {
        veri_hata('json');
        return false;
    }

    $h = @fopen(VERI_DOSYA, 'c+');
    if ($h === false) {
        veri_hata('izin');
        return false;
    }
    $kilitli = @flock($h, LOCK_EX);

    rewind($h);
    $eski = (string)stream_get_contents($h);
    if (!veri_gecerli_mi($eski)) {
        if ($kilitli) {
            flock($h, LOCK_UN);
        }
        fclose($h);
        veri_hata('bozuk');
        return false;
    }

    ftruncate($h, 0);
    rewind($h);
    $yazilan = fwrite($h, $json);
    fflush($h);
    $tamam = ($yazilan === strlen($json));
    if (!$tamam) {
        ftruncate($h, 0);
        rewind($h);
        fwrite($h, $eski);
        fflush($h);
        veri_hata('yazma');
    }
    if ($kilitli) {
        flock($h, LOCK_UN);
    }
    fclose($h);

    return $tamam;
}

/* admin.php bu dosyayı yalnızca fonksiyonlar için çağırır; sayfa çizilmez. */
if (defined('ADMIN_MODU')) {
    return;
}

/* ------------------------------------------------------------------
 * İLETİŞİM FORMU (POST)
 * ------------------------------------------------------------------ */
$hatalar = array();
$eski = array('ad' => '', 'eposta' => '', 'telefon' => '', 'mesaj' => '');

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && post_str('islem') === 'iletisim') {

    // Gizli tuzak alan (botlar doldurur): sessizce başarılıymış gibi davran
    if (trim(post_str('web')) !== '') {
        header('Location: ?gonderildi=1#iletisim');
        exit;
    }

    foreach ($eski as $alan => $bos) {
        $ham = trim(post_str($alan));
        if ($alan !== 'mesaj') {
            $tek = preg_replace('/\s+/u', ' ', $ham);
            $ham = ($tek === null) ? $ham : $tek;
        } else {
            $ham = str_replace(array("\r\n", "\r"), "\n", $ham);
        }
        $eski[$alan] = kisalt($ham, $alan === 'mesaj' ? 2000 : 150);
    }

    if (uzunluk($eski['ad']) < 2) {
        $hatalar[] = 'Lütfen adınızı ve soyadınızı yazın.';
    }
    if ($eski['eposta'] === '' && $eski['telefon'] === '') {
        $hatalar[] = 'E-posta veya telefon alanlarından en az birini doldurun.';
    }
    if ($eski['eposta'] !== '' && !filter_var($eski['eposta'], FILTER_VALIDATE_EMAIL)) {
        $hatalar[] = 'E-posta adresi geçerli görünmüyor.';
    }
    if ($eski['telefon'] !== '') {
        $rakam = strlen(preg_replace('/\D+/', '', $eski['telefon']));
        if ($rakam < 7 || $rakam > 15) {
            $hatalar[] = 'Telefon numarası geçerli görünmüyor.';
        }
    }
    if (uzunluk($eski['mesaj']) < 5) {
        $hatalar[] = 'Lütfen mesajınızı yazın.';
    }

    if (!$hatalar) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $son = isset($_SESSION['son_mesaj']) ? (int)$_SESSION['son_mesaj'] : 0;
        if (time() - $son < 30) {
            $hatalar[] = 'Çok hızlı gönderim yaptınız, lütfen biraz bekleyip tekrar deneyin.';
        }
    }

    if (!$hatalar) {
        $kayit = veri_oku();
        if (!isset($kayit['mesajlar']) || !is_array($kayit['mesajlar'])) {
            $kayit['mesajlar'] = array();
        }
        $kayit['mesajlar'][] = array(
            'tarih' => date('d.m.Y H:i'),
            'ad' => $eski['ad'],
            'eposta' => $eski['eposta'],
            'telefon' => $eski['telefon'],
            'mesaj' => $eski['mesaj'],
        );
        $kayit['mesajlar'] = array_slice($kayit['mesajlar'], -300);   // dosya şişmesin

        if (veri_yaz($kayit)) {
            $_SESSION['son_mesaj'] = time();
            header('Location: ?gonderildi=1#iletisim');
            exit;
        }
        $hatalar[] = 'Mesajınız şu anda kaydedilemedi. Lütfen WhatsApp veya telefon ile ulaşın.';
    }
}

/* ------------------------------------------------------------------
 * SAYFA VERİSİ
 * ------------------------------------------------------------------ */
$veri = veri_oku();
$site = $veri['site'];

$wa_no = preg_replace('/\D+/', '', al($site, 'whatsapp'));
$wa_link = ($wa_no !== '') ? 'https://wa.me/' . $wa_no : '';

$blog = satirlar($veri, 'blog', 'baslik');

// Blog yazısı isteniyor mu? (?yazi=ID)
$yazi_istendi = isset($_GET['yazi']);
$yazi = null;
if ($yazi_istendi) {
    $istenen = is_string($_GET['yazi']) ? (int)$_GET['yazi'] : 0;
    foreach ($blog as $b) {
        if ((int)al($b, 'id') === $istenen) {
            $yazi = $b;
            break;
        }
    }
    if ($yazi === null) {
        http_response_code(404);
    }
}

$sayfa_baslik = al($site, 'ad') . ' - ' . al($site, 'unvan');
$sayfa_aciklama = al($site, 'aciklama');
if ($yazi !== null) {
    $sayfa_baslik = al($yazi, 'baslik') . ' | ' . al($site, 'ad');
    $sayfa_aciklama = al($yazi, 'ozet');
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($sayfa_baslik) ?></title>
<meta name="description" content="<?= e($sayfa_aciklama) ?>">
<style>
/* GEÇİCİ İSKELET STİLİ — tasarım en son yapılacak */
body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;margin:0;line-height:1.6;color:#222}
header,section,footer,article{padding:24px 16px;max-width:900px;margin:0 auto}
nav a{margin-right:12px;display:inline-block}
img{max-width:100%;height:auto}
.izgara{display:grid;gap:12px;grid-template-columns:repeat(auto-fill,minmax(220px,1fr))}
.kutu{border:1px solid #ddd;padding:12px;border-radius:6px}
.hata{background:#fdecea;border:1px solid #f5c6cb;padding:10px;margin-bottom:12px}
.ok{background:#e8f5e9;border:1px solid #c8e6c9;padding:10px;margin-bottom:12px}
input,textarea{width:100%;box-sizing:border-box;padding:8px;margin:4px 0 12px;font:inherit}
</style>
</head>
<body>

<header>
    <strong><?= e(al($site, 'ad')) ?></strong>
    <nav>
        <a href="./#hero">Ana Sayfa</a>
        <a href="./#services">Hizmetler</a>
        <a href="./#gallery">Galeri</a>
        <a href="./#team">Hakkında</a>
        <a href="./#blog">Blog</a>
        <a href="./#testimonials">Yorumlarımız</a>
        <a href="./#faq">S.S.S.</a>
        <a href="./#iletisim">İletişim</a>
        <?php if ($wa_link !== ''): ?><a href="<?= e($wa_link) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
    </nav>
</header>

<?php if ($yazi_istendi): ?>

    <article>
    <?php if ($yazi !== null): ?>
        <p><a href="./#blog">&larr; Tüm yazılar</a></p>
        <h1><?= e(al($yazi, 'baslik')) ?></h1>
        <?= paragraflar(al($yazi, 'icerik')) ?>
    <?php else: ?>
        <h1>Yazı bulunamadı</h1>
        <p><a href="./#blog">&larr; Blog yazılarına dön</a></p>
    <?php endif; ?>
    </article>

<?php else: ?>

<section id="hero">
    <?php $hero = satirlar($veri, 'hero', 'baslik'); ?>
    <?php foreach ($hero as $i => $h): ?>
        <div class="kutu">
            <?php if ($i === 0): ?><h1><?= e(al($h, 'baslik')) ?></h1><?php else: ?><h2><?= e(al($h, 'baslik')) ?></h2><?php endif; ?>
            <p><?= e(al($h, 'alt')) ?></p>
            <?php if (url_ok(al($h, 'gorsel')) !== ''): ?><img src="<?= e(url_ok(al($h, 'gorsel'))) ?>" alt="<?= e(al($h, 'baslik')) ?>"><?php endif; ?>
            <?php if (al($h, 'buton') !== '' && url_ok(al($h, 'link')) !== ''): ?>
                <p><a href="<?= e(url_ok(al($h, 'link'))) ?>"><?= e(al($h, 'buton')) ?></a></p>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</section>

<section id="services">
    <h2>Hizmetlerimiz</h2>
    <div class="izgara">
    <?php foreach (satirlar($veri, 'hizmetler', 'baslik') as $h): ?>
        <div class="kutu"><h3><?= e(al($h, 'baslik')) ?></h3><p><?= e(al($h, 'metin')) ?></p></div>
    <?php endforeach; ?>
    </div>
</section>

<section id="stats">
    <h2>Sayılarla Başarılarımız</h2>
    <div class="izgara">
    <?php foreach (satirlar($veri, 'sayilar', 'etiket') as $s): ?>
        <div class="kutu"><strong><?= (int)al($s, 'deger') ?></strong><br><?= e(al($s, 'etiket')) ?></div>
    <?php endforeach; ?>
    </div>
</section>

<section id="gallery">
    <h2>Galeri</h2>
    <div class="izgara">
    <?php foreach (satirlar($veri, 'galeri', 'kaynak') as $g): ?>
        <?php
        $yt = youtube_id(al($g, 'kaynak'));
        $url = url_ok(al($g, 'kaynak'));
        ?>
        <?php if ($yt !== ''): ?>
            <a class="kutu" href="https://www.youtube.com/watch?v=<?= e($yt) ?>" target="_blank" rel="noopener">
                <img loading="lazy" src="https://img.youtube.com/vi/<?= e($yt) ?>/hqdefault.jpg" alt="<?= e(al($g, 'baslik')) ?>">
            </a>
        <?php elseif ($url !== ''): ?>
            <div class="kutu"><img loading="lazy" src="<?= e($url) ?>" alt="<?= e(al($g, 'baslik')) ?>"></div>
        <?php endif; ?>
    <?php endforeach; ?>
    </div>
</section>

<section id="team">
    <h2>Hakkında</h2>
    <h3><?= e(al($veri['hakkinda'], 'baslik')) ?></h3>
    <?= paragraflar(al($veri['hakkinda'], 'metin')) ?>
</section>

<section id="blog">
    <h2>Blog Yazıları</h2>
    <div class="izgara">
    <?php foreach ($blog as $b): ?>
        <div class="kutu">
            <h3><?= e(al($b, 'baslik')) ?></h3>
            <p><?= e(al($b, 'ozet')) ?></p>
            <a href="?yazi=<?= (int)al($b, 'id') ?>">Devamını Oku</a>
        </div>
    <?php endforeach; ?>
    </div>
</section>

<section id="testimonials">
    <h2>Danışan Yorumları</h2>
    <?php foreach (satirlar($veri, 'yorumlar', 'metin') as $y): ?>
        <blockquote class="kutu">
            <p><?= e(al($y, 'metin')) ?></p>
            <?php if (al($y, 'isim') !== ''): ?><footer>- <?= e(al($y, 'isim')) ?></footer><?php endif; ?>
        </blockquote>
    <?php endforeach; ?>
</section>

<section id="faq">
    <h2>Sık Sorulan Sorular</h2>
    <?php foreach (satirlar($veri, 'sss', 'soru') as $q): ?>
        <details>
            <summary><?= e(al($q, 'soru')) ?></summary>
            <p><?= e(al($q, 'cevap')) ?></p>
        </details>
    <?php endforeach; ?>
</section>

<section id="iletisim">
    <h2 id="contact">Bizimle İletişime Geçin</h2>

    <?php if (isset($_GET['gonderildi']) && !$hatalar): ?>
        <div class="ok">Mesajınız alındı, en kısa sürede dönüş yapacağız. Teşekkürler!</div>
    <?php endif; ?>
    <?php if ($hatalar): ?>
        <div class="hata"><?php foreach ($hatalar as $h): ?><div><?= e($h) ?></div><?php endforeach; ?></div>
    <?php endif; ?>

    <form method="post" action="?#iletisim" novalidate>
        <input type="hidden" name="islem" value="iletisim">
        <div style="position:absolute;left:-9999px" aria-hidden="true">
            <label>Bu alanı boş bırakın <input type="text" name="web" tabindex="-1" autocomplete="off"></label>
        </div>
        <label>Ad Soyad<input type="text" name="ad" maxlength="150" value="<?= e($eski['ad']) ?>" required></label>
        <label>E-posta<input type="email" name="eposta" maxlength="150" value="<?= e($eski['eposta']) ?>"></label>
        <label>Telefon<input type="tel" name="telefon" maxlength="150" value="<?= e($eski['telefon']) ?>"></label>
        <label>Mesajınız<textarea name="mesaj" rows="5" maxlength="2000" required><?= e($eski['mesaj']) ?></textarea></label>
        <button type="submit">Gönder</button>
    </form>

    <h3>Adres</h3>
    <p><?= e(al($site, 'adres')) ?></p>
    <?php if (url_ok(al($site, 'harita')) !== ''): ?><p><a href="<?= e(url_ok(al($site, 'harita'))) ?>" target="_blank" rel="noopener">Google Haritalarda Aç</a></p><?php endif; ?>

    <h3>Çalışma Saatleri</h3>
    <?php foreach (preg_split('/\R/u', al($site, 'saatler')) as $satir): ?>
        <?php if (trim($satir) !== ''): ?><div><?= e(trim($satir)) ?></div><?php endif; ?>
    <?php endforeach; ?>

    <h3>İletişim Bilgileri</h3>
    <?php if (al($site, 'telefon') !== ''): ?><div><?= e(al($site, 'telefon')) ?></div><?php endif; ?>
    <?php if (al($site, 'eposta') !== ''): ?><div><?= e(al($site, 'eposta')) ?></div><?php endif; ?>
    <?php if (url_ok(al($site, 'instagram')) !== ''): ?><div><a href="<?= e(url_ok(al($site, 'instagram'))) ?>" target="_blank" rel="noopener">Instagram</a></div><?php endif; ?>
    <?php if (url_ok(al($site, 'linkedin')) !== ''): ?><div><a href="<?= e(url_ok(al($site, 'linkedin'))) ?>" target="_blank" rel="noopener">LinkedIn</a></div><?php endif; ?>
</section>

<?php endif; ?>

<footer>
    &copy; <?= date('Y') ?> <?= e(al($site, 'ad')) ?>. Tüm hakları saklıdır.
</footer>

</body>
</html>
