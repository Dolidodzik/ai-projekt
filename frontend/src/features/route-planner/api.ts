import { apiGet } from '../../lib/api'
import type { PlanRouteResult, TripDetails } from './types'

export interface PlanRouteParams {
  from_lat?: number
  from_lon?: number
  to_lat?: number
  to_lon?: number
  from_stop_id?: number
  to_stop_id?: number
  max_transfers?: number
  depart_at?: string
}

export function planRoute(params: PlanRouteParams): Promise<PlanRouteResult> {
  return apiGet<PlanRouteResult>('/plan-route', params as Record<string, string | number | undefined>)
}

export function fetchTripDetails(tripPk: number): Promise<TripDetails> {
  return apiGet<TripDetails>(`/trip-details/${tripPk}`)
}
