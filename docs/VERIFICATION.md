# Verification report

Updated 12 September 2026 after the AirPrint implementation. Environment: Xcode 26.6, iOS 26.5 Simulator runtime. Existing PhotoBooth project and architecture retained.

## Results

| Check | Result | Scope |
| --- | --- | --- |
| Simulator Debug build | PASS | Existing PhotoBooth scheme |
| Physical-device Debug build | PASS | iphoneos, unsigned; compilation only |
| Physical-device Release build | PASS | iphoneos, unsigned; compilation only |
| Latest hosted unit tests | PASS | 42 tests, zero failures on iPhone Simulator: 32 session, 4 renderer/storage, 6 printing |
| Customer UI flow on iPad | PASS | One UI test plus the then-current 41 unit tests |
| Customer UI flow on iPhone | PASS | One UI test plus the final 42 unit tests |
| Actual AirPrint delivery and paper output | PENDING | Requires a physical device and printer |
| Physical camera and device acceptance | PENDING | Requires hardware |

The customer UI test covers package/template selection, simulated payment, simulated capture, review, final preview, mock print, completion, and return home. It verifies that the final print button is hittable and within the application frame without scrolling on both tested screen sizes. Simulator tests use MockPrintService; they do not prove physical printing or camera operation.

Printing tests cover configuration persistence and invalid URLs, no-printer failure, the 1200×1800 / 300 DPI test page, aspect-preserving page fitting, and simulator routing. Session tests cover callback-only print counts, cancellation and failures, unknown outcomes, duplicate/concurrent request protection, retained final images, expiration, reason-required reprints, and active-order recovery. Payment behavior is unchanged.

## Local evidence

Logs are temporary local artifacts:

- `/tmp/photobooth-airprint-build.log`: Simulator build succeeded.
- `/tmp/photobooth-airprint-device.log`: physical-device Debug build succeeded.
- `/tmp/photobooth-airprint-release.log`: physical-device Release build succeeded.
- `/tmp/photobooth-airprint-ui-tests.log`: iPad tests succeeded.
- `/tmp/photobooth-airprint-iphone-tests.log`: final iPhone tests succeeded.

Result bundles:

- iPad: `/tmp/PhotoBoothAirPrintBuild/Logs/Test/Test-PhotoBooth-2026.09.12_06-42-32-+0700.xcresult`
- iPhone: `/tmp/PhotoBoothAirPrintBuild/Logs/Test/Test-PhotoBooth-2026.09.12_06-44-35-+0700.xcresult`

Earlier launch repairs remain in place: CameraPreview safely unwraps the optional capture device; PhotoBoothCore uses `@rpath/PhotoBoothCore.framework/PhotoBoothCore`. A standalone Simulator launch was verified in that earlier repair phase.

## Changed areas

Added printing models/history, persistent printer configuration, centralized AirPrint service, mode routing, photo/test-page renderers, Admin printer/reprint sections, a separate final preview, printing unit tests, and a customer UI test target in the existing project.

Updated service protocols/mocks, session print and history handling, app dependency injection, root/review/completion/Admin views, privacy manifest, session tests/test doubles, project generator and generated project/scheme, README, architecture, roadmap, and printing documentation.

See [PRINTING.md](PRINTING.md) for implementation details, printer setup, reprint behavior, retention, limitations, and the physical acceptance checklist. Admin remains a Debug feature; production Admin authentication is outside this phase. Release payment remains fail-closed as before.

## Reproduction

Use an available Simulator destination in Xcode and Product → Test. The shared PhotoBooth scheme includes unit and UI tests. For command-line verification:

```sh
xcodebuild -project PhotoBooth.xcodeproj -scheme PhotoBooth \
  -destination 'generic/platform=iOS Simulator' CODE_SIGNING_ALLOWED=NO build
xcodebuild -project PhotoBooth.xcodeproj -scheme PhotoBooth \
  -destination 'platform=iOS Simulator,id=<available-UDID>' test
xcodebuild -project PhotoBooth.xcodeproj -scheme PhotoBooth \
  -configuration Release -destination 'generic/platform=iOS' CODE_SIGNING_ALLOWED=NO build
```

