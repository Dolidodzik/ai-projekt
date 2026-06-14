export interface GeocodingResult {
  label: string
  lat: number
  lon: number
}

// Northwest and southeast corners of the Rzeszów area (lon/lat).
const RZESZOW_VIEWBOX = '21.85,50.15,22.20,49.95'

function buildSearchQuery(query: string): string {
  const trimmed = query.trim()
  if (/rzesz[oó]w/i.test(trimmed)) {
    return trimmed
  }
  return `${trimmed}, Rzeszów`
}

export async function searchAddresses(query: string): Promise<GeocodingResult[]> {
  if (query.trim().length < 3) {
    return []
  }

  const url = new URL('https://nominatim.openstreetmap.org/search')
  url.searchParams.set('q', buildSearchQuery(query))
  url.searchParams.set('format', 'json')
  url.searchParams.set('limit', '5')
  url.searchParams.set('countrycodes', 'pl')
  url.searchParams.set('viewbox', RZESZOW_VIEWBOX)
  url.searchParams.set('bounded', '1')

  const response = await fetch(url.toString(), {
    headers: { Accept: 'application/json' },
  })

  if (!response.ok) {
    return []
  }

  const data = (await response.json()) as Array<{ display_name: string; lat: string; lon: string }>

  return data.map((item) => ({
    label: item.display_name,
    lat: Number(item.lat),
    lon: Number(item.lon),
  }))
}
