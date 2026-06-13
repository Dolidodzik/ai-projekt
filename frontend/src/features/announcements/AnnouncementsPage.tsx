import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { Alert } from '../../components/ui/Alert'
import { Spinner } from '../../components/ui/Spinner'
import { fetchAnnouncements, formatAnnouncementDate } from './api'
import type { AnnouncementListItem } from './types'

export function AnnouncementsPage() {
  const [announcements, setAnnouncements] = useState<AnnouncementListItem[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    let cancelled = false

    setLoading(true)
    fetchAnnouncements()
      .then((items) => {
        if (!cancelled) {
          setAnnouncements(items)
        }
      })
      .catch((e: unknown) => {
        if (!cancelled) {
          setError(e instanceof Error ? e.message : 'Nie udalo sie zaladowac ogloszen.')
        }
      })
      .finally(() => {
        if (!cancelled) {
          setLoading(false)
        }
      })

    return () => {
      cancelled = true
    }
  }, [])

  return (
    <section className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold">Ogloszenia</h1>
        <p className="mt-2 text-slate-600">Informacje i komunikaty od operatora komunikacji miejskiej.</p>
      </div>

      {error ? <Alert>{error}</Alert> : null}

      {loading ? (
        <Spinner label="Ladowanie ogloszen..." />
      ) : (
        <div className="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
          <table className="w-full text-left text-sm">
            <thead className="bg-slate-50">
              <tr>
                <th className="px-4 py-3 font-medium">Tytul</th>
                <th className="px-4 py-3 font-medium">Data</th>
                <th className="px-4 py-3 font-medium">Akcja</th>
              </tr>
            </thead>
            <tbody>
              {announcements.length === 0 ? (
                <tr className="border-t border-slate-200">
                  <td colSpan={3} className="px-4 py-6 text-center text-slate-500">
                    Brak ogloszen.
                  </td>
                </tr>
              ) : (
                announcements.map((announcement) => (
                  <tr key={announcement.id} className="border-t border-slate-200">
                    <td className="px-4 py-3 font-medium text-slate-900">{announcement.title}</td>
                    <td className="px-4 py-3 text-slate-600">
                      {formatAnnouncementDate(announcement.published_at)}
                    </td>
                    <td className="px-4 py-3">
                      <Link
                        to={`/announcements/${announcement.id}`}
                        className="font-medium text-brand hover:underline"
                      >
                        Czytaj
                      </Link>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      )}
    </section>
  )
}
