import type { LatLngExpression } from 'leaflet'
import type { ShapePoint, TripDetails } from '../../features/route-planner/types'

export function geometryToLatLngs(geometry: GeoJSON.Geometry | null): LatLngExpression[] {
  if (!geometry) {
    return []
  }

  if (geometry.type === 'LineString') {
    return geometry.coordinates.map(([lon, lat]) => [lat, lon] as LatLngExpression)
  }

  if (geometry.type === 'MultiLineString') {
    return geometry.coordinates.flatMap((line) => line.map(([lon, lat]) => [lat, lon] as LatLngExpression))
  }

  return []
}

export function shapeToLatLngs(shape: ShapePoint[]): LatLngExpression[] {
  return [...shape]
    .sort((a, b) => a.sequence - b.sequence)
    .map((point) => [point.lat, point.lon] as LatLngExpression)
}

function haversineMeters(lat1: number, lon1: number, lat2: number, lon2: number): number {
  const earthRadius = 6371000
  const latFrom = (lat1 * Math.PI) / 180
  const latTo = (lat2 * Math.PI) / 180
  const latDelta = ((lat2 - lat1) * Math.PI) / 180
  const lonDelta = ((lon2 - lon1) * Math.PI) / 180
  const a =
    Math.sin(latDelta / 2) ** 2 + Math.cos(latFrom) * Math.cos(latTo) * Math.sin(lonDelta / 2) ** 2

  return earthRadius * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))
}

function nearestShapeIndex(shape: ShapePoint[], lat: number, lon: number): number {
  let bestDistance = Number.POSITIVE_INFINITY
  let bestIndex = 0

  shape.forEach((point, index) => {
    const distance = haversineMeters(lat, lon, point.lat, point.lon)
    if (distance < bestDistance) {
      bestDistance = distance
      bestIndex = index
    }
  })

  return bestIndex
}

export function clipShapeBetweenStops(
  shape: ShapePoint[],
  fromLat: number,
  fromLon: number,
  toLat: number,
  toLon: number,
): LatLngExpression[] {
  if (shape.length < 2) {
    return []
  }

  const ordered = [...shape].sort((a, b) => a.sequence - b.sequence)
  let fromIndex = nearestShapeIndex(ordered, fromLat, fromLon)
  let toIndex = nearestShapeIndex(ordered, toLat, toLon)

  if (fromIndex > toIndex) {
    ;[fromIndex, toIndex] = [toIndex, fromIndex]
  }

  return ordered.slice(fromIndex, toIndex + 1).map((point) => [point.lat, point.lon] as LatLngExpression)
}

export function polylineFromStopsBetween(
  detail: TripDetails,
  fromStopPk: number,
  toStopPk: number,
): LatLngExpression[] {
  const stops = detail.stops
  const fromIdx = stops.findIndex((row) => row.stop.id === fromStopPk)
  const toIdx = stops.findIndex((row) => row.stop.id === toStopPk)
  if (fromIdx === -1 || toIdx === -1 || fromIdx > toIdx) {
    return []
  }
  const pts = stops
    .slice(fromIdx, toIdx + 1)
    .map((row) => [row.stop.stop_lat, row.stop.stop_lon] as LatLngExpression)
  if (pts.length === 1) {
    return [pts[0], pts[0]]
  }
  return pts
}
