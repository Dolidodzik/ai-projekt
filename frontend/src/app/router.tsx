import { createBrowserRouter, Navigate } from 'react-router-dom'
import { AppLayout } from '../components/layout/AppLayout'
import { PlannerPage } from '../features/route-planner/pages/PlannerPage'
import { ResultsPage } from '../features/route-planner/pages/ResultsPage'
import { SchedulePage } from '../features/schedules/SchedulePage'
import { PlaceholderPage } from '../pages/PlaceholderPage'

export const router = createBrowserRouter([
  {
    element: <AppLayout />,
    children: [
      { path: '/', element: <PlannerPage /> },
      { path: '/results', element: <ResultsPage /> },
      { path: '/wyniki', element: <Navigate to="/results" replace /> },
      { path: '/schedule', element: <SchedulePage /> },
      { path: '/rozklad', element: <Navigate to="/schedule" replace /> },
      { path: '/odjazdy', element: <Navigate to="/schedule" replace /> },
      { path: '/sign-in', element: <PlaceholderPage title="Sign in" description="Authentication will be added here." /> },
      { path: '/logowanie', element: <Navigate to="/sign-in" replace /> },
      { path: '/register', element: <PlaceholderPage title="Register" description="Registration will be added here." /> },
      { path: '/rejestracja', element: <Navigate to="/register" replace /> },
      { path: '/account', element: <PlaceholderPage title="Account" description="Account area will be added here." /> },
      { path: '/konto', element: <Navigate to="/account" replace /> },
    ],
  },
])
