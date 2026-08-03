import { defineConfig, devices } from '@playwright/test';

import { sharedConfig } from './playwright.shared.config';

export default defineConfig( {
	...sharedConfig,
	retries: process.env.CI ? 1 : 0,
	maxFailures: process.env.CI ? 3 : 0,
	projects: [
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
		{
			name: 'mobile-safari',
			testMatch:
				/(?:public-tag-route|tag-entry|theme-(?:commerce|homepage))\.spec\.ts/,
			use: { ...devices[ 'iPhone 12' ] },
		},
	],
} );
