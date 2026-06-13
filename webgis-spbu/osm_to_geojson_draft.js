const fs = require('fs');

const data = JSON.parse(fs.readFileSync('osm_pontianak.json', 'utf8'));

const geojson = {
  type: 'FeatureCollection',
  features: []
};

data.elements.forEach(el => {
  if (el.type === 'relation' && el.tags && el.tags.admin_level === '6') {
    const name = el.tags.name;
    // Extract polygons from relation members
    const coordinates = [];
    el.members.forEach(member => {
      if (member.type === 'way' && member.geometry) {
        const wayCoords = member.geometry.map(pt => [pt.lon, pt.lat]);
        coordinates.push(wayCoords);
      }
    });
    
    // Assemble into MultiPolygon or Polygon (simplistic)
    if (coordinates.length > 0) {
      geojson.features.push({
        type: 'Feature',
        properties: {
          kecamatan: name,
          ...el.tags
        },
        geometry: {
          type: 'Polygon',
          coordinates: coordinates
        }
      });
    }
  }
});

// Since OSM returns fragments of outer and inner rings in relations, a naive conversion might have topological issues in Leaflet (like crossing lines if not ordered).
// We should use an existing library like osmtogeojson. Let's install it and use it.
