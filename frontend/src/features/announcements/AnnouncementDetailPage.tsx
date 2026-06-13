import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { Alert } from '../../components/ui/Alert'
import { Spinner } from '../../components/ui/Spinner'
import { fetchAnnouncement, formatAnnouncementDate, rewriteAnnouncementContent } from './api'
import type { AnnouncementDetail } from './types'

export function AnnouncementDetailPage() {
  const { id } = useParams()
  const announcementId = Number(id)
  const [announcement, setAnnouncement] = useState<AnnouncementDetail | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!Number.isFinite(announcementId) || announcementId < 1) {
      setError('Nieprawidlowe ogloszenie.')
      setLoading(false)
      return
    }

    let cancelled = false

    setLoading(true)
    fetchAnnouncement(announcementId)
      .then((item) => {
        if (!cancelled) {
          setAnnouncement(item)
        }
      })
      .catch((e: unknown) => {
        if (!cancelled) {
          setError(e instanceof Error ? e.message : 'Nie udalo sie zaladowac ogloszenia.')
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
  }, [announcementId])

  return (
    <section className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="text-2xl font-semibold text-brand">
          {announcement?.title ?? 'Ogloszenie'}
        </h1>
        <Link to="/announcements" className="text-sm text-slate-600 hover:underline">
          Powrot do listy
        </Link>
      </div>

      {error ? <Alert>{error}</Alert> : null}

      {loading ? (
        <Spinner label="Ladowanie ogloszenia..." />
      ) : announcement ? (
        <div className="rounded-2xl bg-slate-50 p-6 shadow-sm ring-1 ring-slate-200">
          <div className="text-sm text-slate-500">
            {formatAnnouncementDate(announcement.published_at)}
          </div>
          <div
            className="mt-3 max-w-none text-slate-800 [&_img]:my-4 [&_img]:max-w-full [&_img]:rounded-md [&_p+p]:mt-3"
            dangerouslySetInnerHTML={{
              __html: rewriteAnnouncementContent(announcement.content),
            }}
          />
        </div>
      ) : null}
    </section>
  )
}
