const https = require('https');
const fs = require('fs');

const query = `[out:json];
area["name"="Pontianak"]->.searchArea;
(
  relation["admin_level"="6"](area.searchArea);
);
out geom;`;

const options = {
  hostname: 'overpass-api.de',
  path: '/api/interpreter',
  method: 'POST',
  headers: {
    'Content-Type': 'application/x-www-form-urlencoded',
  }
};

const req = https.request(options, (res) => {
  let data = '';
  res.on('data', (chunk) => { data += chunk; });
  res.on('end', () => {
    fs.writeFileSync('osm_pontianak.json', data);
    console.log('Done downloading OSM data.');
  });
});
req.write(query);
req.end();
