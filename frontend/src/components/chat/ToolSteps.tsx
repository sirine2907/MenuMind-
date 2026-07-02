import { CheckCircle2, Loader2 } from 'lucide-react'
import type { ToolStep } from '../../lib/types'

/** The agent's tool calls, shown as small live "steps" above the answer. */
export function ToolSteps({ steps }: { steps: ToolStep[] }) {
  if (steps.length === 0) return null

  return (
    <ol className="mb-3 space-y-1.5" aria-label="Assistant steps">
      {steps.map((step) => (
        <li
          key={step.id}
          className="flex items-center gap-2 rounded-lg border border-stone-200 bg-stone-50 px-2.5 py-1.5 text-xs dark:border-stone-700/70 dark:bg-stone-800/60"
        >
          {step.status === 'running' ? (
            <Loader2 className="h-3.5 w-3.5 shrink-0 animate-spin text-brand-500" aria-label="running" />
          ) : (
            <CheckCircle2 className="h-3.5 w-3.5 shrink-0 text-green-600 dark:text-green-400" aria-label="done" />
          )}
          <span className="font-medium text-stone-700 dark:text-stone-200">{step.label}</span>
          {step.summary && <span className="truncate text-stone-500 dark:text-stone-400">· {step.summary}</span>}
        </li>
      ))}
    </ol>
  )
}
