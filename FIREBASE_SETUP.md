# Paradise Resort Firebase + Vercel setup

The Firebase-ready app is in `firebase-app/`. It runs without a build step and uses the modular Firebase browser SDK. If no Firebase config is present, it runs in local demo mode using `localStorage`, which makes the localhost flow usable immediately.

## 1. Create the Firebase project

1. Create a Firebase project and a Web App in the Firebase Console.
2. Enable **Authentication > Sign-in method > Email/Password**.
3. Create a Firestore database in production mode.
4. Copy the web app config into `firebase-app/firebase-config.js`.
5. Import the four room documents from `firebase/seed-data.json` into the `rooms` collection. The document IDs in the JSON should be preserved.
6. Publish `firestore.rules` in the Firestore Rules tab.
7. To create an admin, register a normal account first, then change that user's `users/{uid}.role` value to `admin` in Firestore.

The web config is not a secret. Never put a Firebase service-account JSON file or private key in this repository.

## 2. Run locally

From the project directory:

```bash
node server.mjs
```

Open [http://localhost:3000](http://localhost:3000). If Firebase config is still blank, the app shows a local-demo badge and stores users, rooms, bookings, payments, and messages in this browser only.

When using XAMPP, `http://localhost/paradiseresort/` now redirects to the same Firebase app. The old PHP/MySQL landing page remains available temporarily at `http://localhost/paradiseresort/index.php?legacy=1`.

## 3. Deploy to Vercel

Import the repository into Vercel and deploy it with no build command. The included `vercel.json` rewrites the root URL to the static Firebase app. The same `firebase-app/firebase-config.js` is served to the browser, so Firebase Auth's authorized domains must include the Vercel domain.

## Booking behavior

Every night from check-in (inclusive) to check-out (exclusive) gets a Firestore `dateLocks/{roomId}_{yyyy-mm-dd}` record inside the same transaction as the booking. A second transaction that touches any of those dates is rejected. The calendar colors are:

- green: available
- red: booked and disabled
- gray: past or blocked
- blue: selected date/range

Pending bookings hold their dates. Paying changes the booking to `approved`; cancelling or rejecting it releases the date locks.
