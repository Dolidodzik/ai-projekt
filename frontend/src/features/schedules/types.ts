import type { ShapePoint } from '../route-planner/types'

export interface ListedRoute {
  id: number
  route_id: string
  short_name: string
  long_name: string | null
  route_type: number
}

export interface ListedStop {
  id: number
  stop_id: string
  stop_name: string
  stop_lat: number
  stop_lon: number
}

export interface PatternStopRow {
  stop_sequence: number
  arrival_time: string
  departure_time: string
  stop: ListedStop
}

export interface DirectionPattern {
  direction_key: number
  direction_id: number | null
  representative_trip_id: number
  headsign: string | null
  stops: PatternStopRow[]
  shape: ShapePoint[]
}

export interface RoutePatternResponse {
  route: ListedRoute
  directions: DirectionPattern[]
  endpoint_split: boolean
}
