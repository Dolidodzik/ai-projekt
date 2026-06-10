export interface GeocodingResult {
  label: string
  lat: number
  lon: number
}

export async function searchAddresses(query: string): Promise<GeocodingResult[]> {
  if (query.trim().length < 3) {
    return []
  }

  const url = new URL('https://nominatim.openstreetmap.org/search')
  url.searchParams.set('q', query)
  url.searchParams.set('format', 'json')
  url.searchParams.set('limit', '5')
  url.searchParams.set('countrycodes', 'pl')

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
