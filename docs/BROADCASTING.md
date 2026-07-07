# Broadcasting (WebSocket events)

This app pushes real-time updates over WebSockets using [Laravel Reverb](https://reverb.laravel.com/). This is **not** covered by Scribe — Scribe only documents HTTP routes under `api/v1/*` (`config/scribe.php`), and broadcasting has no routes or controllers for it to introspect. This document is the source of truth for what gets broadcast, on which channels, and how the frontend consumes it.

## Connection

- Driver: `reverb` (`config/broadcasting.php`), default connection set via `BROADCAST_CONNECTION` in `.env`.
- Server env vars: `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME`.
- Frontend env vars: `VITE_REVERB_APP_KEY`, `VITE_REVERB_HOST`, `VITE_REVERB_PORT`, `VITE_REVERB_SCHEME`.
- Channel authorization endpoint: `POST /broadcasting/auth`, registered automatically via the `channels: __DIR__.'/../routes/channels.php'` key in `bootstrap/app.php`'s `withRouting()`. Authorization rules live in `routes/channels.php`.
- The Reverb server itself runs alongside the app via `composer run dev` (see `CONTRIBUTING.md`).

## Two patterns in this codebase

There are two ways an event ends up broadcast here. Both are valid and in active use — pick based on whether the event needs non-broadcast side effects too.

### 1. Decoupled: plain event + dedicated listener

The event class has no knowledge of broadcasting. A separate `Broadcast*` listener implements `ShouldQueueAfterCommit` and calls the `Broadcast` facade directly.

```php
// app/Events/EmailChanged.php
class EmailChanged
{
    public function __construct(public readonly User $user, public readonly string $origin = 'settings') {}
}

// app/Listeners/BroadcastEmailChanged.php
class BroadcastEmailChanged implements ShouldQueueAfterCommit
{
    public function handle(EmailChanged $event): void
    {
        Broadcast::private('admin-dashboard')
            ->as('EmailChanged')
            ->with(['user_id' => $event->user->id])
            ->send();
    }
}
```

Used by: `EmailChanged` → `BroadcastEmailChanged`, `Illuminate\Auth\Events\Verified` → `BroadcastEmailVerified`, `Illuminate\Auth\Events\Registered` → `BroadcastUserRegistered` (this one also flushes the admin dashboard cache in the same listener — a non-broadcast side effect, which is exactly why this pattern is useful).

### 2. Self-broadcasting: event implements `ShouldBroadcast`

The event itself declares the channel, name, and payload. No listener needed — Laravel automatically queues the broadcast when the event is dispatched.

```php
// app/Events/UserDeactivated.php
class UserDeactivated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(public readonly User $user) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("App.Models.User.{$this->user->id}")];
    }

    public function broadcastAs(): string
    {
        return 'UserDeactivated';
    }

    public function broadcastWith(): array
    {
        return ['user_id' => $this->user->id];
    }
}
```

Used by: `UserDeactivated`, `UserDeleted`, `ProfessionalApplicationStatusChanged` (broadcasts on both the applicant's own channel and `admin-dashboard` at once - see its `broadcastOn()`).

`public bool $afterCommit = true` is the equivalent of `ShouldQueueAfterCommit` in pattern 1 — it delays the broadcast job until the current DB transaction commits.

**Payload rule regardless of pattern**: always define the payload explicitly (`->with([...])` or `broadcastWith()`). If a `ShouldBroadcast` event has no `broadcastWith()`, Laravel falls back to reflecting over every public property and serializing it (see `vendor/laravel/framework/src/Illuminate/Broadcasting/BroadcastEvent.php`) — for an event holding a full `User` model, that means the entire model goes out over the wire. Keep payloads to the minimum the frontend needs (usually just an id).

## Channels

| Channel | Visibility | Authorized in `routes/channels.php` as |
|---|---|---|
| `admin-dashboard` | Private | Users with the `Admin` role |
| `App.Models.User.{id}` | Private | Only the user matching `{id}` |

## Events per channel

| Channel | Event name | Dispatched from | Payload |
|---|---|---|---|
| `admin-dashboard` | `.UserRegistered` | `BroadcastUserRegistered` (listens to `Illuminate\Auth\Events\Registered`) | `{ stats: DashboardStats }` — see `resources/js/pages/admin/dashboard.tsx` for the `DashboardStats` shape (`total`, `active`, `deactivated`, `by_role`) |
| `admin-dashboard` | `.EmailChanged` | `BroadcastEmailChanged` (listens to `App\Events\EmailChanged`) | `{ user_id: number }` |
| `admin-dashboard` | `.InvitationSent` | Inline in `app/Services/User/InvitationService.php` | `{}` (no payload) |
| `admin-dashboard` | `.ProfessionalApplicationStatusChanged` | `App\Events\ProfessionalApplicationStatusChanged` (self-broadcasting), dispatched from `ProfessionalApplicationService::submit()`/`approve()`/`reject()` and the `ProcessProfessionalApplication` job | `{ application_id: number, status: string }` |
| `App.Models.User.{id}` | `.EmailChanged` | `BroadcastEmailChanged` (same listener, also broadcasts on the changed user's own channel) | `{ user_id: number }` |
| `App.Models.User.{id}` | `.EmailVerified` | `BroadcastEmailVerified` (listens to `Illuminate\Auth\Events\Verified`) | `{ email_verified_at: string|null }` |
| `App.Models.User.{id}` | `.UserDeactivated` | `App\Events\UserDeactivated` (self-broadcasting), dispatched from `UserService::setActive()` | `{ user_id: number }` |
| `App.Models.User.{id}` | `.UserDeleted` | `App\Events\UserDeleted` (self-broadcasting), dispatched from `UserService::delete()` | `{ user_id: number }` |
| `App.Models.User.{id}` | `.ProfessionalApplicationStatusChanged` | Same event as above, on the applicant's own channel | `{ application_id: number, status: string }` |

## Frontend consumption

The frontend uses [`@laravel/echo-react`](https://reverb.laravel.com/)'s `useEcho` hook. Pattern:

```ts
useEcho('channel-name', ['.EventOne', '.EventTwo'], (payload) => {
    // react to the event
});
```

Real usages:
- `resources/js/pages/admin/users/index.tsx` and `resources/js/pages/admin/invitations/index.tsx` — listen on `admin-dashboard` for `.UserRegistered`/`.EmailChanged`/`.InvitationSent` and `router.reload()` the list.
- `resources/js/pages/admin/dashboard.tsx` — listens on `admin-dashboard` for `.UserRegistered` and updates local stats state from the payload.
- `resources/js/layouts/user-layout.tsx` — listens on the current user's own `App.Models.User.{id}` channel for `.UserDeactivated`/`.UserDeleted` and force-logs the user out (`router.post(logout.url())`) so a deactivated/deleted user's open tab reacts immediately instead of waiting for their next request to hit `CheckUserActive`/`EnsureApiUserActive` middleware. It also listens for `.EmailChanged` on the same channel and does a partial `router.reload({ only: ['auth'] })` so `useAuth()`-driven UI (e.g. the email shown on `resources/js/pages/dashboard.tsx`) reflects an email change made elsewhere without a full page reload.
- `resources/js/pages/admin/professional-applications/index.tsx` and `.../show.tsx` — listen on `admin-dashboard` for `.ProfessionalApplicationStatusChanged` and `router.reload()`, so the list/detail view picks up automatic-verification results and other admins' approve/deny actions live.
- `resources/js/pages/professional-application/show.tsx` — listens on the applicant's own `App.Models.User.{id}` channel for `.ProfessionalApplicationStatusChanged` and `router.reload()`, so the status page updates the moment the background job or an admin decision changes it, without the applicant needing to refresh.

Note: `EmailVerified` is broadcast on `App.Models.User.{id}` but currently has **no frontend consumer** — nothing subscribes to it yet.

## Testing

Self-broadcasting events (`ShouldBroadcast`) get a `tests/Unit/Events/{Event}Test.php` that asserts `broadcastOn()`, `broadcastAs()`, and `broadcastWith()` directly on the event object — no DB or queue involved, since these are pure value assertions. See `tests/Unit/Events/UserDeactivatedTest.php` and `tests/Unit/Events/UserDeletedTest.php` for the pattern.

Gaps today: the decoupled pattern (`BroadcastEmailChanged`, `BroadcastEmailVerified`, `BroadcastUserRegistered`) and the inline `InvitationSent` broadcast in `InvitationService` have no equivalent tests, and no Feature-level test confirms that `UserService::setActive()`/`delete()` actually dispatch `UserDeactivated`/`UserDeleted` — only the events' own broadcast metadata is unit-tested. `ProfessionalApplicationStatusChanged` is the exception: in addition to its own `tests/Unit/Events/ProfessionalApplicationStatusChangedTest.php`, `tests/Feature/Admin/ProfessionalApplicationTest.php` uses `Event::fake([ProfessionalApplicationStatusChanged::class])` + `Event::assertDispatched(...)` to confirm `approve()`/`reject()` actually dispatch it.

## Adding a new broadcast event

1. Decide: does this event need other side effects besides broadcasting (cache flush, side-channel notification, etc.)? If yes, use the **decoupled** pattern (separate listener) so those concerns don't get tangled into the broadcast payload logic. If broadcasting is the only thing that needs to happen, the **self-broadcasting** pattern is less code.
2. If broadcasting to a channel that doesn't exist yet, add an authorization rule in `routes/channels.php`.
3. Always define the payload explicitly — don't rely on default property reflection.
4. Add the event to the tables above and to whichever `useEcho` call needs to react to it.
5. If using the self-broadcasting pattern, add a unit test asserting `broadcastOn()`/`broadcastAs()`/`broadcastWith()` (see Testing above).
