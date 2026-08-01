import { join } from 'node:path';

export const adminAuthStatePath = join(
	process.cwd(),
	'.playwright',
	'.auth',
	'admin.json'
);
