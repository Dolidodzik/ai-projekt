import { createBrowserRouter } from 'react-router-dom'
import { AppLayout } from '../components/layout/AppLayout'
import { PlannerPage } from '../features/route-planner/pages/PlannerPage'
import { ResultsPage } from '../features/route-planner/pages/ResultsPage'
import { PlaceholderPage } from '../pages/PlaceholderPage'

export const router = createBrowserRouter([
  {
    element: <AppLayout />,
    children: [
      { path: '/', element: <PlannerPage /> },
      { path: '/wyniki', element: <ResultsPage /> },
      { path: '/rozklad', element: <PlaceholderPage title="Timetable" description="Timetable view will be added here." /> },
      { path: '/odjazdy', element: <PlaceholderPage title="Departures" description="Stop departures will be added here." /> },
      { path: '/logowanie', element: <PlaceholderPage title="Sign in" description="Authentication will be added here." /> },
      { path: '/rejestracja', element: <PlaceholderPage title="Register" description="Registration will be added here." /> },
      { path: '/konto', element: <PlaceholderPage title="Account" description="Account area will be added here." /> },
    ],
  },
])
