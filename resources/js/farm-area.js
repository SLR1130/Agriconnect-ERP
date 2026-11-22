export function farmOutlineSquareMeters(points) {
    if (!Array.isArray(points) || points.length < 3) return 0;
    const radians = Math.PI / 180;
    const earthRadius = 6371008.8;
    let sum = 0;
    for (let i = 0; i < points.length; i++) {
        const current = points[i];
        const next = points[(i + 1) % points.length];
        if (![current.latitude, current.longitude, next.latitude, next.longitude].every(Number.isFinite)) return 0;
        let delta = (next.longitude - current.longitude) * radians;
        if (delta > Math.PI) delta -= 2 * Math.PI;
        if (delta < -Math.PI) delta += 2 * Math.PI;
        sum += delta * (2 + Math.sin(current.latitude * radians) + Math.sin(next.latitude * radians));
    }
    return Math.abs(sum * earthRadius * earthRadius / 2);
}

export function farmOutlineInteriorPoint(points) {
    if (!Array.isArray(points) || points.length < 3 || points.some(p => !Number.isFinite(Number(p.latitude)) || !Number.isFinite(Number(p.longitude)))) return null;
    const ys = points.map(p => Number(p.latitude));
    const min = Math.min(...ys), max = Math.max(...ys);
    if (min === max) return null;
    let best = null;
    for (let k = 1; k < 40; k++) {
        const lat = min + (max - min) * k / 40;
        const xs = [];
        points.forEach((point, i) => {
            const next = points[(i + 1) % points.length];
            const a = Number(point.latitude), b = Number(next.latitude);
            if ((a > lat) !== (b > lat)) xs.push(Number(point.longitude) + (lat - a) * (Number(next.longitude) - Number(point.longitude)) / (b - a));
        });
        xs.sort((a, b) => a - b);
        for (let i = 0; i + 1 < xs.length; i += 2) {
            const width = xs[i + 1] - xs[i];
            if (!best || width > best.width) best = { lat, lng: (xs[i] + xs[i + 1]) / 2, width };
        }
    }
    return best ? { lat: best.lat, lng: best.lng } : null;
}
