function App() {
  const apiUrl = import.meta.env.VITE_API_URL ?? 'http://localhost:8080/api'

  return (
    <div className="min-h-screen bg-slate-50 text-slate-900">
      <header className="border-b border-slate-200 bg-white">
        <div className="mx-auto flex max-w-5xl items-center justify-between px-6 py-4">
          <h1 className="text-xl font-bold tracking-tight">AI2 — Komunikacja Rzeszow</h1>
          <span className="rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-700">
            szkielet projektu
          </span>
        </div>
      </header>

      <main className="mx-auto max-w-5xl px-6 py-12">
        <section className="rounded-2xl bg-white p-8 shadow-sm ring-1 ring-slate-200">
          <h2 className="text-2xl font-semibold">Frontend dziala</h2>
          <p className="mt-2 text-slate-600">
            Vite + React + TypeScript + Tailwind. Tu pojawia sie planer tras,
            mapa OSM, rozklady jazdy, panel uzytkownika i bilety.
          </p>
          <dl className="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div className="rounded-lg bg-slate-100 p-4">
              <dt className="text-xs font-medium uppercase text-slate-500">Backend API</dt>
              <dd className="mt-1 font-mono text-sm">{apiUrl}</dd>
            </div>
            <div className="rounded-lg bg-slate-100 p-4">
              <dt className="text-xs font-medium uppercase text-slate-500">Stack</dt>
              <dd className="mt-1 text-sm">React 19 + TS + Tailwind v4</dd>
            </div>
          </dl>
        </section>
      </main>
    </div>
  )
}

export default App
