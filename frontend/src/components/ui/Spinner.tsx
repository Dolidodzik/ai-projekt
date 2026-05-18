export function Spinner({ label }: { label: string }) {
  return (
    <div className="flex items-center gap-3 text-sm text-slate-600">
      <span className="inline-block h-4 w-4 animate-spin rounded-full border-2 border-slate-300 border-t-emerald-600" />
      <span>{label}</span>
    </div>
  )
}
