# SmartQ API ↔ Android contract check

Date: 2026-11-10. Server: `web/api/` on http://localhost/smartq_dev (DB `smartq_dev`).
Client: `android/app/src/main/java/com/example/smartquser` (all calls go through `net/ApiClient.java`).
Every endpoint below was called with curl, including the error cases. The PHP response was compared with the fields the Java code reads (`optString/optInt/...`, `models/*.fromJson`) and the parameters it sends.

## Envelope and transport (all endpoints)

| Item | Server | App | Result |
|---|---|---|---|
| Envelope | `{"status":bool,"message":str,"data":{...}}` | `ApiClient.parse()` reads `status`, `message`, `data` (object) | OK. Fixed: an empty `data` used to be sent as `[]`. It is now always `{}`; the app already handled both. |
| Token | Accepts `Authorization: Bearer`, `X-Api-Token`, or `api_token` in the POST body | Sends both headers on every request, plus `api_token` in POST/multipart bodies | OK. All 3 ways tested. |
| 401 handling | `data.code` = `AUTH_REQUIRED` / `AUTH_INVALID` / `AUTH_EXPIRED` | `Kind.AUTH` when the code starts with `AUTH_`: clears the session and opens Welcome with the message | OK. Tested with no token, a wrong token, an expired token (`token_expires_at` set in the past) and a token after logout. |
| Guest mode | Public endpoints need no token | `AuthGate.PUBLIC_ENDPOINTS`: auth_options, email_start, email_verify, google_login, login, register, places, place_details, schedules, slots | OK. The lists match. Private endpoints are never sent without a token. |
| Validation / conflict | 422 = invalid input, 409 = conflict, 403 = not allowed, 404 = not found, 405 = wrong method | Any non-5xx JSON error becomes `Kind.API` and the message is shown as-is | OK. Fixed: booking validation errors (bad email or age, bad time format) were 409; they are now 422. Fixed: `email_start` returned 429 for every error; it now returns 422 (bad email), 429 (rate limit), 400 (disabled) or 503 (mail failure). |
| Image URLs | Absolute URLs built from the request host (`BASE_URL`). This gives `http://10.0.0.1/smartq_dev/uploads/...` over Wi-Fi. | `place_image_url`, `profile_image_url` loaded with Glide | OK. Fetched an uploaded image URL: 200 image/png. |
| Time / date formats | Dates `YYYY-MM-DD`; times `HH:MM:SS`; labels `g:i A`; server time zone Asia/Gaza, the same in PHP and MySQL | `DateUtils` parses `yyyy-MM-dd` and `HH:mm:ss`; the slot `time` is sent back unchanged | OK |

## Endpoints

