import { getApiBaseUrl } from '../../lib/api'
import type { PlanRouteResult, TransitResult } from '../route-planner/types'
import { getTripPks, transitDurationMinutes } from '../route-planner/utils'

export async function saveRideToHistory(
  token: string,
  planResult: PlanRouteResult,
  transit: TransitResult,
): Promise<void> {
  const tripId = getTripPks(transit)[0]
  if (!tripId) {
    return
  }

  const response = await fetch(`${getApiBaseUrl()}/ride-history/add`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      Authorization: `Bearer ${token}`,
    },
    body: JSON.stringify({
      trip_id: tripId,
      from_stop_id: planResult.from_stop.id,
      to_stop_id: planResult.to_stop.id,
      duration_minutes: transitDurationMinutes(transit),
    }),
  })

  if (!response.ok) {
    const payload = await response.json().catch(() => ({}))
    const message = payload?.message ?? `HTTP ${response.status}`
    throw new Error(String(message))
  }
}
