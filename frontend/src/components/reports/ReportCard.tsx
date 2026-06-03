import type { Report } from './types'
import { formatReportDate, statusClass, statusLabel } from './types'

type ReportCardProps = {
  report: Report
}

export function ReportCard({ report }: ReportCardProps) {
  return (
    <article className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h3 className="font-semibold text-slate-900">{report.title}</h3>
          <p className="mt-1 text-xs text-slate-500">{formatReportDate(report.created_at)}</p>
        </div>
        <span className={`rounded-full border px-3 py-1 text-xs font-medium ${statusClass(report.status)}`}>
          {statusLabel(report.status)}
        </span>
      </div>

      <p className="mt-3 text-sm leading-relaxed text-slate-600">{report.description}</p>

      {report.images.length > 0 ? (
        <ul className="mt-4 flex flex-wrap gap-2">
          {report.images.map((image) => (
            <li key={image.id}>
              {image.url ? (
                <a href={image.url} target="_blank" rel="noreferrer" className="block overflow-hidden rounded-lg border border-slate-200">
                  <img
                    src={image.url}
                    alt={`Zalacznik ${image.uuid}`}
                    className="h-24 w-24 object-cover transition hover:opacity-90"
                  />
                </a>
              ) : (
                <div className="flex h-24 w-24 items-center justify-center rounded-lg border border-dashed border-slate-300 bg-slate-50 text-xs text-slate-400">
                  Brak podgladu
                </div>
              )}
            </li>
          ))}
        </ul>
      ) : (
        <p className="mt-3 text-xs italic text-slate-400">Brak zalaczonych zdjec</p>
      )}
    </article>
  )
}
