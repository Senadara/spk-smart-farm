import { defineConfig, devices } from '@playwright/test';
import * as dotenv from 'dotenv';
dotenv.config();

export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 1,
  workers: 1,
  timeout: 60000,
  expect: {
    timeout: 60000,
  },
  reporter: [
    ['html', { open: 'never' }],
    ['junit', { outputFile: 'test-results/junit-report.xml' }]
  ],
  use: {
    baseURL: process.env.BASE_URL || 'http://127.0.0.1:8000',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
  projects: [
    {
      // Proyek setup: Hanya dijalankan satu kali di awal untuk melakukan login
      name: 'setup',
      testMatch: /.*global\.setup\.ts/,
    },
    {
      name: 'chromium',
      use: { 
        ...devices['Desktop Chrome'],
        // Menyuntikkan file sesi state yang dihasilkan oleh setup ke seluruh test case
        storageState: 'tests/e2e/.auth/user.json',
      },
      // Menunggu proyek setup selesai sebelum test utama dijalankan
      dependencies: ['setup'],
    }
  ],
});
