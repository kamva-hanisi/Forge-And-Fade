const { spawn, spawnSync } = require("node:child_process");
const { existsSync } = require("node:fs");
const { createServer } = require("node:net");
const { resolve } = require("node:path");

const root = resolve(__dirname, "..");
const frontend = resolve(root, "frontend");
const backend = resolve(root, "backend");
const children = new Set();
let stopping = false;

function isListening(host, port) {
  return new Promise((resolve, reject) => {
    const probe = createServer();
    probe.once("error", (error) => {
      if (error.code === "EADDRINUSE") resolve(true);
      else if (host === "::1" && error.code === "EADDRNOTAVAIL") resolve(false);
      else reject(error);
    });
    probe.listen({ host, port, exclusive: true }, () => probe.close(() => resolve(false)));
  });
}

function stop(exitCode) {
  if (stopping) return;
  stopping = true;
  process.exitCode = exitCode;

  for (const child of children) {
    if (process.platform === "win32") {
      spawnSync("taskkill.exe", ["/PID", String(child.pid), "/T", "/F"], { stdio: "ignore" });
    } else {
      child.kill("SIGTERM");
    }
  }
}

function start(label, command, args, cwd, env = process.env) {
  const child = spawn(command, args, { cwd, env, stdio: ["ignore", "inherit", "inherit"] });
  if (child.pid) children.add(child);

  child.once("error", (error) => {
    console.error(`${label} could not start: ${error.message}`);
    stop(1);
  });
  child.once("exit", (code, signal) => {
    children.delete(child);
    if (stopping) return;
    console.error(`${label} stopped (${signal ?? code}). Shutting down the development stack.`);
    stop(code || 1);
  });
}

async function main() {
  const mailpit = resolve(backend, "storage/app/private/tools/mailpit.exe");
  if (!existsSync(mailpit)) throw new Error(`Install Mailpit at ${mailpit}.`);

  const ports = [
    ["Frontend", "::1", 5173],
    ["Frontend", "127.0.0.1", 5173],
    ["Laravel API", "127.0.0.1", 8000],
    ["Mailpit SMTP", "127.0.0.1", 1025],
    ["Mailpit inbox", "127.0.0.1", 8025],
  ];
  const occupied = await Promise.all(
    ports.map(async ([label, host, port]) =>
      (await isListening(host, port)) ? `${label} (${port})` : null,
    ),
  );
  const conflicts = [...new Set(occupied.filter(Boolean))];
  if (conflicts.length) {
    throw new Error(
      `Already in use: ${conflicts.join(", ")}. Stop those services and rerun npm run dev:all.`,
    );
  }

  if (stopping) return;
  start("Mailpit", process.execPath, [resolve(__dirname, "dev-mailpit.cjs")], root);
  start(
    "Laravel API",
    "php",
    ["artisan", "serve", "--host=127.0.0.1", "--port=8000", "--tries=1"],
    backend,
  );
  start("Email scheduler", "php", ["artisan", "schedule:work"], backend);
  start(
    "Frontend",
    process.execPath,
    [
      process.env.npm_execpath,
      "run",
      "dev",
      "--",
      "--host",
      "localhost",
      "--port",
      "5173",
      "--strictPort",
    ],
    frontend,
    { ...process.env, VITE_API_URL: "http://127.0.0.1:8000/api" },
  );
}

process.once("SIGINT", () => stop(0));
process.once("SIGTERM", () => stop(0));

main().catch((error) => {
  console.error(error.message);
  stop(1);
});
