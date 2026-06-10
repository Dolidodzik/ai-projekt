import type { PlanRouteResult } from './types'
import { PLAN_RESULT_STORAGE_KEY } from './types'

export function savePlanResult(result: PlanRouteResult): void {
  sessionStorage.setItem(PLAN_RESULT_STORAGE_KEY, JSON.stringify(result))
}

export function loadPlanResult(): PlanRouteResult | null {
  const raw = sessionStorage.getItem(PLAN_RESULT_STORAGE_KEY)
  if (!raw) {
    return null
  }

  try {
    return JSON.parse(raw) as PlanRouteResult
  } catch {
    return null
  }
}
