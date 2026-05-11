export const PLAN_RESULT_STORAGE_KEY = 'ai2.plan-result'

export type EndpointMode = 'address' | 'map'

export interface PlannerEndpoint {
  mode: EndpointMode
  label: string
  lat: number | null
  lon: number | null
  stopId: number | null
}

export interface StopSummary {
  id: number
  stop_id: string
  stop_name: string
  stop_lat: number
  stop_lon: number
  distance_m: number
}

export interface WalkingSegment {
  type: string
  from: { lat: number; lon: number }
  to_stop_id: number
  ors: {
    distance_m: number
    duration_s: number
    geometry: GeoJSON.Geometry | null
  } | null
}

export interface RouteSummary {
  id: number
  route_id: string
  short_name: string
  long_name: string | null
}

export interface TransitLeg {
  trip_id: string
  trip_pk: number
  from_stop_id: number
  to_stop_id: number
  route: RouteSummary
  from_departure_time: string
  to_arrival_time: string
  stops_span: number
}

export type TransitResult =
  | {
      type: 'direct'
      trip_id: string
      trip_pk: number
      from_stop_id: number
      to_stop_id: number
      route: RouteSummary
      from_departure_time: string
      to_arrival_time: string
      stops_span: number
    }
  | {
      type: 'one_transfer'
      transfer_stop_id: number
      legs: TransitLeg[]
    }
  | {
      type: 'multi_transfer'
      transfer_stop_ids: number[]
      legs: TransitLeg[]
    }

export interface PlanRouteResult {
  from_stop: StopSummary
  to_stop: StopSummary
  max_transfers: number
  depart_at: string
  walking_segments: WalkingSegment[]
  transit_options: TransitResult[]
  transit: TransitResult
}

export interface ShapePoint {
  lat: number
  lon: number
  sequence: number
}

export interface TripDetails {
  trip: {
    id: number
    trip_id: string
    shape_id: string | null
    direction_id: number | null
    route: RouteSummary
  }
  stops: Array<{
    stop_sequence: number
    arrival_time: string
    departure_time: string
    stop: StopSummary
  }>
  shape: ShapePoint[]
}
