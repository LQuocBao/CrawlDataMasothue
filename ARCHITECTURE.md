# Automated Scraping & Notification System - Architecture

## System Overview

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                          SYSTEM ARCHITECTURE                                 │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌──────────────┐         ┌──────────────────────────────────────────────┐  │
│  │  Next.js App │◄───────►│              Laravel API                      │  │
│  │  (Dashboard) │  REST   │                                              │  │
│  │              │         │  ┌─────────────┐   ┌──────────────────────┐  │  │
│  │ - Filters    │         │  │  Scheduler  │   │   Queue Workers      │  │  │
│  │ - Telegram   │         │  │  (1-2 min)  │   │                      │  │  │
│  │   Config     │         │  │             │   │  ┌─────────────────┐ │  │  │
│  │ - Logs       │         │  │  Triggers:  │   │  │ ProcessCompany  │ │  │  │
│  └──────────────┘         │  │  ScrapeCmd  │   │  │ NotificationJob │ │  │  │
│                           │  └──────┬──────┘   │  │                 │ │  │  │
│                           │         │          │  │ 1. Gen PDF      │ │  │  │
│                           │         ▼          │  │ 2. Send Telegram│ │  │  │
│                           │  ┌─────────────┐   │  └─────────────────┘ │  │  │
│                           │  │  Scraper    │   └──────────────────────┘  │  │
│                           │  │  Service    │                             │  │
│                           │  │             │   ┌──────────────────────┐  │  │
│                           │  │ HTTP Client │──►│   Redis Cache/Queue  │  │  │
│                           │  │ + Proxy     │   └──────────────────────┘  │  │
│                           │  └──────┬──────┘                             │  │
│                           │         │                                    │  │
│                           └─────────┼────────────────────────────────────┘  │
│                                     │                                        │
│                                     ▼                                        │
│                           ┌─────────────────┐     ┌───────────────────┐     │
│                           │  masothue.com   │     │   Telegram Bot    │     │
│                           │  (Data Source)  │     │   (sendDocument)  │     │
│                           └─────────────────┘     └───────────────────┘     │
│                                     ▲                                        │
│                           ┌─────────────────┐                               │
│                           │ Rotating Proxy  │                               │
│                           │   Pool          │                               │
│                           └─────────────────┘                               │
└─────────────────────────────────────────────────────────────────────────────┘
```

## Data Flow (< 4 minutes end-to-end)

1. **Scheduler** triggers `scrape:companies` command every 1-2 minutes.
2. **ScraperService** fetches new registrations from masothue.com via rotating proxies.
3. **Anti-Duplicate**: Each Tax ID (MST) is checked against Redis cache + DB.
4. **Filter Engine**: New companies are matched against user-defined filters (province, industry keywords).
5. **Queue Dispatch**: Matching companies are dispatched as `ProcessCompanyNotification` jobs.
6. **PDF Generation**: Job generates formatted PDF with company info + industry table.
7. **Telegram Delivery**: PDF is sent via `sendDocument` API to configured Chat IDs.

## Key Design Decisions

- **Redis for dedup**: O(1) lookup for Tax IDs already processed (SET data structure).
- **Queue isolation**: PDF + Telegram are async jobs, never blocking the scrape loop.
- **Proxy rotation**: Each request cycles through proxy pool to avoid IP bans.
- **Retry with backoff**: Failed jobs retry 3x with exponential backoff.

## Directory Structure

```
project-root/
├── backend/                    # Laravel 11.x application
│   ├── app/
│   │   ├── Console/Commands/   # ScrapeCompanies command
│   │   ├── Jobs/               # ProcessCompanyNotification
│   │   ├── Models/             # Company, Filter, TelegramConfig
│   │   ├── Services/           # ScraperService, PdfService, TelegramService, FilterService
│   │   └── Http/Controllers/   # API controllers
│   ├── config/
│   ├── database/migrations/
│   ├── resources/views/pdf/    # Blade templates for PDF
│   ├── routes/
│   └── .env.example
├── frontend/                   # Next.js application
│   ├── src/
│   │   ├── app/                # App Router pages
│   │   ├── components/         # React components
│   │   ├── lib/                # API client, utils
│   │   └── types/              # TypeScript types
│   └── .env.local.example
├── docker-compose.yml          # Dev environment
├── docker-compose.prod.yml     # Production environment
├── supervisor/                 # Supervisor configs
└── ARCHITECTURE.md             # This file
```
