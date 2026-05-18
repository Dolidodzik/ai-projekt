import { useCallback, useEffect, useRef, useState } from 'react'
import type { ChangeEvent, FormEvent } from 'react'
import { ReportCard } from './ReportCard'
import type { ApiFormRequestFn, ApiRequestFn, Report, ReportsResponse } from './types'

type ReportsPanelProps = {
  apiRequest: ApiRequestFn
  apiFormRequest: ApiFormRequestFn
  onMessage: (message: string) => void
  onError: (error: string) => void
}

type PanelTab = 'list' | 'create'

export function ReportsPanel({ apiRequest, apiFormRequest, onMessage, onError }: ReportsPanelProps) {
  const apiRequestRef = useRef(apiRequest)
  apiRequestRef.current = apiRequest

  const [tab, setTab] = useState<PanelTab>('list')
  const [reports, setReports] = useState<Report[]>([])
  const [loading, setLoading] = useState(true)
  const [submitting, setSubmitting] = useState(false)

  const [title, setTitle] = useState('')
  const [description, setDescription] = useState('')
  const [files, setFiles] = useState<File[]>([])

  const loadReports = useCallback(async () => {
    try {
      setLoading(true)
      const response = await apiRequestRef.current<ReportsResponse>('/reports/user')
      setReports(response.data)
    } catch (err) {
      onError(err instanceof Error ? err.message : 'Nie udalo sie pobrac zgloszen')
    } finally {
      setLoading(false)
    }
  }, [onError])

  useEffect(() => {
    void loadReports()
  }, [loadReports])

  function handleFilesChange(event: ChangeEvent<HTMLInputElement>) {
    const selected = event.target.files ? Array.from(event.target.files) : []
    setFiles(selected)
  }

  function removeFile(index: number) {
    setFiles((prev) => prev.filter((_, i) => i !== index))
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    onMessage('')
    onError('')

    try {
      setSubmitting(true)
      const formData = new FormData()
      formData.append('title', title)
      formData.append('description', description)
      files.forEach((file) => formData.append('images[]', file))

      const response = await apiFormRequest<{ message: string }>('/reports', formData)

      onMessage(response.message)
      setTitle('')
      setDescription('')
      setFiles([])
      setTab('list')
      await loadReports()
    } catch (err) {
      onError(err instanceof Error ? err.message : 'Nie udalo sie utworzyc zgloszenia')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <section className="space-y-6">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h2 className="text-lg font-semibold text-slate-900">Zgloszenia uzytkownika</h2>
          <p className="mt-1 text-sm text-slate-500">
            Zglaszaj problemy i dolaczaj zdjecia. Administrator moze zmienic status zgloszenia.
          </p>
        </div>

        <TabSwitch tab={tab} onTabChange={setTab} />
      </header>

      {tab === 'list' ? (
        <ListTab loading={loading} reports={reports} onCreateClick={() => setTab('create')} />
      ) : (
        <CreateTab
          title={title}
          description={description}
          files={files}
          submitting={submitting}
          onTitleChange={setTitle}
          onDescriptionChange={setDescription}
          onFilesChange={handleFilesChange}
          onRemoveFile={removeFile}
          onSubmit={handleSubmit}
          onCancel={() => setTab('list')}
        />
      )}
    </section>
  )
}

function TabSwitch({
  tab,
  onTabChange,
}: {
  tab: PanelTab
  onTabChange: (tab: PanelTab) => void
}) {
  return (
    <div className="inline-flex rounded-lg bg-slate-100 p-1">
      <button
        type="button"
        onClick={() => onTabChange('list')}
        className={`rounded-md px-4 py-2 text-sm font-medium ${
          tab === 'list' ? 'bg-[#1754d8] text-white' : 'text-slate-700'
        }`}
      >
        Lista
      </button>
      <button
        type="button"
        onClick={() => onTabChange('create')}
        className={`rounded-md px-4 py-2 text-sm font-medium ${
          tab === 'create' ? 'bg-[#1754d8] text-white' : 'text-slate-700'
        }`}
      >
        Nowe zgloszenie
      </button>
    </div>
  )
}

function ListTab({
  loading,
  reports,
  onCreateClick,
}: {
  loading: boolean
  reports: Report[]
  onCreateClick: () => void
}) {
  if (loading) {
    return <p className="text-center text-sm text-slate-500 py-10">Ladowanie zgloszen...</p>
  }

  if (reports.length === 0) {
    return (
      <div className="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
        <p className="text-sm font-medium text-slate-700">Nie masz jeszcze zadnych zgloszen</p>
        <button
          type="button"
          onClick={onCreateClick}
          className="mt-4 rounded-lg bg-[#1754d8] px-4 py-2 text-sm font-medium text-white hover:bg-[#1549bc]"
        >
          Utworz pierwsze zgloszenie
        </button>
      </div>
    )
  }

  return (
    <ul className="space-y-4">
      {reports.map((report) => (
        <li key={report.id}>
          <ReportCard report={report} />
        </li>
      ))}
    </ul>
  )
}

function CreateTab({
  title,
  description,
  files,
  submitting,
  onTitleChange,
  onDescriptionChange,
  onFilesChange,
  onRemoveFile,
  onSubmit,
  onCancel,
}: {
  title: string
  description: string
  files: File[]
  submitting: boolean
  onTitleChange: (value: string) => void
  onDescriptionChange: (value: string) => void
  onFilesChange: (event: ChangeEvent<HTMLInputElement>) => void
  onRemoveFile: (index: number) => void
  onSubmit: (event: FormEvent<HTMLFormElement>) => void
  onCancel: () => void
}) {
  return (
    <form onSubmit={(e) => void onSubmit(e)} className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
      <label className="block">
        <span className="mb-1 block text-sm font-medium text-slate-700">Tytul</span>
        <input
          type="text"
          value={title}
          onChange={(e) => onTitleChange(e.target.value)}
          required
          minLength={3}
          maxLength={255}
          className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-[#1754d8] focus:ring-2 focus:ring-[#1754d8]/20"
          placeholder="Np. Uszkodzony przystanek"
        />
      </label>

      <label className="block">
        <span className="mb-1 block text-sm font-medium text-slate-700">Opis</span>
        <textarea
          value={description}
          onChange={(e) => onDescriptionChange(e.target.value)}
          required
          minLength={10}
          rows={5}
          maxLength={5000}
          className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-[#1754d8] focus:ring-2 focus:ring-[#1754d8]/20"
          placeholder="Opisz problem szczegolowo..."
        />
      </label>

      <div>
        <span className="mb-1 block text-sm font-medium text-slate-700">Zdjecia (opcjonalnie, max 10)</span>
        <input
          type="file"
          accept="image/jpeg,image/jpg,image/png,image/webp"
          multiple
          onChange={onFilesChange}
          className="w-full text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-[#1754d8]/10 file:px-4 file:py-2 file:text-sm file:font-medium file:text-[#1754d8]"
        />
        <p className="mt-1 text-xs text-slate-400">JPG, PNG lub WebP, do 5 MB na plik</p>

        {files.length > 0 && (
          <ul className="mt-3 space-y-2">
            {files.map((file, index) => (
              <li
                key={`${file.name}-${index}`}
                className="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm"
              >
                <span className="truncate text-slate-700">{file.name}</span>
                <button
                  type="button"
                  onClick={() => onRemoveFile(index)}
                  className="ml-2 shrink-0 text-xs font-medium text-red-600 hover:text-red-700"
                >
                  Usun
                </button>
              </li>
            ))}
          </ul>
        )}
      </div>

      <div className="flex flex-wrap gap-3 pt-2">
        <button
          type="submit"
          disabled={submitting}
          className="rounded-lg bg-[#1754d8] px-4 py-2 text-sm font-medium text-white hover:bg-[#1549bc] disabled:opacity-60"
        >
          {submitting ? 'Wysylanie...' : 'Wyslij zgloszenie'}
        </button>
        <button
          type="button"
          onClick={onCancel}
          disabled={submitting}
          className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-60"
        >
          Anuluj
        </button>
      </div>
    </form>
  )
}