Unsigned device builds validate compilation, not installation, provisioning, or physical print output. Complete the hardware checklist before marking physical AirPrint acceptance complete.


## Operator settings — 12 September 2026

Added per-package price/copy overrides, countdown, per-session retake budget, completion timeout, and changeable local Admin PIN. Settings use a versioned UserDefaults value; the PIN uses device-only Keychain storage. Session preferences are snapshotted when starting a customer session. Packages that forbid retakes continue to forbid them.

- Physical-device Debug build: PASS, unsigned compilation (`/tmp/photobooth-operator-build.log`). The initial sandboxed attempt blocked compiler macros; the authorized local build succeeded.
- Portable core suite: PASS, 35 cases, zero assertion failures (`/tmp/photobooth-operator-tests.log`). This is not a new hosted XCTest run. New tests cover active-order isolation/next-session application, copy counts and completion timeout, retake exhaustion/reset/disable, invalid settings, and serialization.
- Admin form interaction, persistence after relaunch, and Keychain PIN change on physical iPhone: pending manual verification. No new UI test or physical print was run for this change.
- Existing Xcode project/signing configuration was preserved; project generation was not rerun.

Manual check: open Debug Admin, save a changed package price/copies and session timing, then start a new session. Close/reopen the app to check persistence. Change PIN using the current PIN, close Admin, and confirm that the old PIN fails and the new PIN unlocks it. Initial PIN is 2468 only when no saved Keychain credential exists.

## Physical print receipt correction — 12 September 2026

The user reported a delayed Epson L5190 failure after the app had already returned Home. Added separate physical delivery status and explicit customer receipt/help actions. AirPrint acknowledgement no longer starts the Home timer until the customer confirms receipt. Missing-photo reports remain on the help screen without automatically resubmitting; Admin displays service copy counts and delivery status separately.

- Unsigned iPhone Debug build: PASS (`/tmp/photobooth-receipt-build.log`).
- Portable session tests: PASS, 38 cases, zero assertion failures (`/tmp/photobooth-receipt-tests.log`). New coverage: no timeout before receipt, confirmation starts timeout, missing-photo report preserves paid order without duplicate submission, Admin recovery resets receipt state, and receipt actions are ignored before completion.
- Existing mock UI test expectation updated; UI suite was not rerun.
- Physical Epson offline retest and visual verification on iPhone: pending. This change does not automatically detect late iOS queue errors or establish physical printer telemetry.

## Admin help resolution — 12 September 2026

Added a confirmed “Selesaikan bantuan” action for paid missing-photo reports. Staff-confirmed delivery and resolution time are recorded; active help returns to completion without resubmission or payment changes. Archived help can be resolved after image expiry without disturbing another active order.

- Unsigned iPhone Debug build: PASS (`/tmp/photobooth-help-resolution-build.log`).
- Portable core suite: PASS, 40 cases, zero assertion failures (`/tmp/photobooth-help-resolution-tests.log`). Added coverage for active help resolution, unchanged print count/payment, duplicate rejection, timer/reset history preservation, expired-image resolution, and isolation from a new order.
- Physical Admin button/confirmation interaction: not run here; verify on the user's iPhone. No real print sent by this verification.

## Midtrans sandbox preparation — 15 September 2026

Added Laravel 12 backend (PHP 8.2-compatible) with persistent SQLite orders, server-priced catalog, device-scoped bearer authentication, idempotent QRIS charge reservation, authenticated QR proxy, verified provider-status polling, and signature-checked webhooks followed by authoritative status lookup. Added a Debug-only iOS sandbox wiring option, response/amount/identity validation, QR display, server-price enforcement, retained reconciliation IDs, and transient polling failure handling. Pending sandbox payments do not expire due to the kiosk idle timer. Release continues to reject unconfigured payments.

