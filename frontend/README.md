# MenuMind Frontend

React 19 + Vite + TypeScript + Tailwind CSS v4 single-page app: menu, streaming chat assistant, orders and back office.

```bash
npm install
npm run dev        # http://localhost:5173, /api is proxied to http://localhost:8080
npm run lint       # oxlint
npm run typecheck  # tsc
npm test           # Vitest + Testing Library
npm run build
```

Set `VITE_API_PROXY_TARGET` to point the dev proxy at another backend. See the [root README](../README.md) for the full picture.
