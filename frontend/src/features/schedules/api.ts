import { apiGet } from '../../lib/api'
import type { ListedRoute, RoutePatternResponse } from './types'

export async function fetchRoutesList(): Promise<ListedRoute[]> {
  const res = await apiGet<{ routes: ListedRoute[] }>('/routes/list')
  return res.routes
}

export async function fetchRoutePattern(routeId: number): Promise<RoutePatternResponse> {
  return apiGet<RoutePatternResponse>(`/schedules/routes/${routeId}/pattern`)
}

export async function fetchRouteStopTimes(params: {
  routeId: number
  stopId: number
  directionKey: number | null
  date: string
  tripPatternId?: number
  useTripEndpoints?: boolean
}): Promise<{ date: string; times: string[] }> {
  return apiGet<{ date: string; times: string[] }>(
    `/schedules/routes/${params.routeId}/stops/${params.stopId}/departures`,
    {
      direction_key: params.directionKey ?? undefined,
      trip_pattern_id: params.tripPatternId,
      use_trip_endpoints: params.useTripEndpoints === true ? 1 : undefined,
      date: params.date,
    },
  )
}
