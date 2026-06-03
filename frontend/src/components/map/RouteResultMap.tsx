import { MapContainer, Marker, Polyline, TileLayer } from 'react-leaflet'
import type { PlanRouteResult, TransitLeg, TransitResult, TripDetails } from '../../features/route-planner/types'
import { clipShapeBetweenStops, geometryToLatLngs, polylineFromStopsBetween } from './geo'

interface RouteResultMapProps {
  planResult: PlanRouteResult
  tripDetails: TripDetails[]
  transit: TransitResult
}

function getLegs(transit: TransitResult): TransitLeg[] {
  if (transit.type === 'direct') {
    return [
      {
        trip_id: transit.trip_id,
        trip_pk: transit.trip_pk,
        from_stop_id: transit.from_stop_id,
        to_stop_id: transit.to_stop_id,
        route: transit.route,
        from_departure_time: transit.from_departure_time,
        to_arrival_time: transit.to_arrival_time,
        stops_span: transit.stops_span,
      },
    ]
  }

  return transit.legs
}

export function RouteResultMap({ planResult, tripDetails, transit }: RouteResultMapProps) {
  const walkLines = planResult.walking_segments.flatMap((segment) => geometryToLatLngs(segment.ors?.geometry ?? null))
  const legs = getLegs(transit)
  const transitLines = legs.flatMap((leg) => {
    const detail = tripDetails.find((item) => item.trip.id === leg.trip_pk)
    if (!detail) {
      return []
    }

    const fromStop = detail.stops.find((stop) => stop.stop.id === leg.from_stop_id)?.stop
    const toStop = detail.stops.find((stop) => stop.stop.id === leg.to_stop_id)?.stop
    if (!fromStop || !toStop) {
      return []
    }

    const clipped = clipShapeBetweenStops(
      detail.shape,
      fromStop.stop_lat,
      fromStop.stop_lon,
      toStop.stop_lat,
      toStop.stop_lon,
    )
    if (clipped.length >= 2) {
      return clipped
    }
    return polylineFromStopsBetween(detail, leg.from_stop_id, leg.to_stop_id)
  })
  const center: [number, number] = [planResult.from_stop.stop_lat, planResult.from_stop.stop_lon]

  return (
    <MapContainer center={center} zoom={13} className="h-[480px] w-full rounded-xl">
      <TileLayer url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png" />
      <Marker position={[planResult.from_stop.stop_lat, planResult.from_stop.stop_lon]} />
      <Marker position={[planResult.to_stop.stop_lat, planResult.to_stop.stop_lon]} />
      {walkLines.length > 0 ? <Polyline positions={walkLines} color="#2563eb" weight={4} /> : null}
      {transitLines.length > 0 ? <Polyline positions={transitLines} color="#1754d8" weight={5} /> : null}
    </MapContainer>
  )
}