| Endpoint (method) | Android caller | Sent by app | Fields the app reads | Server response (verified) | Status |
|---|---|---|---|---|---|
| `../health.php` (GET) | MainActivity | none | status only | `server_time`, `api_version` | OK. localhost and **http://10.0.0.1/smartq_dev/api/health.php both answer 200** |
| `auth_options.php` (GET) | MainActivity, WelcomeActivity, AuthOptions | none | `email_code`, `google`, `google_client_id`, `password` | same keys | OK |
| `login.php` (POST) | LoginActivity → `BaseActivity.saveAuth` | `email`, `password` | `api_token`, `user{id,full_name,email,phone,birth_date,profile_image_url}` | 200. Errors: 401 wrong password, 403 manager/admin, 422 missing fields, 405 GET | OK |
| `register.php` (POST) | RegisterActivity | `full_name,email,phone,birth_date,password` | same as login | 200. Errors: 409 duplicate email, 422 bad date/email/password | OK |
| `email_start.php` (POST) | EmailSignInActivity | `email` | `email`, `is_new_user`, `resend_in` | same keys + `expires_in` | OK (status codes fixed, see above) |
| `email_verify.php` (POST) | EmailSignInActivity | `email, code, full_name` (only for new users) | `api_token`, `user`, `is_new_user` | same keys. Wrong code: 401 **without** an `AUTH_` code, so the app shows "Wrong code. N attempts left." and does not log out | OK |
| `google_login.php` (POST) | BaseActivity | `id_token` | `api_token`, `user`, `is_new_user` | same keys. Google is not configured on dev (`google:false`), so the button is hidden | Not testable without a Google client ID |
| `places.php` (GET) | PlacesActivity, DashboardActivity | `search`, `category` ("" = all) | `places[]` (`Place.fromJson`: id, place_name, category, city, address, description, place_image_url), `categories[]` | same | OK |
| `place_details.php` (GET) | PlaceDetailsActivity | `place_id` | `place`, `schedules[]` (`Schedule.fromJson`: id, schedule_date, start_time, end_time, capacity, booked_count, available_slots, slot_duration) | same. Errors: 422 missing id, 404 unknown or inactive place | OK |
| `schedules.php` (GET) | (not used by the app, kept for compatibility) | `place_id` | – | same schedule objects | OK |
| `slots.php` (GET) | BookAppointmentActivity | `schedule_id` | `schedule{schedule_date,start_time,end_time,place_name,slot_duration}`, `free_slots`, `slots[]{time,label,available,reason}`, **`suggested_time`** | same. **New: `suggested_time` ("HH:MM:SS" or null)**. Backwards compatible. `reason` is null, `booked`, `past` or `full` | OK. The app now uses it for **"Help me choose"** |
| `book_appointment.php` (POST) | BookAppointmentActivity → BookingSuccessActivity | `schedule_id, appointment_time, customer_name, customer_phone, customer_email, customer_age, notes` | `appointment_id`, `queue_number`, `queue_code`, `appointment_time`, `appointment_time_label`, `status`, `place_name`, `schedule_date`, `start_time`, `end_time` | same. 409: "This time was just booked by someone else…", "You already have a booking in this schedule.", "This schedule is full.", "This time is not part of the schedule." 422: bad email or age. 401: no token | OK |
| `appointments.php` (GET) | AppointmentsActivity, DashboardActivity | none | `appointments[]` (`Appointment.fromJson`: id, queue_number, queue_code, appointment_status, appointment_time, appointment_time_label, schedule_date, start_time, end_time, place_name, category, city, address, place_image_url, can_cancel, nested schedule/place), `stats{upcoming,done,total,...}` | same | OK |
| `next_appointment.php` (GET) | DashboardActivity | none | `appointment` (object **or null**) | `{"appointment":null}` with 200 when there is none | OK. `optJSONObject` returns null, and the app shows "no ticket" |
| `cancel_appointment.php` (POST) | AppointmentsActivity | `appointment_id`, `status=cancelled` | message only | 200. Errors: 409 already cancelled / not found / past, 422 missing id, 403 if status ≠ cancelled | OK |
| `update_appointment_status.php` (POST) | (not used by the app; old alias of cancel) | – | – | same as cancel | OK |
| `profile.php` (GET) | ProfileActivity | none | `user{...}` | same | OK |
| `update_profile.php` (POST) | ProfileActivity | `full_name, email, phone, birth_date` (`YYYY-MM-DD` or "") | `user` → `SessionManager.saveUser` | **Fixed.** An empty `birth_date` was stored as `0000-00-00` (and fails with SQL error 500 on strict MySQL 8). It is now NULL and returned as `""`. The response now contains the **full** user, including `profile_image_url`. `YYYY/MM/DD` is accepted. Invalid or future dates return 422, a duplicate email returns 409 | OK |
| `upload_profile_image.php` (POST multipart) | ProfileActivity | field **`profile_image`** (JPEG `avatar.jpg`) + `api_token` | `profile_image_url` | same. A non-image returns 422, a missing file returns 422. The old file is deleted only when it is inside `uploads/` | OK. Uses the same field name as the app |
| `logout.php` (POST) | BaseActivity | none | – | 200. The token is cleared, and the next call returns 401 `AUTH_INVALID` | OK |

## Mismatches found and fixed

1. `update_profile.php`: an empty birth date was stored as `0000-00-00`, and the response had no `profile_image_url`. Fixed on the server. Old rows are cleaned by `database/migration_003_data_cleanup.sql`. `userPayload()` also maps `0000-00-00` to `""`.
2. `upload_profile_image.php`: the extension check could be bypassed by renaming a file, and the old file was deleted with an unchecked path. The server now uses the shared `smartq_store_image_upload()` (real image check, 5 MB limit) and `smartq_delete_upload()`.
3. "Help me choose" was different on web and app: the website picked the first free slot and the app had no button. Now `smartq_suggest_slot()` in `config/booking.php` is used by both: `suggested_time` in slots.php, `data-suggested-time` on the website, and a new "Help me choose" button in BookAppointmentActivity.
4. HTTP codes: booking validation errors now return 422 instead of 409. `email_start.php` returns specific codes instead of always 429. The empty `data` is now `{}` instead of `[]`.

## Not verified

- No emulator or device was available, so the APK was built, unit-tested and linted but not run. The Wi-Fi URL was checked from this PC only (`http://10.0.0.1/smartq_dev/api/health.php` returns 200). The phone must be on the same network, and Windows Firewall must allow port 80.
- Google sign-in could not be tested end to end (no client ID is configured on dev).
