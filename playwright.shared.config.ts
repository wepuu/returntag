import { devices, type PlaywrightTestConfig } from '@playwright/test';

export const baseURL = process.env.WP_BASE_URL ?? 'http://localhost:8888';

export const sharedConfig: PlaywrightTestConfig = {
	testDir: './tests/e2e',
	timeout: 60_000,
	fullyParallel: true,
	forbidOnly: Boolean( process.env.CI ),
	workers: process.env.CI ? 2 : undefined,
	reporter: process.env.CI
		? [ [ 'html', { open: 'never' } ], [ 'github' ] ]
		: 'list',
	globalSetup: './tests/e2e/global-setup.ts',
	use: {
		baseURL,
		screenshot: 'only-on-failure',
		trace: 'on-first-retry',
		video: 'on-first-retry',
	},
};

export const fullProjects: PlaywrightTestConfig[ 'projects' ] = [
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
];