- Backend: `php artisan test` PASS, 8 tests / 36 assertions (6 payment tests plus 2 framework scaffold tests). Provider traffic is faked and stray requests blocked. Log: `/tmp/photobooth-backend-tests.log`.
- Core: portable runner PASS, 43 cases / zero assertion failures. Added HTTP-response tests for valid settlement, stale status, wrong amount, malformed response, insecure configuration, server-price enforcement, pending-payment idle handling and network recovery. Log: `/tmp/photobooth-payment-core.log`.
- iPhone Debug unsigned build: PASS. Log: `/tmp/photobooth-payment-build.log`.
- Real Midtrans sandbox account/QR/webhook, iPhone HTTPS connectivity, production payments and deployment: NOT RUN. Merchant credentials and an HTTPS endpoint are not yet available. No provider charge, public deployment or real-money transaction was performed.
- Recovery IDs are persisted, but paid-abandoned session recovery/fulfillment reservation is not complete. This remains sandbox development, not production acceptance. Exact setup and remaining work: `backend/README.md`.


## Server Key format correction — 19 September 2026

The user's Sandbox dashboard displayed a Server Key without the assumed SB- prefix. Corrected local setup and backend/webhook validation to accept both Mid-server- and SB-Mid-server- formats without altering credentials. API host remains hardcoded to sandbox. Added a fake-provider regression test covering charge authorization and notification verification with the unprefixed format. Backend suite: 9 tests passed / 40 assertions. Actual authentication awaits an exact dashboard key being stored locally; no secret from the screenshot was copied into code or logs.

## Live sandbox connectivity — 19 September 2026

After correcting the key-prefix assumption and re-entering the dashboard key, Midtrans authentication passed: an intentionally nonexistent transaction returned provider status 404 instead of unauthorized. No credentials were printed.

User explicitly approved Cloudflare temporary public tunnelling. Local backend and HTTPS checks: unauthenticated catalog 401, authenticated catalog 200 with three packages, local /.env 404. QUIC timed out; HTTP/2 tunnel registered successfully. Private PhotoBooth Sandbox Xcode user scheme was generated with local credentials; no signing/shared-scheme edits.

One real Midtrans **sandbox** test order was created through the HTTPS API: create 201, QRIS charge 200/pending, QR PNG 200 with valid signature. No real-money payment or print was sent. Test order reference is saved in ignored backend/.local/setup-test-order.json. Settlement and externally delivered webhook verification remain pending until user configures the notification URL and completes the simulator flow.

## First-order API response fix — 19 September 2026

Reproduced the iPhone payment-data mismatch: a newly inserted Eloquent order did not have the database-default payment_status loaded, so the first create response serialized status=null. The iOS client correctly rejected it before requesting a QR charge. The previous charge test had not asserted the initial response status.

Controller now refreshes the inserted model before serialization; amount has an explicit integer cast. Regression test verifies first-response pending status, amount type, QR flag, environment, expiry type and identical idempotent replay. Test failed before the fix and passed afterward. Full backend suite: 10 tests / 50 assertions passed. Public HTTPS first-order check: 201, pending, integer amount, IDR, sandbox. No charge/payment was made by this check. No iOS rebuild is needed for this backend correction.

## Provider polling status correction — 19 September 2026

The QR screen reported a connection error. Inspection found refresh() only accepted provider status_code 200 even when HTTP succeeded. Added state-constrained handling for 201/pending, 407/expire, and 202/deny/cancel/failure; settlement still requires 200 and all identity, amount, type and transaction-ID checks remain enforced. Pending/expired regression cases failed before the fix. Full backend suite afterward: 14 tests / 66 assertions passed, including code/state mismatch and amount mismatch checks. Logs: /tmp/photobooth-poll-before.log and /tmp/photobooth-poll-after.log.

Live status diagnosis was denied by automatic approval review due to the usage limit. The customer's actual provider response was therefore not observed, and the live fix is not yet verified. No workaround or further remote check was attempted. No iOS changes/build are needed for this backend fix; retest with a fresh sandbox session if the displayed QR has expired.

## Payment failure-path acceptance — 19 September 2026

User reported successful sandbox payment simulation. Physical capture/print following that verified payment has not yet been explicitly confirmed.

