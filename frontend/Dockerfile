# syntax=docker/dockerfile:1

# ---- Build -----------------------------------------------------------------
# glibc-based image: TypeScript 7, oxlint and rolldown ship native binaries.
FROM node:22-bookworm-slim AS build
ENV PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY . .
RUN npm run build

# ---- Serve -----------------------------------------------------------------
FROM nginx:1.27-alpine
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist /usr/share/nginx/html
EXPOSE 80
