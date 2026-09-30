// Playwright harness for the Table IX automated functional benchmark (24 cases).
// Black-box: runs against the real dev database (no RefreshDatabase) because
// several cases assert on device-originated Mar-Jun 2026 rows and the
// 2026-09-16 farm forecast. Do NOT point this at production.
// Serial workers: cases share DB/session state.
import { defineConfig } from '@playwright/test';

const baseURL = process.env.LAYRATE_BASE_URL ?? 'http://127.0.0.1:8000';
const port = new URL(baseURL).port || '80';

export default defineConfig({
  testDir: './e2e',
  outputDir: './test-results',
  timeout: 60_000,
  expect: { timeout: 10_000 },
  fullyParallel: false,
  workers: 1,
  reporter: [['list'], ['json', { outputFile: 'test-results/table-ix.json' }]],
  use: {
    baseURL,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  webServer: {
    command: `php artisan serve --host=127.0.0.1 --port=${port}`,
    url: `${baseURL}/login`,
    reuseExistingServer: true,
    timeout: 60_000,
  },
});
