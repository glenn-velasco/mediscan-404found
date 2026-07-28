# Broadcasting (WebSocket events)

This app pushes real-time updates over WebSockets using [Laravel Reverb](https://reverb.laravel.com/). This is **not** covered by Scribe — Scribe only documents HTTP routes under `api/v1/*` (`config/scribe.php`), and broadcasting has no routes or controllers for it to introspect. This document is the source of truth for what gets broadcast, on which channels, and how the frontend consumes it.

## Connection

- Driver: `reverb` (`config/broadcasting.php`), default connection set via `BROADCAST_CONNECTION` in `.env`.
- Server env vars: `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME`.
- Frontend env vars: `VITE_REVERB_APP_KEY`, `VITE_REVERB_HOST`, `VITE_REVERB_PORT`, `VITE_REVERB_SCHEME`.
- Channel authorization endpoint: `POST /broadcasting/auth`, registered automatically via the `channels: __DIR__.'/../routes/channels.php'` key in `bootstrap/app.php`'s `withRouting()`. The auto-registered route uses the **web** guard (sessions). An explicit `POST /api/v1/broadcasting/auth` route is also registered in `routes/api/v1.php` under the `auth:sanctum` + `api.active` middleware for mobile API clients. Authorization rules live in `routes/channels.php`.
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

Used by: `EmailChanged` → `BroadcastEmailChanged`, `Illuminate\Auth\Events\Verified` → `BroadcastEmailVerified`, `Illuminate\Auth\Events\Registered` → `BroadcastUserRegistered` (this one also flushes the admin dashboard cache in the same listener — a non-broadcast side effect, which is exactly why this pattern is useful). Also `Illuminate\Auth\Events\Login`/`Logout` → `LogSuccessfulLogin`/`LogSuccessfulLogout` (audit-only, no broadcast — see `docs/TODO.MD` / `AuditLogger`).

Note: public web registration (`/register`) has been removed — regular users now sign up via the mobile app (`POST /api/v1/register`), which does **not** dispatch `Registered`. The only remaining trigger for `Registered` (and therefore `BroadcastUserRegistered`/`.UserRegistered`) is an admin invitation being accepted (`InvitationService::acceptInvitation()` dispatches it manually).

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

Used by: `UserDeactivated`, `UserDeleted`, `ProfessionalApplicationStatusChanged` (broadcasts on both the applicant's own channel and `admin-dashboard` at once - see its `broadcastOn()`), `MedicalInformationUpdated` (broadcasts on every currently-linked user's own channel at once, since one medical information record can back many accounts - see its `broadcastOn()`), `AccountRetrievalRequestStatusChanged` (broadcasts on `admin-dashboard` and, when the request has a `requester_user_id`, the requester's own channel too - see its `broadcastOn()`), `MedicalInformationRegistrationMatchCreated` (broadcasts only on the candidate record's primary user's own channel - this flow never involves an admin, and there's no requester channel to reach either, since a registration match holds the submitted data in a `PendingRegistration` rather than creating an account until the primary accepts - see `App\Services\Medical\MedicalInformationRegistrationMatchService`), `PatientRecordUpdated` (one shared event for all four patient-authored CRUD resources - allergy/diagnosis/medication/emergency_contact - dispatched from `App\Services\Medical\PatientRecordService::create()`/`update()`/`delete()`; see `docs/SYNC.md`).

`public bool $afterCommit = true` is the equivalent of `ShouldQueueAfterCommit` in pattern 1 — it delays the broadcast job until the current DB transaction commits.

**Payload rule regardless of pattern**: always define the payload explicitly (`->with([...])` or `broadcastWith()`). If a `ShouldBroadcast` event has no `broadcastWith()`, Laravel falls back to reflecting over every public property and serializing it (see `vendor/laravel/framework/src/Illuminate/Broadcasting/BroadcastEvent.php`) — for an event holding a full `User` model, that means the entire model goes out over the wire. Keep payloads to the minimum the frontend needs (usually just an id).

## Channels

| Channel | Visibility | Authorized in `routes/channels.php` as |
|---|---|---|
| `admin-dashboard` | Private | Users with the `Admin` role |
| `App.Models.User.{id}` | Private | Only the user matching `{id}` |
| `family` | Private | Any authenticated user (returns `{ id, name, email }`) |

## Events per channel

| Channel | Event name | Dispatched from | Payload |
|---|---|---|---|
| `admin-dashboard` | `.UserRegistered` | `BroadcastUserRegistered` (listens to `Illuminate\Auth\Events\Registered`, now only dispatched via `InvitationService::acceptInvitation()` since public web registration was removed) | `{ stats: DashboardStats }` — see `resources/js/pages/admin/dashboard.tsx` for the `DashboardStats` shape (`total`, `active`, `deactivated`, `by_role`) |
| `admin-dashboard` | `.EmailChanged` | `BroadcastEmailChanged` (listens to `App\Events\EmailChanged`) | `{ user_id: number }` |
| `admin-dashboard` | `.InvitationSent` | Inline in `app/Services/User/InvitationService.php` | `{}` (no payload) |
| `admin-dashboard` | `.ProfessionalApplicationStatusChanged` | `App\Events\ProfessionalApplicationStatusChanged` (self-broadcasting), dispatched from `ProfessionalApplicationService::submit()`/`approve()`/`reject()` and the `ProcessProfessionalApplication` job | `{ application_id: number, status: string }` |
| `App.Models.User.{id}` | `.EmailChanged` | `BroadcastEmailChanged` (same listener, also broadcasts on the changed user's own channel) | `{ user_id: number }` |
| `App.Models.User.{id}` | `.EmailVerified` | `BroadcastEmailVerified` (listens to `Illuminate\Auth\Events\Verified`) | `{ email_verified_at: string|null }` |
| `App.Models.User.{id}` | `.UserDeactivated` | `App\Events\UserDeactivated` (self-broadcasting), dispatched from `UserService::setActive()` | `{ user_id: number }` |
| `App.Models.User.{id}` | `.UserDeleted` | `App\Events\UserDeleted` (self-broadcasting), dispatched from `UserService::delete()` | `{ user_id: number }` |
| `App.Models.User.{id}` | `.ProfessionalApplicationStatusChanged` | Same event as above, on the applicant's own channel | `{ application_id: number, status: string }` |
| `App.Models.User.{id}` | `.MedicalInformationUpdated` | `App\Events\MedicalInformationUpdated` (self-broadcasting), dispatched from `MedicalInformationService::update()`/`syncAvatar()`/`repointUserToRecord()`, on every currently-linked user's channel | `{ medical_information_id: number }` — deliberately metadata-only, no PHI fields, consistent with `PendingSyncEnvelopeCreated`'s convention |
| `admin-dashboard` | `.AccountRetrievalRequestStatusChanged` | `App\Events\AccountRetrievalRequestStatusChanged` (self-broadcasting), dispatched from `AccountRetrievalRequestService::approve()`/`deny()` | `{ account_retrieval_request_id: number, status: string }` |
| `App.Models.User.{id}` | `.AccountRetrievalRequestStatusChanged` | Same event as above, on the requester's own channel - only when the request has a `requester_user_id` (pre-registration submissions have no account to notify) | `{ account_retrieval_request_id: number, status: string }` |
| `App.Models.User.{id}` | `.MedicalInformationRegistrationMatchCreated` | `App\Events\MedicalInformationRegistrationMatchCreated` (self-broadcasting), dispatched from `MedicalInformationRegistrationMatchService::createForPendingRegistration()`, on the candidate record's primary user's own channel | `{ medical_information_registration_match_id: number }` — metadata only, no PHI. There is no equivalent event on a requester channel: a registration match holds the submitted data in a `PendingRegistration` row rather than creating a `User`, so there's no account/channel to notify until the primary accepts (at which point they're emailed instead - see `PendingRegistrationConfirmedNotification`). No frontend UI here consumes this yet (the primary-side review experience lives in the mobile client); documented here for that client to wire up |
| `App.Models.User.{id}` | `.PatientRecordUpdated` | `App\Events\PatientRecordUpdated` (self-broadcasting), dispatched from `App\Services\Medical\PatientRecordService::create()`/`update()`/`delete()` (shared by `AllergyService`/`DiagnosisService`/`MedicationService`/`EmergencyContactService`) | `{ record_type: string, record_id: string }` — `record_type` is one of `allergy`/`diagnosis`/`medication`/`emergency_contact`, `record_id` is that record's UUID. Metadata only, no PHI. No frontend UI consumes this yet (same as `MedicalInformationRegistrationMatchCreated` above) — the mobile client is expected to react by calling `GET /sync?since=` instead of waiting for its poll interval; see `docs/SYNC.md` |


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
- `resources/js/layouts/user-layout.tsx` — listens on the current user's own `App.Models.User.{id}` channel for `.UserDeactivated`/`.UserDeleted` and force-logs the user out (`router.post(logout.url())`) so a deactivated/deleted user's open tab reacts immediately instead of waiting for their next request to hit `CheckUserActive`/`EnsureApiUserActive` middleware. It also listens for `.EmailChanged` on the same channel and does a partial `router.reload({ only: ['auth'] })` so `useAuth()`-driven UI (e.g. the email shown on `resources/js/components/user-info.tsx`) reflects an email change made elsewhere without a full page reload.
- `resources/js/pages/admin/professional-applications/index.tsx` and `.../show.tsx` — listen on `admin-dashboard` for `.ProfessionalApplicationStatusChanged` and `router.reload()`, so the list/detail view picks up automatic-verification results and other admins' approve/deny actions live.
- `resources/js/pages/professional-application/show.tsx` — listens on the applicant's own `App.Models.User.{id}` channel for `.ProfessionalApplicationStatusChanged` and `router.reload()`, so the status page updates the moment the background job or an admin decision changes it, without the applicant needing to refresh. Also uses a `visibilitychange` listener as a fallback: when the tab regains focus, it reloads the `application` prop so missed WebSocket events (connection drops, auth expiry) don't leave the page stale.
- `resources/js/layouts/user-layout.tsx` — listens on the current user's own `App.Models.User.{id}` channel for `.ProfessionalApplicationStatusChanged` and `router.reload()`, so the user menu's "Professional Application" link and any status-dependent UI elsewhere in the layout reflect the updated status without requiring a manual page visit.
- `resources/js/pages/admin/account-retrieval-requests/index.tsx` and `.../show.tsx` — listen on `admin-dashboard` for `.AccountRetrievalRequestStatusChanged` and `router.reload()`, so the queue picks up other admins' approve/deny actions live.
Note: `EmailVerified` is consumed by the mobile client (`mediscan-mobile`) via Pusher — see `src/realtime/events/handlers.ts` which calls `refreshUser()` on receipt. `MedicalInformationRegistrationMatchCreated` and `PatientRecordUpdated` are broadcast on `App.Models.User.{id}` but currently have **no frontend consumer** in this repo — this app's frontend is the admin panel; the end-user (requester/primary) experience for these two events lives in the mobile client.

### Mobile fallback mechanisms

The mobile client (`mediscan-mobile`) has two additional resilience mechanisms for when realtime events are missed:

1. **Subscription diagnostics** (`src/realtime/channels/user-channel.ts`): Binds `pusher:subscription_succeeded` and `pusher:subscription_error` handlers so channel auth failures are visible in console logs. Also logs every received event with `[UserChannel] RECEIVED`.

2. **Foreground refetch** (`src/app/account/index.tsx`, `src/app/professional-application/[id].tsx`): Listens for `AppState` changes and invalidates the relevant React Query caches (`myProfessionalApplicationQueryKey` and `professionalApplicationQueryKey`) when the app returns to the foreground. This ensures the UI updates even if the Pusher WebSocket was disconnected while the app was backgrounded.

## Testing

Self-broadcasting events (`ShouldBroadcast`) get a `tests/Unit/Events/{Event}Test.php` that asserts `broadcastOn()`, `broadcastAs()`, and `broadcastWith()` directly on the event object — no DB or queue involved, since these are pure value assertions. See `tests/Unit/Events/UserDeactivatedTest.php`, `tests/Unit/Events/UserDeletedTest.php`, and `tests/Unit/Events/PatientRecordUpdatedTest.php` for the pattern.

Gaps today: the decoupled pattern (`BroadcastEmailChanged`, `BroadcastEmailVerified`, `BroadcastUserRegistered`) and the inline `InvitationSent` broadcast in `InvitationService` have no broadcast-specific tests. `ProfessionalApplicationStatusChanged` is tested in `tests/Feature/Admin/ProfessionalApplicationTest.php` via `Event::fake()` + `Event::assertDispatched()`. The login/logout audit listeners (`LogSuccessfulLogin`/`LogSuccessfulLogout`) are covered in `tests/Feature/Audit/AuditLoggerTest.php`.

## Adding a new broadcast event

1. Decide: does this event need other side effects besides broadcasting (cache flush, side-channel notification, etc.)? If yes, use the **decoupled** pattern (separate listener) so those concerns don't get tangled into the broadcast payload logic. If broadcasting is the only thing that needs to happen, the **self-broadcasting** pattern is less code.
2. If broadcasting to a channel that doesn't exist yet, add an authorization rule in `routes/channels.php`.
3. Always define the payload explicitly — don't rely on default property reflection.
4. Add the event to the tables above and to whichever `useEcho` call needs to react to it.
5. If using the self-broadcasting pattern, add a unit test asserting `broadcastOn()`/`broadcastAs()`/`broadcastWith()` (see Testing above).
