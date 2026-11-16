<?php
include 'koneksi.php';

/* Ambil data penduduk dari basis data */
$result = mysqli_query($conn, "SELECT * FROM penduduk ORDER BY kecamatan");
$data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $row['longitude'] = (float)$row['longitude'];
    $row['latitude']  = (float)$row['latitude'];
    $row['luas']      = (float)$row['luas'];
    $row['jumlah_penduduk'] = (int)$row['jumlah_penduduk'];
    $row['kepadatan'] = $row['luas'] > 0 ? round($row['jumlah_penduduk'] / $row['luas'], 1) : 0;
    $data[] = $row;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Web GIS Data Penduduk</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.5.1/dist/leaflet.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet-measure@3.1.0/dist/leaflet-measure.css">
<style>
  * { box-sizing: border-box; }
  html, body { height: 100%; margin: 0; font-family: system-ui, sans-serif; color: #1d2b2a; }
  .app { display: grid; grid-template-columns: 1fr 400px; height: 100%; }
  #map { height: 100%; }
  .panel { overflow-y: auto; background: #f6f8f7; border-left: 1px solid #cfd8d5; padding: 16px; }
  h1 { font-size: 1.15rem; margin: 0 0 4px; }
  h2 { font-size: 1rem; margin: 20px 0 8px; }
  p { margin: 0 0 12px; font-size: .85rem; color: #51615e; }
  table { width: 100%; border-collapse: collapse; font-size: .82rem; background: #fff; }
  th, td { padding: 7px; border-bottom: 1px solid #dfe6e3; text-align: left; }
  th { background: #1f4f4a; color: #fff; font-weight: 600; }
  td.n, th.n { text-align: right; }
  tbody tr { cursor: pointer; }
  tbody tr:hover, tbody tr:focus { background: #e3f0ed; outline: none; }
  form { background: #fff; border: 1px solid #dfe6e3; padding: 12px; }
  label { display: block; font-size: .82rem; margin-bottom: 2px; }
  input[type=text], input[type=number] { width: 100%; padding: 6px; margin-bottom: 10px; border: 1px solid #b8c5c1; }
  input[type=submit] { background: #1f4f4a; color: #fff; border: 0; padding: 8px 16px; cursor: pointer; }
  #informasi { color: #b91c1c; margin: 8px 0 0; }
  @media (max-width: 800px) {
    .app { grid-template-columns: 1fr; grid-template-rows: 55% 45%; }
    .panel { border-left: 0; border-top: 1px solid #cfd8d5; }
  }
</style>
</head>
<body>
<div class="app">
  <div id="map"></div>
  <aside class="panel">
    <h1>Data penduduk per kecamatan</h1>
    <p>Klik baris untuk memusatkan peta pada kecamatan tersebut.</p>
    <table>
      <thead><tr><th>Kecamatan</th><th class="n">Luas</th><th class="n">Penduduk</th><th class="n">Kepadatan</th></tr></thead>
      <tbody>
      <?php foreach ($data as $i => $d): ?>
        <tr tabindex="0" data-i="<?= $i ?>">
          <td><?= htmlspecialchars($d['kecamatan']) ?></td>
          <td class="n"><?= $d['luas'] ?></td>
          <td class="n"><?= number_format($d['jumlah_penduduk'], 0, ',', '.') ?></td>
          <td class="n"><?= $d['kepadatan'] ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <h2>Tambah data</h2>
    <form action="input.php" method="post" onsubmit="return validateForm()">
      <label for="kec">Kecamatan</label>
      <input type="text" id="kec" name="kecamatan" required>
      <label for="long">Longitude</label>
      <input type="number" step="any" id="long" name="longitude" required>
      <label for="lat">Latitude</label>
      <input type="number" step="any" id="lat" name="latitude" required>
      <label for="luas">Luas</label>
      <input type="number" step="any" id="luas" name="luas" required>
      <label for="jml_ppdk">Jumlah Penduduk</label>
      <input type="number" id="jml_ppdk" name="jumlah_penduduk" min="0" required>
      <input type="submit" value="Simpan data">
      <p id="informasi"></p>
    </form>
    <p>Tip: klik di peta untuk mengisi longitude dan latitude otomatis.</p>
  </aside>
</div>

<script src="https://unpkg.com/leaflet@1.5.1/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-measure@3.1.0/dist/leaflet-measure.js"></script>
<script>
var data = <?= json_encode($data, JSON_UNESCAPED_UNICODE) ?>;

var map = L.map('map').setView([-7.677567, 110.406899], 11);

/* Basemap */
var basemap1 = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
  attribution: 'Tiles &copy; Esri'
});
var basemap2 = L.tileLayer('https://{s}.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', {
  maxZoom: 20, subdomains: ['mt0','mt1','mt2','mt3'], attribution: 'Map data &copy; Google'
});
var basemap3 = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '&copy; OpenStreetMap contributors'
});
basemap1.addTo(map);

/* Titik dari basis data (koordinat longitude, latitude) */
var layerKec = L.layerGroup().addTo(map);
var markers = [], bounds = [];
data.forEach(function (d) {
  var m = L.marker([d.latitude, d.longitude]);
  m.bindPopup(
    "<b>Kec. " + d.kecamatan + "</b><br>" +
    "Luas: " + d.luas + "<br>" +
    "Jumlah penduduk: " + d.jumlah_penduduk.toLocaleString('id-ID') + "<br>" +
    "Kepadatan: " + d.kepadatan
  );
  m.addTo(layerKec);
  markers.push(m);
  bounds.push([d.latitude, d.longitude]);
});
if (bounds.length) map.fitBounds(bounds, {padding: [40, 40]});

/* Layer control, scale bar, alat ukur */
L.control.layers(
  {"Esri Imagery": basemap1, "Google Street": basemap2, "OpenStreetMap": basemap3},
  {"Titik kecamatan": layerKec},
  {collapsed: false}
).addTo(map);

L.control.scale({maxWidth: 150, position: 'bottomright'}).addTo(map);

L.control.measure({
  position: 'topleft',
  primaryLengthUnit: 'meters', secondaryLengthUnit: 'kilometers',
  primaryAreaUnit: 'sqmeters', secondaryAreaUnit: 'hectares',
  activeColor: '#c2410c', completedColor: '#1f4f4a'
}).addTo(map);

/* Tabel -> peta */
document.querySelectorAll('tbody tr').forEach(function (tr) {
  function go() {
    var m = markers[tr.dataset.i];
    map.flyTo(m.getLatLng(), 14);
    m.openPopup();
  }
  tr.addEventListener('click', go);
  tr.addEventListener('keydown', function (e) { if (e.key === 'Enter') go(); });
});

/* Klik peta -> isi form koordinat */
map.on('click', function (e) {
  document.getElementById('lat').value = e.latlng.lat.toFixed(6);
  document.getElementById('long').value = e.latlng.lng.toFixed(6);
});

/* Validasi form (dari Latihan 8) */
function validateForm() {
  var luas = document.getElementById("luas").value;
  var info = document.getElementById("informasi");
  if (luas === "" || isNaN(luas) || Number(luas) <= 0) {
    info.innerHTML = "Luas harus berupa angka lebih dari 0.";
    return false;
  }
  info.innerHTML = "";
  return true;
}
</script>
</body>
</html>