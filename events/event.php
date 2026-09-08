<?php
// Renders a standalone, crawlable page for one event, in English or Farsi
// depending on ?lang=fa. Content is fetched server-side from the same
// Google Sheet the homepage uses, so editing the sheet updates this page too.

$SHEETS_ENDPOINT = "https://script.google.com/macros/s/AKfycbz17nLpRn3llqi-0UYypDrjoUQg57SEyKT3yRZpGgUSXBRELVakcVpwr48lIpXO9dbw/exec";

$id = isset($_GET['id']) ? $_GET['id'] : '';
$lang = (isset($_GET['lang']) && $_GET['lang'] === 'fa') ? 'fa' : 'en';
$isFa = $lang === 'fa';

function h($s) {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

$event = null;
$ch = curl_init($SHEETS_ENDPOINT . '?action=events');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 10,
]);
$json = curl_exec($ch);
curl_close($ch);

if ($json !== false) {
    $events = json_decode($json, true);
    if (is_array($events)) {
        foreach ($events as $e) {
            if (isset($e['id']) && $e['id'] === $id) {
                $event = $e;
                break;
            }
        }
    }
}

$baseUrl = "https://tarabridge.ca/events/" . rawurlencode($id) . ".html";
$enUrl = $baseUrl;
$faUrl = $baseUrl . "?lang=fa";
$canonical = $isFa ? $faUrl : $enUrl;
$altUrl = $isFa ? $enUrl : $faUrl;

