import { useState } from 'react'
import { MapContainer, Marker, TileLayer, useMapEvents } from 'react-leaflet'
import type { LatLngExpression } from 'leaflet'

interface MapPickerModalProps {
  open: boolean
  initialPosition: LatLngExpression | null
  onClose: () => void
  onConfirm: (lat: number, lon: number) => void
}

function MapClickHandler({ onPick }: { onPick: (lat: number, lon: number) => void }) {
  useMapEvents({
    click(event) {
      onPick(event.latlng.lat, event.latlng.lng)
    },
  })

  return null
}

export function MapPickerModal({ open, initialPosition, onClose, onConfirm }: MapPickerModalProps) {
  const [position, setPosition] = useState<LatLngExpression | null>(initialPosition)

  if (!open) {
    return null
  }

  const center: LatLngExpression = position ?? [50.0413, 21.999]

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
      <div className="w-full max-w-3xl rounded-2xl bg-white p-4 shadow-xl">
        <div className="mb-4 flex items-center justify-between gap-4">
          <h2 className="text-lg font-semibold">Pick a point on the map</h2>
          <button type="button" onClick={onClose} className="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            Close
          </button>
        </div>
        <MapContainer center={center} zoom={14} className="h-[420px] w-full rounded-xl">
          <TileLayer url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png" />
          <MapClickHandler onPick={(lat, lon) => setPosition([lat, lon])} />
          {position ? <Marker position={position} /> : null}
        </MapContainer>
        <div className="mt-4 flex justify-end gap-3">
          <button type="button" onClick={onClose} className="rounded-lg border border-slate-300 px-4 py-2 text-sm">
            Cancel
          </button>
          <button
            type="button"
            disabled={!position}
            onClick={() => {
              if (!position || !Array.isArray(position)) {
                return
              }

              onConfirm(position[0], position[1])
            }}
            className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
          >
            Use point
          </button>
        </div>
      </div>
    </div>
  )
}
