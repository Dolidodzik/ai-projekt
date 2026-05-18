import type { PlannerEndpoint, TransitResult, WalkingSegment } from './types'

export function createEmptyEndpoint(): PlannerEndpoint {
  return {
    mode: 'address',
    label: '',
    lat: null,
    lon: null,
    stopId: null,
  }
}

export function endpointToParams(
  endpoint: PlannerEndpoint,
  prefix: 'from' | 'to',
): Record<string, number> {
  if (endpoint.stopId !== null) {
    return { [`${prefix}_stop_id`]: endpoint.stopId }
  }

  if (endpoint.lat === null || endpoint.lon === null) {
    return {}
  }

  return {
    [`${prefix}_lat`]: endpoint.lat,
    [`${prefix}_lon`]: endpoint.lon,
  }
}

export function validateEndpoint(endpoint: PlannerEndpoint): string | null {
  if (endpoint.stopId !== null) {
    return null
  }

  if (endpoint.lat === null || endpoint.lon === null) {
    return 'Select an address or map point.'
  }

  return null
}

function padTimePart(value: number): string {
  return String(value).padStart(2, '0')
}

export function formatDateInputValue(date: Date): string {
  return `${date.getFullYear()}-${padTimePart(date.getMonth() + 1)}-${padTimePart(date.getDate())}`
}

export function formatTimeInputValue(date: Date): string {
  return `${padTimePart(date.getHours())}:${padTimePart(date.getMinutes())}`
}

export function validateTimeInput24(value: string): string | null {
  if (!/^([01]\d|2[0-3]):[0-5]\d$/.test(value)) {
    return 'Use 24-hour time in HH:mm format.'
  }

  return null
}

export function formatLocaleDateTime24(value: string): string {
  const date = new Date(value)

  return `${padTimePart(date.getDate())}.${padTimePart(date.getMonth() + 1)}.${date.getFullYear()}, ${formatTimeInputValue(date)}`
}

export function formatWalkingSummary(segments: WalkingSegment[]): string | null {
  if (segments.length === 0) {
    return null
  }

  const totalMeters = segments.reduce((sum, segment) => sum + (segment.ors?.distance_m ?? 0), 0)
  const totalSeconds = segments.reduce((sum, segment) => sum + (segment.ors?.duration_s ?? 0), 0)

  if (totalMeters <= 0 && totalSeconds <= 0) {
    return `${segments.length} walking segment(s)`
  }

  return `${Math.round(totalMeters)} m, ${Math.round(totalSeconds / 60)} min`
}

function gtfsTimeToSeconds(time: string): number {
  const [hours, minutes, seconds] = time.split(':').map(Number)
  return hours * 3600 + minutes * 60 + seconds
}

function localAnchorSeconds(departAt: string): number {
  const formatter = new Intl.DateTimeFormat('en-GB', {
    timeZone: 'Europe/Warsaw',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false,
  })
  const parts = formatter.formatToParts(new Date(departAt))
  const hour = Number(parts.find((part) => part.type === 'hour')?.value ?? 0)
  const minute = Number(parts.find((part) => part.type === 'minute')?.value ?? 0)
  const second = Number(parts.find((part) => part.type === 'second')?.value ?? 0)

  return hour * 3600 + minute * 60 + second
}

function minutesUntilDeparture(gtfsTime: string, departAt: string): number {
  const delta = gtfsTimeToSeconds(gtfsTime) - localAnchorSeconds(departAt)
  return Math.max(0, Math.round(delta / 60))
}

export function transitSummary(transit: TransitResult, departAt: string): string {
  const firstDeparture = transit.type === 'direct' ? transit.from_departure_time : transit.legs[0].from_departure_time
  const minutes = minutesUntilDeparture(firstDeparture, departAt)

  if (transit.type === 'direct') {
    return `Departure in ${minutes} min, line ${transit.route.short_name}`
  }

  const lines = transit.legs.map((leg) => leg.route.short_name).join(' -> ')

  return `Departure in ${minutes} min, ${lines}`
}

export function getTripPks(transit: TransitResult): number[] {
  if (transit.type === 'direct') {
    return [transit.trip_pk]
  }

  return transit.legs.map((leg) => leg.trip_pk)
}

function gtfsTimeSpanMinutes(fromTime: string, toTime: string): number {
  let fromSeconds = gtfsTimeToSeconds(fromTime)
  let toSeconds = gtfsTimeToSeconds(toTime)

  if (toSeconds < fromSeconds) {
    toSeconds += 24 * 3600
  }

  return Math.max(1, Math.round((toSeconds - fromSeconds) / 60))
}

export function transitDurationMinutes(transit: TransitResult): number {
  if (transit.type === 'direct') {
    return gtfsTimeSpanMinutes(transit.from_departure_time, transit.to_arrival_time)
  }

  const firstLeg = transit.legs[0]
  const lastLeg = transit.legs[transit.legs.length - 1]

  return gtfsTimeSpanMinutes(firstLeg.from_departure_time, lastLeg.to_arrival_time)
}

export function formatDurationMinutes(minutes: number | null | undefined): string {
  if (minutes === null || minutes === undefined || minutes < 1) {
    return '—'
  }

  if (minutes < 60) {
    return `${minutes} min`
  }

  const hours = Math.floor(minutes / 60)
  const rest = minutes % 60

  if (rest === 0) {
    return `${hours} h`
  }

  return `${hours} h ${rest} min`
}
