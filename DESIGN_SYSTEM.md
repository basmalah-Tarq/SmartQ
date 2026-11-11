# SmartQ Design System (shared by the website and the Android app)

The brand comes from the logo (`web/img/logo1.png`, `web/img/logo-small.png`): an hourglass in blue,
a teal/green "brain", a gold light-bulb and the wordmark "smart Q" in deep navy with a blue check mark.
Website and app MUST use exactly these tokens so they read as one product.

## Colors
| Token | Hex | Use |
|---|---|---|
| navy-900 | #0B1F45 | headings, dark sections, footer |
| navy-700 | #12306B | wordmark navy, dark buttons |
| blue-600 (primary) | #1F6FE0 | primary buttons, links, active states |
| blue-500 | #3B82F6 | hover/secondary accents |
| sky-300 | #8EC5FF | glows, illustrations |
| teal-500 (secondary) | #14A39A | success-ish accents, manager role, "live" indicators |
| gold-400 (highlight) | #F5B82E | highlights, "AI / smart" badges, star ratings |
| bg | #F5F8FD | page background |
| surface | #FFFFFF | cards |
| surface-alt | #EEF4FC | subtle panels, chips |
| border | #E2E9F4 | 1px borders |
| text | #0B1B36 | body text |
| muted | #5B6B85 | secondary text |
| success | #16A36B | confirmed / done |
| warning | #F59E0B | waiting |
| danger | #E5484D | cancelled / errors |

Brand gradient: `linear-gradient(135deg, #0B1F45 0%, #1F6FE0 55%, #14A39A 100%)`
Soft gradient (backgrounds): `radial-gradient(circle at 15% 10%, rgba(31,111,224,.14), transparent 45%), radial-gradient(circle at 85% 0%, rgba(20,163,154,.12), transparent 40%)`

Role accents (keep consistent everywhere): User = blue-600, Manager = teal-500, Admin = navy-700 with gold-400 details.

Status colors: waiting = warning, confirmed = blue-600, done = success, cancelled = danger, missed = muted.

## Typography
- Headings: **Plus Jakarta Sans** 700/800 (fallback: Inter, system-ui)
- Body/UI: **Inter** 400/500/600
- Scale: 12 / 14 / 16 / 18 / 22 / 28 / 36 / 48 / 60
- Queue numbers / big stats: Plus Jakarta Sans 800, tabular numbers.

## Shape & elevation
- Radius: cards 20, inputs 14, buttons pill (999) for primary CTAs, 12 for small buttons, chips 999.
- Shadow sm: `0 1px 2px rgba(11,31,69,.06), 0 1px 1px rgba(11,31,69,.04)`
- Shadow md: `0 10px 30px -12px rgba(11,31,69,.18)`
- Shadow glow (primary CTA): `0 12px 28px -10px rgba(31,111,224,.55)`
- Spacing scale: 4 / 8 / 12 / 16 / 24 / 32 / 48 / 72 / 96

## Motion (strong but tasteful)
- Durations: 150ms (hover), 250ms (UI), 450–700ms (entrances), easing `cubic-bezier(.22,1,.36,1)`.
- Entrances: fade + 16px slide up, staggered 60–80ms between items.
- Hover: cards lift 4px + shadow md; buttons scale 1.02 + glow.
- Signature motion: the "live queue" — numbers ticking (Q-041 → Q-042), a progress ring filling,
  hourglass sand subtly flowing, floating info chips.
- Always respect reduced motion (web: `prefers-reduced-motion`; Android: animator duration scale).

## Components (names shared by both clients)
Buttons (primary / secondary / ghost / danger), Input, Chip (filter), Card, PlaceCard (image, name, category chip,
city, "Book" CTA), ScheduleCard (date, time range, seats-left bar), SlotButton (available / selected / booked /
passed), QueueTicket (big queue code "Q-007", place, date, time, status pill – looks like a ticket with a notch),
StatusPill, StatCard, EmptyState (illustration + text + action), Skeleton/shimmer loaders, Toast/Snackbar.

## Voice
Short, friendly, confident: "Book your turn. Skip the line." Buttons are verbs: "Book now", "Choose a time",
"Confirm booking", "Cancel booking".
