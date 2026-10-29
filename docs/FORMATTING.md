# Automatic formatting in VS Code

Open the whole `C:\Project\Forge-And-Fade` folder in VS Code so the shared `.vscode/settings.json` applies.

Install these extensions from the Extensions panel (`Ctrl+Shift+X`):

- **Prettier - Code formatter**, publisher Prettier, ID `esbenp.prettier-vscode`: React/JavaScript, SCSS, CSS, HTML and JSON.
- **Laravel Pint**, publisher Open Southeners, ID `open-southeners.laravel-pint`: PHP using the project's existing Laravel Pint dependency. This editor integration is community-maintained, not an official Laravel extension.

Save a file with `Ctrl+S` to format automatically. To format it manually, press `Shift+Alt+F`. If prompted, choose Prettier for frontend files and Laravel Pint for PHP. Formatting on save is configured for this project only, not your other VS Code projects.

## Terminal commands

In `frontend`:

```powershell
npm install
npm run format
npm run format:check
```

In `backend`:

```powershell
C:\xampp\php\php.exe vendor/bin/pint app routes tests
C:\xampp\php\php.exe vendor/bin/pint --test app routes tests
```

Prettier is a pinned development dependency. Laravel Pint is already installed through Composer. Prettier deliberately does not format PHP, dependencies, build output or environment files. PHP needs XAMPP's `C:\xampp\php` on PATH for the Pint extension; restart VS Code after changing PATH. If the extension cannot discover Pint from the combined folder, open `backend` as a VS Code folder and select its `vendor/bin/pint` executable in the extension settings.

Formatting improves indentation and line wrapping; it does not repair application logic. Continue using ESLint, production builds and Laravel tests separately.
