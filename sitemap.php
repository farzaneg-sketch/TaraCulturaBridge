<?php
// Dynamic sitemap: lists the homepage (en/fa) plus every event page (en/fa),
// generated from the same Google Sheet the site itself reads from.
header('Content-Type: application/xml; charset=utf-8');

$SHEETS_ENDPOINT = "https://script.google.com/macros/s/AKfycbz17nLpRn3llqi-0UYypDrjoUQg57SEyKT3yRZpGgUSXBRELVakcVpwr48lIpXO9dbw/exec";

$events = [];
$ch = curl_init($SHEETS_ENDPOINT . '?action=events');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 10,
]);
$json = curl_exec($ch);
curl_close($ch);
if ($json !== false) {
    $decoded = json_decode($json, true);
    if (is_array($decoded)) {
        $events = $decoded;
    }
}

function urlEntry($loc, $priority) {
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($loc, ENT_QUOTES, 'UTF-8') . "</loc>\n";
    echo "    <changefreq>weekly</changefreq>\n";
    echo "    <priority>{$priority}</priority>\n";
    echo "  </url>\n";
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
urlEntry("https://tarabridge.ca/", "1.0");
urlEntry("https://tarabridge.ca/?lang=fa", "0.9");
foreach ($events as $ev) {
    if (empty($ev['id'])) {
        continue;
    }
    $base = "https://tarabridge.ca/events/" . rawurlencode($ev['id']) . ".html";
    urlEntry($base, "0.8");
    urlEntry($base . "?lang=fa", "0.7");
}
echo '</urlset>' . "\n";
