import { apiGet, getApiBaseUrl } from '../../lib/api'
import type { AnnouncementDetail, AnnouncementListItem } from './types'

export function getBackendOrigin(): string {
  return getApiBaseUrl().replace(/\/api\/?$/, '')
}

export function rewriteAnnouncementContent(html: string): string {
  const origin = getBackendOrigin()
  return html.replace(/src="\/uploads\//g, `src="${origin}/uploads/`)
}

export async function fetchAnnouncements(): Promise<AnnouncementListItem[]> {
  const res = await apiGet<{ data: AnnouncementListItem[] }>('/announcements')
  return res.data
}

export async function fetchAnnouncement(id: number): Promise<AnnouncementDetail> {
  const res = await apiGet<{ data: AnnouncementDetail }>(`/announcements/${id}`)
  return res.data
}

export function formatAnnouncementDate(value: string | null): string {
  if (!value) {
    return '—'
  }

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return value
  }

  return new Intl.DateTimeFormat('pl-PL', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  }).format(date)
}
