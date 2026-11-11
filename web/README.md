# SmartQ - Smart Queue Reservation Platform

SmartQ is a graduation-project friendly PHP + MySQL queue booking platform inspired by the folder organization of the eDoc project, but redesigned for your own idea.

## Roles
- **Admin**: global system admin.
- **Manager**: manages one or more places (clinic, bank, gym, beauty center, etc.).
- **User**: searches places and books queue slots.

## Main features
- Search places by name, city, and category.
- Book appointments by date and time slot.
- Prevent duplicate booking on the same schedule by the same user.
- Queue number generation.
- Role-based dashboards.
- Simple analytics cards.
- API-ready structure for future Android app.

## Default folders
- `admin/`
- `manager/`
- `user/`
- `css/`
- `img/`
- `SQL_Database_smartq.sql`

## Run with Docker
```bash
docker compose up --build
```
Then open the app in your browser.

## Demo credentials
After importing `SQL_Database_smartq.sql`, use these local demo accounts:

| Role | Email | Password |
| --- | --- | --- |
| Admin | `admin@smartq.com` | `123456` |
| Manager | `manager@smartq.com` | `123456` |
| User | `user@smartq.com` | `123456` |

You can also create new user or manager accounts through the signup page.

## V10 final visual polish
- A stable account name and settings shortcut appear in the top navigation of all workspaces.
- Homepage motion is intentionally subtle and uses the SmartQ purple-teal visual identity.
- Add Manager and related internal forms use a professional two-column layout on desktop and a single column on smaller screens.
- Existing role colors remain consistent: User blue, Manager teal, Admin purple.
