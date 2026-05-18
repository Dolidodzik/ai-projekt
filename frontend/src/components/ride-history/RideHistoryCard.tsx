import type { ReactNode } from 'react'
import type { RideHistoryEntry } from './types'
import { formatSearchDate } from './types'

type RideHistoryCardProps = {
  ride: RideHistoryEntry
}

export function RideHistoryCard({ ride }: RideHistoryCardProps) {
  const lineLabel = ride.route_short_name
    ? `Linia ${ride.route_short_name}`
    : ride.route_long_name

  return (
    <article className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
      <div className="flex flex-col md:flex-row">
        <MapArea />

        <div className="flex flex-1 flex-col gap-3 p-4 md:p-5">
          <div className="flex flex-wrap items-start justify-between gap-2">
            <h3 className="text-sm font-semibold text-slate-900">Wyszukany przejazd</h3>
            <span className="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs text-slate-500">#{ride.id}</span>
          </div>

          <div className="grid gap-3 sm:grid-cols-2">
            <InfoSlot label="Linia" placeholder="Tu będzie linia">
              {lineLabel ? <p className="font-medium text-slate-900">{lineLabel}</p> : null}
              {ride.route_long_name && ride.route_short_name ? (
                <p className="mt-0.5 text-xs text-slate-500">{ride.route_long_name}</p>
              ) : null}
            </InfoSlot>

            <InfoSlot label="Data i godzina wyszukania" placeholder="Tu będzie data i godzina">
              <p className="font-medium text-slate-900">{formatSearchDate(ride.created_at)}</p>
            </InfoSlot>

            <InfoSlot label="Przystanek początkowy" placeholder="Tu będzie przystanek startowy">
              {ride.from_stop_name ? <p className="font-medium text-slate-900">{ride.from_stop_name}</p> : null}
            </InfoSlot>

            <InfoSlot label="Przystanek docelowy" placeholder="Tu będzie przystanek docelowy">
              {ride.to_stop_name ? <p className="font-medium text-slate-900">{ride.to_stop_name}</p> : null}
            </InfoSlot>

            <InfoSlot label="Numer kursu" placeholder="Tu będzie numer kursu">
              {ride.trip_code ? <p className="font-mono font-medium text-slate-900">{ride.trip_code}</p> : null}
            </InfoSlot>

            <InfoSlot label="Czas przejazdu" placeholder="Tu będzie czas przejazdu" />
          </div>

          <RouteSummary from={ride.from_stop_name} to={ride.to_stop_name} />
        </div>
      </div>
    </article>
  )
}

function MapArea() {
  return (
    <div className="flex min-h-[140px] items-center justify-center border-b border-dashed border-slate-200 bg-gradient-to-br from-slate-50 to-slate-100 md:min-h-[200px] md:w-2/5 md:border-b-0 md:border-r">
      <div className="flex flex-col items-center gap-2 px-4 text-center">
        <div className="flex h-12 w-12 items-center justify-center rounded-full bg-white shadow-sm">
          <svg className="h-6 w-6 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden>
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={1.5}
              d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l5.447 2.724A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"
            />
          </svg>
        </div>
        <p className="text-sm font-medium text-slate-500">Tu będzie mapa</p>
        <p className="text-xs text-slate-400">Podgląd trasy na mapie</p>
      </div>
    </div>
  )
}

type InfoSlotProps = {
  label: string
  placeholder: string
  children?: ReactNode
}

function InfoSlot({ label, placeholder, children }: InfoSlotProps) {
  const hasValue = Boolean(children)

  return (
    <div
      className={`rounded-xl border border-dashed p-3 ${
        hasValue ? 'border-slate-200 bg-white' : 'border-slate-300 bg-slate-50/80'
      }`}
    >
      <p className="mb-1.5 text-xs font-medium uppercase tracking-wide text-slate-400">{label}</p>
      {hasValue ? children : <p className="text-sm italic text-slate-400">{placeholder}</p>}
    </div>
  )
}

function RouteSummary({ from, to }: { from: string | null; to: string | null }) {
  if (!from && !to) {
    return (
      <div className="rounded-lg border border-dashed border-slate-200 bg-slate-50 px-3 py-2 text-sm italic text-slate-400">
        Tu będzie podsumowanie trasy
      </div>
    )
  }

  return (
    <div className="flex items-center gap-2 rounded-lg bg-[#1754d8]/5 px-3 py-2 text-sm text-[#1754d8]">
      <span className="font-medium">{from ?? '—'}</span>
      <span aria-hidden className="text-slate-400">
        →
      </span>
      <span className="font-medium">{to ?? '—'}</span>
    </div>
  )
}
