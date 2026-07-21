import { defineConfig, devices } from '@playwright/test';

const baseURL = process.env.WP_BASE_URL ?? 'http://localhost:8888';

export default defineConfig( {
	testDir: './tests/e2e',
	timeout: 60_000,
	fullyParallel: true,
	forbidOnly: Boolean( process.env.CI ),
	retries: process.env.CI ? 2 : 0,
	workers: process.env.CI ? 2 : undefined,
	reporter: process.env.CI
		? [ [ 'html', { open: 'never' } ], [ 'github' ] ]
		: 'list',
	use: {
		baseURL,
		screenshot: 'only-on-failure',
		trace: 'retain-on-failure',
		video: 'retain-on-failure',
	},
	projects: [
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
		{
			name: 'firefox',
			use: { ...devices[ 'Desktop Firefox' ] },
		},
		{
			name: 'webkit',
			use: { ...devices[ 'Desktop Safari' ] },
		},
		{
			name: 'mobile-chrome',
			use: { ...devices[ 'Pixel 5' ] },
		},
		{
			name: 'mobile-safari',
			use: { ...devices[ 'iPhone 12' ] },
		},
	],
} );
