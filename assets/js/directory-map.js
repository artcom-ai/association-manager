(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var el = document.getElementById('am-directory-map');

        if (!el || typeof L === 'undefined') {
            return;
        }

        var points = [];
        try {
            points = JSON.parse(el.dataset.points || '[]');
        } catch (e) {
            points = [];
        }

        var center = points.length > 0 ? [points[0].lat, points[0].lng] : [0, 0];
        var map = L.map(el).setView(center, points.length > 0 ? 6 : 2);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(map);

        points.forEach(function (point) {
            L.marker([point.lat, point.lng]).addTo(map).bindPopup(point.label || '');
        });
    });
})();
