import { test as base, expect, type Page } from '@playwright/test';

import { adminAuthStatePath } from './auth-state';

type TagCoreFixtures = {
	adminPage: Page;
};

export const adminTest = base.extend( {
	storageState: adminAuthStatePath,
} );

export const test = base.extend< TagCoreFixtures >( {
	adminPage: async ( { browser, baseURL }, provide ) => {
		const context = await browser.newContext( {
			baseURL,
			storageState: adminAuthStatePath,
		} );
		const page = await context.newPage();

		await provide( page );
		await context.close();
	},
} );

export { expect };
