import { mkdir } from 'node:fs/promises';
import { dirname } from 'node:path';

import { chromium, type FullConfig } from '@playwright/test';

import { adminAuthStatePath } from './auth-state';

export default async function globalSetup( config: FullConfig ) {
	const configuredBaseURL = config.projects[ 0 ]?.use.baseURL;
	const baseURL =
		typeof configuredBaseURL === 'string'
			? configuredBaseURL
			: 'http://localhost:8888';
	const browser = await chromium.launch();

	try {
		const context = await browser.newContext( { baseURL } );
		const page = await context.newPage();

		await page.goto( '/wp-login.php', { waitUntil: 'domcontentloaded' } );
		await page.getByLabel( 'Username or Email Address' ).fill( 'admin' );
		await page.getByLabel( 'Password', { exact: true } ).fill( 'password' );
		await Promise.all( [
			page.waitForURL( /\/wp-admin(?:\/|$)/, {
				waitUntil: 'domcontentloaded',
			} ),
			page.getByRole( 'button', { name: 'Log In' } ).click(),
		] );

		await mkdir( dirname( adminAuthStatePath ), { recursive: true } );
		await context.storageState( { path: adminAuthStatePath } );
		await context.close();
	} finally {
		await browser.close();
	}
}
