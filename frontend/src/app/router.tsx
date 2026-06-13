import { createBrowserRouter, Navigate } from 'react-router-dom'
import { AppLayout } from '../components/layout/AppLayout'
import { AnnouncementDetailPage } from '../features/announcements/AnnouncementDetailPage'
import { AnnouncementsPage } from '../features/announcements/AnnouncementsPage'
import { PlannerPage } from '../features/route-planner/pages/PlannerPage'
import { ResultsPage } from '../features/route-planner/pages/ResultsPage'
import { SchedulePage } from '../features/schedules/SchedulePage'
import { AccountSettingsPage } from '../pages/AccountSettingsPage'
import { SignInPage } from '../pages/SignInPage'

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
      { path: '/announcements', element: <AnnouncementsPage /> },
      { path: '/announcements/:id', element: <AnnouncementDetailPage /> },
      { path: '/ogloszenia', element: <Navigate to="/announcements" replace /> },
      { path: '/announcments', element: <Navigate to="/announcements" replace /> },
      { path: '/sign-in', element: <SignInPage /> },
      { path: '/logowanie', element: <Navigate to="/sign-in" replace /> },
      { path: '/register', element: <SignInPage /> },
      { path: '/rejestracja', element: <Navigate to="/register" replace /> },
      { path: '/account', element: <Navigate to="/account/profil" replace /> },
      { path: '/account/:section', element: <AccountSettingsPage /> },
      { path: '/konto', element: <Navigate to="/account/profil" replace /> },
    ],
  },
])
