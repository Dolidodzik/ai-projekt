import type { ApiRequestFn } from '../tickets/types'

export type { ApiRequestFn }

export type ReportImage = {
  id: number
  uuid: string
  file_name: string
  url: string | null
}

export type Report = {
  id: number
  title: string
  description: string
  status: 'new' | 'in_progress' | 'resolved'
  created_at: string
  resolved_at: string | null
  images: ReportImage[]
}

export type ReportsResponse = {
  data: Report[]
}

export type ApiFormRequestFn = <T>(path: string, formData: FormData) => Promise<T>

export function formatReportDate(value: string): string {
  return new Date(value).toLocaleString('pl-PL', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

export function statusLabel(status: Report['status']): string {
  switch (status) {
    case 'new':
      return 'Nowe'
    case 'in_progress':
      return 'W trakcie'
    case 'resolved':
      return 'Rozwiazane'
  }
}

export function statusClass(status: Report['status']): string {
  switch (status) {
    case 'new':
      return 'border-blue-200 bg-blue-50 text-blue-700'
    case 'in_progress':
      return 'border-amber-200 bg-amber-50 text-amber-700'
    case 'resolved':
      return 'border-brand/20 bg-brand/10 text-brand'
  }
}
