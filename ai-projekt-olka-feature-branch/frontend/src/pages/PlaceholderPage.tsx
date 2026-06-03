interface PlaceholderPageProps {
  title: string
  description: string
}

export function PlaceholderPage({ title, description }: PlaceholderPageProps) {
  return (
    <section className="rounded-2xl bg-white p-8 shadow-sm ring-1 ring-slate-200">
      <h1 className="text-2xl font-semibold">{title}</h1>
      <p className="mt-3 text-slate-600">{description}</p>
    </section>
  )
}
