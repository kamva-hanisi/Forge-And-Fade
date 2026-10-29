# Forge & Fade Frontend

React frontend for Forge & Fade, a modern barbershop website for browsing services, meeting the barbers, booking appointments, and sending contact messages.

## Live Deployment

- Website: https://kamva-hanisi.github.io/Forge-And-Fade/
- API: https://forge-and-fade.onrender.com/api

## What It Includes

- Home page with hero, about, services, and barber highlights.
- Services page with pricing and duration details.
- About page for the shop story and brand positioning.
- Booking flow with service, barber, date, and time selection.
- Live availability checks against the Laravel API.
- Booking confirmation with Google Calendar and Apple Calendar options.
- Contact page that submits messages to the backend.
- Terms page and shared navigation/footer layout.

## Tech Stack

- React 19
- Vite
- React Router
- Axios
- Sass
- Lucide React icons

## Project Structure

```text
src/
  assets/       Brand and barbershop images
  components/   Shared UI sections such as Navbar, Hero, Footer
  data/         Local service and barber data
  pages/        Route-level page components
  styles/       Global Sass variables, reset, mixins, and styles
  utils/        Calendar helper functions
```

## Local Setup

Install dependencies:

```bash
npm install
```

Create a local environment file:

```bash
cp .env.example .env
```

Set the API URL:

```env
VITE_API_URL=http://127.0.0.1:8000/api
```

Start the development server:

```bash
npm run dev
```

The app will run on the Vite URL shown in the terminal, usually:

```text
http://localhost:5173
```

## Available Scripts

```bash
npm run dev
```

Starts the local Vite development server.

```bash
npm run build
```

Creates a production build in `dist/`.

```bash
npm run preview
```

Serves the production build locally for testing.

```bash
npm run lint
```

Runs ESLint across the frontend source.

## Backend Connection

The booking and contact pages use `VITE_API_URL` to call the Laravel backend:

- `GET /bookings/availability`
- `POST /bookings`
- `POST /contact`

Make sure the backend server is running and that CORS allows the frontend origin during local development and production deployment.

## Deployment Notes

The Vite config supports a custom base path through `VITE_BASE_PATH`, which is useful for GitHub Pages or subdirectory deployments. For production, set `VITE_API_URL` to the deployed Laravel API URL before building.