if (!$event) {
    http_response_code(404);
    $pageTitle = $isFa ? "رویداد یافت نشد — انجمن پل فرهنگی تارا" : "Event Not Found — Tara Cultural Bridge Society";
} else {
    $title = $isFa ? $event['title_fa'] : $event['title_en'];
    $pageTitle = $title . ($isFa ? " — انجمن پل فرهنگی تارا" : " — Tara Cultural Bridge Society");
    $desc = $isFa ? ($event['desc_fa'] ?? '') : ($event['desc_en'] ?? '');
    $loc = $isFa ? ($event['loc_fa'] ?? '') : ($event['loc_en'] ?? '');
    $time = ($isFa && !empty($event['time_fa'])) ? $event['time_fa'] : ($event['time'] ?? '');
    $date = $event['date'] ?? '';
}
?>
<!DOCTYPE html>
<html lang="<?= $isFa ? 'fa' : 'en' ?>" dir="<?= $isFa ? 'rtl' : 'ltr' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($pageTitle) ?></title>
<?php if ($event): ?>
<meta name="description" content="<?= h(mb_substr($desc, 0, 160)) ?>">
<?php endif; ?>
<link rel="canonical" href="<?= h($canonical) ?>">
<link rel="alternate" hreflang="en" href="<?= h($enUrl) ?>">
<link rel="alternate" hreflang="fa" href="<?= h($faUrl) ?>">
<link rel="alternate" hreflang="x-default" href="<?= h($enUrl) ?>">
<link rel="icon" type="image/jpeg" href="/images/logo.jpg">
<?php if ($event): ?>
<meta property="og:type" content="website">
<meta property="og:title" content="<?= h($title) ?>">
<meta property="og:description" content="<?= h(mb_substr($desc, 0, 200)) ?>">
<meta property="og:url" content="<?= h($canonical) ?>">
<script type="application/ld+json">
<?= json_encode([
    "@context" => "https://schema.org",
    "@type" => "Event",
    "name" => $title,
    "startDate" => $date,
    "eventAttendanceMode" => "https://schema.org/OfflineEventAttendanceMode",
    "eventStatus" => "https://schema.org/EventScheduled",
    "inLanguage" => $isFa ? "fa" : "en",
    "location" => ["@type" => "Place", "name" => $loc],
    "description" => $desc,
    "organizer" => [
        "@type" => "NGO",
        "name" => "Tara Cultural Bridge Society",
        "url" => "https://tarabridge.ca/",
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>
<?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Inter:wght@400;500;600;700&family=Vazirmatn:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/styles.css">
</head>
<body>

<nav class="nav">
  <div class="wrap nav-row">
    <a href="/<?= $isFa ? '?lang=fa' : '' ?>" class="nav-brand">
      <img width="700" height="700" src="/images/logo.jpg" alt="Tara Cultural Bridge Society">
    </a>
    <div class="nav-right">
      <a class="lang-switch" href="<?= h($altUrl) ?>"><?= $isFa ? 'English' : 'فارسی' ?></a>
    </div>
  </div>
</nav>

<?php if (!$event): ?>
<div class="wrap not-found">
  <h1><?= $isFa ? 'رویداد یافت نشد' : 'Event not found' ?></h1>
  <p><a href="/#events" class="btn btn-solid"><?= $isFa ? 'بازگشت به رویدادها' : 'Back to all events' ?></a></p>
</div>
<?php else: ?>
<section class="event-page">
  <div class="wrap">
    <a class="event-back" href="/<?= $isFa ? '?lang=fa' : '' ?>#events">&larr; <?= $isFa ? 'بازگشت به رویدادها' : 'Back to all events' ?></a>
    <h1><?= h($title) ?></h1>
    <div class="event-meta">
      <span>📅 <?= h($date) ?></span>
      <span>🕔 <?= h($time) ?></span>
      <span>📍 <?= h($loc) ?></span>
    </div>
    <p class="event-desc"><?= h($desc) ?></p>

    <form class="reg-form open" id="regForm">
      <input type="text" placeholder="<?= $isFa ? 'نام کودک' : "Child's name" ?>" class="reg-kidname" required>
      <input type="number" min="0" placeholder="<?= $isFa ? 'سن کودک' : "Child's age" ?>" class="reg-kidage" required>
      <input type="text" placeholder="<?= $isFa ? 'نام والد' : "Parent's name" ?>" class="reg-parentname" required>
      <input type="email" placeholder="<?= $isFa ? 'ایمیل' : 'Email' ?>" class="reg-email" required>
      <input type="tel" placeholder="<?= $isFa ? 'تلفن' : 'Phone' ?>" class="reg-phone" required>
      <textarea placeholder="<?= $isFa ? 'حساسیت غذایی (در صورت نبود «ندارد» بنویسید)' : 'Allergies (write “None” if none)' ?>" class="reg-allergy" required></textarea>
      <button type="submit" class="btn btn-solid reg-submit"><?= $isFa ? 'تأیید ثبت‌نام' : 'Confirm registration' ?></button>
      <span class="reg-msg" id="regMsg"></span>
    </form>
  </div>
</section>
<?php endif; ?>

<footer>
  <div class="wrap footer-row">
    <img width="700" height="700" src="/images/logo.jpg" alt="Tara Cultural Bridge Society" style="height:30px;width:auto;">
    <div class="footer-meta">© 2026 Tara Cultural Bridge Society — hello@taracbs.org</div>
  </div>
</footer>

<?php if ($event): ?>
<script>
const SHEETS_ENDPOINT = "<?= $SHEETS_ENDPOINT ?>";
const CONFIRMATION_ENDPOINT = "/api/send-confirmation.php";
const EVENT_ID = <?= json_encode($id) ?>;
const EVENT_TITLE = <?= json_encode($title) ?>;
const EVENT_DATE_DISPLAY = <?= json_encode($date) ?>;
const EVENT_TIME_DISPLAY = <?= json_encode($time) ?>;
const EVENT_LOC_DISPLAY = <?= json_encode($loc) ?>;
const LANG = <?= json_encode($isFa ? 'fa' : 'en') ?>;

async function sendToSheet(payload){
  await fetch(SHEETS_ENDPOINT, {
    method: "POST",
    mode: "no-cors",
    headers: { "Content-Type": "text/plain;charset=utf-8" },
    body: JSON.stringify(payload)
  });
}

async function sendConfirmationEmail(payload){
  try{
    await fetch(CONFIRMATION_ENDPOINT, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload)
    });
  }catch(err){
    console.warn("Confirmation email failed to send.", err);
  }
}

document.getElementById("regForm").addEventListener("submit", async function(e){
  e.preventDefault();
  const form = e.target;
  const kidNameVal = form.querySelector(".reg-kidname").value.trim();
  const kidAgeVal = form.querySelector(".reg-kidage").value.trim();
  const parentNameVal = form.querySelector(".reg-parentname").value.trim();
  const emailVal = form.querySelector(".reg-email").value.trim();
  const phoneVal = form.querySelector(".reg-phone").value.trim();
  const allergyVal = form.querySelector(".reg-allergy").value.trim();
  const msgEl = document.getElementById("regMsg");
  try{
    await sendToSheet({
      type: "registration",
      event: EVENT_ID,
      eventTitle: EVENT_TITLE,
      kidName: kidNameVal,
      kidAge: kidAgeVal,
      parentName: parentNameVal,
      email: emailVal,
      phone: phoneVal,
      allergy: allergyVal
    });
    sendConfirmationEmail({
      to: emailVal,
      parentName: parentNameVal,
      kidName: kidNameVal,
      eventTitle: EVENT_TITLE,
      eventDate: EVENT_DATE_DISPLAY,
      eventTime: EVENT_TIME_DISPLAY,
      eventLoc: EVENT_LOC_DISPLAY,
      lang: LANG
    });
    msgEl.textContent = LANG === "fa" ? "ثبت‌نام شما انجام شد — آنجا می‌بینیمتان!" : "You're registered — see you there!";
    msgEl.className = "reg-msg ok";
    form.querySelector(".reg-submit").disabled = true;
  }catch(err){
    msgEl.textContent = LANG === "fa" ? "مشکلی پیش آمد، دوباره تلاش کنید." : "Something went wrong, please try again.";
    msgEl.className = "reg-msg err";
  }
});
</script>
<?php endif; ?>
</body>
</html>
