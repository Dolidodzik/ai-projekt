import type { ApiRequestFn } from '../tickets/types'

export type { ApiRequestFn }

export type RideHistoryEntry = {
  id: number
  trip_id: number
  trip_code: string | null
  route_short_name: string | null
  route_long_name: string | null
  from_stop_id: number
  from_stop_name: string | null
  to_stop_id: number
  to_stop_name: string | null
  duration_minutes: number | null
  created_at: string
}

export { formatDurationMinutes } from '../../features/route-planner/utils'

export type RideHistoryMeta = {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export type RideHistoryResponse = {
  data: RideHistoryEntry[]
  meta: RideHistoryMeta
}

export function formatSearchDate(value: string): string {
  return new Date(value).toLocaleString('pl-PL', {
    weekday: 'long',
    day: '2-digit',
    month: 'long',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}
