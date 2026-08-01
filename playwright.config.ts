import { defineConfig } from '@playwright/test';

import { fullProjects, sharedConfig } from './playwright.shared.config';

export default defineConfig( {
	...sharedConfig,
	retries: process.env.CI ? 2 : 0,
	projects: fullProjects,
} );