Added offline/timeout and expiry regression coverage without contacting Midtrans or interrupting the running tunnel:

- Portable core: 44 cases, zero assertion failures (`/tmp/photobooth-payment-failure-core.log`). A fake URLSession emits actual URLError.notConnectedToInternet and timedOut failures. The session retains its pending order beyond the kiosk idle/QR deadline, keeps camera locked, clears the connection message on recovery, and either opens the paid gate or shows verified expiry. Reset after expiry preserves its history status.
- Backend: 16 tests / 81 assertions passed (`/tmp/photobooth-payment-failure-backend.log`). Status-service outage preserves pending state and recovers on the same order using GET only. Expiry followed by verified settlement becomes paid; replayed expiry cannot downgrade it.
- Only test/documentation files changed in this phase; no new app build required. These are automated simulated failures, not completed physical-device network/expiry acceptance.

Manual acceptance still required: leave a fresh sandbox QR unpaid with the app in foreground until provider expiry, check the expired-payment message and locked camera, then return Home. For outage testing, keep PhotoBooth in foreground and briefly disconnect the backend Mac's network; expect a connection message and unchanged order ID. Restore network, use Midtrans's sandbox simulator on that same unexpired order, and check the paid gate. If the order expires meanwhile, expect verified expiry instead. Backgrounding iPhone is a separate recovery limitation and is not covered by this test. Never pay sandbox QR with real funds.

## Offline payment, recovery and operational preparation — 19 September 2026

Implemented order-bound 8-character random offline codes with SHA-256 digests, 15-minute validity,
one-time consumption committed together with payment, attempt lock surviving restart, and atomic durable
metadata journal. Customer chooses cash before order creation; no QR charge is made for cash orders.
Admin verifies receipt before issuance. Codes are single-device by design, accepted by the user as an implementation choice.

Admin-assisted same-device recovery keeps order identity and re-verifies remote payment/amount before opening camera.
Photos are not restored; staff restarts capture for paid unfulfilled orders. Print reservation is persistent locally
and atomic on backend; submitted/ambiguous jobs block automatic recovery. Admin reprint intent and resolution notes
are recorded locally. Offline transactions are not synced to backend. Uninstall/backup rollback recovery is not implemented.

Keychain backend provisioning survives Home Screen launch. Admin available in Release; new installations require
6–8 digit PIN setup with no default. Existing stored PIN retained. PIN attempt lock now survives process restart.
Backend adds environment tagging, explicit production enable, fixed per-environment host allowlist,
provider-aligned two-minute expiry and fulfillment reservation. Current local environment remains sandbox.
Both migrations applied to local database. No live charge or new printer request was sent.

Validation:
- Portable core runner: **49 cases, zero assertion failures** (`/tmp/photobooth-operations-core.log`).
  Includes persisted code hashing, order binding, replay rejection, rotation/expiry/lock,
  offline session recovery, print reservation across restart, corrupted ledger rejection,
  remote recovery amount mismatch rejection, and all previous cases.
- Laravel: **19 tests / 99 assertions passed** (`/tmp/photobooth-operations-backend.log`).
  Includes production disabled/wrong-environment rejection, production-host request via fake only,
  two-minute expiry request, device-scoped recovery, unpaid/duplicate fulfillment rejection.
- Early Debug unsigned iPhone build: PASS (`/tmp/photobooth-operations-build.log`).
  Later edits added backend fulfillment wiring, admin refinements and release diagnostic guard; latest full build is **not verified**.
- Release build invocation was blocked by automatic approval review due to service usage limit.
  No workaround was attempted. Static inspection found/fixed a Debug-only diagnostic dependency in Admin.
- Hardware offline/recovery acceptance and latest Xcode Debug/Release build: pending user/device validation.
- Hosting source archive verified to exclude .env, .local secrets, database files and logs; Composer dependencies are not bundled.

Operational guide: OPERATIONS.md. Hosting package/activation instructions: HOSTING.md.
User has hosting and will upload; domain/provider/access not supplied. Midtrans activation still pending.
Production activation, deployment and real-money acceptance are **not completed**.
