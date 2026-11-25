const { spawnSync } = require("node:child_process");
const { resolve } = require("node:path");

const executable = resolve(__dirname, "../backend/storage/app/private/tools/mailpit.exe");
const result = spawnSync(
  executable,
  ["--listen", "127.0.0.1:8025", "--smtp", "127.0.0.1:1025", ...process.argv.slice(2)],
  { stdio: "inherit" },
);

if (result.error) {
  console.error(`Could not start the project Mailpit binary at ${executable}. ${result.error.message}`);
}

process.exitCode = result.status ?? 1;
