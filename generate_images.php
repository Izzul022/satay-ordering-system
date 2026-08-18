<?php
// Helper script to generate elegant restaurant SVG dish visuals without emoji

$images = [
    'satay_ayam.jpg' => ['title' => 'Satay Ayam', 'subtitle' => 'Charcoal Skewers', 'bg' => '#3D2214', 'accent' => '#C4956A', 'initials' => 'AYAM'],
    'satay_daging.jpg' => ['title' => 'Satay Daging', 'subtitle' => 'Tender Beef Skewers', 'bg' => '#331B1B', 'accent' => '#B85D43', 'initials' => 'DAGING'],
    'satay_kambing.jpg' => ['title' => 'Satay Kambing', 'subtitle' => 'Mutton Skewers', 'bg' => '#3A2614', 'accent' => '#C88D4B', 'initials' => 'KAMBING'],
    'satay_perut.jpg' => ['title' => 'Satay Perut', 'subtitle' => 'Tripe Skewers', 'bg' => '#2E2218', 'accent' => '#B57C48', 'initials' => 'PERUT'],
    'satay_bebek.jpg' => ['title' => 'Satay Bebek', 'subtitle' => 'Smoked Duck', 'bg' => '#351D16', 'accent' => '#A8624C', 'initials' => 'BEBEK'],
    'satay_rusa.jpg' => ['title' => 'Satay Rusa', 'subtitle' => 'Wild Venison', 'bg' => '#2D1B18', 'accent' => '#9E5040', 'initials' => 'RUSA'],
    'platter_meriah.jpg' => ['title' => 'Platter Meriah 30', 'subtitle' => 'Mixed Satay Feast', 'bg' => '#342210', 'accent' => '#D49A50', 'initials' => 'COMBO 30'],
    'platter_diraja.jpg' => ['title' => 'Platter Diraja 50', 'subtitle' => 'Royal Family Platter', 'bg' => '#382012', 'accent' => '#E0A054', 'initials' => 'COMBO 50'],
    'platter_mini.jpg' => ['title' => 'Platter Mini 15', 'subtitle' => 'Duo Combo Set', 'bg' => '#30221B', 'accent' => '#C6874E', 'initials' => 'COMBO 15'],
    'ketupat.jpg' => ['title' => 'Ketupat Nasi Impit', 'subtitle' => 'Compressed Rice', 'bg' => '#1F2E22', 'accent' => '#5C8A67', 'initials' => 'KETUPAT'],
    'kuah_kacang.jpg' => ['title' => 'Kuah Kacang Pekat', 'subtitle' => 'Spicy Peanut Sauce', 'bg' => '#3B2313', 'accent' => '#C67A43', 'initials' => 'KUAH KACANG'],
    'sambal_kicap.jpg' => ['title' => 'Sambal Kicap Cili', 'subtitle' => 'Fiery Soy & Lime', 'bg' => '#281A1A', 'accent' => '#A84D43', 'initials' => 'SAMBAL KICAP'],
    'acar.jpg' => ['title' => 'Acar Timun Bawang', 'subtitle' => 'Fresh Crisp Relish', 'bg' => '#1E2C22', 'accent' => '#6A9E75', 'initials' => 'ACAR'],
    'teh_tarik.jpg' => ['title' => 'Teh Tarik Kaw Ais', 'subtitle' => 'Pulled Frothy Tea', 'bg' => '#36271D', 'accent' => '#B89B84', 'initials' => 'TEH TARIK'],
    'bandung.jpg' => ['title' => 'Sirap Bandung Selasih', 'subtitle' => 'Rose Milk & Basil', 'bg' => '#381C26', 'accent' => '#C96C88', 'initials' => 'BANDUNG'],
    'kelapa.jpg' => ['title' => 'Jus Kelapa Muda', 'subtitle' => 'Fresh Coconut Juice', 'bg' => '#1C2C2A', 'accent' => '#4FA399', 'initials' => 'KELAPA'],
    'cendol.jpg' => ['title' => 'Cendol Royale', 'subtitle' => 'Gula Melaka Shaved Ice', 'bg' => '#28281C', 'accent' => '#8CA85C', 'initials' => 'CENDOL']
];

$dir = __DIR__ . '/assets/images';
if (!is_dir($dir)) mkdir($dir, 0777, true);

foreach ($images as $filename => $info) {
    // Generate SVG format with warm culinary aesthetic
    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 400" width="100%" height="100%">
  <defs>
    <radialGradient id="bgGlow_{$filename}" cx="50%" cy="50%" r="65%">
      <stop offset="0%" stop-color="{$info['accent']}" stop-opacity="0.30"/>
      <stop offset="100%" stop-color="{$info['bg']}" stop-opacity="1"/>
    </radialGradient>
    <filter id="shadow_{$filename}" x="-20%" y="-20%" width="140%" height="140%">
      <feDropShadow dx="0" dy="6" stdDeviation="10" flood-color="#000" flood-opacity="0.4"/>
    </filter>
  </defs>

  <!-- Background -->
  <rect width="600" height="400" fill="url(#bgGlow_{$filename})"/>

  <!-- Warm ambient circles -->
  <circle cx="100" cy="80" r="140" fill="{$info['accent']}" opacity="0.08"/>
  <circle cx="520" cy="320" r="160" fill="{$info['accent']}" opacity="0.08"/>

  <!-- Elegant Platter Plate -->
  <ellipse cx="300" cy="205" rx="190" ry="105" fill="#241711" stroke="{$info['accent']}" stroke-width="2" opacity="0.9" filter="url(#shadow_{$filename})"/>
  <ellipse cx="300" cy="205" rx="170" ry="90" fill="none" stroke="rgba(255,255,255,0.12)" stroke-width="1.5" stroke-dasharray="6 4"/>

  <!-- Dish Title Badge -->
  <rect x="180" y="180" width="240" height="50" rx="6" fill="#1A100B" stroke="{$info['accent']}" stroke-width="1.5" filter="url(#shadow_{$filename})"/>
  <text x="300" y="212" font-family="'Playfair Display', Georgia, serif" font-size="18" font-weight="700" fill="#FAF8F5" text-anchor="middle" letter-spacing="2">
    {$info['initials']}
  </text>

  <!-- Bottom Details -->
  <rect x="0" y="325" width="600" height="75" fill="rgba(20, 12, 8, 0.85)"/>
  <text x="40" y="356" font-family="'Playfair Display', Georgia, serif" font-size="22" font-weight="bold" fill="#FAF8F5" letter-spacing="0.5">
    {$info['title']}
  </text>
  <text x="40" y="380" font-family="'DM Sans', sans-serif" font-size="14" fill="{$info['accent']}">
    {$info['subtitle']} • Warung Satay Royale
  </text>
</svg>
SVG;

    file_put_contents("$dir/$filename", $svg);
    echo "Generated: $filename\n";
}

echo "\nAll images successfully generated in elegant restaurant styling.\n";
