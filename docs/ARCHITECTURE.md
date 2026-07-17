# System architecture

This is not covered by Scribe — Scribe only documents HTTP routes under `api/v1/*` (`config/scribe.php`), and has nothing to say about deployment topology, infrastructure, or how services relate to each other. This document is the source of truth for that. Scribe's own docs (the HTTP API reference) live at `/docs` on the app itself, staging only (`app/Http/Middleware/RestrictScribeDocsAccess.php` 404s it elsewhere).

For the one-time external account setup (Supabase, Resend, Cloudflare, SSH keys) referenced by these diagrams, see [`DEPLOYMENT_SETUP.md`](DEPLOYMENT_SETUP.md). For day-to-day operational commands, see [`infrastructure/README.md`](../infrastructure/README.md).

## Context

Who and what talks to MediSCAN, and what it talks to.

```mermaid
C4Context
    Person(user, "User / Admin", "Uses the web dashboard")
    System(mobileApp, "MediSCAN Mobile App", "Separate repo/codebase - consumes the API. Its internals are out of scope here.")
    System(mediscan, "MediSCAN", "Laravel app: KYC verification, professional applications, admin dashboard")
    System_Ext(cloudflare, "Cloudflare", "DNS, edge proxy, CDN caching, TLS")
    System_Ext(supabase, "Supabase", "Managed Postgres (production)")
    System_Ext(resend, "Resend", "Transactional email")
    System_Ext(ghcr, "GHCR", "Container image registry")
    System_Ext(actions, "GitHub Actions", "CI/CD")

    Rel(user, cloudflare, "HTTPS")
    Rel(mobileApp, cloudflare, "HTTPS (api/v1/*)")
    Rel(cloudflare, mediscan, "Proxies to origin nginx")
    Rel(mediscan, supabase, "Postgres (production)")
    Rel(mediscan, resend, "Sends email")
    Rel(actions, ghcr, "Pushes images")
    Rel(actions, mediscan, "Deploys (SSH)")
```

## Container

Internals of the MediSCAN system itself — everything else (mobile app, Supabase, Resend, Cloudflare) is external and undecomposed here, same treatment as the Context diagram.

```mermaid
C4Container
    System_Ext(mobileApp, "Mobile App", "External")
    System_Ext(cloudflare, "Cloudflare", "External")
    System_Ext(supabase, "Supabase", "Production DB - external")
    System_Ext(resend, "Resend", "External")

    Container_Boundary(mediscan, "MediSCAN") {
        Container(nginx, "nginx", "nginx", "TLS termination, routing, static asset caching")
        Container(app, "app", "Octane / FrankenPHP", "Serves routes/web.php and routes/api/v1 from one process")
        Container(horizon, "horizon", "Laravel Horizon", "Queue workers")
        Container(reverb, "reverb", "Laravel Reverb", "WebSocket broadcasting")
        Container(scheduler, "scheduler", "schedule:work", "Scheduled tasks")
        Container(redis, "redis", "Redis", "Cache, session, queue")
        Container(rustfs, "rustfs", "RustFS", "S3-compatible object storage - KYC photos, documents")
        Container(postgres, "postgres", "Postgres", "Staging DB only")
        Container(machineLearning, "machine-learning", "Python/Flask", "OCR + face-match + liveness sidecar")
        Container(grafana, "grafana + prometheus + loki + promtail + node-exporter + cadvisor", "Observability stack", "Logs + metrics + dashboards")
    }

    Rel(cloudflare, nginx, "HTTPS")
    Rel(mobileApp, cloudflare, "HTTPS")
    Rel(nginx, app, "proxy_pass")
    Rel(nginx, reverb, "WebSocket upgrade (/app/*)")
    Rel(nginx, rustfs, "proxy_pass (cdn.*, read-only)")
    Rel(app, redis, "cache/session/queue")
    Rel(app, rustfs, "S3 API")
    Rel(app, postgres, "Postgres (staging)")
    Rel(app, supabase, "Postgres (production)")
    Rel(app, machineLearning, "HTTP")
    Rel(app, resend, "SMTP/API")
    Rel(horizon, redis, "queue")
    Rel(scheduler, redis, "cache")
```

## Deployment

Physical/infrastructure placement — where things actually run.

```mermaid
C4Deployment
    Deployment_Node(gh, "GitHub", "GitHub Actions"){
        Container(actionsRunner, "build/deploy jobs", "ubuntu-latest runners")
    }
    Deployment_Node(ghcrNode, "GHCR", "Container registry"){
        Container(images, "mediscan-* images", "7 images per tag")
    }
    Deployment_Node(cf, "Cloudflare", "Edge network"){
        Container(edge, "Proxy + CDN cache", "Full (Strict) TLS")
    }
    Deployment_Node(vps, "VPS", "Single Docker Engine host (8GB/2vCPU/100GB)"){
        Container(stack, "docker-compose stack", "nginx, app, horizon, reverb, scheduler, redis, rustfs, machine-learning, postgres (staging), observability")
    }
    Deployment_Node(supabaseInfra, "Supabase infrastructure", "Managed"){
        Container(pg, "Postgres", "Production DB")
    }
    Deployment_Node(resendInfra, "Resend infrastructure", "Managed"){
        Container(mail, "Mail sending", "")
    }

    Rel(actionsRunner, images, "docker push")
    Rel(actionsRunner, stack, "SSH deploy - no self-hosted runner")
    Rel(edge, stack, "HTTPS to origin")
    Rel(stack, images, "docker pull")
    Rel(stack, pg, "Postgres (production)")
    Rel(stack, mail, "API")
```

## Notes on these diagrams

- Rendered via Mermaid's C4 diagram types, which GitHub renders natively in markdown. If a given Mermaid version doesn't render these acceptably, the fallback is a plain flowchart styled by C4 level (Context/Container/Deployment) — same content, different syntax.
- The mobile app and every external service (Supabase, Resend, Cloudflare, GHCR) are deliberately left undecomposed — their internals are out of scope for this document, same as the C4 method intends.
