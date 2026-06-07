import { AlertTriangle, ChefHat } from 'lucide-react'
import Markdown from 'react-markdown'
import { cn } from '../../lib/format'
import type { ChatMessage } from '../../lib/types'
import { Citations } from './Citations'
import { ToolSteps } from './ToolSteps'

export function MessageBubble({ message }: { message: ChatMessage }) {
  if (message.role === 'user') {
    return (
      <div className="flex justify-end">
        <div className="max-w-[85%] rounded-2xl rounded-br-md bg-brand-600 px-4 py-2.5 text-sm text-white shadow-sm sm:max-w-[70%]">
          {message.content}
        </div>
      </div>
    )
  }

  const thinking = message.status === 'streaming' && !message.content

  return (
    <div className="flex gap-3">
      <div className="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-brand-600 text-white shadow-sm">
        <ChefHat className="h-4 w-4" />
      </div>
      <div
        className={cn(
          'card min-w-0 max-w-[90%] rounded-tl-md px-4 py-3 text-sm sm:max-w-[80%]',
          message.status === 'error' && 'border-red-200 dark:border-red-900',
        )}
      >
        <ToolSteps steps={message.steps} />

        {thinking && message.steps.length === 0 && (
          <div className="flex gap-1 py-1" aria-label="Assistant is thinking">
            {[0, 150, 300].map((delay) => (
              <span key={delay} className="h-2 w-2 animate-bounce rounded-full bg-stone-400" style={{ animationDelay: `${delay}ms` }} />
            ))}
          </div>
        )}

        {message.status === 'error' ? (
          <p className="flex items-center gap-2 text-red-600 dark:text-red-400">
            <AlertTriangle className="h-4 w-4" /> {message.content}
          </p>
        ) : (
          message.content && (
            <div className="prose-chat text-stone-700 dark:text-stone-300">
              <Markdown>{message.content}</Markdown>
              {message.status === 'streaming' && <span className="ml-0.5 inline-block h-4 w-1.5 animate-pulse rounded-sm bg-brand-500 align-middle" />}
            </div>
          )
        )}

        {message.status === 'done' && <Citations citations={message.citations} />}
      </div>
    </div>
  )
}
