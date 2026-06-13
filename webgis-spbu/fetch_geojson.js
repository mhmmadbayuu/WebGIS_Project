const https = require('https');
const fs = require('fs');

const kecamatans = [
  "Kecamatan Pontianak Barat",
  "Kecamatan Pontianak Kota",
  "Kecamatan Pontianak Selatan",
  "Kecamatan Pontianak Tenggara",
  "Kecamatan Pontianak Timur",
  "Kecamatan Pontianak Utara"
];

const featureCollection = {
  type: "FeatureCollection",
  features: []
};

let completed = 0;

function fetchGeojson(name) {
  const url = `https://nominatim.openstreetmap.org/search.php?q=${encodeURIComponent(name)}&polygon_geojson=1&format=jsonv2`;
  const options = {
    headers: { 'User-Agent': 'WebGIS-SPBU-App/1.0' }
  };
  
  https.get(url, options, (res) => {
    let data = '';
    res.on('data', chunk => data += chunk);
    res.on('end', () => {
      const results = JSON.parse(data);
      const geomResult = results.find(r => r.geojson && r.category === 'boundary' && r.type === 'administrative');
      if (geomResult) {
        featureCollection.features.push({
          type: "Feature",
          properties: {
            Kecamatan: name.replace('Kecamatan ', ''),
            Name: name
          },
          geometry: geomResult.geojson
        });
        console.log("Fetched " + name);
      } else {
        console.log("Not found for " + name);
      }
      completed++;
      if (completed === kecamatans.length) {
        fs.writeFileSync('js/pontianak_kecamatan.geojson', JSON.stringify(featureCollection, null, 2));
        console.log("All done! Saved to js/pontianak_kecamatan.geojson");
      }
    });
  }).on('error', (e) => {
    console.error(e);
  });
}

// Nominatim has a usage policy of 1 request per second. We will stagger the requests.
kecamatans.forEach((kec, i) => {
  setTimeout(() => {
    fetchGeojson(kec);
  }, i * 1500); // 1.5 seconds apart
});
